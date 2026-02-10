# LLM Content

A Drupal 11 module that generates markdown views of your site content for LLM crawlers and AI agents.

Keep your normal Drupal-rendered HTML for humans and classic SEO, while also serving parallel markdown versions for AI bots. LLM crawlers discover content via a dedicated sitemap and structured index files, getting a clean, low-noise representation that's easier to embed and summarize than raw HTML.

## Requirements

- Drupal 11.1+
- PHP 8.3+
- `league/html-to-markdown` ^5.0

## Installation

```bash
composer require league/html-to-markdown:^5.0
drush en llm_content
```

Place the module in `web/modules/custom/llm_content/` or install via Composer.

## Configuration

Navigate to **Administration > Configuration > Content > LLM Content Settings** (`/admin/config/content/llm-content`).

- **Enabled content types** -- Select which content types to expose as markdown
- **View mode** -- Choose which view mode to render before converting to markdown
- **Auto-generate** -- Automatically regenerate markdown when content is saved

## Endpoints

| Path | Description | Access |
|------|-------------|--------|
| `/llm-md/node/{id}` | Individual node as markdown with YAML frontmatter | Node view access |
| `/llms.txt` | Directory listing of all enabled content with links | `access content` |
| `/llms-full.txt` | Full markdown of all enabled content concatenated | `access content` |
| `/sitemap-llm.xml` | XML sitemap listing all markdown URLs | `access content` |

### Example: Individual Node Markdown

```
GET /llm-md/node/1
```

Returns:

```markdown
---
title: "My Article"
url: /my-article
type: article
date: "February 9, 2026"
updated: "2026-02-09T12:00:00+00:00"
---

# My Article

The full content of the article converted to clean markdown...
```

### Example: llms.txt

```
GET /llms.txt
```

Returns a directory-style listing following the [llms.txt specification](https://llmstxt.org/):

```markdown
# My Site

> Site slogan here

## Content

- [My Article](/llm-md/node/1): Brief description...
- [Another Page](/llm-md/node/2): Brief description...
```

## How It Works

1. When a node is saved, the module renders it using Drupal's render system with the configured view mode
2. Drupal-specific chrome (navigation, comments, admin toolbars) is stripped via DOM manipulation
3. The cleaned HTML is converted to markdown using `league/html-to-markdown`
4. YAML frontmatter (title, URL, type, dates) is prepended
5. The result is stored in a custom database table (`llm_content_markdown`) for fast retrieval
6. Endpoints serve the stored markdown with appropriate cache tags for automatic invalidation

## Security

- Individual node endpoints respect Drupal's entity access system (`node.view`)
- Listing endpoints require `access content` permission
- Only published nodes of enabled content types are exposed
- YAML frontmatter values are sanitized against injection
- URI schemes in markdown links use an allowlist (http, https, mailto, tel, relative paths)
- XML sitemap uses `XMLWriter` for safe generation
- All responses include `X-Content-Type-Options: nosniff`

## Permissions

| Permission | Description |
|------------|-------------|
| `administer llm content` | Configure module settings (restricted) |

Public endpoints use standard Drupal access controls -- no additional permissions needed for viewing.

## Cache Invalidation

The module uses Drupal's cache tag system for automatic invalidation:

- `node:{id}` -- Individual node markdown is invalidated when that node changes
- `node_list` -- Listing endpoints are invalidated when any node is created/deleted
- `llm_content:list` -- Custom tag invalidated on saves to enabled content types

## Architecture

```
src/
  Controller/
    LlmMarkdownController.php    # /llm-md/node/{id}
    LlmsTxtController.php        # /llms.txt and /llms-full.txt
    LlmSitemapController.php     # /sitemap-llm.xml
  Service/
    MarkdownConverterInterface.php
    MarkdownConverter.php         # HTML-to-markdown conversion + DB storage
  Hook/
    LlmContentHooks.php          # Entity lifecycle hooks (D11 OOP attributes)
  Form/
    LlmContentSettingsForm.php   # Admin configuration form
```

## License

GPL-2.0-or-later
