<?php

declare(strict_types=1);

namespace Drupal\llm_content\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\llm_content\Service\MarkdownConverterInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the LLM Content module.
 */
final class LlmContentHooks {

  use StringTranslationTrait;

  public function __construct(
    protected MarkdownConverterInterface $markdownConverter,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $requirements = [];

    if (!class_exists('League\HTMLToMarkdown\HtmlConverter')) {
      $requirements['llm_content_html_to_markdown'] = [
        'title' => $this->t('LLM Content - HTML to Markdown library'),
        'value' => $this->t('Not installed'),
        'description' => $this->t('The league/html-to-markdown library is required. Run <code>composer require league/html-to-markdown:^5.0</code> in your project root.'),
        'severity' => RequirementSeverity::Error,
      ];
    }
    else {
      $requirements['llm_content_html_to_markdown'] = [
        'title' => $this->t('LLM Content - HTML to Markdown library'),
        'value' => $this->t('Installed'),
        'severity' => RequirementSeverity::OK,
      ];
    }

    return $requirements;
  }

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
