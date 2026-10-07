import { McpServer } from '@modelcontextprotocol/server';
import * as z from 'zod/v4';
import { callBridge } from './wordpress/client.js';
import { listSites } from './storage/sites.js';

function result(data: unknown) {
  return {
    content: [{ type: 'text' as const, text: JSON.stringify(data, null, 2) }],
    structuredContent: { result: data },
  };
}

function failure(error: unknown) {
  const message = error instanceof Error ? error.message : 'Unknown error';
  return {
    isError: true,
    content: [{ type: 'text' as const, text: message }],
  };
}

const siteId = z.string().uuid().describe('Stable WPGPTVibe site UUID');

export function createServer(): McpServer {
  const server = new McpServer({
    name: 'wpgptvibe-mcp',
    version: '0.1.0',
  });

  server.registerTool(
    'list_sites',
    {
      description: 'List connected WordPress sites without exposing API tokens.',
      inputSchema: z.object({}),
    },
    async () => {
      try {
        return result(await listSites());
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'site_info',
    {
      description: 'Get WordPress, PHP, theme, Bridge version, and enabled capabilities for one connected site.',
      inputSchema: z.object({ site_id: siteId }),
    },
    async ({ site_id }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'site'));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'list_files',
    {
      description: 'List files in the active or named WordPress theme.',
      inputSchema: z.object({
        site_id: siteId,
        theme: z.string().optional(),
        path: z.string().optional(),
      }),
    },
    async ({ site_id, theme, path }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'theme/files', { theme, path }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'read_file',
    {
      description: 'Read a safe theme file, optionally restricted to a line range.',
      inputSchema: z.object({
        site_id: siteId,
        path: z.string().min(1),
        theme: z.string().optional(),
        start_line: z.number().int().min(1).optional(),
        end_line: z.number().int().min(1).optional(),
      }),
    },
    async ({ site_id, path, theme, start_line, end_line }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'theme/file', {
          path,
          theme,
          start_line,
          end_line,
        }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'create_draft_theme',
    {
      description: 'Clone the active WordPress theme into the WPGPTVibe draft theme. Existing draft is preserved unless replace=true.',
      inputSchema: z.object({
        site_id: siteId,
        replace: z.boolean().default(false),
      }),
    },
    async ({ site_id, replace }) => {
      try {
        return result(await callBridge(site_id, 'POST', 'theme/draft/create', { replace }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'get_draft_status',
    {
      description: 'Check whether a WPGPTVibe draft theme exists and get its metadata.',
      inputSchema: z.object({ site_id: siteId }),
    },
    async ({ site_id }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'theme/draft'));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'get_preview_url',
    {
      description: 'Create a short-lived preview URL for the current WPGPTVibe draft theme.',
      inputSchema: z.object({ site_id: siteId }),
    },
    async ({ site_id }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'theme/draft/preview'));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'edit_file',
    {
      description: 'Exact-match edit of a file in the draft theme only. Fails on zero matches and on multiple matches unless replace_all=true.',
      inputSchema: z.object({
        site_id: siteId,
        path: z.string().min(1),
        old_content: z.string().min(1),
        new_content: z.string(),
        replace_all: z.boolean().default(false),
      }),
    },
    async ({ site_id, path, old_content, new_content, replace_all }) => {
      try {
        return result(await callBridge(site_id, 'POST', 'theme/file/edit', {
          path,
          old_content,
          new_content,
          replace_all,
        }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'write_file',
    {
      description: 'Write a complete allowed file inside the current draft theme only.',
      inputSchema: z.object({
        site_id: siteId,
        path: z.string().min(1),
        content: z.string(),
      }),
    },
    async ({ site_id, path, content }) => {
      try {
        return result(await callBridge(site_id, 'POST', 'theme/file/write', { path, content }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'publish_draft_theme',
    {
      description: 'HIGH RISK: validate and activate the current draft theme. The WordPress publish_themes capability must be explicitly enabled.',
      inputSchema: z.object({
        site_id: siteId,
        confirm: z.literal('PUBLISH'),
      }),
    },
    async ({ site_id }) => {
      try {
        return result(await callBridge(site_id, 'POST', 'theme/draft/publish', {}));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'rollback_theme',
    {
      description: 'HIGH RISK: reactivate the theme recorded before a WPGPTVibe release.',
      inputSchema: z.object({
        site_id: siteId,
        release_id: z.string().uuid(),
        confirm: z.literal('ROLLBACK'),
      }),
    },
    async ({ site_id, release_id }) => {
      try {
        return result(await callBridge(site_id, 'POST', 'theme/rollback', { release_id }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  server.registerTool(
    'audit_log',
    {
      description: 'Read recent WPGPTVibe Bridge audit entries for a site.',
      inputSchema: z.object({
        site_id: siteId,
        limit: z.number().int().min(1).max(200).default(50),
      }),
    },
    async ({ site_id, limit }) => {
      try {
        return result(await callBridge(site_id, 'GET', 'audit', { limit }));
      } catch (error) {
        return failure(error);
      }
    },
  );

  return server;
}
