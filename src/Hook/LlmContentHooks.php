<?php

declare(strict_types=1);

namespace Drupal\llm_content\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\llm_content\Service\MarkdownConverterInterface;
use Drupal\llm_content\Service\XmlSitemapLinkManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the LLM Content module.
 */
final class LlmContentHooks {

  public function __construct(
    protected MarkdownConverterInterface $markdownConverter,
    protected ConfigFactoryInterface $configFactory,
    protected XmlSitemapLinkManagerInterface $xmlSitemapLinkManager,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $this->handleNodeSave($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $this->handleNodeSave($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface) {
      return;
    }
    $this->markdownConverter->deleteMarkdown((int) $entity->id());
    if ($this->xmlSitemapLinkManager->isEnabled()) {
      $this->xmlSitemapLinkManager->deleteNodeLink((int) $entity->id());
    }
    Cache::invalidateTags(['llm_content:list']);
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    // Get the current route to check if we're on a node page.
    $route_match = \Drupal::routeMatch();
    if ($route_match->getRouteName() === 'entity.node.canonical') {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $route_match->getParameter('node');
      if ($node instanceof NodeInterface) {
        $config = $this->configFactory->get('llm_content.settings');
        $enabledTypes = $config->get('enabled_content_types') ?? [];

        // Only add alternate link for enabled content types.
        if (in_array($node->bundle(), $enabledTypes, TRUE) && $node->isPublished()) {
          $page['#attached']['html_head'][] = [
            [
              '#tag' => 'link',
              '#attributes' => [
                'rel' => 'alternate',
                'type' => 'text/markdown',
                'href' => '/node/' . $node->id() . '/llm-md',
              ],
            ],
            'llm_content_alternate',
          ];
        }
      }
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $config = $this->configFactory->get('llm_content.settings');
    $types = $config->get('enabled_content_types') ?: [];
    if (empty($types)) {
      return;
    }

    $existing = $this->database
      ->query('SELECT DISTINCT nid FROM {llm_content_markdown}')
      ->fetchCol();

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->condition('type', $types, 'IN')
      ->accessCheck(FALSE);
    if ($existing) {
      $query->condition('nid', $existing, 'NOT IN');
    }
    $nids = $query->execute();

    if (empty($nids)) {
      return;
    }

    $queue = $this->queueFactory->get('llm_content_markdown_generation');
    $queued = 0;
    foreach ($nids as $nid) {
      $queue->createItem(['nid' => (int) $nid]);
      $queued++;
    }

    \Drupal::logger('llm_content')->notice('Cron queued @count nodes for markdown generation.', [
      '@count' => $queued,
    ]);
  }

  /**
   * Handles node save events for enabled content types.
   */
  protected function handleNodeSave(NodeInterface $node): void {
    $config = $this->configFactory->get('llm_content.settings');

    if (!$config->get('auto_generate')) {
      return;
    }

    $enabledTypes = $config->get('enabled_content_types') ?? [];
    if (!in_array($node->bundle(), $enabledTypes, TRUE)) {
      return;
    }

    if ($node->isPublished()) {
      $this->markdownConverter->convert($node);
      if ($this->xmlSitemapLinkManager->isEnabled()) {
        $this->xmlSitemapLinkManager->saveNodeLink($node);
      }
    }
    else {
      // Remove markdown for this specific translation only.
      $this->markdownConverter->deleteMarkdown((int) $node->id(), $node->language()->getId());
      if ($this->xmlSitemapLinkManager->isEnabled()) {
        $this->xmlSitemapLinkManager->deleteNodeLink((int) $node->id());
      }
    }

    Cache::invalidateTags(['llm_content:list']);
  }

}
