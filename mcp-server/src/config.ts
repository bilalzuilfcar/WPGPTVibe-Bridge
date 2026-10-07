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

const nodeEnv = process.env.NODE_ENV?.trim() || 'development';
const sessionSecret = process.env.WPGPTVIBE_SESSION_SECRET?.trim() || process.env.MCP_API_KEY?.trim() || '';

if (sessionSecret.length < 32) {
  throw new Error('WPGPTVIBE_SESSION_SECRET (or MCP_API_KEY fallback) must be at least 32 characters.');
}

export const config = {
  nodeEnv,
  port: Number(process.env.PORT ?? 8787),
  mcpApiKey: required('MCP_API_KEY'),
  masterKey: masterKey(),
  dataDir: path.resolve(process.env.WPGPTVIBE_DATA_DIR ?? './data'),
  databaseUrl: process.env.DATABASE_URL?.trim() || '',
  adminUsername: process.env.WPGPTVIBE_ADMIN_USERNAME?.trim() || 'admin',
  adminPasswordHash: process.env.WPGPTVIBE_ADMIN_PASSWORD_HASH?.trim() || '',
  sessionSecret,
  cookieSecure: nodeEnv === 'production' || /^(1|true|yes)$/i.test(process.env.WPGPTVIBE_COOKIE_SECURE ?? ''),
  browserTesting: /^(1|true|yes)$/i.test(process.env.WPGPTVIBE_BROWSER_TESTING ?? ''),
  browserTimeoutMs: Math.max(3000, Math.min(60000, Number(process.env.WPGPTVIBE_BROWSER_TIMEOUT_MS ?? 15000))),
  mcpMaxBodyBytes: Math.max(65536, Math.min(16 * 1024 * 1024, Number(process.env.WPGPTVIBE_MCP_MAX_BODY_BYTES ?? 4 * 1024 * 1024))),
  mcpRequestsPerMinute: Math.max(10, Math.min(1000, Number(process.env.WPGPTVIBE_MCP_REQUESTS_PER_MINUTE ?? 240))),
  adminLoginAttemptsPer15Minutes: Math.max(3, Math.min(100, Number(process.env.WPGPTVIBE_ADMIN_LOGIN_ATTEMPTS ?? 10))),
};

if (!Number.isInteger(config.port) || config.port < 1 || config.port > 65535) {
  throw new Error('PORT must be a valid TCP port.');
}

if (!Number.isFinite(config.browserTimeoutMs)) {
  throw new Error('WPGPTVIBE_BROWSER_TIMEOUT_MS must be numeric.');
}

if (config.nodeEnv === 'production' && !config.databaseUrl) {
  console.warn('[wpgptvibe] DATABASE_URL is not configured. Falling back to encrypted local file storage.');
}

if (config.nodeEnv === 'production' && !config.adminPasswordHash) {
  console.warn('[wpgptvibe] Admin dashboard login is disabled until WPGPTVIBE_ADMIN_PASSWORD_HASH is configured.');
}

if (!Number.isFinite(config.mcpMaxBodyBytes) || !Number.isFinite(config.mcpRequestsPerMinute) || !Number.isFinite(config.adminLoginAttemptsPer15Minutes)) {
  throw new Error('WPGPTVibe numeric security limits must be valid numbers.');
}
