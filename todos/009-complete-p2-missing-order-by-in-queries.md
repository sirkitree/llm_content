---
status: complete
priority: p2
issue_id: "009"
tags: [code-review, data-integrity, drupal]
dependencies: []
---

# Missing ORDER BY in Aggregated Queries

## Problem Statement

Queries for `/llms-full.txt` fallback and node listings don't specify ORDER BY, resulting in non-deterministic output ordering. Flagged by Data Integrity Guardian (CRITICAL), Architecture Strategist (HIGH).

## Findings

1. **`LlmsTxtController.php`** — `generateLlmsFullTxt()` fallback query has no `->orderBy()`
2. Output order may change between page loads
3. Makes debugging and comparison difficult

## Proposed Solutions

### Solution A: Add consistent ORDER BY (Recommended)
- Add `->orderBy('nid', 'ASC')` to all listing queries
- **Pros:** Deterministic output, simple
- **Cons:** None
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] All listing queries have explicit ORDER BY
- [ ] Output is deterministic between requests

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
