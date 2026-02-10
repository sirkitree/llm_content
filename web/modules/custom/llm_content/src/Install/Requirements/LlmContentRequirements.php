<?php

declare(strict_types=1);

namespace Drupal\llm_content\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Install-time requirements for the LLM Content module.
 */
class LlmContentRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];

    if (!class_exists('League\HTMLToMarkdown\HtmlConverter')) {
      $requirements['llm_content_html_to_markdown'] = [
        'title' => new TranslatableMarkup('LLM Content - HTML to Markdown library'),
        'description' => new TranslatableMarkup('The league/html-to-markdown library is required. Run <code>composer require league/html-to-markdown:^5.0</code> in your project root.'),
        'severity' => RequirementSeverity::Error,
      ];
    }

    return $requirements;
  }

}
