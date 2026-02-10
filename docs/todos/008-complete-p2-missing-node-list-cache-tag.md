---
status: complete
priority: p2
issue_id: "008"
tags: [code-review, drupal, caching]
dependencies: []
---

# Missing node_list Cache Tag on List Endpoints

## Problem Statement

The `/llms.txt` and `/llms-full.txt` endpoints list content from multiple nodes but don't include the `node_list` cache tag. When new nodes are created, these endpoints may serve stale content. Flagged by Drupal Expert (HIGH).

## Findings

1. **`LlmsTxtController.php`** — Uses custom `llm_content:list` tag but not `node_list`
2. Drupal's `node_list` tag is invalidated when any node is created/deleted
3. Without it, a newly published node won't appear until manual cache clear

## Proposed Solutions

### Solution A: Add node_list cache tag (Recommended)
- Add `$cacheMetadata->addCacheTags(['node_list'])` to list endpoints
- Keep custom `llm_content:list` tag for module-specific invalidation
- **Pros:** Correct Drupal caching behavior
- **Cons:** More frequent invalidation (but that's correct)
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] `node_list` cache tag present on `/llms.txt` and `/llms-full.txt` responses
- [ ] New published nodes appear without manual cache clear

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
