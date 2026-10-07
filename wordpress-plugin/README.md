# WPGPTVibe Bridge

Version 0.1.0 MVP.

## Install

1. Zip the contents of this `wordpress-plugin` directory so `wpgptvibe-bridge.php` is at the ZIP root.
2. WordPress Admin → Plugins → Add Plugin → Upload Plugin.
3. Activate **WPGPTVibe Bridge**.
4. Go to Tools → WPGPTVibe.
5. Generate an API key and copy it immediately.
6. Keep write/publish permissions disabled until needed.

## Authentication

All REST calls use:

```http
Authorization: Bearer <site-api-token>
X-WPGPTVIBE-Site-ID: <site-uuid>
```

The site ID header is optional for direct testing but the MCP server sends it.

## MVP endpoints

- `GET /wp-json/wpgptvibe/v1/site`
- `GET /wp-json/wpgptvibe/v1/theme/files`
- `GET /wp-json/wpgptvibe/v1/theme/file?path=...`
- `POST /wp-json/wpgptvibe/v1/theme/draft/create`
- `GET /wp-json/wpgptvibe/v1/theme/draft`
- `GET /wp-json/wpgptvibe/v1/theme/draft/preview`
- `POST /wp-json/wpgptvibe/v1/theme/file/edit`
- `POST /wp-json/wpgptvibe/v1/theme/file/write`
- `POST /wp-json/wpgptvibe/v1/theme/draft/publish`
- `POST /wp-json/wpgptvibe/v1/theme/rollback`
- `GET /wp-json/wpgptvibe/v1/audit`

## Safety

Theme writes are draft-only in this MVP. Publishing and rollback each require separate disabled-by-default permissions. File paths reject traversal and writes allow only php/css/js/json/html/txt/svg.
