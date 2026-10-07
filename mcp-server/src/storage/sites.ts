import fs from 'node:fs/promises';
import path from 'node:path';
import { config } from '../config.js';
import { decryptSecret, encryptSecret } from './crypto.js';

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

async function readFile(): Promise<SiteFile> {
  try {
    const parsed = JSON.parse(await fs.readFile(sitesPath, 'utf8')) as SiteFile;
    if (parsed.version !== 1 || !Array.isArray(parsed.sites)) {
      throw new Error('Unsupported sites registry format.');
    }
    return parsed;
  } catch (error: unknown) {
    if ((error as NodeJS.ErrnoException).code === 'ENOENT') {
      return { version: 1, sites: [] };
    }
    throw error;
  }
}

async function writeFile(data: SiteFile): Promise<void> {
  await fs.mkdir(config.dataDir, { recursive: true, mode: 0o700 });
  const temp = sitesPath + '.tmp';
  await fs.writeFile(temp, JSON.stringify(data, null, 2), { encoding: 'utf8', mode: 0o600 });
  await fs.rename(temp, sitesPath);
}

export async function listSites(): Promise<Omit<SiteRecord, 'encryptedApiToken'>[]> {
  const data = await readFile();
  return data.sites.map(({ encryptedApiToken: _secret, ...safe }) => safe);
}

export async function getSite(siteId: string): Promise<SiteRecord & { apiToken: string }> {
  const data = await readFile();
  const site = data.sites.find((entry) => entry.siteId === siteId);
  if (!site) {
    throw new Error(`Unknown site_id: ${siteId}`);
  }
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
  const siteUrl = new URL(input.siteUrl);
  if (siteUrl.protocol !== 'https:' && siteUrl.hostname !== 'localhost' && siteUrl.hostname !== '127.0.0.1') {
    throw new Error('WordPress site URL must use HTTPS.');
  }

  const apiEndpoint = new URL('/wp-json/wpgptvibe/v1/', siteUrl).toString();
  const data = await readFile();
  const next: SiteRecord = {
    siteId: input.siteId,
    displayName: input.displayName,
    siteUrl: siteUrl.toString(),
    apiEndpoint,
    encryptedApiToken: encryptSecret(input.apiToken),
    permissions: input.permissions ?? [],
    pluginVersion: input.pluginVersion,
  };

  const index = data.sites.findIndex((entry) => entry.siteId === input.siteId);
  if (index >= 0) {
    data.sites[index] = next;
  } else {
    data.sites.push(next);
  }

  await writeFile(data);
}

export async function updateSiteTelemetry(siteId: string, patch: Pick<SiteRecord, 'lastSeen' | 'pluginVersion' | 'permissions'>): Promise<void> {
  const data = await readFile();
  const index = data.sites.findIndex((entry) => entry.siteId === siteId);
  if (index < 0) return;

  data.sites[index] = { ...data.sites[index]!, ...patch };
  await writeFile(data);
}
