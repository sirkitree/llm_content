<?php

declare(strict_types=1);

namespace Drupal\Tests\llm_content\Unit;

use Drupal\llm_content\Service\MarkdownConverter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the MarkdownConverter HTML transformation methods.
 *
 * @group llm_content
 * @coversDefaultClass \Drupal\llm_content\Service\MarkdownConverter
 */
class MarkdownConverterTest extends TestCase {

  /**
   * Reflection method for stripDrupalChrome().
   */
  protected \ReflectionMethod $stripMethod;

  /**
   * The converter instance (created without constructor).
   */
  protected MarkdownConverter $converter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create instance without calling constructor (avoids DI dependencies).
    $reflection = new \ReflectionClass(MarkdownConverter::class);
    $this->converter = $reflection->newInstanceWithoutConstructor();

    $this->stripMethod = $reflection->getMethod('stripDrupalChrome');
    $this->stripMethod->setAccessible(TRUE);
  }

  /**
   * Invokes the protected stripDrupalChrome method.
   */
  protected function strip(string $html): string {
    return $this->stripMethod->invoke($this->converter, $html);
  }

  /**
   * Tests that details/summary elements are converted to heading + paragraph.
   */
  public function testDetailsSummaryConversion(): void {
    $html = '<details><summary>Accordion Title</summary><p>The accordion body content.</p></details>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<h3>Accordion Title</h3>', $result);
    $this->assertStringNotContainsString('<details>', $result);
    $this->assertStringNotContainsString('<summary>', $result);
    $this->assertStringContainsString('The accordion body content', $result);
  }

  /**
   * Tests details/summary with plain text content.
   */
  public function testDetailsSummaryPlainText(): void {
    $html = '<details><summary>FAQ Question</summary>The answer to the question.</details>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<h3>FAQ Question</h3>', $result);
    $this->assertStringContainsString('The answer to the question', $result);
  }

  /**
   * Tests figure/figcaption conversion to img + italic caption.
   */
  public function testFigureFigcaptionConversion(): void {
    $html = '<figure><img src="/sites/default/files/photo.jpg" alt="A photo"><figcaption>Photo by John Doe</figcaption></figure>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<img src="/sites/default/files/photo.jpg" alt="A photo">', $result);
    $this->assertStringContainsString('<em>Photo by John Doe</em>', $result);
    $this->assertStringNotContainsString('<figure>', $result);
    $this->assertStringNotContainsString('<figcaption>', $result);
  }

  /**
   * Tests figure without figcaption.
   */
  public function testFigureWithoutCaption(): void {
    $html = '<figure><img src="/photo.jpg" alt="A photo"></figure>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<img src="/photo.jpg" alt="A photo">', $result);
    $this->assertStringNotContainsString('<figure>', $result);
    $this->assertStringNotContainsString('<em>', $result);
  }

  /**
   * Tests iframe replacement with link placeholder.
   */
  public function testIframeReplacementWithLink(): void {
    $html = '<div class="field__item"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315"></iframe></div>';
    $result = $this->strip($html);

    $this->assertStringContainsString('href="https://www.youtube.com/embed/dQw4w9WgXcQ"', $result);
    $this->assertStringContainsString('Embedded Video', $result);
    $this->assertStringNotContainsString('<iframe', $result);
  }

  /**
   * Tests that iframes without http(s) src are just removed (no placeholder).
   */
  public function testIframeWithInvalidSrcRemoved(): void {
    $html = '<iframe src="javascript:alert(1)"></iframe>';
    $result = $this->strip($html);

    $this->assertStringNotContainsString('javascript:', $result);
    $this->assertStringNotContainsString('<iframe', $result);
    $this->assertStringNotContainsString('Embedded Video', $result);
  }

  /**
   * Tests that existing chrome stripping still works.
   */
  public function testExistingChromeStripping(): void {
    $html = '<article><p>Content here</p></article>'
      . '<nav><ul><li><a href="/">Home</a></li></ul></nav>'
      . '<div id="comments"><h2>Comments</h2></div>'
      . '<ul class="links inline"><li>Log in to post</li></ul>';
    $result = $this->strip($html);

    $this->assertStringContainsString('Content here', $result);
    $this->assertStringNotContainsString('<nav>', $result);
    $this->assertStringNotContainsString('Comments', $result);
    $this->assertStringNotContainsString('Log in to post', $result);
  }

  /**
   * Tests nested paragraph div wrappers pass through.
   */
  public function testNestedParagraphDivsPassthrough(): void {
    $html = '<div class="paragraph--type--text"><div class="field__items"><div class="field__item"><p>Hello world</p></div></div></div>';
    $result = $this->strip($html);

    $this->assertStringContainsString('Hello world', $result);
  }

  /**
   * Tests details with empty or missing summary produces no empty heading.
   */
  public function testDetailsWithEmptyOrMissingSummary(): void {
    // Empty summary text.
    $html = '<details><summary>   </summary><p>Body content.</p></details>';
    $result = $this->strip($html);
    $this->assertStringNotContainsString('<h3>', $result);
    $this->assertStringContainsString('Body content.', $result);

    // No summary element at all.
    $html2 = '<details><p>Just body content.</p></details>';
    $result2 = $this->strip($html2);
    $this->assertStringNotContainsString('<h3>', $result2);
    $this->assertStringContainsString('Just body content.', $result2);
    $this->assertStringNotContainsString('<details>', $result2);
  }

  /**
   * Tests that details/summary preserves inner HTML (links, emphasis, etc.).
   */
  public function testDetailsSummaryPreservesInnerHtml(): void {
    $html = '<details><summary>Title</summary><p>See <a href="/more">this link</a> and <em>emphasis</em>.</p></details>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<h3>Title</h3>', $result);
    $this->assertStringContainsString('<a href="/more">this link</a>', $result);
    $this->assertStringContainsString('<em>emphasis</em>', $result);
  }

  /**
   * Tests Paragraphs accordion title field div is converted to h3.
   */
  public function testParagraphsAccordionTitleField(): void {
    $html = '<div class="paragraph paragraph--type--accordion">'
      . '<div class="field field--name-field-accordion-title field--type-string field--label-hidden field__item">FAQ Question</div>'
      . '<div class="field field--name-field-accordion-body field--type-text-long field--label-hidden field__item"><p>The answer.</p></div>'
      . '</div>';
    $result = $this->strip($html);

    $this->assertStringContainsString('<h3>FAQ Question</h3>', $result);
    $this->assertStringContainsString('The answer.', $result);
  }

  /**
   * Tests Paragraphs media embed URL field is converted to a link.
   */
  public function testParagraphsMediaEmbedUrlField(): void {
    $html = '<div class="paragraph paragraph--type--media-embed">'
      . '<div class="field field--name-field-embed-url field--type-string field--label-hidden field__item">https://www.youtube.com/embed/xyz123</div>'
      . '</div>';
    $result = $this->strip($html);

    $this->assertStringContainsString('href="https://www.youtube.com/embed/xyz123"', $result);
    $this->assertStringContainsString('Embedded Video', $result);
  }

  /**
   * Tests combination of paragraph patterns in a single page.
   */
  public function testCombinedParagraphPatterns(): void {
    $html = '<div class="paragraph--type--text"><p>Intro paragraph.</p></div>'
      . '<div class="paragraph--type--accordion"><details><summary>FAQ 1</summary>Answer 1.</details></div>'
      . '<div class="paragraph--type--image"><figure><img src="/photo.jpg" alt="Photo"><figcaption>A caption</figcaption></figure></div>'
      . '<div class="paragraph--type--video"><iframe src="https://youtube.com/embed/abc123"></iframe></div>';
    $result = $this->strip($html);

    $this->assertStringContainsString('Intro paragraph.', $result);
    $this->assertStringContainsString('<h3>FAQ 1</h3>', $result);
    $this->assertStringContainsString('Answer 1', $result);
    $this->assertStringContainsString('<img src="/photo.jpg"', $result);
    $this->assertStringContainsString('<em>A caption</em>', $result);
    $this->assertStringContainsString('https://youtube.com/embed/abc123', $result);
    $this->assertStringContainsString('Embedded Video', $result);
  }

}
