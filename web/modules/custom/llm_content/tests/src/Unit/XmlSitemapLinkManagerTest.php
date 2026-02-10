<?php

declare(strict_types=1);

namespace Drupal\Tests\llm_content\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\llm_content\Service\XmlSitemapLinkManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests the XmlSitemapLinkManager guard conditions.
 *
 * @group llm_content
 * @coversDefaultClass \Drupal\llm_content\Service\XmlSitemapLinkManager
 */
class XmlSitemapLinkManagerTest extends TestCase {

  /**
   * Creates a manager with xmlsitemap unavailable (null link storage).
   */
  private function createUnavailableManager(): XmlSitemapLinkManager {
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('xmlsitemap')->willReturn(FALSE);

    return new XmlSitemapLinkManager(
      $moduleHandler,
      $this->createStub(ConfigFactoryInterface::class),
      $this->createStub(Connection::class),
      NULL,
      $this->createStub(TimeInterface::class),
    );
  }

  /**
   * Tests isAvailable() returns false when xmlsitemap is not installed.
   *
   * @covers ::isAvailable
   */
  public function testIsAvailableWhenModuleNotInstalled(): void {
    $this->assertFalse($this->createUnavailableManager()->isAvailable());
  }

  /**
   * Tests isAvailable() returns true when xmlsitemap is installed.
   *
   * @covers ::isAvailable
   */
  public function testIsAvailableWhenModuleInstalled(): void {
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('xmlsitemap')->willReturn(TRUE);

    $manager = new XmlSitemapLinkManager(
      $moduleHandler,
      $this->createStub(ConfigFactoryInterface::class),
      $this->createStub(Connection::class),
      new \stdClass(),
      $this->createStub(TimeInterface::class),
    );

    $this->assertTrue($manager->isAvailable());
  }

  /**
   * Tests isEnabled() returns false when xmlsitemap is not installed.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledWhenModuleNotInstalled(): void {
    $this->assertFalse($this->createUnavailableManager()->isEnabled());
  }

  /**
   * Tests isEnabled() returns false when linkStorage is null.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledWhenLinkStorageNull(): void {
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('xmlsitemap')->willReturn(TRUE);

    $manager = new XmlSitemapLinkManager(
      $moduleHandler,
      $this->createStub(ConfigFactoryInterface::class),
      $this->createStub(Connection::class),
      NULL,
      $this->createStub(TimeInterface::class),
    );

    $this->assertFalse($manager->isEnabled());
  }

  /**
   * Tests isEnabled() returns false when config toggle is off.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledWhenConfigDisabled(): void {
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('xmlsitemap')->willReturn(TRUE);

    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->with('xmlsitemap_integration')->willReturn(FALSE);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('llm_content.settings')->willReturn($config);

    $manager = new XmlSitemapLinkManager(
      $moduleHandler,
      $configFactory,
      $this->createStub(Connection::class),
      new \stdClass(),
      $this->createStub(TimeInterface::class),
    );

    $this->assertFalse($manager->isEnabled());
  }

  /**
   * Tests isEnabled() returns true when module installed and config enabled.
   *
   * @covers ::isEnabled
   */
  public function testIsEnabledWhenModuleInstalledAndConfigEnabled(): void {
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->with('xmlsitemap')->willReturn(TRUE);

    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->with('xmlsitemap_integration')->willReturn(TRUE);

    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('llm_content.settings')->willReturn($config);

    $manager = new XmlSitemapLinkManager(
      $moduleHandler,
      $configFactory,
      $this->createStub(Connection::class),
      new \stdClass(),
      $this->createStub(TimeInterface::class),
    );

    $this->assertTrue($manager->isEnabled());
  }

  /**
   * Tests saveNodeLink() does nothing when disabled.
   *
   * @covers ::saveNodeLink
   */
  public function testSaveNodeLinkDoesNothingWhenDisabled(): void {
    $manager = $this->createUnavailableManager();

    $node = $this->createStub(\Drupal\node\NodeInterface::class);
    $manager->saveNodeLink($node);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests deleteNodeLink() does nothing when disabled.
   *
   * @covers ::deleteNodeLink
   */
  public function testDeleteNodeLinkDoesNothingWhenDisabled(): void {
    $this->createUnavailableManager()->deleteNodeLink(42);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests removeAllLinks() does nothing when module not available.
   *
   * @covers ::removeAllLinks
   */
  public function testRemoveAllLinksDoesNothingWhenNotAvailable(): void {
    $this->createUnavailableManager()->removeAllLinks();
    $this->addToAssertionCount(1);
  }

}
