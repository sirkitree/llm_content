---
status: complete
priority: p3
issue_id: "012"
tags: [code-review, security]
dependencies: []
---

# URI Scheme Sanitization Should Use Allowlist

## Problem Statement

The dangerous URI scheme regex in `MarkdownConverter.php` uses a denylist (`javascript|vbscript|data`), which could miss new dangerous schemes. An allowlist (`https?|mailto|tel`) would be more secure. Flagged by Security Sentinel (LOW).

## Findings

1. **`MarkdownConverter.php:60`** — `preg_replace('/\[([^\]]*)\]\((javascript|vbscript|data):[^)]*\)/i', ...)`
2. Denylist approach may miss future dangerous URI schemes

## Proposed Solutions

### Solution A: Switch to allowlist approach
- Only allow `http`, `https`, `mailto`, `tel`, and relative URLs in links
- Strip any other scheme
- **Pros:** More secure, future-proof
- **Cons:** May need to add schemes as needed
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] Only allowed URI schemes pass through
- [ ] Unknown schemes are stripped

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
