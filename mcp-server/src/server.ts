import { McpServer } from '@modelcontextprotocol/server';
import * as z from 'zod/v4';
import { config } from './config.js';
import { scanConsoleErrors, testCalculatorUI, testPage, testRoutes } from './browser/runner.js';
import { getSite, listSites } from './storage/sites.js';
import { BridgeError, callBridge } from './wordpress/client.js';

function result(data: unknown) {
  return {
    content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }],
    structuredContent: { result: data },
  };
}

function failure(error: unknown) {
  if (error instanceof BridgeError) {
    return {
      isError: true,
      content: [{ type: 'text' as const, text: `${error.message} (HTTP ${error.status}${error.code ? `, ${error.code}` : ''})` }],
      structuredContent: { error: { message: error.message, status: error.status, code: error.code } },
    };
  }
  const message = error instanceof Error ? error.message : 'Unknown error';
  return {
    isError: true,
    content: [{ type: 'text' as const, text: message }],
    structuredContent: { error: { message } },
  };
}

async function execute<T>(operation: () => Promise<T>) {
  try {
    return result(await operation());
  } catch (error) {
    return failure(error);
  }
}

const siteId = z.string().uuid().describe('Stable WPGPTVibe site UUID');
const jsonObject = z.record(z.string(), z.unknown());
const pathString = z.string().min(1).max(500);

async function registeredSiteUrl(id: string, path: string): Promise<string> {
  if (!path.startsWith('/') || /^\/\//.test(path) || /^https?:\/\//i.test(path)) {
    throw new Error('Browser path must be a relative site path beginning with /.');
  }
  const site = await getSite(id);
  const base = new URL(site.siteUrl);
  const target = new URL(path, base);
  if (target.origin !== base.origin) {
    throw new Error('Browser path must stay on the registered WordPress site.');
  }
  return target.toString();
}

export function createServer(): McpServer {
  const server = new McpServer({ name: 'wpgptvibe-mcp', version: '0.3.0' });

  server.registerTool('list_sites', {
    description: 'List connected WordPress sites without exposing API tokens.',
    inputSchema: z.object({}),
  }, async () => execute(() => listSites()));

  server.registerTool('site_info', {
    description: 'Get site, WordPress, PHP, theme, Bridge, SEO provider, WP-CLI status and enabled capabilities.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'site')));

  server.registerTool('audit_log', {
    description: 'Read recent WPGPTVibe Bridge audit entries.',
    inputSchema: z.object({ site_id: siteId, limit: z.number().int().min(1).max(200).default(50) }),
  }, async ({ site_id, limit }) => execute(() => callBridge(site_id, 'GET', 'audit', { limit })));

  server.registerTool('get_bridge_capabilities', {
    description: 'Read available and enabled WPGPTVibe Bridge capabilities for a site.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'bridge/capabilities')));

  server.registerTool('update_bridge_capabilities', {
    description: 'HIGH RISK: replace enabled Bridge capabilities. The manage_bridge capability must already be enabled locally on WordPress.',
    inputSchema: z.object({
      site_id: siteId,
      enabled: z.array(z.string()).max(50),
      confirm: z.literal('UPDATE_PERMISSIONS'),
    }),
  }, async ({ site_id, enabled }) => execute(() => callBridge(site_id, 'PUT', 'bridge/capabilities', { enabled })));

  server.registerTool('list_files', {
    description: 'List files in an active or named WordPress theme.',
    inputSchema: z.object({ site_id: siteId, theme: z.string().optional(), path: z.string().optional() }),
  }, async ({ site_id, theme, path }) => execute(() => callBridge(site_id, 'GET', 'theme/files', { theme, path })));

  server.registerTool('search_files', {
    description: 'Search theme files server-side without reading whole files into the MCP context.',
    inputSchema: z.object({
      site_id: siteId,
      pattern: z.string().min(1).max(500),
      theme: z.string().optional(),
      extensions: z.array(z.string()).max(20).default([]),
      case_sensitive: z.boolean().default(false),
      max_results: z.number().int().min(1).max(500).default(100),
    }),
  }, async ({ site_id, pattern, theme, extensions, case_sensitive, max_results }) => execute(() => callBridge(site_id, 'GET', 'theme/search', {
    pattern, theme, extensions, case_sensitive, max_results,
  })));

  server.registerTool('read_file', {
    description: 'Read a safe theme file, optionally restricted to a line range.',
    inputSchema: z.object({
      site_id: siteId,
      path: pathString,
      theme: z.string().optional(),
      start_line: z.number().int().min(1).optional(),
      end_line: z.number().int().min(1).optional(),
    }),
  }, async ({ site_id, path, theme, start_line, end_line }) => execute(() => callBridge(site_id, 'GET', 'theme/file', {
    path, theme, start_line, end_line,
  })));

  server.registerTool('get_file_diff', {
    description: 'Compare a draft theme file with its source/live theme version.',
    inputSchema: z.object({ site_id: siteId, path: pathString }),
  }, async ({ site_id, path }) => execute(() => callBridge(site_id, 'GET', 'theme/file/diff', { path })));

  server.registerTool('create_draft_theme', {
    description: 'Clone the active theme into a WPGPTVibe draft. Existing draft is preserved unless replace=true.',
    inputSchema: z.object({ site_id: siteId, replace: z.boolean().default(false) }),
  }, async ({ site_id, replace }) => execute(() => callBridge(site_id, 'POST', 'theme/draft/create', { replace })));

  server.registerTool('get_draft_status', {
    description: 'Get current draft-theme status.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'theme/draft')));

  server.registerTool('list_theme_releases', {
    description: 'List WPGPTVibe theme releases and rollback records for a site.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'theme/releases')));

  server.registerTool('get_preview_url', {
    description: 'Create a short-lived preview URL for the current draft theme.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'theme/draft/preview')));

  server.registerTool('edit_file', {
    description: 'Exact-match edit of a draft-theme file. Fails on zero or ambiguous matches unless replace_all=true.',
    inputSchema: z.object({
      site_id: siteId,
      path: pathString,
      old_content: z.string().min(1),
      new_content: z.string(),
      replace_all: z.boolean().default(false),
    }),
  }, async ({ site_id, path, old_content, new_content, replace_all }) => execute(() => callBridge(site_id, 'POST', 'theme/file/edit', {
    path, old_content, new_content, replace_all,
  })));

  server.registerTool('write_file', {
    description: 'Write a complete allowed file inside the current draft theme.',
    inputSchema: z.object({ site_id: siteId, path: pathString, content: z.string() }),
  }, async ({ site_id, path, content }) => execute(() => callBridge(site_id, 'POST', 'theme/file/write', { path, content })));

  server.registerTool('delete_file', {
    description: 'HIGH RISK: delete one file from the current draft theme only.',
    inputSchema: z.object({ site_id: siteId, path: pathString, confirm: z.literal('DELETE') }),
  }, async ({ site_id, path }) => execute(() => callBridge(site_id, 'POST', 'theme/file/delete', { path, confirm: 'DELETE' })));

  server.registerTool('batch_edit_files', {
    description: 'Apply up to 100 exact-match draft-theme edits in one action and return item-level results.',
    inputSchema: z.object({
      site_id: siteId,
      items: z.array(z.object({
        path: pathString,
        old_content: z.string().min(1),
        new_content: z.string(),
        replace_all: z.boolean().default(false),
      })).min(1).max(100),
    }),
  }, async ({ site_id, items }) => execute(() => callBridge(site_id, 'POST', 'theme/files/batch-edit', { items })));

  server.registerTool('publish_draft_theme', {
    description: 'HIGH RISK: validate and activate the current draft theme. Bridge publish permission must be enabled.',
    inputSchema: z.object({ site_id: siteId, confirm: z.literal('PUBLISH') }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'POST', 'theme/draft/publish', { confirm: 'PUBLISH' })));

  server.registerTool('rollback_theme', {
    description: 'HIGH RISK: reactivate the theme recorded before a WPGPTVibe release.',
    inputSchema: z.object({ site_id: siteId, release_id: z.string().uuid(), confirm: z.literal('ROLLBACK') }),
  }, async ({ site_id, release_id }) => execute(() => callBridge(site_id, 'POST', 'theme/rollback', { release_id, confirm: 'ROLLBACK' })));

  server.registerTool('list_content_types', {
    description: 'List public WordPress post types WPGPTVibe can manage.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'content/types')));

  server.registerTool('list_content', {
    description: 'List pages, posts or another public post type with filters.',
    inputSchema: z.object({
      site_id: siteId,
      post_type: z.string().default('page'),
      status: z.string().optional(),
      parent: z.number().int().optional(),
      slug: z.string().optional(),
      search: z.string().optional(),
      per_page: z.number().int().min(1).max(200).default(100),
    }),
  }, async ({ site_id, ...params }) => execute(() => callBridge(site_id, 'GET', 'content', params)));

  server.registerTool('get_content', {
    description: 'Get full content for a WordPress page/post/custom post.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive() }),
  }, async ({ site_id, id }) => execute(() => callBridge(site_id, 'GET', `content/${id}`)));

  server.registerTool('create_content', {
    description: 'Create WordPress content. Status defaults to draft when omitted.',
    inputSchema: z.object({ site_id: siteId, post_type: z.string().default('page'), data: jsonObject }),
  }, async ({ site_id, post_type, data }) => execute(() => callBridge(site_id, 'POST', 'content', { ...data, post_type })));

  server.registerTool('update_content', {
    description: 'Update a WordPress page/post/custom post.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive(), data: jsonObject }),
  }, async ({ site_id, id, data }) => execute(() => callBridge(site_id, 'PUT', `content/${id}`, data)));

  server.registerTool('delete_content', {
    description: 'HIGH RISK: trash or permanently delete WordPress content.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive(), force: z.boolean().default(false), confirm: z.literal('DELETE') }),
  }, async ({ site_id, id, force }) => execute(() => callBridge(site_id, 'DELETE', `content/${id}`, { force, confirm: 'DELETE' })));

  server.registerTool('batch_update_content', {
    description: 'Batch update up to 100 content items with per-item results.',
    inputSchema: z.object({ site_id: siteId, items: z.array(z.object({ id: z.number().int().positive(), data: jsonObject })).min(1).max(100) }),
  }, async ({ site_id, items }) => execute(() => callBridge(site_id, 'POST', 'content/batch-update', { items })));

  server.registerTool('get_meta', {
    description: 'Read public and explicitly allowlisted protected post metadata.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive() }),
  }, async ({ site_id, id }) => execute(() => callBridge(site_id, 'GET', `content/${id}/meta`)));

  server.registerTool('update_meta', {
    description: 'Update public or allowlisted protected post metadata. Null deletes a key.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive(), meta: jsonObject }),
  }, async ({ site_id, id, meta }) => execute(() => callBridge(site_id, 'PUT', `content/${id}/meta`, { meta })));

  server.registerTool('batch_update_meta', {
    description: 'Batch metadata updates with item-level results.',
    inputSchema: z.object({ site_id: siteId, items: z.array(z.object({ id: z.number().int().positive(), meta: jsonObject })).min(1).max(100) }),
  }, async ({ site_id, items }) => execute(() => callBridge(site_id, 'POST', 'meta/batch-update', { items })));

  server.registerTool('list_media', {
    description: 'List WordPress media attachments.',
    inputSchema: z.object({ site_id: siteId, search: z.string().optional(), mime_type: z.string().optional(), per_page: z.number().int().min(1).max(200).default(100) }),
  }, async ({ site_id, ...params }) => execute(() => callBridge(site_id, 'GET', 'media', params)));

  server.registerTool('get_media', {
    description: 'Get one media attachment and its metadata.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive() }),
  }, async ({ site_id, id }) => execute(() => callBridge(site_id, 'GET', `media/${id}`)));

  server.registerTool('upload_media', {
    description: 'Upload an allowed WordPress media file from base64 (Bridge limit 10 MB).',
    inputSchema: z.object({ site_id: siteId, filename: z.string().min(1), base64: z.string().min(1), mime_type: z.string().optional() }),
  }, async ({ site_id, filename, base64, mime_type }) => execute(() => callBridge(site_id, 'POST', 'media/upload', { filename, base64, mime_type })));

  server.registerTool('import_media', {
    description: 'Import media from an HTTPS URL with MIME, redirect and size controls.',
    inputSchema: z.object({ site_id: siteId, url: z.string().url(), filename: z.string().optional() }),
  }, async ({ site_id, url, filename }) => execute(() => callBridge(site_id, 'POST', 'media/import', { url, filename })));

  server.registerTool('update_media', {
    description: 'Update media title, caption, description and alt text.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive(), data: jsonObject }),
  }, async ({ site_id, id, data }) => execute(() => callBridge(site_id, 'PUT', `media/${id}`, data)));

  server.registerTool('get_seo', {
    description: 'Read SEO fields through Rank Math, Yoast or the generic fallback.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive() }),
  }, async ({ site_id, id }) => execute(() => callBridge(site_id, 'GET', `seo/${id}`)));

  server.registerTool('update_seo', {
    description: 'Update supported SEO title, description, canonical, robots and focus keyword fields.',
    inputSchema: z.object({ site_id: siteId, id: z.number().int().positive(), seo: jsonObject }),
  }, async ({ site_id, id, seo }) => execute(() => callBridge(site_id, 'PUT', `seo/${id}`, seo)));

  server.registerTool('batch_update_seo', {
    description: 'Batch SEO updates with item-level results.',
    inputSchema: z.object({ site_id: siteId, items: z.array(z.object({ id: z.number().int().positive(), seo: jsonObject })).min(1).max(100) }),
  }, async ({ site_id, items }) => execute(() => callBridge(site_id, 'POST', 'seo/batch-update', { items })));

  server.registerTool('clear_cache', {
    description: 'Purge selected WordPress/object/plugin/SEO cache layers when available.',
    inputSchema: z.object({ site_id: siteId, layers: z.array(z.string()).max(20).default([]) }),
  }, async ({ site_id, layers }) => execute(() => callBridge(site_id, 'POST', 'cache/purge', { layers })));

  server.registerTool('flush_rewrites', {
    description: 'HIGH RISK maintenance: flush WordPress rewrite rules.',
    inputSchema: z.object({ site_id: siteId, confirm: z.literal('FLUSH') }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'POST', 'rewrite/flush', { confirm: 'FLUSH' })));

  server.registerTool('wpcli_status', {
    description: 'Check allowlisted WP-CLI availability and operations.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'wpcli/status')));

  server.registerTool('run_wpcli', {
    description: 'Run a named allowlisted WP-CLI operation. Raw shell commands are not accepted.',
    inputSchema: z.object({
      site_id: siteId,
      operation: z.enum(['plugin_list', 'theme_list', 'option_get', 'post_list', 'cron_list', 'cache_flush', 'rewrite_flush']),
      args: jsonObject.default({}),
      confirm: z.enum(['RUN']).optional(),
    }),
  }, async ({ site_id, operation, args, confirm }) => execute(() => callBridge(site_id, 'POST', 'wpcli/run', { operation, args, confirm })));

  server.registerTool('list_calculators', {
    description: 'List calculators/tools registered through the WPGPTVibe calculator provider contract.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'calculators')));

  server.registerTool('get_calculator', {
    description: 'Get one registered calculator configuration.',
    inputSchema: z.object({ site_id: siteId, slug: z.string().min(1) }),
  }, async ({ site_id, slug }) => execute(() => callBridge(site_id, 'GET', `calculators/${encodeURIComponent(slug)}`)));

  server.registerTool('update_calculator', {
    description: 'Update a calculator through the site provider contract when supported.',
    inputSchema: z.object({ site_id: siteId, slug: z.string().min(1), config: jsonObject }),
  }, async ({ site_id, slug, config: calculatorConfig }) => execute(() => callBridge(site_id, 'PUT', `calculators/${encodeURIComponent(slug)}`, calculatorConfig)));

  server.registerTool('validate_calculator', {
    description: 'Validate calculator config, declared inputs/outputs, formula checks and provider validations.',
    inputSchema: z.object({ site_id: siteId, slug: z.string().min(1) }),
  }, async ({ site_id, slug }) => execute(() => callBridge(site_id, 'GET', `calculators/${encodeURIComponent(slug)}/validate`)));

  server.registerTool('validate_all_calculators', {
    description: 'Validate every registered calculator in one MCP action.',
    inputSchema: z.object({ site_id: siteId }),
  }, async ({ site_id }) => execute(() => callBridge(site_id, 'GET', 'calculators/validate-all')));

  server.registerTool('test_calculator', {
    description: 'Run deterministic calculator test logic exposed by the site provider.',
    inputSchema: z.object({ site_id: siteId, slug: z.string().min(1), input: jsonObject.default({}) }),
  }, async ({ site_id, slug, input }) => execute(() => callBridge(site_id, 'POST', `calculators/${encodeURIComponent(slug)}/test`, input)));

  server.registerTool('batch_update_calculators', {
    description: 'Batch calculator updates with per-item success/failure results.',
    inputSchema: z.object({ site_id: siteId, items: z.array(z.object({ slug: z.string().min(1), config: jsonObject })).min(1).max(100) }),
  }, async ({ site_id, items }) => execute(() => callBridge(site_id, 'POST', 'calculators/batch-update', { items })));

  if (config.browserTesting) {
    server.registerTool('test_page', {
      description: 'Open a page on a registered WordPress site in Chromium and check selectors/console errors.',
      inputSchema: z.object({ site_id: siteId, path: z.string().startsWith('/'), selectors: z.array(z.string()).max(50).default([]) }),
    }, async ({ site_id, path, selectors }) => execute(async () => testPage(await registeredSiteUrl(site_id, path), selectors)));

    server.registerTool('scan_console_errors', {
      description: 'Open a page on a registered site and return console warnings/errors and page exceptions.',
      inputSchema: z.object({ site_id: siteId, path: z.string().startsWith('/') }),
    }, async ({ site_id, path }) => execute(async () => scanConsoleErrors(await registeredSiteUrl(site_id, path))));

    server.registerTool('test_all_routes', {
      description: 'Test up to 50 paths on one registered WordPress site using Chromium.',
      inputSchema: z.object({ site_id: siteId, paths: z.array(z.string().startsWith('/')).min(1).max(50) }),
    }, async ({ site_id, paths }) => execute(async () => {
      const urls = await Promise.all(paths.map((path) => registeredSiteUrl(site_id, path)));
      return testRoutes(urls);
    }));

    server.registerTool('test_calculator_ui', {
      description: 'Open a calculator page, fill declared fields, calculate, capture outputs, inspect console, and optionally test reset.',
      inputSchema: z.object({
        site_id: siteId,
        path: z.string().startsWith('/'),
        ready_selector: z.string().optional(),
        fields: z.array(z.object({ selector: z.string(), value: z.string() })).max(30).default([]),
        calculate_selector: z.string().optional(),
        reset_selector: z.string().optional(),
        output_selectors: z.array(z.string()).max(30).default([]),
      }),
    }, async ({ site_id, path, ...input }) => execute(async () => testCalculatorUI(await registeredSiteUrl(site_id, path), input)));
  }

  return server;
}
