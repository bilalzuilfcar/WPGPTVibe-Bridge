import { isBridgePathAllowed } from '../security/policy.js';
import { getSite, updateSiteTelemetry } from '../storage/sites.js';
import { recordActivity } from '../storage/activity.js';

type JsonObject = Record<string, unknown>;
export type BridgeMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

type BridgeResponse<T> = {
  ok: boolean;
  data: T;
};

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
  if (!isBridgePathAllowed(path)) {
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

  const startedAt = Date.now();
  let response: Response;
  try {
    response = await fetch(url, init);
  } catch (error) {
    await recordActivity({ siteId, operation: `bridge:${method}`, target: path, status: 'error', durationMs: Date.now() - startedAt, errorMessage: error instanceof Error ? error.message : 'Network error' });
    throw error;
  }
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
    await recordActivity({ siteId, operation: `bridge:${method}`, target: path, status: 'error', durationMs: Date.now() - startedAt, errorMessage: message });
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

  await recordActivity({ siteId, operation: `bridge:${method}`, target: path, status: 'success', durationMs: Date.now() - startedAt });
  return bridge.data;
}
