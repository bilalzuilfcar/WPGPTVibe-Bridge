const EXACT_BRIDGE_PATHS = new Set([
  'site',
  'audit',
  'bridge/capabilities',
  'theme/files',
  'theme/file',
  'theme/search',
  'theme/file/diff',
  'theme/draft/create',
  'theme/draft',
  'theme/releases',
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

const DYNAMIC_BRIDGE_PATHS = [
  /^content\/\d+$/,
  /^content\/\d+\/meta$/,
  /^media\/\d+$/,
  /^seo\/\d+$/,
  /^calculators\/[a-z0-9-]+$/,
  /^calculators\/[a-z0-9-]+\/validate$/,
  /^calculators\/[a-z0-9-]+\/test$/,
];

export function isBridgePathAllowed(path: string): boolean {
  return EXACT_BRIDGE_PATHS.has(path) || DYNAMIC_BRIDGE_PATHS.some((pattern) => pattern.test(path));
}

export function resolveRegisteredSitePath(siteUrl: string, path: string): string {
  if (!path.startsWith('/') || path.startsWith('//') || /^https?:\/\//i.test(path)) {
    throw new Error('Browser path must be a relative site path beginning with /.');
  }

  const base = new URL(siteUrl);
  const target = new URL(path, base);
  if (target.origin !== base.origin) {
    throw new Error('Browser path must stay on the registered WordPress site.');
  }
  return target.toString();
}
