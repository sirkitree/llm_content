---
status: complete
priority: p2
issue_id: "010"
tags: [code-review, security, drupal, permissions]
dependencies: []
---

# Missing restrict_access on Admin Permission

## Problem Statement

The module's permission definition in `llm_content.permissions.yml` lacks `restrict access: TRUE`. This means the permission appears as a regular permission rather than a restricted one, making it easier to accidentally grant. Flagged by Drupal Expert (MEDIUM).

## Findings

1. **`llm_content.permissions.yml`** — Permission defined without `restrict access` flag
2. Admin permissions should use `restrict access: TRUE` to show a warning on the permissions page

## Proposed Solutions

### Solution A: Add restrict access flag (Recommended)
- Add `restrict access: TRUE` to the admin permission
- **Pros:** Follows Drupal best practices, warns admins
- **Cons:** None
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] `restrict access: TRUE` present on admin permission
- [ ] Permission shows warning on permissions page

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
