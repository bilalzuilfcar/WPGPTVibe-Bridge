import { upsertSite } from '../storage/sites.js';

const siteId = process.env.WPGPTVIBE_SITE_ID?.trim();
const displayName = process.env.WPGPTVIBE_SITE_NAME?.trim();
const siteUrl = process.env.WPGPTVIBE_SITE_URL?.trim();
const apiToken = process.env.WPGPTVIBE_SITE_TOKEN?.trim();

if (!siteId || !displayName || !siteUrl || !apiToken) {
  throw new Error(
    'Set WPGPTVIBE_SITE_ID, WPGPTVIBE_SITE_NAME, WPGPTVIBE_SITE_URL, and WPGPTVIBE_SITE_TOKEN before running site:add.',
  );
}

await upsertSite({
  siteId,
  displayName,
  siteUrl,
  apiToken,
});

console.log(`Registered site ${displayName} (${siteId}). Token was encrypted before storage.`);
