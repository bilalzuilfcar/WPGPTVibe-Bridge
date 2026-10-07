import fs from 'node:fs/promises';
import path from 'node:path';
import { config } from '../config.js';
import { decryptSecret, encryptSecret } from './crypto.js';
import { databaseEnabled, dbExecute, dbRows } from './database.js';

export type SiteRecord = {
  siteId: string;
  displayName: string;
  siteUrl: string;
  apiEndpoint: string;
  encryptedApiToken: string;
  permissions: string[];
  pluginVersion?: string;
  lastSeen?: string;
};

type SiteFile = {
  version: 1;
  sites: SiteRecord[];
};

const sitesPath = path.join(config.dataDir, 'sites.json');

function normalizeSite(input: {
  siteId: string;
  displayName: string;
  siteUrl: string;
  apiToken: string;
  permissions?: string[];
  pluginVersion?: string;
}): SiteRecord {
  const siteUrl = new URL(input.siteUrl);
  if (siteUrl.protocol !== 'https:' && siteUrl.hostname !== 'localhost' && siteUrl.hostname !== '127.0.0.1') {
    throw new Error('WordPress site URL must use HTTPS.');
  }

  return {
    siteId: input.siteId,
    displayName: input.displayName.trim(),
    siteUrl: siteUrl.toString(),
    apiEndpoint: new URL('/wp-json/wpgptvibe/v1/', siteUrl).toString(),
    encryptedApiToken: encryptSecret(input.apiToken),
    permissions: input.permissions ?? [],
    pluginVersion: input.pluginVersion,
  };
}

async function readFile(): Promise<SiteFile> {
  try {
    const parsed = JSON.parse(await fs.readFile(sitesPath, 'utf8')) as SiteFile;
    if (parsed.version !== 1 || !Array.isArray(parsed.sites)) {
      throw new Error('Unsupported sites registry format.');
    }
    return parsed;
  } catch (error: unknown) {
    if ((error as NodeJS.ErrnoException).code === 'ENOENT') return { version: 1, sites: [] };
    throw error;
  }
}

async function writeFile(data: SiteFile): Promise<void> {
  await fs.mkdir(config.dataDir, { recursive: true, mode: 0o700 });
  const temp = sitesPath + '.tmp';
  await fs.writeFile(temp, JSON.stringify(data, null, 2), { encoding: 'utf8', mode: 0o600 });
  await fs.rename(temp, sitesPath);
}

function fromDb(row: any): SiteRecord {
  let permissions: string[] = [];
  try {
    const parsed = typeof row.permissions_json === 'string' ? JSON.parse(row.permissions_json) : row.permissions_json;
    if (Array.isArray(parsed)) permissions = parsed.filter((v): v is string => typeof v === 'string');
  } catch {
    permissions = [];
  }

  return {
    siteId: String(row.site_id),
    displayName: String(row.display_name),
    siteUrl: String(row.site_url),
    apiEndpoint: String(row.api_endpoint),
    encryptedApiToken: String(row.encrypted_api_token),
    permissions,
    pluginVersion: row.plugin_version ? String(row.plugin_version) : undefined,
    lastSeen: row.last_seen ? new Date(row.last_seen).toISOString() : undefined,
  };
}

export async function listSites(): Promise<Omit<SiteRecord, 'encryptedApiToken'>[]> {
  if (databaseEnabled()) {
    const rows = await dbRows<any[]>(
      `SELECT site_id, display_name, site_url, api_endpoint, encrypted_api_token,
              permissions_json, plugin_version, last_seen
       FROM wpgptvibe_sites ORDER BY display_name ASC`,
    );
    return rows.map(fromDb).map(({ encryptedApiToken: _secret, ...safe }) => safe);
  }

  const data = await readFile();
  return data.sites.map(({ encryptedApiToken: _secret, ...safe }) => safe);
}

export async function getSite(siteId: string): Promise<SiteRecord & { apiToken: string }> {
  let site: SiteRecord | undefined;

  if (databaseEnabled()) {
    const rows = await dbRows<any[]>(
      `SELECT site_id, display_name, site_url, api_endpoint, encrypted_api_token,
              permissions_json, plugin_version, last_seen
       FROM wpgptvibe_sites WHERE site_id = ? LIMIT 1`,
      [siteId],
    );
    site = rows[0] ? fromDb(rows[0]) : undefined;
  } else {
    const data = await readFile();
    site = data.sites.find((entry) => entry.siteId === siteId);
  }

  if (!site) throw new Error(`Unknown site_id: ${siteId}`);
  return { ...site, apiToken: decryptSecret(site.encryptedApiToken) };
}

export async function upsertSite(input: {
  siteId: string;
  displayName: string;
  siteUrl: string;
  apiToken: string;
  permissions?: string[];
  pluginVersion?: string;
}): Promise<void> {
  const next = normalizeSite(input);

  if (databaseEnabled()) {
    await dbExecute(
      `INSERT INTO wpgptvibe_sites
       (site_id, display_name, site_url, api_endpoint, encrypted_api_token, permissions_json, plugin_version)
       VALUES (?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         display_name = VALUES(display_name),
         site_url = VALUES(site_url),
         api_endpoint = VALUES(api_endpoint),
         encrypted_api_token = VALUES(encrypted_api_token),
         permissions_json = VALUES(permissions_json),
         plugin_version = VALUES(plugin_version),
         updated_at = CURRENT_TIMESTAMP(3)`,
      [
        next.siteId,
        next.displayName,
        next.siteUrl,
        next.apiEndpoint,
        next.encryptedApiToken,
        JSON.stringify(next.permissions),
        next.pluginVersion ?? null,
      ],
    );
    return;
  }

  const data = await readFile();
  const index = data.sites.findIndex((entry) => entry.siteId === input.siteId);
  if (index >= 0) data.sites[index] = next;
  else data.sites.push(next);
  await writeFile(data);
}

export async function deleteSite(siteId: string): Promise<boolean> {
  if (databaseEnabled()) {
    const existing = await dbRows<any[]>('SELECT site_id FROM wpgptvibe_sites WHERE site_id = ? LIMIT 1', [siteId]);
    if (!existing[0]) return false;
    await dbExecute('DELETE FROM wpgptvibe_sites WHERE site_id = ?', [siteId]);
    return true;
  }

  const data = await readFile();
  const before = data.sites.length;
  data.sites = data.sites.filter((entry) => entry.siteId !== siteId);
  if (data.sites.length === before) return false;
  await writeFile(data);
  return true;
}

export async function updateSiteTelemetry(
  siteId: string,
  patch: Pick<SiteRecord, 'lastSeen' | 'pluginVersion' | 'permissions'>,
): Promise<void> {
  if (databaseEnabled()) {
    await dbExecute(
      `UPDATE wpgptvibe_sites
       SET last_seen = ?, plugin_version = ?, permissions_json = ?, updated_at = CURRENT_TIMESTAMP(3)
       WHERE site_id = ?`,
      [
        patch.lastSeen ? patch.lastSeen.slice(0, 23).replace('T', ' ').replace('Z', '') : null,
        patch.pluginVersion ?? null,
        JSON.stringify(patch.permissions ?? []),
        siteId,
      ],
    );
    return;
  }

  const data = await readFile();
  const index = data.sites.findIndex((entry) => entry.siteId === siteId);
  if (index < 0) return;
  data.sites[index] = { ...data.sites[index]!, ...patch };
  await writeFile(data);
}


export async function importLegacySitesFromFile(): Promise<{imported:number;skipped:number}> {
  if (!databaseEnabled()) {
    throw new Error('DATABASE_URL is not configured.');
  }

  const legacy = await readFile();
  let imported = 0;
  let skipped = 0;

  for (const site of legacy.sites) {
    try {
      await upsertSite({
        siteId: site.siteId,
        displayName: site.displayName,
        siteUrl: site.siteUrl,
        apiToken: decryptSecret(site.encryptedApiToken),
        permissions: site.permissions,
        pluginVersion: site.pluginVersion,
      });
      if (site.lastSeen) {
        await updateSiteTelemetry(site.siteId, {
          lastSeen: site.lastSeen,
          pluginVersion: site.pluginVersion,
          permissions: site.permissions,
        });
      }
      imported += 1;
    } catch {
      skipped += 1;
    }
  }

  return { imported, skipped };
}
