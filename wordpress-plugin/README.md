# WPGPTVibe Bridge

Version 0.2.0.

WPGPTVibe Bridge is the WordPress-side policy and execution layer for the WPGPTVibe MCP server. It is designed for internal multi-site management without third-party action quotas.

## Install

1. Download the `wpgptvibe-bridge` artifact from a successful GitHub Actions run.
2. Extract the Actions artifact once. Upload the contained `wpgptvibe-bridge.zip` in WordPress Admin → Plugins → Add Plugin → Upload Plugin.
3. Activate **WPGPTVibe Bridge**.
4. Go to Tools → WPGPTVibe.
5. Generate an API key and copy it immediately. The plaintext token is shown once.
6. Enable only the capabilities required for that site.

## Authentication

All REST calls use:

```http
Authorization: Bearer <site-api-token>
X-WPGPTVIBE-Site-ID: <site-uuid>
```

Production requests require HTTPS. Tokens are stored in WordPress as one-way hashes.

## Capability groups

- `read_site`
- `read_theme_files`
- `edit_theme_files`
- `create_draft_themes`
- `publish_themes`
- `rollback_themes`
- `manage_content`
- `manage_meta`
- `manage_media`
- `manage_seo`
- `purge_cache`
- `run_wpcli`
- `manage_calculators`

High-risk actions also require an explicit confirmation value at the REST layer.

## REST surface

Base namespace:

```text
/wp-json/wpgptvibe/v1/
```

### Site and audit

- `GET site`
- `GET audit`

### Theme and file operations

- `GET theme/files`
- `GET theme/file`
- `GET theme/search`
- `GET theme/file/diff`
- `POST theme/file/edit`
- `POST theme/file/write`
- `POST theme/file/delete` (draft only, `confirm=DELETE`)
- `POST theme/files/batch-edit`
- `POST theme/draft/create`
- `GET theme/draft`
- `GET theme/draft/preview`
- `POST theme/draft/publish` (`confirm=PUBLISH`)
- `POST theme/rollback` (`confirm=ROLLBACK`)

Theme writes remain draft-first. File extensions are restricted to php, css, js, json, html, txt and svg. Paths are constrained to approved theme roots.

### Content

- `GET content/types`
- `GET content`
- `GET content/{id}`
- `POST content` (draft status by default)
- `PUT content/{id}`
- `DELETE content/{id}` (`confirm=DELETE`)
- `POST content/batch-update`

Public WordPress post types with an admin UI can be managed. Attachments are handled through the media API instead.

### Meta

- `GET content/{id}/meta`
- `PUT content/{id}/meta`
- `POST meta/batch-update`

Protected keys beginning with `_` are blocked unless they are explicitly added to the Protected Meta Allowlist in Tools → WPGPTVibe.

### Media

- `GET media`
- `GET media/{id}`
- `POST media/upload`
- `POST media/import`
- `PUT media/{id}`

Uploads/imports enforce WordPress MIME rules and a 10 MB payload limit. Remote imports require HTTPS.

### SEO

- `GET seo/{id}`
- `PUT seo/{id}`
- `POST seo/batch-update`

Provider order: Rank Math → Yoast → generic fallback. Rank Math and Yoast adapters manage their native metadata. Generic mode can update title/excerpt but reports that canonical/robots/focus-keyword writes are not available without a supported SEO provider.

### Cache and maintenance

- `POST cache/purge`
- `POST rewrite/flush` (`confirm=FLUSH`)

The cache manager can flush WordPress object cache and calls known cache/SEO hooks only when they are present.

### WP-CLI

- `GET wpcli/status`
- `POST wpcli/run`

No raw shell command is accepted. Named operations are allowlisted:

- `plugin_list`
- `theme_list`
- `option_get`
- `post_list`
- `cron_list`
- `cache_flush` (`confirm=RUN`)
- `rewrite_flush` (`confirm=RUN`)

### Calculator provider contract

- `GET calculators`
- `GET calculators/{slug}`
- `PUT calculators/{slug}`
- `GET calculators/{slug}/validate`
- `GET calculators/validate-all`
- `POST calculators/{slug}/test`
- `POST calculators/batch-update`

Sites register their calculator data and behavior through WordPress filters so the Bridge core stays generic:

- `wpgptvibe_calculators`
- `wpgptvibe_calculator_get`
- `wpgptvibe_calculator_update`
- `wpgptvibe_calculator_validate`
- `wpgptvibe_calculator_test`

This is the integration point for EzyMFG's existing calculator configurations.

## Security defaults

- HTTPS outside local development
- hashed WordPress API token
- stable site UUID check
- request body limit
- basic rate limiting
- capability allowlist
- path traversal protection
- protected-meta allowlist
- draft-first file writes
- audit logs
- no arbitrary SQL endpoint
- no PHP eval endpoint
- no unrestricted shell endpoint
- allowlisted WP-CLI only
- confirmation gates for destructive/high-risk actions
