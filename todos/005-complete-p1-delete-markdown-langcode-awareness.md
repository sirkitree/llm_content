---
status: complete
priority: p1
issue_id: "005"
tags: [code-review, data-integrity, multilingual]
dependencies: []
---

# deleteMarkdown() Destroys All Language Variants

## Problem Statement

`deleteMarkdown()` deletes ALL rows for a given `nid` regardless of `langcode`. If only one translation is unpublished or deleted, all language variants of the markdown are destroyed. Flagged by Data Integrity Guardian (CRITICAL).

## Findings

1. **`MarkdownConverter.php:156`** — `->condition('nid', $nid)` without langcode filter
2. The `convert()` method stores per-langcode, but delete ignores langcode
3. Unpublishing a Spanish translation would delete the English markdown too

## Proposed Solutions

### Solution A: Add langcode parameter to deleteMarkdown() (Recommended)
- Change signature to `deleteMarkdown(int $nid, ?string $langcode = NULL): void`
- When langcode is provided, only delete that specific translation
- When NULL (node deletion), delete all variants
- Update interface and all callers
- **Pros:** Correct behavior, backward compatible with NULL default
- **Cons:** Callers need updating
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] Unpublishing one translation only removes that language's markdown
- [ ] Deleting a node removes all language variants
- [ ] Interface updated with new signature

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
