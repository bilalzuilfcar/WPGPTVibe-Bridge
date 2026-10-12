# Direct WordPress MCP and visual editing

## Approved intent
Bilal wants an installable WordPress plugin connected directly to ChatGPT through MCP. Pages must be easily editable without coding: click text, replace images, reorder sections and adjust layout in WordPress. Preserve existing websites and editor formats. The Node control plane is optional and is not required for this route.

## Architecture
Extend wordpress-plugin with a standards-compatible HTTP MCP endpoint and OAuth authorization within WordPress. Reuse existing Bridge managers behind explicit permission checks. Do not expose existing operations anonymously. Existing Node integration remains compatible.

Use an established, maintained OAuth implementation rather than inventing cryptographic protocols. Before implementation, verify PHP/WordPress compatibility, licensing, PKCE S256, discovery, client registration, resource binding, expiry and revocation. This dependency selection is an implementation prerequisite. Require HTTPS and authenticated WordPress administrator consent. Do not trust proxy headers without configured trusted proxies.

## Editable page contract
New pages use native serialized Gutenberg blocks. Core blocks are the baseline; Kadence Blocks is optional when installed and compatible. Do not install or switch a live theme automatically. Do not put ordinary page content in PHP templates, shortcodes or a single opaque HTML block. Heading, paragraph, button, image, group and column blocks must expose normal editor controls. Page copy and images remain editable separately from theme code.

Provide tools to inspect block trees, discover allowed block types and attributes, insert, update, move, duplicate and remove blocks. Use stable revision checks to prevent overwriting concurrent edits. Parse and serialize with WordPress block APIs; validate attributes, nesting and content permissions. Report unsupported blocks without flattening or silently converting them. Do not claim compatibility with Elementor or Divi without dedicated tested adapters.

## Visual workflow
MCP builds and updates native editable layouts; actual mouse-driven drag and drop and screenshot review use separately authorized browser capabilities. The plugin does not grant browser access. Preview links must respect content visibility and expire where access tokens are required. Check desktop and mobile layouts before publishing. Preserve theme and brand choices.

## Authorization and audit
Separate read, content, layout, media, SEO and theme publishing permissions. Check authenticated user capabilities and object permissions on every operation. Bind access tokens to the MCP resource and permissions; support token expiry and revocation. Add correct read-only and destructive tool annotations. Record user, action, object and result without tokens or secrets. Draft creation is the default; changes to published content must be explicit. Existing theme releases and rollback remain available; content changes use revisions with tested restore operations. Never equate a confirmation string with actual user consent.

## Setup
WordPress settings show the MCP URL, connection status, authorization controls, enabled permissions and diagnostics. Guide the user to add the HTTPS endpoint as a custom MCP connection in ChatGPT. Do not require a separate Node deployment or dashboard. Provide an installable plugin ZIP and accurate documentation after checks pass.

## Acceptance tests
1. Install and activate on supported WordPress/PHP versions without the Node app.
2. Complete OAuth discovery, authorization and MCP initialization/tool listing.
3. Reject missing, expired, revoked or wrong-resource credentials and insufficient permissions.
4. Create a draft page with hero, text, image, cards and CTA using native blocks.
5. Reorder and duplicate sections through tools, then open the editor and modify text, images and spacing without code. Save and reopen without invalid-block warnings or content loss.
6. Reject stale revision updates and unsupported builder conversions.
7. Review desktop/mobile output in a browser; publish explicitly and restore a prior content revision.
8. Verify audit entries and regression tests for existing Bridge routes.

## Delivery boundary
A successful CI run is not proof of a working ChatGPT connection. Completion requires an actual authorized ChatGPT-to-WordPress round trip on a test site. No claim of being better than WPVibe until comparable workflows have been verified.
