---
status: complete
priority: p2
issue_id: "007"
tags: [code-review, architecture, duplication]
dependencies: ["002"]
---

# Duplicated Full-Text Generation Logic

## Problem Statement

The logic to generate llms-full.txt content exists in both `LlmContentHooks::regenerateLlmsFullTxt()` and `LlmsTxtController::generateLlmsFullTxt()`. This violates DRY and causes inconsistencies. Flagged by Code Simplicity Reviewer, Architecture Strategist.

## Findings

1. **`LlmContentHooks.php`** — `regenerateLlmsFullTxt()` generates and writes to file
2. **`LlmsTxtController.php`** — `generateLlmsFullTxt()` as fallback generation
3. Both query nodes, build markdown, but may produce different results
4. If we adopt Solution A from todo #002 (remove pre-generated file), this is resolved automatically

## Proposed Solutions

### Solution A: Consolidate into MarkdownConverter service (Recommended)
- Move full-text generation into the service
- Both hooks and controller call the same method
- If adopting on-demand generation (todo #002), only the controller path remains
- **Pros:** Single source of truth, DRY
- **Cons:** None
- **Effort:** Small (especially if combined with #002)
- **Risk:** Low

## Acceptance Criteria

- [ ] Only one implementation of full-text generation exists
- [ ] Hook and controller produce identical output

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
