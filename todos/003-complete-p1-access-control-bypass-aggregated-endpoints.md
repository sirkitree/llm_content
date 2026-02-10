---
status: complete
priority: p1
issue_id: "003"
tags: [code-review, security, drupal, access-control]
dependencies: []
---

# Access Control Bypass in Aggregated Endpoints

## Problem Statement

The `/llms-full.txt` endpoint reads markdown directly from the database without checking individual node access. If a node's access is revoked (unpublished, access restricted), its markdown still appears in the aggregated file. Flagged by Security Sentinel (MEDIUM) and Data Integrity Guardian (HIGH).

## Findings

1. **`LlmsTxtController.php`** — `generateLlmsFullTxt()` queries `llm_content_markdown` table directly
2. No `$node->access('view')` check on individual nodes
3. **`regenerateLlmsFullTxt()`** in hooks also doesn't filter by current access state
4. Stale markdown for access-revoked content persists in DB table

## Proposed Solutions

### Solution A: Filter by published status in query (Recommended)
- JOIN with `node_field_data` to check `status = 1`
- Also filter by enabled content types from config
- **Pros:** Single query, efficient, correct
- **Cons:** Doesn't cover complex access rules (but published check covers 99% of cases)
- **Effort:** Small
- **Risk:** Low

### Solution B: Load entities and check access
- Load each node entity and call `$node->access('view')`
- **Pros:** Respects all access rules
- **Cons:** N+1 entity loads, much slower
- **Effort:** Small
- **Risk:** Low (but performance impact)

## Acceptance Criteria

- [ ] Unpublished nodes do not appear in `/llms-full.txt` or `/llms.txt`
- [ ] Disabled content types are filtered from aggregated endpoints
- [ ] `deleteMarkdown()` is called when a node is unpublished

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
