<?php

declare(strict_types=1);

namespace Drupal\llm_content\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\llm_content\Service\MarkdownConverterInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes markdown generation for nodes in the background.
 *
 * @QueueWorker(
 *   id = "llm_content_markdown_generation",
 *   title = @Translation("LLM Content Markdown Generation"),
 *   cron = {"time" = 60}
 * )
 */
final class MarkdownGenerationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected MarkdownConverterInterface $markdownConverter,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(MarkdownConverterInterface::class),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('llm_content'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (empty($data['nid'])) {
      return;
    }

    $node = $this->entityTypeManager->getStorage('node')->load($data['nid']);
    if (!$node instanceof NodeInterface || !$node->isPublished()) {
      return;
    }

    $this->markdownConverter->convert($node);
    $this->entityTypeManager->getStorage('node')->resetCache([$data['nid']]);
  }

}
