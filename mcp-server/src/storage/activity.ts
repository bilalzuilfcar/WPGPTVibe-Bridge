import { randomUUID } from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';
import { config } from '../config.js';
import { databaseEnabled, dbExecute, dbRows } from './database.js';

export type ActivityRecord = {
  id?: number;
  requestId: string;
  siteId?: string;
  operation: string;
  target?: string;
  status: 'success' | 'error';
  durationMs?: number;
  errorMessage?: string;
  createdAt: string;
};

const filePath = path.join(config.dataDir, 'activity.jsonl');

export async function recordActivity(input: Omit<ActivityRecord, 'requestId' | 'createdAt'> & { requestId?: string }): Promise<string> {
  const requestId = input.requestId ?? randomUUID();
  const createdAt = new Date().toISOString();

  if (databaseEnabled()) {
    await dbExecute(
      `INSERT INTO wpgptvibe_activity
       (request_id, site_id, operation, target, status, duration_ms, error_message, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        requestId,
        input.siteId ?? null,
        input.operation,
        input.target ?? null,
        input.status,
        input.durationMs ?? null,
        input.errorMessage ?? null,
        createdAt.slice(0, 23).replace('T', ' '),
      ],
    );
    return requestId;
  }

  await fs.mkdir(config.dataDir, { recursive: true, mode: 0o700 });
  const line = JSON.stringify({ ...input, requestId, createdAt }) + '\n';
  await fs.appendFile(filePath, line, { encoding: 'utf8', mode: 0o600 });
  return requestId;
}

export async function listActivity(limit = 100, siteId?: string): Promise<ActivityRecord[]> {
  const safeLimit = Math.max(1, Math.min(500, limit));
  if (databaseEnabled()) {
    const rows = siteId
      ? await dbRows<any[]>(
          `SELECT id, request_id, site_id, operation, target, status, duration_ms, error_message, created_at
           FROM wpgptvibe_activity WHERE site_id = ? ORDER BY id DESC LIMIT ${safeLimit}`,
          [siteId],
        )
      : await dbRows<any[]>(
          `SELECT id, request_id, site_id, operation, target, status, duration_ms, error_message, created_at
           FROM wpgptvibe_activity ORDER BY id DESC LIMIT ${safeLimit}`,
        );

    return rows.map((row: any) => ({
      id: Number(row.id),
      requestId: String(row.request_id),
      siteId: row.site_id ? String(row.site_id) : undefined,
      operation: String(row.operation),
      target: row.target ? String(row.target) : undefined,
      status: row.status === 'error' ? 'error' : 'success',
      durationMs: row.duration_ms === null ? undefined : Number(row.duration_ms),
      errorMessage: row.error_message ? String(row.error_message) : undefined,
      createdAt: new Date(row.created_at).toISOString(),
    }));
  }

  try {
    const raw = await fs.readFile(filePath, 'utf8');
    return raw.trim().split('\n').filter(Boolean).slice(-safeLimit).reverse().map((line) => JSON.parse(line) as ActivityRecord);
  } catch (error: unknown) {
    if ((error as NodeJS.ErrnoException).code === 'ENOENT') return [];
    throw error;
  }
}
