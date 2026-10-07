import path from 'node:path';

function required(name: string): string {
  const value = process.env[name]?.trim();
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

function masterKey(): Buffer {
  const raw = required('WPGPTVIBE_MASTER_KEY');
  if (!/^[a-f0-9]{64}$/i.test(raw)) {
    throw new Error('WPGPTVIBE_MASTER_KEY must be exactly 64 hexadecimal characters (32 bytes).');
  }
  return Buffer.from(raw, 'hex');
}

export const config = {
  port: Number(process.env.PORT ?? 8787),
  mcpApiKey: required('MCP_API_KEY'),
  masterKey: masterKey(),
  dataDir: path.resolve(process.env.WPGPTVIBE_DATA_DIR ?? './data'),
  browserTesting: /^(1|true|yes)$/i.test(process.env.WPGPTVIBE_BROWSER_TESTING ?? ''),
  browserTimeoutMs: Math.max(3000, Math.min(60000, Number(process.env.WPGPTVIBE_BROWSER_TIMEOUT_MS ?? 15000))),
};

if (!Number.isInteger(config.port) || config.port < 1 || config.port > 65535) {
  throw new Error('PORT must be a valid TCP port.');
}

if (!Number.isFinite(config.browserTimeoutMs)) {
  throw new Error('WPGPTVIBE_BROWSER_TIMEOUT_MS must be numeric.');
}
