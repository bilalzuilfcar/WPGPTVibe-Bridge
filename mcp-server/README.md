# WPGPTVibe Application

WPGPTVibe is a single deployable Node.js application that serves the MCP endpoint and the web control plane.

## Runtime endpoints

- `GET /health` — application and storage health
- `GET /admin/login` — administration login
- `/admin` — site dashboard, diagnostics, release history and activity
- `POST /mcp` — authenticated MCP endpoint

## Production storage

Set `DATABASE_URL` to a MySQL connection string. On startup WPGPTVibe creates the required tables automatically.

If `DATABASE_URL` is omitted, WPGPTVibe uses encrypted local file storage for compatibility and development.

To migrate an existing `data/sites.json` registry into MySQL:

```bash
DATABASE_URL="mysql://..." \
MCP_API_KEY="..." \
WPGPTVIBE_MASTER_KEY="..." \
WPGPTVIBE_SESSION_SECRET="..." \
npm run migrate:file-sites
```

## Admin login

Generate a scrypt password hash:

```bash
export WPGPTVIBE_ADMIN_PASSWORD='a-long-password'
export MCP_API_KEY='a-32-byte-or-longer-secret'
export WPGPTVIBE_MASTER_KEY='64-hex-characters'
export WPGPTVIBE_SESSION_SECRET='another-32-byte-or-longer-secret'
npm run admin:hash-password
```

Store the generated hash as `WPGPTVIBE_ADMIN_PASSWORD_HASH`. The raw password is never stored by the application.

## WordPress registration

1. Install **WPGPTVibe Bridge** on WordPress.
2. Open **Tools → WPGPTVibe**.
3. Generate the one-time Bridge API token.
4. Copy the Site ID.
5. In WPGPTVibe open **Admin → Sites → Add site**.
6. Enter the display name, WordPress URL, Site ID and Bridge token.
7. Open the site console and run **Test connection**.

The token is encrypted with AES-256-GCM before persistence and is not shown again.

## MCP

Configure the MCP client with:

- URL: `https://your-wpgptvibe-host.example/mcp`
- Authorization: `Bearer <MCP_API_KEY>`

The MCP layer exposes site, theme, content, media, SEO, cache, approved WP-CLI, calculator and browser-QA operations. High-risk operations keep explicit confirmation literals and WordPress-side permission gates.

## Browser QA

Set:

```text
WPGPTVIBE_BROWSER_TESTING=true
```

and install Playwright Chromium on the host. Browser tools are restricted to registered WordPress site origins.

## Request protection

Production defaults:

- MCP body limit: 4 MB
- MCP rate limit: 240 requests/minute per client address
- admin login limit: 10 attempts per 15 minutes per client address

All are configurable through the environment.

## Build and run

```bash
npm install
npm test
npm run check
npm run build
npm start
```

See `../deploy/PRODUCTION.md` and `../docker-compose.yml` for production deployment.
