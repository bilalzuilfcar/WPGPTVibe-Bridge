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

## Current release: 0.3.1 (review-gate branch; not yet released)

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

## Manual review workflow (0.3.1 candidate)

- Bridge API creates posts and pages only as `draft` or `pending`; requests for `publish`, `future`, or `private` are rejected.
- Bridge edits and deletions of already-published content are blocked. Draft content and its SEO/meta can be edited through MCP; use the WordPress editor for final review and publication.
- Direct API edits of existing published SEO/meta and bulk routes are blocked. Calculator update endpoints are deliberately disabled until a staging model is implemented.
- A WordPress administrator reviews a theme draft via its preview, then selects **Tools > WPGPTVibe > Approve Current Theme Draft for Publication**. The approval is tied to the draft-file SHA-256 fingerprint, expires after 15 minutes, and can only be used once. The MCP publish tool still requires its existing confirmation and publish capability.
- Theme file edits invalidate the previous approval because the fingerprint changes.
- Important limitation: this release is not a complete revision/proposal workflow for existing published pages. It blocks their direct updates rather than making reviewable copies. Never claim otherwise.
- This control governs WPGPTVibe Bridge API operations; native WordPress administrators can still publish manually. Keep the plugin read-only during initial deployment and test all review paths before enabling write capabilities.

### Approval test checklist

1. Request a new post with status `publish` using the Bridge: it must fail and create no post.
2. Create a draft and edit it: it must remain unpublished; publish manually in WordPress.
3. Attempt to update a published page, its SEO, its meta, and batch SEO/meta: every request must fail.
4. Attempt to update calculators: it must fail pending a staging implementation.
5. Attempt to publish a draft theme without admin approval: it must fail.
6. Approve the draft inside WordPress, edit any draft theme file, then attempt publish: it must fail (fingerprint changed).
7. Approve the unchanged draft again; the confirmed publish request should succeed once, subject to theme validation. Rollback must remain available with its separate permission and confirmation.
