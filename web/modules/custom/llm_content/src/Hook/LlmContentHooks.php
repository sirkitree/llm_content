<?php

declare(strict_types=1);

namespace Drupal\llm_content\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\llm_content\Service\MarkdownConverterInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the LLM Content module.
 */
final class LlmContentHooks {

  public function __construct(
    protected MarkdownConverterInterface $markdownConverter,
    protected ConfigFactoryInterface $configFactory,
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
    Cache::invalidateTags(['llm_content:list']);
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
    }
    else {
      // Remove markdown for this specific translation only.
      $this->markdownConverter->deleteMarkdown((int) $node->id(), $node->language()->getId());
    }

    Cache::invalidateTags(['llm_content:list']);
  }

}
