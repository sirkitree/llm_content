<?php

declare(strict_types=1);

namespace Drupal\llm_content\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Controller for the LLM sitemap XML endpoint.
 */
final class LlmSitemapController extends ControllerBase {

  /**
   * Generates the LLM sitemap XML.
   */
  public function generate(): CacheableResponse {
    $config = $this->config('llm_content.settings');
    $enabledTypes = $config->get('enabled_content_types') ?? [];
    // Use Drupal's URL generator for safe base URL resolution.
    $baseUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $baseUrl = rtrim($baseUrl, '/');

    // Use XMLWriter for safe XML generation.
    $xml = new \XMLWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('urlset');
    $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    if (!empty($enabledTypes)) {
      $nodeStorage = $this->entityTypeManager()->getStorage('node');
      $query = $nodeStorage->getQuery()
        ->condition('status', 1)
        ->condition('type', $enabledTypes, 'IN')
        ->accessCheck(TRUE)
        ->sort('changed', 'DESC')
        ->range(0, 50000);
      $nids = $query->execute();

      foreach (array_chunk($nids, 100) as $batch) {
        $nodes = $nodeStorage->loadMultiple($batch);
        foreach ($nodes as $node) {
          $xml->startElement('url');

          $xml->startElement('loc');
          $xml->text(Url::fromRoute('llm_content.markdown_view', ['node' => $node->id()], ['absolute' => TRUE])->toString());
          $xml->endElement();

          $xml->startElement('lastmod');
          $xml->text(date('Y-m-d\TH:i:sP', (int) $node->getChangedTime()));
          $xml->endElement();

          $xml->startElement('changefreq');
          $xml->text('weekly');
          $xml->endElement();

          $xml->endElement(); // url
        }
        $nodeStorage->resetCache($batch);
      }
    }

    // Add llms.txt and llms-full.txt.
    $xml->startElement('url');
    $xml->startElement('loc');
    $xml->text($baseUrl . '/llms.txt');
    $xml->endElement();
    $xml->startElement('changefreq');
    $xml->text('daily');
    $xml->endElement();
    $xml->endElement();

    $xml->startElement('url');
    $xml->startElement('loc');
    $xml->text($baseUrl . '/llms-full.txt');
    $xml->endElement();
    $xml->startElement('changefreq');
    $xml->text('daily');
    $xml->endElement();
    $xml->endElement();

    $xml->endElement(); // urlset
    $xml->endDocument();

    $output = $xml->outputMemory();

    $response = new CacheableResponse($output, 200, [
      'Content-Type' => 'application/xml; charset=utf-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);

    $cacheMetadata = new CacheableMetadata();
    $cacheMetadata->addCacheTags(['llm_content:list', 'node_list', 'path_alias_list']);
    $cacheMetadata->addCacheContexts(['user.permissions']);
    $response->addCacheableDependency($cacheMetadata);
    $response->addCacheableDependency($config);

    return $response;
  }

}
