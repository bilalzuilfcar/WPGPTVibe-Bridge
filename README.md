# WPGPTVibe

Internal WordPress MCP system for secure, draft-first management of multiple WordPress sites from ChatGPT-compatible MCP clients.

## Components

- `wordpress-plugin/` — WPGPTVibe Bridge WordPress plugin
- `mcp-server/` — remote MCP server (Node.js/TypeScript)

## MVP scope

The first end-to-end milestone is intentionally narrow:

1. Install Bridge on WordPress.
2. Generate and rotate an API token.
3. Call authenticated `site_info`.
4. List and read theme files safely.
5. Create a draft theme.
6. Edit/write a harmless draft-theme file.
7. Preview the draft.
8. Review the audit log.
9. Publish only after explicit approval and validation.

## Security defaults

- HTTPS required outside local development.
- API tokens are never stored in plaintext by the WordPress plugin.
- Capabilities are allowlisted.
- Theme paths are constrained to approved theme roots.
- File extensions are allowlisted.
- Writes target draft themes by default.
- Audit logging records writes without recording secrets.
- No arbitrary SQL, shell, PHP eval, or unrestricted WP-CLI endpoint.

## Versions

- WPGPTVibe Bridge: 0.1.0
- WPGPTVibe MCP: 0.1.0

See each component README for setup instructions.
