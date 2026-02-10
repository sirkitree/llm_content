---
status: complete
priority: p1
issue_id: "001"
tags: [code-review, drupal, architecture, dependency-injection]
dependencies: []
---

# Static \Drupal:: Service Calls in Injected Services

## Problem Statement

The module uses static `\Drupal::` calls inside services that already have dependency injection, violating Drupal 11 best practices and making the code untestable. This is flagged as critical by multiple reviewers (Security Sentinel, Drupal Expert, Architecture Strategist, Pattern Recognition).

## Findings

1. **`MarkdownConverter.php:84`** — `\Drupal::time()->getRequestTime()` used inside an injected service
2. **`LlmsTxtController.php`** — `\Drupal::database()` used in fallback `generateLlmsFullTxt()` method
3. **`LlmContentHooks.php`** — May have static calls for database or file system operations

## Proposed Solutions

### Solution A: Inject all dependencies via constructor (Recommended)
- Add `TimeInterface` and `Connection` to constructor parameters
- Update `services.yml` if not using autowire
- **Pros:** Testable, follows D11 conventions, autowire handles it
- **Cons:** None
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] No `\Drupal::` static calls remain in any PHP file
- [ ] All services use constructor injection
- [ ] Module still functions correctly after changes

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |

## Resources

- Drupal 11 DI docs: https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection
