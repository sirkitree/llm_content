<?php

declare(strict_types=1);

namespace Drupal\llm_content\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\llm_content\Service\MarkdownConverterInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for serving node markdown views.
 */
final class LlmMarkdownController extends ControllerBase {

  public function __construct(
    protected MarkdownConverterInterface $markdownConverter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(MarkdownConverterInterface::class),
    );
  }

  /**
   * Serves a node as markdown.
   */
  public function view(NodeInterface $node): CacheableResponse {
    $config = $this->config('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];

    // Check if this content type is enabled.
    if (!in_array($node->bundle(), $enabledTypes, TRUE)) {
      throw new NotFoundHttpException();
    }

    // Check if the node is published.
    if (!$node->isPublished()) {
      throw new NotFoundHttpException();
    }

    $markdown = $this->markdownConverter->getMarkdown($node);

    $response = new CacheableResponse($markdown, 200, [
      'Content-Type' => 'text/markdown; charset=utf-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);

    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheTags(['node:' . $node->id()]);
    $cacheMetadata->addCacheContexts(['url.path']);
    $response->addCacheableDependency($cacheMetadata);
    $response->addCacheableDependency($node);
    $response->addCacheableDependency($config);

    return $response;
  }

}
