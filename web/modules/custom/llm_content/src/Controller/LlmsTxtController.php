<?php

declare(strict_types=1);

namespace Drupal\llm_content\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for llms.txt and llms-full.txt endpoints.
 */
final class LlmsTxtController extends ControllerBase {

  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = new static();
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * Generates the llms.txt file.
   */
  public function llmsTxt(): CacheableResponse {
    $config = $this->config('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];
    $siteConfig = $this->config('system.site');
    $siteName = $siteConfig->get('name') ?? 'Site';
    $siteSlogan = $siteConfig->get('slogan') ?? '';

    $output = "# {$siteName}\n\n";
    if ($siteSlogan) {
      $output .= "> {$siteSlogan}\n\n";
    }

    if (!empty($enabledTypes)) {
      $nodeStorage = $this->entityTypeManager()->getStorage('node');
      $query = $nodeStorage->getQuery()
        ->condition('status', 1)
        ->condition('type', $enabledTypes, 'IN')
        ->accessCheck(TRUE)
        ->sort('created', 'DESC')
        ->range(0, 500);
      $nids = $query->execute();

      if (!empty($nids)) {
        $output .= "## Content\n\n";
        foreach (array_chunk($nids, 50) as $batch) {
          $nodes = $nodeStorage->loadMultiple($batch);
          foreach ($nodes as $node) {
            $title = $node->label() ?? 'Untitled';
            $url = '/llm-md/node/' . $node->id();
            $description = '';
            if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
              $body = $node->get('body')->first();
              $description = $body->summary ?: mb_substr(strip_tags($body->value ?? ''), 0, 200);
            }
            $output .= "- [{$title}]({$url})";
            if ($description) {
              $output .= ": {$description}";
            }
            $output .= "\n";
          }
          $nodeStorage->resetCache($batch);
        }
      }
    }

    $response = new CacheableResponse($output, 200, [
      'Content-Type' => 'text/plain; charset=utf-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);

    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheTags(['llm_content:list']);
    $cacheMetadata->addCacheContexts(['user.permissions']);
    $response->addCacheableDependency($cacheMetadata);
    $response->addCacheableDependency($config);
    $response->addCacheableDependency($siteConfig);

    return $response;
  }

  /**
   * Serves the pre-generated llms-full.txt file.
   */
  public function llmsFullTxt(): Response {
    $filePath = 'public://llm_content/llms-full.txt';
    $realPath = $this->fileSystem->realpath($filePath);

    if ($realPath && file_exists($realPath)) {
      $content = file_get_contents($realPath);
      $response = new CacheableResponse($content ?: '', 200, [
        'Content-Type' => 'text/plain; charset=utf-8',
        'X-Content-Type-Options' => 'nosniff',
      ]);

      $cacheMetadata = new CacheableMetadata();
      $cacheMetadata->addCacheTags(['llm_content:list']);
      $response->addCacheableDependency($cacheMetadata);

      return $response;
    }

    // Generate on the fly if file doesn't exist yet (fallback).
    return $this->generateLlmsFullTxt();
  }

  /**
   * Generates the llms-full.txt content as a fallback.
   */
  protected function generateLlmsFullTxt(): CacheableResponse {
    $config = $this->config('llm_content.settings');
    $siteConfig = $this->config('system.site');
    $siteName = $siteConfig->get('name') ?? 'Site';
    $siteSlogan = $siteConfig->get('slogan') ?? '';

    $output = "# {$siteName}\n\n";
    if ($siteSlogan) {
      $output .= "> {$siteSlogan}\n\n";
    }

    // Read from stored markdown in DB, limited to 500 nodes.
    $results = \Drupal::database()->select('llm_content_markdown', 'm')
      ->fields('m', ['markdown'])
      ->range(0, 500)
      ->execute()
      ->fetchCol();

    foreach ($results as $markdown) {
      $output .= $markdown . "\n\n---\n\n";
    }

    $response = new CacheableResponse($output, 200, [
      'Content-Type' => 'text/plain; charset=utf-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);

    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheTags(['llm_content:list']);
    $response->addCacheableDependency($cacheMetadata);
    $response->addCacheableDependency($config);

    return $response;
  }

}
