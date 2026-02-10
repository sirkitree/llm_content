<?php

declare(strict_types=1);

namespace Drupal\llm_content\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Converts Drupal nodes to markdown and manages storage.
 */
final class MarkdownConverter implements MarkdownConverterInterface {

  /**
   * The HTML to Markdown converter.
   */
  protected HtmlConverter $htmlConverter;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RendererInterface $renderer,
    protected ConfigFactoryInterface $configFactory,
    protected AliasManagerInterface $aliasManager,
    protected Connection $database,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
  ) {
    $this->htmlConverter = new HtmlConverter([
      'strip_tags' => TRUE,
      'remove_nodes' => 'script style iframe nav header footer aside',
      'header_style' => 'atx',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function convert(NodeInterface $node): string {
    $config = $this->configFactory->get('llm_content.settings');
    $viewMode = $config->get('view_mode') ?? 'full';

    // Render the node to HTML.
    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    $build = $viewBuilder->view($node, $viewMode);
    $html = (string) $this->renderer->renderInIsolation($build);

    // Strip comment sections and Drupal chrome using DOM for reliability.
    $html = $this->stripDrupalChrome($html);

    // Convert HTML to markdown.
    $markdown = $this->htmlConverter->convert($html);

    // Clean up: only allow safe URI schemes in links (allowlist approach).
    $markdown = preg_replace_callback(
      '/\[([^\]]*)\]\(([^)]+)\)/i',
      static function (array $matches): string {
        $text = $matches[1];
        $url = $matches[2];
        // Allow relative URLs, http(s), mailto, and tel schemes.
        if (preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $url)) {
          return "[{$text}]({$url})";
        }
        return "[{$text}](#)";
      },
      $markdown
    ) ?? $markdown;

    // Collapse whitespace-only lines and excessive newlines from nested
    // paragraph divs (e.g. lines with just spaces or non-breaking spaces).
    $markdown = preg_replace("/\n[ \t]*\n[ \t]*\n/", "\n\n", $markdown) ?? $markdown;
    $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

    // Build frontmatter.
    $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    $title = $node->label() ?? '';
    // Sanitize title for YAML safety: escape quotes, strip control characters.
    $title = preg_replace('/[\x00-\x1f\x7f]/', '', $title) ?? $title;
    $title = str_replace(['\\', '"'], ['\\\\', '\\"'], $title);
    $frontmatter = "---\n";
    $frontmatter .= 'title: "' . $title . "\"\n";
    $frontmatter .= 'url: ' . $alias . "\n";
    $frontmatter .= 'type: ' . $node->bundle() . "\n";
    $frontmatter .= 'date: ' . $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'Y-m-d') . "\n";
    if ($node->getRevisionCreationTime()) {
      $frontmatter .= 'updated: ' . $this->dateFormatter->format($node->getRevisionCreationTime(), 'custom', 'Y-m-d') . "\n";
    }
    $frontmatter .= "---\n\n";

    $fullMarkdown = $frontmatter . '# ' . ($node->label() ?? '') . "\n\n" . trim($markdown);

    // Store in database.
    $this->database->merge('llm_content_markdown')
      ->keys([
        'nid' => $node->id(),
        'langcode' => $node->language()->getId(),
      ])
      ->fields([
        'markdown' => $fullMarkdown,
        'generated' => $this->time->getRequestTime(),
      ])
      ->execute();

    return $fullMarkdown;
  }

  /**
   * Strips comment sections, nav, and other Drupal chrome from HTML.
   */
  protected function stripDrupalChrome(string $html): string {
    $doc = new \DOMDocument();
    @$doc->loadHTML('<html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new \DOMXPath($doc);

    // Remove elements by ID (comments wrapper).
    // Use iterator_to_array() on all XPath loops that mutate the DOM,
    // because DOMNodeList is live and mutations during iteration skip nodes.
    foreach (iterator_to_array($xpath->query('//*[@id="comments"]')) as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove elements with data-drupal-selector="comments".
    foreach (iterator_to_array($xpath->query('//*[@data-drupal-selector="comments"]')) as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove "links inline" lists (contains "Log in to post comments").
    foreach (iterator_to_array($xpath->query('//ul[contains(@class, "links") and contains(@class, "inline")]')) as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove nav elements.
    foreach (iterator_to_array($xpath->query('//nav')) as $node) {
      $node->parentNode->removeChild($node);
    }

    // Convert <details>/<summary> (accordion paragraphs) to heading + content.
    foreach (iterator_to_array($xpath->query('//details')) as $details) {
      $summary = $xpath->query('summary', $details)->item(0);
      $heading = $doc->createElement('h3');
      $heading->textContent = $summary ? trim($summary->textContent) : '';
      if ($summary) {
        $details->removeChild($summary);
      }
      // Move remaining child nodes to preserve inner HTML structure.
      $parent = $details->parentNode;
      $parent->insertBefore($heading, $details);
      while ($details->firstChild) {
        $parent->insertBefore($details->firstChild, $details);
      }
      $parent->removeChild($details);
    }

    // Convert <figure>/<figcaption> to img + italic caption.
    foreach (iterator_to_array($xpath->query('//figure')) as $figure) {
      $img = $xpath->query('.//img', $figure)->item(0);
      $caption = $xpath->query('figcaption', $figure)->item(0);
      if ($img) {
        $figure->parentNode->insertBefore($img->cloneNode(TRUE), $figure);
      }
      if ($caption && trim($caption->textContent) !== '') {
        $p = $doc->createElement('p');
        $em = $doc->createElement('em');
        $em->textContent = trim($caption->textContent);
        $p->appendChild($em);
        $figure->parentNode->insertBefore($p, $figure);
      }
      $figure->parentNode->removeChild($figure);
    }

    // Replace <iframe> elements with link placeholders before HtmlConverter
    // strips them (iframe is in remove_nodes config).
    foreach (iterator_to_array($xpath->query('//iframe[@src]')) as $iframe) {
      $src = $iframe->getAttribute('src');
      if (preg_match('#^https?://#i', $src)) {
        $p = $doc->createElement('p');
        $a = $doc->createElement('a', '[Embedded Video]');
        $a->setAttribute('href', $src);
        $p->appendChild($a);
        $iframe->parentNode->insertBefore($p, $iframe);
      }
      $iframe->parentNode->removeChild($iframe);
    }

    // Convert Paragraphs accordion title fields to <h3> headings.
    // Paragraphs renders accordion titles as:
    // <div class="field--name-field-accordion-title ...">Title text</div>
    foreach (iterator_to_array($xpath->query('//*[contains(@class, "field--name-field-accordion-title")]')) as $titleDiv) {
      $heading = $doc->createElement('h3');
      $heading->textContent = trim($titleDiv->textContent);
      $titleDiv->parentNode->replaceChild($heading, $titleDiv);
    }

    // Convert Paragraphs media embed URL fields to clickable links.
    // Paragraphs renders embed URLs as plain text in:
    // <div class="field--name-field-embed-url ...">https://youtube.com/...</div>
    foreach (iterator_to_array($xpath->query('//*[contains(@class, "field--name-field-embed-url")]')) as $urlDiv) {
      $url = trim($urlDiv->textContent);
      if (preg_match('#^https?://#i', $url)) {
        $p = $doc->createElement('p');
        $a = $doc->createElement('a', '[Embedded Video]');
        $a->setAttribute('href', $url);
        $p->appendChild($a);
        $urlDiv->parentNode->replaceChild($p, $urlDiv);
      }
    }

    $body = $doc->getElementsByTagName('body')->item(0);
    if ($body === NULL) {
      return $html;
    }

    $result = '';
    foreach ($body->childNodes as $child) {
      $result .= $doc->saveHTML($child);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkdown(NodeInterface $node): string {
    // Try to load from storage first.
    $result = $this->database->select('llm_content_markdown', 'm')
      ->fields('m', ['markdown'])
      ->condition('nid', $node->id())
      ->condition('langcode', $node->language()->getId())
      ->execute()
      ->fetchField();

    if ($result !== FALSE) {
      return $result;
    }

    // Generate on demand if not stored.
    return $this->convert($node);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMarkdown(int $nid, ?string $langcode = NULL): void {
    $query = $this->database->delete('llm_content_markdown')
      ->condition('nid', $nid);
    if ($langcode !== NULL) {
      $query->condition('langcode', $langcode);
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function generateFullText(): string {
    $config = $this->configFactory->get('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];
    $siteConfig = $this->configFactory->get('system.site');
    $siteName = $siteConfig->get('name') ?? 'Site';
    $siteSlogan = $siteConfig->get('slogan') ?? '';

    $output = "# {$siteName}\n\n";
    if ($siteSlogan) {
      $output .= "> {$siteSlogan}\n\n";
    }

    if (empty($enabledTypes)) {
      return $output;
    }

    // Join with node_field_data for access control and type filtering.
    $query = $this->database->select('llm_content_markdown', 'm');
    $query->innerJoin('node_field_data', 'n', 'm.nid = n.nid AND m.langcode = n.langcode');
    $query->fields('m', ['markdown']);
    $query->condition('n.status', 1);
    $query->condition('n.type', $enabledTypes, 'IN');
    $query->orderBy('m.nid', 'ASC');
    $query->range(0, 500);
    $results = $query->execute()->fetchCol();

    foreach ($results as $markdown) {
      $output .= $markdown . "\n\n---\n\n";
    }

    return $output;
  }

}
