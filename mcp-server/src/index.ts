import { createServer as createHttpServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { createMcpHandler } from '@modelcontextprotocol/server';
import { toNodeHandler } from '@modelcontextprotocol/node';
import { config } from './config.js';
import { closeDatabase, ensureSchema } from './storage/database.js';
import { checkRateLimit, requestIdentity } from './security/rate-limit.js';
import { createServer as createMcpServer } from './server.js';
import { handleWeb } from './web/router.js';

const handler = createMcpHandler(() => createMcpServer());
const nodeHandler = toNodeHandler(handler, {
  onerror(error) {
    console.error('[wpgptvibe] MCP transport error', error);
  },
});

function authorized(header: string | undefined): boolean {
  if (!header?.startsWith('Bearer ')) return false;
  const supplied = Buffer.from(header.slice(7));
  const expected = Buffer.from(config.mcpApiKey);
  return supplied.length === expected.length && timingSafeEqual(supplied, expected);
}

const server = createHttpServer(async (req, res) => {
  try {
    const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);

    if (await handleWeb(req, res, url)) return;

    if (url.pathname !== '/mcp') {
      res.writeHead(404, { 'content-type': 'application/json', 'cache-control': 'no-store' });
      res.end(JSON.stringify({ error: 'Not found' }));
      return;
    }

    const identity = requestIdentity(req.headers, req.socket.remoteAddress);
    const rate = checkRateLimit('mcp:' + identity, config.mcpRequestsPerMinute, 60_000);
    if (!rate.ok) {
      res.writeHead(429, {
        'content-type': 'application/json',
        'cache-control': 'no-store',
        'retry-after': String(rate.retryAfterSeconds),
      });
      res.end(JSON.stringify({ error: 'Rate limit exceeded' }));
      return;
    }

    const contentLength = Number(req.headers['content-length'] ?? 0);
    if (Number.isFinite(contentLength) && contentLength > config.mcpMaxBodyBytes) {
      res.writeHead(413, { 'content-type': 'application/json', 'cache-control': 'no-store' });
      res.end(JSON.stringify({ error: 'MCP request body too large' }));
      return;
    }

    if (!authorized(req.headers.authorization)) {
      res.writeHead(401, {
        'content-type': 'application/json',
        'www-authenticate': 'Bearer',
        'cache-control': 'no-store',
      });
      res.end(JSON.stringify({ error: 'Unauthorized' }));
      return;
    }

    await nodeHandler(req, res);
  } catch (error) {
    console.error('[wpgptvibe] request error', error);
    if (!res.headersSent) {
      res.writeHead(500, { 'content-type': 'application/json', 'cache-control': 'no-store' });
    }
    if (!res.writableEnded) {
      res.end(JSON.stringify({ error: 'Internal server error' }));
    }
  }
});

async function start(): Promise<void> {
  await ensureSchema();
  server.listen(config.port, '0.0.0.0', () => {
    console.error(`[wpgptvibe] listening on port ${config.port} (storage: ${config.databaseUrl ? 'mysql' : 'file'})`);
  });
}

async function shutdown(signal: string): Promise<void> {
  console.error(`[wpgptvibe] ${signal}, shutting down`);
  server.close();
  await handler.close();
  await closeDatabase();
}

process.on('SIGTERM', () => void shutdown('SIGTERM'));
process.on('SIGINT', () => void shutdown('SIGINT'));

void start().catch((error) => {
  console.error('[wpgptvibe] startup failed', error);
  process.exitCode = 1;
});
