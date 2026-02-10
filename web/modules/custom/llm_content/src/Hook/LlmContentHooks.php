<?php

declare(strict_types=1);

namespace Drupal\llm_content\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
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
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
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
    $this->regenerateLlmsFullTxt();
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
      // Remove markdown for unpublished nodes.
      $this->markdownConverter->deleteMarkdown((int) $node->id());
    }

    Cache::invalidateTags(['llm_content:list']);
    $this->regenerateLlmsFullTxt();
  }

  /**
   * Regenerates the llms-full.txt static file.
   */
  protected function regenerateLlmsFullTxt(): void {
    $config = $this->configFactory->get('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];

    if (empty($enabledTypes)) {
      return;
    }

    $siteConfig = $this->configFactory->get('system.site');
    $siteName = $siteConfig->get('name') ?? 'Site';
    $siteSlogan = $siteConfig->get('slogan') ?? '';

    $output = "# {$siteName}\n\n";
    if ($siteSlogan) {
      $output .= "> {$siteSlogan}\n\n";
    }

    // Read from stored markdown in DB, limited to 500 nodes.
    $results = $this->database->select('llm_content_markdown', 'm')
      ->fields('m', ['markdown'])
      ->range(0, 500)
      ->orderBy('generated', 'DESC')
      ->execute()
      ->fetchCol();

    foreach ($results as $markdown) {
      $output .= $markdown . "\n\n---\n\n";
    }

    // Write to public files directory.
    $directory = 'public://llm_content';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    $this->fileSystem->saveData($output, $directory . '/llms-full.txt', FileSystemInterface::EXISTS_REPLACE);
  }

}
