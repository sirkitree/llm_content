---
status: complete
priority: p2
issue_id: "006"
tags: [code-review, drupal, architecture, dependency-injection]
dependencies: ["001"]
---

# Inconsistent Dependency Injection Patterns Across Controllers

## Problem Statement

Three different DI patterns are used across three controllers: `create()` factory, no DI at all, and promoted constructor parameters. Drupal 11 recommends autowired constructor injection. Flagged by Pattern Recognition, Architecture Strategist, Drupal Expert.

## Findings

1. **`LlmMarkdownController.php`** — Uses `create()` factory pattern
2. **`LlmSitemapController.php`** — No `create()` method, no DI
3. **`LlmsTxtController.php`** — Uses `create()` factory with multiple services
4. **`MarkdownConverter.php`** — Uses promoted constructor params (correct D11 pattern)

## Proposed Solutions

### Solution A: Standardize on autowired constructor injection (Recommended)
- Convert all controllers to use promoted constructor parameters
- Remove `create()` methods
- Ensure `autowire: true` covers all controllers
- **Pros:** Consistent, modern D11, less boilerplate
- **Cons:** Controllers need `ContainerInjectionInterface` or autowire
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] All controllers use the same DI pattern
- [ ] No `create()` factory methods remain
- [ ] All services properly injected via constructor

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
