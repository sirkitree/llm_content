---
status: complete
priority: p2
issue_id: "011"
tags: [code-review, drupal, data-integrity]
dependencies: []
---

# Missing Content Type Filtering in Aggregated Queries

## Problem Statement

The aggregated endpoints (`/llms.txt`, `/llms-full.txt`) may include markdown from content types that have been disabled in module settings if stale records remain in the database. Flagged by Data Integrity Guardian (HIGH), Security Sentinel.

## Findings

1. **`LlmsTxtController.php`** — Queries don't filter by enabled content types from config
2. **`LlmContentHooks.php`** — `regenerateLlmsFullTxt()` may include disabled types
3. If an admin disables a content type, existing markdown records still get served

## Proposed Solutions

### Solution A: Filter by enabled types in all queries (Recommended)
- JOIN with `node_field_data` and filter by `type IN (enabled_types)`
- Also filter by `status = 1` (published)
- **Pros:** Correct, consistent with per-node endpoint behavior
- **Cons:** Slightly more complex query
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] Disabled content types don't appear in aggregated endpoints
- [ ] Only published nodes of enabled types are included

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
