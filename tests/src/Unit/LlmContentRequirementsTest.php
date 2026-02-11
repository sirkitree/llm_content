<?php

declare(strict_types=1);

namespace Drupal\Tests\llm_content\Unit;

use Drupal\llm_content\Install\Requirements\LlmContentRequirements;
use PHPUnit\Framework\TestCase;

/**
 * Tests install-time requirements for the LLM Content module.
 *
 * @group llm_content
 * @coversDefaultClass \Drupal\llm_content\Install\Requirements\LlmContentRequirements
 */
class LlmContentRequirementsTest extends TestCase {

  /**
   * Tests that getRequirements() returns empty when library is present.
   *
   * @covers ::getRequirements
   */
  public function testRequirementsMetWhenLibraryPresent(): void {
    // The library is installed in the test environment via Composer.
    $requirements = LlmContentRequirements::getRequirements();
    $this->assertEmpty($requirements);
  }

}
