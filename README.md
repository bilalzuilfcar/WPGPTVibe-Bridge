# WPGPTVibe

Internal WordPress MCP system for secure management of multiple WordPress sites from ChatGPT-compatible MCP clients.

WPGPTVibe is built to replace our dependency on WPVibe for internal site work. It removes third-party action quotas and gives us our own permissions, batching, multi-site registry, calculator QA and deployment workflow while retaining safety controls for production sites.

## Components

- `wordpress-plugin/` — WPGPTVibe Bridge WordPress plugin
- `mcp-server/` — remote MCP server (Node.js/TypeScript)

## Versions

- WPGPTVibe Bridge: **0.2.0**
- WPGPTVibe MCP: **0.2.0**

## Current capability surface

- multi-site registration and encrypted site-token storage
- site/environment diagnostics
- audit logs
- theme file list/search/read/diff
- draft-theme creation, preview, edit, write, delete and batch edit
- publish and rollback with explicit confirmation gates
- WordPress pages/posts/public custom post-type CRUD
- batch content updates
- protected-safe post metadata and batch meta updates
- media list/get/upload/HTTPS import/update
- Rank Math and Yoast SEO adapters plus generic fallback
- batch SEO updates
- object/plugin/SEO cache purge utilities
- rewrite flush
- allowlisted WP-CLI wrapper
- calculator provider contract
- calculator get/update/validate/test/batch tools
- validate-all-calculators in one action
- optional Playwright page/calculator/route/console QA
- GitHub CI and installable WordPress plugin packaging

## Security defaults

- HTTPS required outside local development
- WordPress API token stored as a one-way hash
- MCP site tokens encrypted at rest with AES-256-GCM
- stable site UUID verification
- explicit capability allowlist
- request size and basic rate limits
- theme path traversal protection
- file extension allowlist
- protected metadata allowlist
- draft-first theme writes
- high-risk confirmation gates
- audit logging without secrets
- no arbitrary SQL endpoint
- no PHP eval endpoint
- no unrestricted shell endpoint
- allowlisted WP-CLI only

WPGPTVibe has no external daily action quota. Limits that remain are technical/safety limits under our control (for example request size, batch size and media size) and can be adjusted in our own code when needed.

## Documentation

- `wordpress-plugin/README.md` — Bridge install, REST surface and WordPress-side controls
- `mcp-server/README.md` — server deployment, site registration and MCP tools
- `docs/superpowers/specs/2026-10-07-wpgptvibe-full-connector-design.md` — approved design
- `docs/superpowers/plans/2026-10-07-wpgptvibe-full-connector.md` — implementation plan
