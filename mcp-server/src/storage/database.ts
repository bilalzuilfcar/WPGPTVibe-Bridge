import mysql, { type Pool, type RowDataPacket } from 'mysql2/promise';
import { config } from '../config.js';

let pool: Pool | null = null;
let initialized = false;

export function databaseEnabled(): boolean {
  return Boolean(config.databaseUrl);
}

export function getPool(): Pool {
  if (!config.databaseUrl) {
    throw new Error('DATABASE_URL is not configured.');
  }
  if (!pool) {
    pool = mysql.createPool({
      uri: config.databaseUrl,
      connectionLimit: 8,
      enableKeepAlive: true,
      keepAliveInitialDelay: 0,
      timezone: 'Z',
      charset: 'utf8mb4',
    });
  }
  return pool;
}

export async function ensureSchema(): Promise<void> {
  if (!databaseEnabled() || initialized) return;
  const db = getPool();

  await db.query(`
    CREATE TABLE IF NOT EXISTS wpgptvibe_sites (
      site_id VARCHAR(36) PRIMARY KEY,
      display_name VARCHAR(191) NOT NULL,
      site_url TEXT NOT NULL,
      api_endpoint TEXT NOT NULL,
      encrypted_api_token TEXT NOT NULL,
      permissions_json LONGTEXT NOT NULL,
      plugin_version VARCHAR(64) NULL,
      last_seen DATETIME(3) NULL,
      created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
      updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  `);

  await db.query(`
    CREATE TABLE IF NOT EXISTS wpgptvibe_activity (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      request_id VARCHAR(64) NOT NULL,
      site_id VARCHAR(36) NULL,
      operation VARCHAR(191) NOT NULL,
      target TEXT NULL,
      status VARCHAR(32) NOT NULL,
      duration_ms INT UNSIGNED NULL,
      error_message TEXT NULL,
      created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
      INDEX idx_activity_created_at (created_at),
      INDEX idx_activity_site_id (site_id)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  `);

  initialized = true;
}

export async function dbRows<T extends RowDataPacket[]>(sql: string, values: any[] = []): Promise<T> {
  await ensureSchema();
  const [rows] = await getPool().execute<T>(sql, values);
  return rows;
}

export async function dbExecute(sql: string, values: unknown[] = []): Promise<void> {
  await ensureSchema();
  await getPool().execute(sql, values);
}

export async function closeDatabase(): Promise<void> {
  if (pool) {
    await pool.end();
    pool = null;
    initialized = false;
  }
}
