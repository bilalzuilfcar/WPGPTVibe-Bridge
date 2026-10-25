# WPGPTVibe Bridge 0.5.0

A WordPress plugin for connecting a website directly to ChatGPT through MCP. A separate Node application is optional.

## Install and connect

1. Upload `wpgptvibe-bridge-0.5.0.zip` in WordPress → Plugins → Add Plugin → Upload Plugin, then activate it.
2. Open Tools → WPGPTVibe. Check HTTPS and the two discovery links.
3. In ChatGPT Plugins, choose Add custom MCP server. Copy the MCP URL from WordPress, select OAuth and dynamic client registration (DCR).
4. Use `site:read pages:read pages:write layout:write media:write seo:write` for ordinary page work. Add `pages:publish` only when publication is required.
5. Sign in to WordPress and review the requested permissions. If ChatGPT shows a different callback URL, copy that exact HTTPS URL into the plugin's approved callbacks, then reconnect.
6. Enable Read Content, Manage Content and Edit Layout. Enable media, SEO and recovery capabilities as needed. Publishing and theme permissions remain separate.
7. Ask ChatGPT to inspect the website, create a draft from a pattern, and return the WordPress editor link.

New connections use OAuth. The existing API token controls are for the optional older Node application, not for this direct ChatGPT connection.

## Edit without code

Open Pages → Edit. Click headings or body text to rewrite them. Select an image and choose Replace image. Use the block toolbar or List View to move, duplicate or remove sections. The section sidebar includes spacing, colours and typography sizes; zero font size uses the active theme's default.

The inserter contains five editable patterns: hero, services, four cards, FAQs and a call to action. Generated containers are native Gutenberg plugin blocks with editable fields. Core paragraphs and headings can also be created and updated; other native or third-party blocks retain their original markup during reordering.

“Protect section layouts” keeps supported content editable while protecting the layout. An administrator can turn protection off to restructure the page. Save current edits before asking ChatGPT to modify the same page.

The plugin must remain active to render its dynamic sections. Deactivation preserves their stored content, but does not convert them into static HTML.

## Native Spectra pages (0.5.0)

Install and activate **Spectra Blocks 1.0.11** (`spectra-blocks`) separately. It is different from **Spectra Legacy** (`ultimate-addons-for-gutenberg`, `uagb/` blocks). Keep Legacy active for existing Legacy pages. This release does not migrate them. Other Spectra Blocks versions are detected but writes are refused until tested.

Ask ChatGPT to call `get_builder_capabilities`, then `list_spectra_patterns` or `create_spectra_page`. The Partnerships and Certifications starter is a demonstration draft: replace examples with verified credentials and approved logos before publishing.

Pages use Spectra's actual Container, Text, Buttons and Button blocks, plus WordPress Image blocks. Open the returned editor link to edit text, replace images through the Media Library, move/duplicate sections and change builder settings. Supported MCP updates include text, links, spacing, colours, font settings, container dimensions and grid/wrapping layouts. Advanced per-device controls remain in the visual editor. If an existing page has responsive overrides or authoritative dimension settings, conflicting design edits are refused with a visual-editor instruction rather than silently ignored; text and link edits remain available. Container background colours use Spectra's native `backgroundColor` field. Images can be inserted from accessible media IDs; replace them visually or use remove/insert operations. The adapter does not rewrite arbitrary image markup.

Ordinary supported MCP edits do not require an open editor tab or screenshot for each action. Save local editor changes before requesting MCP edits, then reload the editor afterwards. Visual review still requires browser access.

Astra is compatible with these page elements; its global settings and header/footer builder are not exposed by this adapter. No theme is installed or switched on your website automatically. Spectra must remain active to render Spectra's dynamic blocks; WPGPTVibe is required for its own blocks and the connection, not for rendering native Spectra elements.

Test matrix: WordPress 7.1.3, PHP 8.3, Spectra Blocks 1.0.11 and Astra 4.14.1 (disposable MySQL site in CI). Do not interpret this as testing every WordPress/PHP/theme combination. Spectra requires WordPress 6.6 or newer.

## Shared sections and recovery

Synced patterns can be reused across pages. Read the usage report before updating one: the plugin requires the affected-page hash as well as the pattern's content hash. The report includes indirect references, templates, navigation and block widgets. It refuses to claim a complete impact report on sites exceeding its 500-item scan limit.

Mutations use operation IDs. Identical retries return the original result for seven days; conflicting or uncertain retries fail safely. Page edits require a current content hash and enabled WordPress revisions. Publishing, shared changes and restoring live content require explicit confirmation. Theme drafts/releases retain the previous Bridge workflow.

## Requirements and boundaries

- WordPress 6.4+, PHP 8.1+, HTTPS, WordPress Block Editor and MySQL/InnoDB.
- OAuth metadata and WordPress REST requests must be reachable through the host's firewall/cache configuration.
- Elementor, Divi and WPBakery content is preserved; those visual builders need separate adapters.
- Headers and footers use the active theme's WordPress controls. This release does not expose universal header/footer or navigation builder tools.
- Native WordPress theme colours and fonts remain the default. No theme is switched automatically.
- The direct endpoint exposes page, shared section, media, SEO and draft theme tools. The older REST Bridge retains its additional metadata, calculator, cache and allowlisted WP-CLI operations.

## Security

OAuth uses a bundled, namespaced MIT OAuth library with S256 PKCE, exact redirect matching, resource binding, single-use codes, rotating refresh tokens and revocation. Only administrators can authorize connections. Tool scopes, local Bridge capabilities and WordPress object permissions are checked on every call. Tokens are stored under hashed transient keys with expiry; operation history is non-autoloaded durable storage. No anonymous website tools, arbitrary SQL, shell commands or PHP eval are exposed.

Disconnect or reset registrations from Tools → WPGPTVibe. Callback changes invalidate existing sessions. Source code contains no production credentials.

## Verification

CI runs PHP syntax and behavioral tests, WordPress/MySQL integration, editor registration and browser acceptance. A passing CI build does not prove that a real ChatGPT account is linked to a deployed website. Verify that final connection on a test site before using production publishing tools.

## 0.6.0 internal product safety boundaries

Existing synced sections require native WordPress editing. Usage reports cover database content and block widgets, excluding theme files. Theme API writes/deletes are CSS only; changed executable files and changed parent headers block preview, approval and activation. See [connection guide](../docs/CONNECT-CHATGPT.md).
