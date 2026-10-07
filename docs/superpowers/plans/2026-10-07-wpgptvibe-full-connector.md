# WPGPTVibe Full Connector Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand WPGPTVibe 0.1.0 into the full internal WordPress connector layer needed to replace WPVibe for routine site management, batching, SEO, media, maintenance and calculator QA.

**Architecture:** Keep the WordPress plugin as the policy/enforcement boundary and the remote MCP server as the orchestration boundary. Add focused WordPress manager classes for content, media, SEO, cache, WP-CLI and calculators, then expose only allowlisted routes through the MCP server. Batch operations fan out through existing safe primitives and preserve per-item results.

**Tech Stack:** PHP 8.1+, WordPress REST API, Node.js 20+, TypeScript 5.9+, MCP TypeScript v2 packages, Zod v4, optional Playwright.

**Spec:** `docs/superpowers/specs/2026-10-07-wpgptvibe-full-connector-design.md`

## Global Constraints

- Multi-site reusable architecture. No EzyMFG-specific coupling in the core bridge.
- Draft-first theme writes.
- No arbitrary SQL, shell, PHP eval or unrestricted WP-CLI.
- High-risk operations remain explicitly gated.
- Protected metadata requires an allowlist.
- Batch endpoints return item-level success/failure.
- WordPress Bridge and MCP target version `0.2.0`.
- GitHub CI must be green before completion.

## Review Focus

- Path traversal and symlink escape attempts must not read/write outside approved theme roots.
- Media remote imports must reject non-HTTPS URLs, oversized payloads and disallowed MIME types.
- Protected meta must remain inaccessible unless explicitly allowlisted.
- Batch actions must not abort the entire batch when one item fails.
- WP-CLI input must map only to named allowlisted commands, never user-supplied raw shell strings.

---

### Task 1: Plugin Core Version, Request Limits and Manager Wiring

**Files:**
- Modify: `wordpress-plugin/wpgptvibe-bridge.php`
- Modify: `wordpress-plugin/includes/class-auth.php`
- Create: `wordpress-plugin/includes/class-request-guard.php`
- Modify: `wordpress-plugin/includes/class-rest.php`

**Interfaces:**
- Produces: `WPGPTVibe_Request_Guard::check(WP_REST_Request): true|WP_Error`
- Produces: Bridge version `0.2.0`

- [ ] Add request-size and lightweight IP/token rate limiting with local-development exception.
- [ ] Compose request guard into REST permission callbacks after token/capability checks.
- [ ] Wire new manager class includes without changing current safe theme behavior.
- [ ] Run PHP syntax checks.

### Task 2: Theme Search, Delete, Diff and Batch Editing

**Files:**
- Modify: `wordpress-plugin/includes/class-theme-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: `search_files`, `delete_file`, `get_file_diff`, `batch_edit_files` routes/tools.

- [ ] Implement server-side text search with extension filters, case-sensitivity and max results.
- [ ] Implement draft-only delete with extension/path protection and audit log.
- [ ] Implement unified diff output with relevant hunks for text files.
- [ ] Implement batch exact-match edits using existing edit primitive and per-item results.
- [ ] Add matching MCP tools and allowlist paths.
- [ ] Run PHP and TypeScript checks.

### Task 3: Pages, Posts and Generic Content Management

**Files:**
- Create: `wordpress-plugin/includes/class-content-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: list/get/create/update/delete content methods and batch update.

- [ ] Support pages, posts and explicitly requested public post types.
- [ ] Default create status to `draft`.
- [ ] Support title, slug, content, excerpt, parent, status, template, featured media and dates where valid.
- [ ] Add batch content updates with per-item results.
- [ ] Audit create/update/delete writes.
- [ ] Add MCP tools.
- [ ] Run checks.

### Task 4: Metadata Manager

**Files:**
- Create: `wordpress-plugin/includes/class-meta-manager.php`
- Modify: `wordpress-plugin/includes/class-auth.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: get/update/batch meta routes and tools.

- [ ] Read/write public meta.
- [ ] Add protected-meta allowlist setting with safe defaults.
- [ ] Reject `_` protected keys not allowlisted.
- [ ] Add batch updates and audit writes.
- [ ] Run checks.

### Task 5: Media Manager

**Files:**
- Create: `wordpress-plugin/includes/class-media-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: media list/get/upload/update/import routes and tools.

- [ ] List/get attachments.
- [ ] Upload base64 files with MIME and size validation.
- [ ] Import HTTPS remote URLs with timeout, MIME and size limits.
- [ ] Update title, caption, description and alt text.
- [ ] Audit writes.
- [ ] Run checks.

### Task 6: SEO Provider Adapters

**Files:**
- Create: `wordpress-plugin/includes/seo/interface-seo-provider.php`
- Create: `wordpress-plugin/includes/seo/class-rank-math-provider.php`
- Create: `wordpress-plugin/includes/seo/class-yoast-provider.php`
- Create: `wordpress-plugin/includes/seo/class-generic-seo-provider.php`
- Create: `wordpress-plugin/includes/class-seo-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: get/update/batch SEO routes and tools.

- [ ] Detect Rank Math, Yoast or generic provider.
- [ ] Read/write title, description, canonical, robots and focus keyword when supported.
- [ ] Batch updates with item-level results.
- [ ] Audit SEO writes.
- [ ] Run checks.

### Task 7: Cache and Maintenance Utilities

**Files:**
- Create: `wordpress-plugin/includes/class-cache-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: cache purge and rewrite flush tools.

- [ ] Flush WordPress object cache.
- [ ] Flush rewrite rules only through explicit operation.
- [ ] Clear known Rank Math sitemap/transient caches when available.
- [ ] Call supported known cache-plugin/Hostinger hooks only when callable.
- [ ] Return which layers were actually purged.
- [ ] Run checks.

### Task 8: Allowlisted WP-CLI

**Files:**
- Create: `wordpress-plugin/includes/class-wpcli-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces: named WP-CLI operations only.

- [ ] Detect WP-CLI availability.
- [ ] Map named read operations to fixed argument arrays.
- [ ] Map approved mutating operations separately and require confirmation.
- [ ] Never accept a raw command string.
- [ ] Capture bounded stdout/stderr and audit mutating operations.
- [ ] Run checks.

### Task 9: Calculator Provider Contract and Validation

**Files:**
- Create: `wordpress-plugin/includes/class-calculator-manager.php`
- Modify: `wordpress-plugin/includes/class-rest.php`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/server.ts`

**Interfaces:**
- Produces WordPress filters: `wpgptvibe_calculators`, `wpgptvibe_calculator_get`, `wpgptvibe_calculator_update`, `wpgptvibe_calculator_validate`, `wpgptvibe_calculator_test`.
- Produces tools: list/get/update/validate/validate_all/test/batch_update calculators.

- [ ] Build provider/filter contract so EzyMFG can register current configs without core coupling.
- [ ] Normalize validation results to config/syntax/inputs/outputs/formula checks/validation checks/errors/warnings.
- [ ] Validate all in one action.
- [ ] Return unsupported-update/test errors clearly when a provider does not implement them.
- [ ] Run checks.

### Task 10: Optional Browser Testing Worker

**Files:**
- Modify: `mcp-server/package.json`
- Create: `mcp-server/src/browser/runner.ts`
- Modify: `mcp-server/src/server.ts`
- Modify: `mcp-server/src/config.ts`

**Interfaces:**
- Produces: `test_page`, `test_calculator_ui`, `test_all_routes`, `scan_console_errors`.

- [ ] Add Playwright as optional runtime dependency path.
- [ ] Disable tools unless `WPGPTVIBE_BROWSER_TESTING=true`.
- [ ] Test page response, selectors, console/page errors and calculator input/calculate/reset flows from declarative test input.
- [ ] Bound navigation timeouts and returned console output.
- [ ] Run TypeScript checks/build.

### Task 11: MCP Batch Surface, Site Registration and Error Normalization

**Files:**
- Modify: `mcp-server/src/server.ts`
- Modify: `mcp-server/src/wordpress/client.ts`
- Modify: `mcp-server/src/storage/sites.ts`
- Modify: `mcp-server/src/cli/add-site.ts`

**Interfaces:**
- Produces consistent structured success/error envelopes and complete tool surface.

- [ ] Standardize Bridge errors with HTTP/status/code context without secrets.
- [ ] Keep site tokens out of all tool outputs/logs.
- [ ] Add per-site capability telemetry updates.
- [ ] Verify all write tools map to explicit Bridge paths.
- [ ] Run checks.

### Task 12: Admin UI, Documentation, CI and Packaging

**Files:**
- Modify: `wordpress-plugin/admin/class-admin.php`
- Modify: `wordpress-plugin/README.md`
- Modify: `mcp-server/README.md`
- Modify: `README.md`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Produces installable `wpgptvibe-bridge.zip` and deployment docs.

- [ ] Expose new capabilities/settings/status in WordPress admin without showing secrets.
- [ ] Document every endpoint/tool and risk class.
- [ ] Add tests/checks feasible in CI and retain PHP lint + TS check/build + packaging.
- [ ] Run final CI and confirm both jobs green.
- [ ] Verify packaged artifact is generated.
