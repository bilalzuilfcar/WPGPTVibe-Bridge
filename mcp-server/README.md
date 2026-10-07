# WPGPTVibe MCP

Version 0.2.0 remote MCP server for WPGPTVibe Bridge.

The server uses the MCP TypeScript v2 packages and exposes one authenticated remote endpoint at `/mcp`.

## Requirements

- Node.js 20+
- HTTPS reverse proxy in production
- Persistent writable directory for `data/sites.json`
- Optional: Playwright Chromium for browser QA tools

## Environment

Copy `.env.example` values into the host environment manager.

Generate two different strong values locally:

```bash
openssl rand -hex 32
```

- `MCP_API_KEY`: bearer secret protecting `/mcp`
- `WPGPTVIBE_MASTER_KEY`: exactly 64 hex characters (32 bytes), used to encrypt WordPress Bridge tokens at rest

Optional browser QA:

```text
WPGPTVIBE_BROWSER_TESTING=true
WPGPTVIBE_BROWSER_TIMEOUT_MS=15000
```

When browser testing is enabled, install Chromium on the server after `npm install`:

```bash
npx playwright install chromium
```

Do not commit real secrets.

## Register a WordPress site

After installing WPGPTVibe Bridge and generating its token:

```bash
export WPGPTVIBE_SITE_ID="<site UUID from Tools > WPGPTVibe>"
export WPGPTVIBE_SITE_NAME="EzyMFG"
export WPGPTVIBE_SITE_URL="https://mfg.martzine.com/"
export WPGPTVIBE_SITE_TOKEN="<Bridge token shown once>"
npm run site:add
```

The token is encrypted with AES-256-GCM before it is written to the site registry. `list_sites` never returns encrypted or decrypted site tokens.

## Run

```bash
npm install
npm run check
npm run build
npm start
```

Health:

```text
GET /health
```

MCP:

```text
POST /mcp
Authorization: Bearer <MCP_API_KEY>
```

## Tool surface

### Sites and audit

- `list_sites`
- `site_info`
- `audit_log`

### Theme/files

- `list_files`
- `search_files`
- `read_file`
- `get_file_diff`
- `create_draft_theme`
- `get_draft_status`
- `get_preview_url`
- `edit_file`
- `write_file`
- `delete_file`
- `batch_edit_files`
- `publish_draft_theme`
- `rollback_theme`

### Content

- `list_content_types`
- `list_content`
- `get_content`
- `create_content`
- `update_content`
- `delete_content`
- `batch_update_content`

### Metadata

- `get_meta`
- `update_meta`
- `batch_update_meta`

### Media

- `list_media`
- `get_media`
- `upload_media`
- `import_media`
- `update_media`

### SEO

- `get_seo`
- `update_seo`
- `batch_update_seo`

Rank Math is preferred, Yoast is supported, and a generic fallback is available.

### Maintenance

- `clear_cache`
- `flush_rewrites`
- `wpcli_status`
- `run_wpcli`

`run_wpcli` accepts only named allowlisted operations. It never accepts a raw shell command.

### Calculator QA

- `list_calculators`
- `get_calculator`
- `update_calculator`
- `validate_calculator`
- `validate_all_calculators`
- `test_calculator`
- `batch_update_calculators`

These tools use a generic Bridge provider/filter contract. EzyMFG can register its existing calculator definitions without coupling the MCP server to one theme.

### Optional browser QA

Registered only when `WPGPTVIBE_BROWSER_TESTING=true`:

- `test_page`
- `scan_console_errors`
- `test_all_routes`
- `test_calculator_ui`

Browser tools can navigate only paths on a site already registered in WPGPTVibe. Arbitrary cross-origin URLs are rejected.

## Risk controls

WPGPTVibe removes third-party quota limits, not safety controls.

High-risk operations require explicit confirmation literals as part of the MCP schema and separate Bridge capabilities. Examples include theme publish/rollback, file deletion, content deletion and rewrite flushes.
