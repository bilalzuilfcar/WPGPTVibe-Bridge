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

## Advanced editing experience
These additions are authorized by Bilal's request to choose useful advanced features. Deliver them in stages after the direct connection is reliable.

### Page patterns and brand consistency
Provide editor-native starter patterns for hero, services, card grids, FAQ and CTA sections. Use the active site's palette, typography and spacing rather than importing another brand's settings. Patterns are editable block compositions, not opaque HTML. Offer unsynced copies by default and explicit synced patterns for shared content. Before modifying a synced pattern, show the pages affected. Detect theme capabilities before offering navigation, header, footer or global-style editing; classic themes require adapters and must not be presented as Full Site Editing compatible.

### Guided editing
Add a compact WordPress settings screen with Connect ChatGPT, Test connection, Permissions and Recent changes. In the editor, provide clear labels and controls for the supported page sections, duplicate/reorder actions and a preview shortcut. Use existing editor controls first; custom controls must offer keyboard operation and visible focus. Content-only editing is opt-in per template, permits text/link/image updates, and can be disabled by an administrator to unlock layout editing. Do not claim responsive controls exist for every block; expose only supported core or adapter controls.

### Reversible changes and conflict protection
Return a readable summary and changed-block paths for proposed layout updates before applying them. Require an expected revision/content hash for writes. Make destructive block removals and published-content updates explicit. Save revisions when supported and refuse operations that promise revision recovery if revisions are disabled. An undo tool restores a selected revision with a new audit event; it must not overwrite intervening edits silently. Add operation IDs for mutating calls and replay detection so network retries do not duplicate pages or sections.

### Media and content checks
Provide image replacement while preserving attachment IDs, alt-text controls and existing links. Add checks for missing alt text, empty buttons, broken heading order and malformed links. Link checking is limited to registered site URLs with bounded requests and safe redirects. Reports identify issues and suggestions rather than claiming accessibility compliance or automatically rewriting copy. SEO fields continue to use existing providers.

### Honest diagnostics
Show installed editor/theme versions, supported blocks, enabled tools and unsupported builders. Classify connection failures as HTTPS, OAuth, permissions, WordPress REST availability or hosting errors. Show actionable recovery without exposing secrets. Report reconnect requirements after authorization expiry or permission changes.

### Additional acceptance tests
- Insert and edit a starter pattern without block validation warnings.
- Update a shared pattern only after presenting affected pages.
- Duplicate and reorder sections using mouse and keyboard in the editor.
- Update text/image in a content-only template, then unlock its layout as administrator.
- Replay a successful operation ID without duplicate mutations; reject reuse with a different payload.
- Simulate a concurrent human edit and reject the stale MCP write.
- Restore a revision, audit the restore, and refuse false recovery guarantees when revisions are disabled.
- Test empty, loading, permission-denied and disconnected screens.
- Keep plugin assets scoped to its settings/editor surfaces and verify no unnecessary frontend assets.
