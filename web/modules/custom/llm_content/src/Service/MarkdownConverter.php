<?php

declare(strict_types=1);

namespace Drupal\llm_content\Service;

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

    // Clean up: strip dangerous URI schemes from links.
    $markdown = preg_replace('/\[([^\]]*)\]\((javascript|vbscript|data):[^)]*\)/i', '[$1](#)', $markdown) ?? $markdown;

    // Build frontmatter.
    $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    $frontmatter = "---\n";
    $frontmatter .= 'title: "' . str_replace('"', '\\"', $node->label() ?? '') . "\"\n";
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
        'generated' => \Drupal::time()->getRequestTime(),
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
    foreach ($xpath->query('//*[@id="comments"]') as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove elements with data-drupal-selector="comments".
    foreach ($xpath->query('//*[@data-drupal-selector="comments"]') as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove "links inline" lists (contains "Log in to post comments").
    foreach ($xpath->query('//ul[contains(@class, "links") and contains(@class, "inline")]') as $node) {
      $node->parentNode->removeChild($node);
    }

    // Remove nav elements.
    foreach ($xpath->query('//nav') as $node) {
      $node->parentNode->removeChild($node);
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
  public function deleteMarkdown(int $nid): void {
    $this->database->delete('llm_content_markdown')
      ->condition('nid', $nid)
      ->execute();
  }

}
