<?php

declare(strict_types=1);

namespace Drupal\llm_content\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters routes to disable the built-in LLM sitemap when configured.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $config = $this->configFactory->get('llm_content.settings');
    if ($config->get('disable_builtin_sitemap')) {
      $route = $collection->get('llm_content.sitemap_llm');
      if ($route) {
        $route->setRequirement('_access', 'FALSE');
      }
    }
  }

}
