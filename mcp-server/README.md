# WPGPTVibe MCP

Remote MCP server for WPGPTVibe Bridge.

This server uses the current MCP TypeScript v2 packages and exposes one remote endpoint at `/mcp`.

## Requirements

- Node.js 20+
- HTTPS reverse proxy in production
- A persistent writable data directory for `data/sites.json`

## Environment

Copy `.env.example` values into your host's environment manager.

Generate secrets locally:

```bash
openssl rand -hex 32
```

Use one random value for `MCP_API_KEY` and a different 64-hex-character value for `WPGPTVIBE_MASTER_KEY`.

Do not commit real secrets.

## Register a WordPress site

After installing the Bridge and generating a token, export these environment variables temporarily:

```bash
export WPGPTVIBE_SITE_ID="<site UUID from Tools > WPGPTVibe>"
export WPGPTVIBE_SITE_NAME="EzyMFG"
export WPGPTVIBE_SITE_URL="https://mfg.martzine.com/"
export WPGPTVIBE_SITE_TOKEN="<Bridge token shown once>"
npm run site:add
```

The WordPress token is encrypted with AES-256-GCM before it is written to the site registry.

## Run

```bash
npm install
npm run check
npm run build
npm start
```

Health check:

```text
GET /health
```

Remote MCP endpoint:

```text
POST /mcp
Authorization: Bearer <MCP_API_KEY>
```

## Initial tools

- `list_sites`
- `site_info`
- `list_files`
- `read_file`
- `create_draft_theme`
- `get_draft_status`
- `get_preview_url`
- `edit_file`
- `write_file`
- `publish_draft_theme`
- `rollback_theme`
- `audit_log`

Publish and rollback also require an explicit confirmation literal in the MCP call and a separately enabled WordPress Bridge permission.
