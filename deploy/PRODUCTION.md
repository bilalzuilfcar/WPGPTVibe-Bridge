# WPGPTVibe Production Deployment

WPGPTVibe is deployed as one Node.js service. The same process serves:

- `/mcp` — authenticated MCP endpoint
- `/admin` — signed-session administration UI
- `/health` — deployment/database health endpoint

## Required secrets

Generate these outside Git and store them in your hosting environment:

- `MCP_API_KEY`: at least 32 random bytes
- `WPGPTVIBE_MASTER_KEY`: exactly 64 hexadecimal characters
- `WPGPTVIBE_SESSION_SECRET`: at least 32 random bytes
- `WPGPTVIBE_ADMIN_PASSWORD_HASH`: generated with `npm run admin:hash-password`
- `DATABASE_URL`: production MySQL connection string

Never commit any of these values.

## Generate the admin password hash

```bash
cd mcp-server
export MCP_API_KEY="$(openssl rand -hex 32)"
export WPGPTVIBE_MASTER_KEY="$(openssl rand -hex 32)"
export WPGPTVIBE_SESSION_SECRET="$(openssl rand -hex 32)"
export WPGPTVIBE_ADMIN_PASSWORD='use-a-long-password-here'
npm install
npm run admin:hash-password
```

Copy only the resulting `scrypt$...` value into `WPGPTVIBE_ADMIN_PASSWORD_HASH` on the server.

## Hostinger / Node hosting

Deploy the repository and use `mcp-server` as the application directory.

Build command:

```bash
npm install && npm run check && npm run build
```

Start command:

```bash
npm start
```

Set all required environment variables in the hosting dashboard. Use a persistent MySQL database. The application creates its own `wpgptvibe_sites` and `wpgptvibe_activity` tables.

After deployment verify:

1. `GET /health` returns `ok: true`.
2. `/admin/login` loads over HTTPS.
3. Add a WordPress site using its Bridge Site ID and one-time API token.
4. Run **Test connection** from the site console.
5. Configure the remote MCP client to use `https://<host>/mcp` with the `MCP_API_KEY` bearer token.
6. Run `site_info` and `list_sites`.

## Docker

From repository root:

```bash
docker compose up -d --build
```

The compose file starts MySQL and WPGPTVibe with a health check. Put secrets in the shell environment or an uncommitted `.env` file.

## Production requirements

- HTTPS termination in front of the Node service
- MySQL backups
- strong, unique Bridge tokens per site
- WordPress Bridge dangerous capabilities disabled unless required
- MCP API key stored only in the MCP client and server environment
- no public exposure of the MySQL service
- rotate tokens immediately if a secret is copied into an unsafe channel
