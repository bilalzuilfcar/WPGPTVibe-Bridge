# WPGPTVibe

WPGPTVibe is a self-hosted WordPress control plane and MCP application. It is designed to replace quota-bound third-party WordPress connectors for internal site operations while keeping destructive actions permission-gated and auditable.

## Architecture

```text
ChatGPT / MCP client
        |
        v
WPGPTVibe application
  /mcp      MCP endpoint
  /admin    management console
  /health   deployment health
        |
        v
Encrypted multi-site registry + activity log
(MySQL in production, encrypted local file fallback in development)
        |
        v
WPGPTVibe Bridge plugin on each WordPress site
        |
        v
Themes / content / media / SEO / cache / calculators / approved WP-CLI
```

## Current release: 0.3.0

### Application

- remote MCP endpoint
- responsive admin dashboard
- signed admin sessions and CSRF protection
- MySQL-backed multi-site registry
- AES-256-GCM encrypted WordPress Bridge tokens
- site add/update/remove and connection testing
- live WordPress diagnostics
- central activity logging
- theme release and rollback history
- optional Playwright browser QA
- Docker and Docker Compose deployment
- production health endpoint

### WordPress Bridge

- capability-gated REST API
- theme listing/search/read/diff
- draft theme creation and preview
- exact file edits, writes, deletes and batches
- immutable release themes on publish
- rollback records
- pages/posts/custom post types
- meta management
- media upload/import/update
- Rank Math / Yoast / generic SEO fields
- cache and rewrite controls
- structured allowlisted WP-CLI operations
- calculator provider contract and validation
- request guards, audit logs and confirmation gates

## Repository

- `wordpress-plugin/` — installable WordPress Bridge
- `mcp-server/` — deployable WPGPTVibe Node application
- `deploy/` — production deployment notes
- `docker-compose.yml` — app + MySQL deployment

GitHub Actions validates PHP syntax, runs MCP tests/type checks/builds, boots the application for smoke tests and produces both deployment artifacts.

## Production rule

Do not commit real API tokens, MCP keys, database credentials, admin password hashes or master keys. Production secrets belong only in the hosting environment.
