---
status: complete
priority: p1
issue_id: "002"
tags: [code-review, performance, drupal, hooks]
dependencies: []
---

# Synchronous llms-full.txt Regeneration on Every Node Save

## Problem Statement

Every node save triggers synchronous `regenerateLlmsFullTxt()` which loads ALL enabled nodes, generates markdown for each, and writes a file. This blocks the editor for 200-500ms+ and scales linearly with content count. Flagged as CRITICAL by Performance Oracle and HIGH by Architecture Strategist, Drupal Expert.

## Findings

1. **`LlmContentHooks.php`** — `regenerateLlmsFullTxt()` called synchronously in entity presave/delete hooks
2. Loads every published node of enabled types on every single save
3. With 1000+ nodes, this could block the editor for seconds
4. Also regenerates markdown for the current node synchronously (150-300ms)

## Proposed Solutions

### Solution A: Remove pre-generated file, generate on-demand (Recommended)
- Remove the file-based caching of llms-full.txt entirely
- Generate dynamically in the controller from the DB cache table
- Invalidate via cache tags (already in place)
- **Pros:** Eliminates the blocking save entirely, simpler architecture, removes YAGNI
- **Cons:** First request after cache clear is slower
- **Effort:** Medium
- **Risk:** Low

### Solution B: Queue-based async regeneration
- Use Drupal Queue API to defer regeneration
- **Pros:** Still has file cache, non-blocking
- **Cons:** More complex, stale data between save and queue run
- **Effort:** Medium
- **Risk:** Low

## Acceptance Criteria

- [ ] Node save operations complete in <50ms for the module's hooks
- [ ] `/llms-full.txt` still serves correct content
- [ ] No synchronous full-site operations in entity hooks

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
