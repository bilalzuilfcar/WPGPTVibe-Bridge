# WPGPTVibe Bridge 0.3.0

WPGPTVibe Bridge is the WordPress-side execution layer for the self-hosted WPGPTVibe application.

## Installation

1. Download the `wpgptvibe-bridge` artifact from GitHub Actions.
2. Extract the workflow artifact once.
3. Upload the inner `wpgptvibe-bridge.zip` through WordPress Admin → Plugins → Add Plugin → Upload Plugin.
4. Activate the plugin.
5. Open Tools → WPGPTVibe.
6. Generate an API token and copy it immediately.
7. Enable only the capabilities needed on this WordPress site.

## Security model

- HTTPS is required outside local development.
- WordPress stores only a password hash of the Bridge token.
- the central WPGPTVibe application stores the site token encrypted with AES-256-GCM.
- every capability is independently enabled/disabled.
- request size and rate guards are enforced.
- theme writes occur in draft themes.
- destructive operations use explicit confirmations.
- arbitrary SQL, shell execution and PHP eval are not exposed.
- WP-CLI is a named allowlist, not a raw command endpoint.
- all writes are audited.

## Major capabilities

- site diagnostics
- audit log
- theme files: list/search/read/diff/edit/write/delete/batch
- draft theme: create/status/preview
- immutable theme release publishing and rollback history
- pages, posts and public custom post types
- post meta
- media upload/import/update
- Rank Math / Yoast / generic SEO
- cache and rewrite controls
- approved WP-CLI operations
- calculator provider contract, validation and deterministic tests

## Release flow

```text
active theme
   ↓
draft clone
   ↓
edit / validate / preview
   ↓
immutable release clone
   ↓
activate release
   ↓
release record
   ↓
rollback to previous theme if required
```

The mutable draft directory is never used as the final production release after 0.3.0.
