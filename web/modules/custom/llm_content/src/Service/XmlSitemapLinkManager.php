<?php

declare(strict_types=1);

namespace Drupal\llm_content\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\node\NodeInterface;

/**
 * Manages xmlsitemap links for LLM content URLs.
 */
final class XmlSitemapLinkManager implements XmlSitemapLinkManagerInterface {

  /**
   * The link type used for all LLM content sitemap links.
   */
  public const LINK_TYPE = 'llm_content';

  /**
   * Subtype for node markdown URLs.
   */
  public const SUBTYPE_NODE = 'node_markdown';

  /**
   * Subtype for index endpoint URLs.
   */
  public const SUBTYPE_INDEX = 'index';

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected ConfigFactoryInterface $configFactory,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return $this->moduleHandler->moduleExists('xmlsitemap');
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    if (!$this->isAvailable()) {
      return FALSE;
    }
    return (bool) $this->configFactory->get('llm_content.settings')
      ->get('xmlsitemap_integration');
  }

  /**
   * {@inheritdoc}
   */
  public function saveNodeLink(NodeInterface $node): void {
    if (!$this->isEnabled()) {
      return;
    }

    $config = $this->configFactory->get('llm_content.settings');
    $linkStorage = \Drupal::service('xmlsitemap.link_storage');

    $link = [
      'type' => self::LINK_TYPE,
      'id' => (string) $node->id(),
      'subtype' => self::SUBTYPE_NODE,
      'loc' => '/node/' . $node->id() . '/llm-md',
      'language' => $node->language()->getId(),
      'access' => $node->isPublished() ? 1 : 0,
      'status' => 1,
      'lastmod' => $node->getChangedTime(),
      'priority' => (float) ($config->get('xmlsitemap_priority') ?? '0.5'),
      'changefreq' => (int) ($config->get('xmlsitemap_changefreq') ?? 604800),
    ];

    $linkStorage->save($link);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNodeLink(int $nid): void {
    if (!$this->isEnabled()) {
      return;
    }

    $linkStorage = \Drupal::service('xmlsitemap.link_storage');
    $linkStorage->delete(self::LINK_TYPE, (string) $nid);
  }

  /**
   * {@inheritdoc}
   */
  public function saveIndexLinks(): void {
    if (!$this->isEnabled()) {
      return;
    }

    $config = $this->configFactory->get('llm_content.settings');
    $linkStorage = \Drupal::service('xmlsitemap.link_storage');
    $priority = (float) ($config->get('xmlsitemap_index_priority') ?? '0.7');
    $changefreq = (int) ($config->get('xmlsitemap_changefreq') ?? 604800);

    $indexLinks = [
      'llms_txt' => '/llms.txt',
      'llms_full_txt' => '/llms-full.txt',
    ];

    foreach ($indexLinks as $id => $loc) {
      $link = [
        'type' => self::LINK_TYPE,
        'id' => $id,
        'subtype' => self::SUBTYPE_INDEX,
        'loc' => $loc,
        'language' => '',
        'access' => 1,
        'status' => 1,
        'lastmod' => \Drupal::time()->getRequestTime(),
        'priority' => $priority,
        'changefreq' => $changefreq,
      ];
      $linkStorage->save($link);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function syncAllLinks(): void {
    if (!$this->isEnabled()) {
      return;
    }

    // Remove existing links first.
    $this->removeAllLinks();

    // Save index links.
    $this->saveIndexLinks();

    // Save links for all nodes with stored markdown.
    $config = $this->configFactory->get('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];
    if (empty($enabledTypes)) {
      return;
    }

    $query = $this->database->select('llm_content_markdown', 'm');
    $query->innerJoin('node_field_data', 'n', 'm.nid = n.nid AND m.langcode = n.langcode');
    $query->fields('m', ['nid', 'langcode']);
    $query->fields('n', ['type', 'status', 'changed']);
    $query->condition('n.type', $enabledTypes, 'IN');
    $results = $query->execute();

    $linkStorage = \Drupal::service('xmlsitemap.link_storage');
    $priority = (float) ($config->get('xmlsitemap_priority') ?? '0.5');
    $changefreq = (int) ($config->get('xmlsitemap_changefreq') ?? 604800);

    foreach ($results as $row) {
      $link = [
        'type' => self::LINK_TYPE,
        'id' => (string) $row->nid,
        'subtype' => self::SUBTYPE_NODE,
        'loc' => '/node/' . $row->nid . '/llm-md',
        'language' => $row->langcode,
        'access' => (int) $row->status,
        'status' => 1,
        'lastmod' => (int) $row->changed,
        'priority' => $priority,
        'changefreq' => $changefreq,
      ];
      $linkStorage->save($link);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllLinks(): void {
    if (!$this->isAvailable()) {
      return;
    }

    $linkStorage = \Drupal::service('xmlsitemap.link_storage');
    $linkStorage->deleteMultiple(['type' => self::LINK_TYPE]);
  }

}
