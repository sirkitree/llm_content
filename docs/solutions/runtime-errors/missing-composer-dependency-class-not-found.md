---
title: Fatal error when enabling llm_content module without league/html-to-markdown dependency
problem_type: runtime-error
component: llm_content
technology: [drupal, php, composer]
symptoms:
  - Fatal error during module installation when league/html-to-markdown not installed
  - MarkdownConverter constructor fails attempting to instantiate League\HTMLToMarkdown\HtmlConverter
  - No validation of required third-party dependencies before module enable
  - Runtime requirements check fails to execute when library missing due to DI chain
root_cause: Drupal doesn't auto-install module-level composer dependencies and module had no install-time validation for required league/html-to-markdown library
severity: critical
date_solved: 2026-02-10
github_issue: 7
github_pr: 10
files_created:
  - src/Install/Requirements/LlmContentRequirements.php
  - src/Hook/LlmContentRequirementsHooks.php
  - tests/src/Unit/LlmContentRequirementsTest.php
  - tests/src/Unit/LlmContentRequirementsHooksTest.php
files_modified:
  - src/Hook/LlmContentHooks.php
tags: [drupal-11, dependency-management, requirements-checking, fatal-error, composer-dependencies, install-hooks, dependency-injection, runtime-validation]
---

# Fatal error when enabling llm_content without league/html-to-markdown

## Problem Symptoms

Enabling the `llm_content` module without first running `composer require league/html-to-markdown` causes a PHP fatal error:

```
Class "League\HTMLToMarkdown\HtmlConverter" not found
```

This happens because `MarkdownConverter.__construct()` unconditionally instantiates `League\HTMLToMarkdown\HtmlConverter`. Drupal does not auto-install module-level Composer dependencies, so users who skip the `composer require` step hit a crash.

## Root Cause Analysis

When a Drupal 11 module depends on third-party Composer packages, Drupal does not automatically install those dependencies. The `MarkdownConverter` service unconditionally instantiates `League\HTMLToMarkdown\HtmlConverter` in its constructor. When the library is not installed, the service container fails at the moment it tries to build the service.

**Critical architectural constraint**: Any hook class that depends on services which themselves depend on the missing library will also fail to instantiate. This means runtime requirement checks cannot live in the same class that uses `MarkdownConverterInterface`.

## Solution

Three-part defense-in-depth approach:

### 1. Install-Time Gate

`src/Install/Requirements/LlmContentRequirements.php` implements `InstallRequirementsInterface` to block module installation:

```php
final class LlmContentRequirements implements InstallRequirementsInterface {
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
```

### 2. Runtime Status Check

`src/Hook/LlmContentRequirementsHooks.php` — a **separate** class with NO dependencies on MarkdownConverter:

```php
final class LlmContentRequirementsHooks {
  use StringTranslationTrait;

  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    if (!class_exists('League\HTMLToMarkdown\HtmlConverter')) {
      // Return error with actionable message
    } else {
      // Return OK status
    }
  }
}
```

### 3. Architectural Separation

The runtime check **must** be in a separate class from `LlmContentHooks` because:

```
LlmContentHooks
  → depends on MarkdownConverterInterface
    → implemented by MarkdownConverter
      → instantiates HtmlConverter in __construct()
        → FATAL if library missing
```

If the hook lived in `LlmContentHooks`, the DI container would fail to create the entire class, and `runtimeRequirements()` would never fire.

### File Structure

```
src/
  Install/
    Requirements/
      LlmContentRequirements.php        # Install-time gate (static, no DI)
  Hook/
    LlmContentHooks.php                 # Main hooks (depends on MarkdownConverter)
    LlmContentRequirementsHooks.php     # Runtime check (NO dependencies)
```

## Bugs Encountered During Implementation

### 1. Wrong Namespace for RequirementSeverity

```php
// WRONG - this namespace does not exist
use Drupal\Core\Requirements\RequirementSeverity;

// CORRECT
use Drupal\Core\Extension\Requirement\RequirementSeverity;
```

### 2. Wrong Enum Case

```php
// WRONG - lowercase 'k'
RequirementSeverity::Ok

// CORRECT - both letters uppercase
RequirementSeverity::OK
```

The full enum: `::OK`, `::Error`, `::Warning`, `::Info`.

## Verification Steps

1. **Without library**: `ddev drush en llm_content` shows error, blocks install
2. **With library**: `ddev drush en llm_content -y` installs cleanly
3. **Status report**: `ddev drush core:requirements | grep -i llm` shows green "OK - Installed"

## Prevention Strategies

### For Future Drupal Modules with Composer Dependencies

1. **Always implement both install-time and runtime requirements checks** when depending on Composer libraries
2. **Keep requirements hook classes dependency-free** — no injected services that depend on the third-party library
3. **Use `class_exists()` checks** rather than trying to instantiate classes from the library
4. **Use `TranslatableMarkup` directly** in install requirements (limited Drupal API available at install time)
5. **Include exact `composer require` command** with version constraints in error messages

### Drupal 11 Requirements API Quick Reference

| Pattern | Location | Interface/Attribute |
|---------|----------|-------------------|
| Install-time | `src/Install/Requirements/` | `InstallRequirementsInterface` |
| Runtime | `src/Hook/` | `#[Hook('runtime_requirements')]` |
| Severity enum | `Drupal\Core\Extension\Requirement\RequirementSeverity` | `::OK`, `::Error`, `::Warning`, `::Info` |

### Testing Checklist

- [ ] Module install blocked without library (without `-y` flag)
- [ ] Module installs cleanly with library
- [ ] Status report shows green when library present
- [ ] Status report shows red when library removed after install
- [ ] Requirements hook class has zero service dependencies
- [ ] Correct namespace: `Drupal\Core\Extension\Requirement\RequirementSeverity`
- [ ] Correct enum cases: `::OK` (not `::Ok`)
- [ ] Unit tests pass for both requirements classes

## Related

- [GitHub Issue #7](https://github.com/sirkitree/llm_content/issues/7)
- [GitHub PR #10](https://github.com/sirkitree/llm_content/pull/10)
- [Module README](../../README.md)
