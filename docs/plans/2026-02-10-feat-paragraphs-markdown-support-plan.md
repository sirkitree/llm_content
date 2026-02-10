---
title: "feat: improve markdown conversion for Paragraphs-based content"
type: feat
date: 2026-02-10
issue: https://github.com/sirkitree/llm_content/issues/6
---

# feat: Improve Markdown Conversion for Paragraphs-based Content

## Overview

Enhance the llm_content module's HTML-to-markdown pipeline to produce high-quality output from Drupal Paragraphs-based content. The existing pipeline already handles most paragraph rendering correctly (wrapper stripping, field ordering, basic text), but specific structural patterns (accordion/details, figure/figcaption, iframe media) produce degraded output.

## Problem Statement

Content types using the Paragraphs module often lack a `body` field and use complex HTML structures that the current generic converter handles poorly:

1. `<details>/<summary>` elements (accordion paragraphs) lose their heading/body structure
2. `<figure>/<figcaption>` elements lose caption text
3. `<iframe>` elements (embedded videos) are silently removed with no trace
4. Deeply nested `<div>` wrappers produce excessive whitespace (6-10 blank lines)
5. `/llms.txt` descriptions are empty for content types without a `body` field

## What Already Works (No Changes Needed)

Per spec flow analysis, these requirements from Issue #6 are **already handled**:

- **Paragraph wrapper stripping** (#2): `league/html-to-markdown`'s `DivConverter` with `strip_tags=TRUE` already strips all `<div class="paragraph--type--*">` wrappers
- **Paragraph field ordering** (#3): Drupal's render system renders entity_reference_revisions fields in delta order; the pipeline preserves HTML order
- **Custom paragraph view mode** (#4): The module already supports a configurable node view mode; paragraph view modes are configured in Drupal's display management UI, not in code

## Proposed Solution

Extend the `stripDrupalChrome()` method and add a post-processing step to handle Paragraphs-specific HTML patterns at the HTML/DOM level. No Paragraphs module dependency needed -- all changes operate on rendered HTML.

## Technical Approach

### Phase 1: Install Paragraphs and Create Test Content

- [x] `ddev composer require drupal/paragraphs`
- [x] `ddev drush en paragraphs`
- [x] Create paragraph types for testing: `text`, `image`, `accordion` (using `<details>/<summary>`), `media_embed`
- [x] Create a "Landing Page" content type with a `field_content` (entity_reference_revisions) field instead of `body`
- [x] Enable "Landing Page" in llm_content settings
- [x] Create test nodes with various paragraph combinations
- [x] Verify current output to confirm the problems described above

### Phase 2: Enhance stripDrupalChrome() for Paragraph Patterns

**File:** `src/Service/MarkdownConverter.php` - `stripDrupalChrome()` method (line 112)

Add XPath queries to handle Paragraphs-specific HTML patterns before markdown conversion:

- [x] Convert `<details>/<summary>` to semantic HTML that converts well to markdown:
  ```
  <details><summary>Title</summary>Content</details>
  → <h3>Title</h3><p>Content</p>
  ```
  This produces `### Title\n\nContent` in markdown.

- [x] Convert `<figure>/<figcaption>` to img + italic caption:
  ```
  <figure><img src="..." alt="..."><figcaption>Caption</figcaption></figure>
  → <img src="..." alt="..."><p><em>Caption</em></p>
  ```
  This produces `![alt](src)\n\n*Caption*` in markdown.

- [x] Replace removed `<iframe>` elements with a link placeholder (before the HtmlConverter strips them):
  ```
  <iframe src="https://youtube.com/embed/xyz"></iframe>
  → <p><a href="https://youtube.com/embed/xyz">[Embedded Video]</a></p>
  ```
  Extract the `src` attribute via XPath before removal. Only for iframes with valid http/https src.

### Phase 3: Whitespace Normalization

**File:** `src/Service/MarkdownConverter.php` - `convert()` method (after line 74)

- [x] Add post-processing after HTML-to-markdown conversion to collapse excessive newlines:
  ```php
  $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;
  ```
  This collapses 3+ consecutive newlines into exactly 2 (one blank line), fixing the whitespace explosion from nested paragraph divs.

### Phase 4: Fix llms.txt Description for Paragraphs Content

**File:** `src/Controller/LlmsTxtController.php` - `llmsTxt()` method (lines 61-67)

- [x] Update the description extraction to fall back when `body` field is missing:
  ```php
  $description = '';
  if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
    $body = $node->get('body')->first();
    $description = $body->summary ?: mb_substr(strip_tags($body->value ?? ''), 0, 200);
  }
  else {
    // Fallback: use stored markdown (strip frontmatter and title, take first 200 chars).
    $stored = $this->markdownConverter->getMarkdown($node);
    // Remove YAML frontmatter block.
    $stored = preg_replace('/^---\n.*?\n---\n+/s', '', $stored) ?? $stored;
    // Remove the H1 title line.
    $stored = preg_replace('/^# .+\n+/', '', $stored) ?? $stored;
    $description = mb_substr(trim($stored), 0, 200);
  }
  ```

### Phase 5: Tests

**File:** `tests/src/Unit/MarkdownConverterTest.php` (new)

- [x] Unit tests for the HTML-to-markdown conversion with representative Paragraphs HTML:
  - Text paragraph with nested `<div class="paragraph--type--text">` wrappers
  - Image paragraph with `<figure>` and `<figcaption>`
  - Accordion paragraph with `<details>` and `<summary>`
  - Iframe/video embed paragraph
  - Deeply nested paragraphs (verify whitespace normalization)
  - Node with no body field (llms.txt description fallback)

Tests should use hand-crafted HTML snippets representing typical Paragraphs output, not require the Paragraphs module to be installed.

## Acceptance Criteria

- [x] `<details>/<summary>` elements produce `### Summary Title\n\nContent` in markdown
- [x] `<figure>/<figcaption>` produces `![alt](src)\n\n*Caption*`
- [x] Removed iframes leave a `[Embedded Video](url)` link placeholder
- [x] No more than one blank line between content sections (3+ newlines collapsed to 2)
- [x] `/llms.txt` shows descriptions for content types using Paragraphs (no body field)
- [x] All existing endpoints continue to work correctly (no regressions)
- [x] Unit tests pass for all paragraph HTML patterns

## Files to Modify

| File | Changes |
|------|---------|
| `src/Service/MarkdownConverter.php` | Extend `stripDrupalChrome()` with details/figure/iframe handling; add whitespace normalization in `convert()` |
| `src/Controller/LlmsTxtController.php` | Add fallback description extraction for non-body content types |
| `tests/src/Unit/MarkdownConverterTest.php` | New file: unit tests for paragraph HTML patterns |

## References

- Existing converter: `src/Service/MarkdownConverter.php:46-107`
- Existing chrome stripping: `src/Service/MarkdownConverter.php:112-148`
- llms.txt description: `src/Controller/LlmsTxtController.php:61-67`
- league/html-to-markdown DivConverter strips wrapper divs automatically
- GitHub Issue: https://github.com/sirkitree/llm_content/issues/6
