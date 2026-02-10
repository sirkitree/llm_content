<?php

declare(strict_types=1);

namespace Drupal\Tests\llm_content\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\llm_content\Routing\RouteSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the RouteSubscriber for disabling the built-in LLM sitemap.
 *
 * @group llm_content
 * @coversDefaultClass \Drupal\llm_content\Routing\RouteSubscriber
 */
class RouteSubscriberTest extends TestCase {

  /**
   * Tests that the route is not altered when config is disabled.
   *
   * @covers ::alterRoutes
   */
  public function testRouteNotAlteredWhenConfigDisabled(): void {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->with('disable_builtin_sitemap')->willReturn(FALSE);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('llm_content.settings')->willReturn($config);

    $subscriber = new RouteSubscriber($configFactory);

    $route = new Route('/sitemap-llm.xml');
    $route->setRequirement('_permission', 'access content');
    $collection = new RouteCollection();
    $collection->add('llm_content.sitemap_llm', $route);

    // Use reflection to call protected alterRoutes.
    $method = new \ReflectionMethod(RouteSubscriber::class, 'alterRoutes');

    $method->invoke($subscriber, $collection);

    // Route should still have original access requirement.
    $this->assertSame('access content', $route->getRequirement('_permission'));
    $this->assertNull($route->getRequirement('_access'));
  }

  /**
   * Tests that the route is disabled when config is enabled.
   *
   * @covers ::alterRoutes
   */
  public function testRouteDisabledWhenConfigEnabled(): void {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->with('disable_builtin_sitemap')->willReturn(TRUE);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('llm_content.settings')->willReturn($config);

    $subscriber = new RouteSubscriber($configFactory);

    $route = new Route('/sitemap-llm.xml');
    $route->setRequirement('_permission', 'access content');
    $collection = new RouteCollection();
    $collection->add('llm_content.sitemap_llm', $route);

    $method = new \ReflectionMethod(RouteSubscriber::class, 'alterRoutes');

    $method->invoke($subscriber, $collection);

    // Route should now deny access.
    $this->assertSame('FALSE', $route->getRequirement('_access'));
  }

  /**
   * Tests that missing route does not cause an error.
   *
   * @covers ::alterRoutes
   */
  public function testMissingRouteDoesNotCauseError(): void {
    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->with('disable_builtin_sitemap')->willReturn(TRUE);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('llm_content.settings')->willReturn($config);

    $subscriber = new RouteSubscriber($configFactory);

    // Empty collection — route doesn't exist.
    $collection = new RouteCollection();

    $method = new \ReflectionMethod(RouteSubscriber::class, 'alterRoutes');

    $method->invoke($subscriber, $collection);

    // Should not throw.
    $this->addToAssertionCount(1);
  }

}
