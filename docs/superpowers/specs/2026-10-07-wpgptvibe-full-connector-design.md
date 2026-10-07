# WPGPTVibe Full Connector Design

## Goal

Expand WPGPTVibe from the current secure theme-management MVP into the internal WordPress management layer used for Bilal's sites, so routine work previously performed through WPVibe can be performed through WPGPTVibe without third-party action quotas.

## Product boundary

WPGPTVibe remains an internal multi-site management system. It is not a public SaaS in this phase.

The system has two components:

1. `wordpress-plugin/` (WPGPTVibe Bridge) installed on each WordPress site.
2. `mcp-server/` (WPGPTVibe MCP) deployed remotely and connected to ChatGPT-compatible MCP clients.

## Security model

Security controls are part of the product and are not optional limitations.

- HTTPS in production.
- Strong bearer tokens. WordPress stores only token hashes.
- MCP stores WordPress tokens encrypted at rest.
- Stable site UUID verification.
- Explicit capability allowlist.
- Draft-first theme writes.
- Protected meta allowlists.
- File path and extension allowlists.
- Request size limits and basic rate limiting.
- Audit logging for writes and high-risk actions.
- No arbitrary SQL endpoint.
- No PHP eval endpoint.
- No unrestricted shell endpoint.
- WP-CLI is allowlisted only.
- High-risk operations require explicit confirmation and separately enabled capabilities.

## Functional surface

### Site and diagnostics

- list connected sites
- site info and versions
- plugin/theme inventory
- basic environment and health diagnostics
- recent audit entries

### Theme and file management

- list/search/read theme files
- line-range reads
- safe draft-theme creation
- exact-match edits
- full file writes
- file creation/deletion in draft themes
- file diff
- PHP/JSON validation
- draft preview
- publish with release record
- rollback
- batch file edits

### Content management

- list/get/create/update/delete pages
- list/get/create/update/delete posts and supported public post types
- draft by default
- parent, template, slug, excerpt, status, featured media and dates where WordPress supports them
- batch content updates

### Metadata

- read/write public meta
- protected meta only through a configurable allowlist
- batch meta updates

### Media

- list/get attachments
- upload base64 payloads with strict size/MIME controls
- remote URL import with HTTPS, MIME and size validation
- title/caption/description/alt-text updates

### SEO adapters

Provider interface with:

- Rank Math first
- Yoast adapter
- generic fallback for standard canonical/robots metadata where possible

Operations:

- SEO title
- meta description
- canonical
- robots
- focus keyword when provider supports it
- batch SEO updates

### Cache and maintenance

- WordPress object-cache flush
- rewrite flush
- Rank Math sitemap/transient cleanup
- known cache-plugin hooks when installed
- Hostinger-compatible cache purge where an installed integration exposes a safe callable hook

### WP-CLI

Expose named operations, never a raw command string:

- plugin list/status
- theme list/status
- option get
- post list
- cache flush
- rewrite flush
- cron event list

Mutating commands require `run_wpcli` permission and high-risk confirmation.

### Calculator tooling

Build generic calculator endpoints that discover calculator definitions through a filter/provider contract so EzyMFG can register its current calculator configuration without coupling WPGPTVibe core to one theme.

Operations:

- list/get calculator
- update calculator through provider contract
- validate calculator
- validate all calculators in one call
- deterministic test cases where providers expose test vectors
- batch calculator updates

Validation reports configuration presence, syntax, declared inputs/outputs, formula/provider checks, errors and warnings.

### Browser-testing hooks

The MCP server includes an optional Playwright worker interface for:

- test page
- test calculator UI
- test route set
- scan console errors

Browser tools are enabled only when Playwright is installed and `WPGPTVIBE_BROWSER_TESTING=true`.

### Batching

Batch tools reduce tool-call count and return item-level success/failure:

- batch edit files
- batch update content
- batch update meta
- batch update SEO
- batch update calculators

A failed item does not hide results for other items.

## Version target

This expansion becomes WPGPTVibe Bridge `0.2.0` and WPGPTVibe MCP `0.2.0`.

## Verification

GitHub CI must run PHP syntax checks, TypeScript type checks/builds, unit tests for server-side pure functions, and package the WordPress plugin. The implementation is not considered complete until CI is green.