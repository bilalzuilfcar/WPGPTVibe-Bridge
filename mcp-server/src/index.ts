import { createServer as createHttpServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { createMcpHandler } from '@modelcontextprotocol/server';
import { toNodeHandler } from '@modelcontextprotocol/node';
import { config } from './config.js';
import { createServer as createMcpServer } from './server.js';

const handler = createMcpHandler(() => createMcpServer());
const nodeHandler = toNodeHandler(handler, {
  onerror(error) {
    console.error('[wpgptvibe-mcp] transport error', error);
  },
});

function authorized(header: string | undefined): boolean {
  if (!header?.startsWith('Bearer ')) return false;
  const supplied = Buffer.from(header.slice(7));
  const expected = Buffer.from(config.mcpApiKey);
  return supplied.length === expected.length && timingSafeEqual(supplied, expected);
}

const server = createHttpServer(async (req, res) => {
  const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);

  if (url.pathname === '/health') {
    res.writeHead(200, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ ok: true, service: 'wpgptvibe-mcp', version: '0.1.0' }));
    return;
  }

  if (url.pathname !== '/mcp') {
    res.writeHead(404, { 'content-type': 'application/json' });
    res.end(JSON.stringify({ error: 'Not found' }));
    return;
  }

  if (!authorized(req.headers.authorization)) {
    res.writeHead(401, {
      'content-type': 'application/json',
      'www-authenticate': 'Bearer',
    });
    res.end(JSON.stringify({ error: 'Unauthorized' }));
    return;
  }

  await nodeHandler(req, res);
});

server.listen(config.port, '0.0.0.0', () => {
  console.error(`[wpgptvibe-mcp] listening on port ${config.port}`);
});

async function shutdown(signal: string) {
  console.error(`[wpgptvibe-mcp] ${signal}, shutting down`);
  server.close();
  await handler.close();
}

process.on('SIGTERM', () => void shutdown('SIGTERM'));
process.on('SIGINT', () => void shutdown('SIGINT'));
