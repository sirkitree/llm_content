---
status: complete
priority: p3
issue_id: "013"
tags: [code-review, security, drupal]
dependencies: []
---

# Sitemap Uses Request Host Instead of Configured Base URL

## Problem Statement

The sitemap controller uses the request's host header for generating URLs, which could be manipulated. Should use Drupal's configured base URL instead. Flagged by Security Sentinel (LOW).

## Findings

1. **`LlmSitemapController.php`** — Uses request object for base URL construction
2. Host header can be forged in some server configurations

## Proposed Solutions

### Solution A: Use Drupal's configured base URL
- Use `\Drupal::request()->getSchemeAndHttpHost()` or Settings::get('base_url')
- Or use the `url_generator` service for absolute URLs
- **Pros:** Not susceptible to host header injection
- **Cons:** May need configuration
- **Effort:** Small
- **Risk:** Low

## Acceptance Criteria

- [ ] Sitemap URLs use configured base URL, not request host
- [ ] URLs are correct in all environments

## Work Log

| Date | Action | Learnings |
|------|--------|-----------|
| | | |
