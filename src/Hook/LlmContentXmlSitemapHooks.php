<?php

declare(strict_types=1);

namespace Drupal\llm_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * XML Sitemap hook implementations for the LLM Content module.
 *
 * No constructor dependencies on xmlsitemap — this hook class only fires
 * when the xmlsitemap module is installed.
 */
final class LlmContentXmlSitemapHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_xmlsitemap_link_info().
   */
  #[Hook('xmlsitemap_link_info')]
  public function xmlsitemapLinkInfo(): array {
    return [
      'llm_content' => [
        'label' => $this->t('LLM Content'),
        'bundle label' => $this->t('Link type'),
        'bundles' => [
          'node_markdown' => [
            'label' => $this->t('Node markdown URLs'),
            'xmlsitemap' => [
              'status' => 1,
              'priority' => 0.5,
            ],
          ],
          'index' => [
            'label' => $this->t('Index endpoints (llms.txt)'),
            'xmlsitemap' => [
              'status' => 1,
              'priority' => 0.7,
            ],
          ],
        ],
      ],
    ];
  }

}
