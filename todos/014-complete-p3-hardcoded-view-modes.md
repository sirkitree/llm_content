---
status: complete
priority: p3
issue_id: "014"
tags: [code-review, drupal, configuration]
dependencies: []
---

# Hardcoded View Mode Options in Settings Form

## Problem Statement

The settings form hardcodes view mode options (`full`, `teaser`, `default`) instead of loading them dynamically from the entity display system. Flagged by Drupal Expert (MEDIUM).

## Findings

1. **`LlmContentSettingsForm.php`** — View modes are hardcoded in the form
2. Custom view modes created by site builders won't appear as options

## Proposed Solutions

### Solution A: Load view modes dynamically
- Use `EntityDisplayRepository::getViewModeOptions('node')` to get available view modes
- **Pros:** Shows all available view modes, respects custom configurations
- **Cons:** Slightly more complex form build
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] All available node view modes appear in the settings form
- [ ] Custom view modes are included

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
