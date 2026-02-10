---
status: complete
priority: p1
issue_id: "004"
tags: [code-review, security, yaml, injection]
dependencies: []
---

# YAML Frontmatter Injection via Node Title

## Problem Statement

Node titles are inserted into YAML frontmatter via string concatenation. A title containing `"` followed by a newline and YAML directives could inject arbitrary frontmatter fields. Flagged by Security Sentinel (MEDIUM).

## Findings

1. **`MarkdownConverter.php:65`** — `'title: "' . str_replace('"', '\\"', $node->label()) . '"'`
2. Only escapes double quotes, not newlines, colons, or other YAML special characters
3. A title like `My Title"\ninjected: true` could break frontmatter parsing

## Proposed Solutions

### Solution A: Sanitize title more thoroughly (Recommended)
- Strip or escape newlines, carriage returns, and other control characters from the title
- Also escape backslashes to prevent escape sequence injection
- **Pros:** Simple, minimal change
- **Cons:** Edge cases may remain
- **Effort:** Small
- **Risk:** Low

### Solution B: Use a YAML library for serialization
- Use `Symfony\Component\Yaml\Yaml::dump()` for the frontmatter values
- **Pros:** Handles all edge cases correctly
- **Cons:** Adds dependency (Symfony YAML is already in Drupal core)
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] Node titles with special YAML characters don't break frontmatter
- [ ] No newlines or control characters can be injected via title
- [ ] Frontmatter remains valid YAML

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
