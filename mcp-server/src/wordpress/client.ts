import { getSite, updateSiteTelemetry } from '../storage/sites.js';

type JsonObject = Record<string, unknown>;

type BridgeResponse<T> = {
  ok: boolean;
  data: T;
};

const ALLOWED_PATHS = new Set([
  'site',
  'theme/files',
  'theme/file',
  'theme/draft/create',
  'theme/draft',
  'theme/draft/preview',
  'theme/file/edit',
  'theme/file/write',
  'theme/draft/publish',
  'theme/rollback',
  'audit',
]);

export async function callBridge<T>(
  siteId: string,
  method: 'GET' | 'POST',
  path: string,
  params?: JsonObject,
): Promise<T> {
  if (!ALLOWED_PATHS.has(path)) {
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
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value));
      }
    }
  } else if (method === 'POST') {
    headers['content-type'] = 'application/json';
    init.body = JSON.stringify(params ?? {});
  }

  const response = await fetch(url, init);
  const text = await response.text();

  let payload: unknown;
  try {
    payload = text ? JSON.parse(text) : null;
  } catch {
    throw new Error(`Bridge returned non-JSON HTTP ${response.status}.`);
  }

  if (!response.ok) {
    const message =
      typeof payload === 'object' && payload && 'message' in payload
        ? String((payload as { message?: unknown }).message)
        : `Bridge HTTP ${response.status}`;
    throw new Error(message);
  }

  if (!payload || typeof payload !== 'object' || !('ok' in payload) || !('data' in payload)) {
    throw new Error('Bridge returned an unexpected response envelope.');
  }

  const bridge = payload as BridgeResponse<T>;
  if (!bridge.ok) {
    throw new Error('Bridge reported an unsuccessful operation.');
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
