import { getSite, updateSiteTelemetry } from '../storage/sites.js';

type JsonObject = Record<string, unknown>;
export type BridgeMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

type BridgeResponse<T> = {
  ok: boolean;
  data: T;
};

const EXACT_PATHS = new Set([
  'site',
  'audit',
  'theme/files',
  'theme/file',
  'theme/search',
  'theme/file/diff',
  'theme/draft/create',
  'theme/draft',
  'theme/draft/preview',
  'theme/file/edit',
  'theme/file/write',
  'theme/file/delete',
  'theme/files/batch-edit',
  'theme/draft/publish',
  'theme/rollback',
  'content/types',
  'content',
  'content/batch-update',
  'meta/batch-update',
  'media',
  'media/upload',
  'media/import',
  'seo/batch-update',
  'cache/purge',
  'rewrite/flush',
  'wpcli/status',
  'wpcli/run',
  'calculators',
  'calculators/validate-all',
  'calculators/batch-update',
]);

const DYNAMIC_PATHS = [
  /^content\/\d+$/,
  /^content\/\d+\/meta$/,
  /^media\/\d+$/,
  /^seo\/\d+$/,
  /^calculators\/[a-z0-9-]+$/,
  /^calculators\/[a-z0-9-]+\/validate$/,
  /^calculators\/[a-z0-9-]+\/test$/,
];

function pathAllowed(path: string): boolean {
  return EXACT_PATHS.has(path) || DYNAMIC_PATHS.some((pattern) => pattern.test(path));
}

export class BridgeError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
  ) {
    super(message);
    this.name = 'BridgeError';
  }
}

export async function callBridge<T>(
  siteId: string,
  method: BridgeMethod,
  path: string,
  params?: JsonObject,
): Promise<T> {
  if (!pathAllowed(path)) {
    throw new Error(`Bridge path is not allowlisted: ${path}`);
  }

  const site = await getSite(siteId);
  const url = new URL(path, site.apiEndpoint);

  const headers: Record<string, string> = {
    accept: 'application/json',
    authorization: `Bearer ${site.apiToken}`,
    'x-wpgptvibe-site-id': site.siteId,
    'x-request-id': crypto.randomUUID(),
  };

  const init: RequestInit = {
    method,
    headers,
    signal: AbortSignal.timeout(30_000),
  };

  if (method === 'GET' && params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === '') continue;
      url.searchParams.set(key, Array.isArray(value) ? value.join(',') : String(value));
    }
  } else if (params) {
    headers['content-type'] = 'application/json';
    init.body = JSON.stringify(params);
  }

  const response = await fetch(url, init);
  const text = await response.text();

  let payload: unknown;
  try {
    payload = text ? JSON.parse(text) : null;
  } catch {
    throw new BridgeError(`Bridge returned non-JSON HTTP ${response.status}.`, response.status);
  }

  if (!response.ok) {
    const object = typeof payload === 'object' && payload ? payload as Record<string, unknown> : {};
    const message = typeof object.message === 'string' ? object.message : `Bridge HTTP ${response.status}`;
    const code = typeof object.code === 'string' ? object.code : undefined;
    throw new BridgeError(message, response.status, code);
  }

  if (!payload || typeof payload !== 'object' || !('ok' in payload) || !('data' in payload)) {
    throw new BridgeError('Bridge returned an unexpected response envelope.', response.status);
  }

  const bridge = payload as BridgeResponse<T>;
  if (!bridge.ok) {
    throw new BridgeError('Bridge reported an unsuccessful operation.', response.status);
  }

  if (path === 'site' && bridge.data && typeof bridge.data === 'object') {
    const data = bridge.data as Record<string, unknown>;
    await updateSiteTelemetry(siteId, {
      lastSeen: new Date().toISOString(),
      pluginVersion: typeof data.plugin_version === 'string' ? data.plugin_version : undefined,
      permissions: Array.isArray(data.capabilities) ? data.capabilities.filter((x): x is string => typeof x === 'string') : [],
    });
  }

  return bridge.data;
}
