# Spectra visual builder integration

Status: proposed design for review. No Spectra compatibility is claimed yet.

## Outcome

Bilal can ask ChatGPT to build or improve pages, then open the same pages in WordPress and edit text, images, buttons, containers, section order and responsive settings visually. Pages must use the installed builder's actual blocks. Pasted HTML and replacement WPGPTVibe blocks do not satisfy this requirement.

The first acceptance page is a Partnerships and Certifications draft on Astra. It contains a hero, an introduction, a responsive credentials grid, a partner section and a contact CTA. Use clearly labelled demonstration credentials until Bilal supplies verified credentials and approved logos. Do not publish demonstration claims.

## Findings from the existing code and upstream

The current block manager creates WPGPTVibe blocks and core paragraphs/headings. It rejects attribute edits on third-party blocks. Moving and duplicating third-party blocks preserves their markup but does not provide full builder integration.

Spectra now has two products:

* Spectra Legacy (`ultimate-addons-for-gutenberg`, blocks under `uagb/`). Its save functions generate static markup, sometimes with source-backed text attributes and nested wrappers. Updating comment attributes alone is insufficient and can invalidate blocks.
* Spectra Blocks (`spectra-blocks`, blocks under `spectra/`). Its schema and rendering differ. For example, the inspected container saves InnerBlocks content and its button stores `text` and `linkURL`.

Research snapshots (not promises about a customer installation):

* Legacy source: https://github.com/brainstormforce/wp-spectra at ea0852c086a63fb85904a80f76751fbf6504233c.
* Blocks source: https://github.com/brainstormforce/wp-spectra-blocks at 1f742decc8457dffc1ff50c299c06145aa00139b.
* Product split: https://wordpress.org/plugins/ultimate-addons-for-gutenberg/ and https://wordpress.org/plugins/spectra-blocks/.

## Approach

Use versioned builder adapters with explicit supported block/attribute lists. Discover the installed plugin, active theme, registered blocks and versions before offering writes. Preserve Legacy pages as Legacy; never silently migrate them. If both builders exist, choose explicitly for each new draft.

First implement and prove one installed builder generation. Expand to the second only after a complete create/edit/save/reopen test passes. Unsupported versions or elements remain readable and are identified in the response; do not claim universal Spectra support.

Prefer the builder's registered JavaScript block serialization for Legacy rather than reconstructing its static markup from guessed PHP templates. A serializer can run inside an authenticated editor tab with the actual installed block registrations. The design must expose this dependency honestly: if a tab is needed it must be open, and a queued request must remain pending until acknowledged. There is no autonomous browser process running inside a PHP plugin.

For Blocks, direct server-side serialization is allowed only for explicitly verified dynamic block types and schemas. Do not generalize one block's save behaviour to all blocks.

## First supported workflow

1. Read builder capabilities and the target page's hash, builder types and editor URL.
2. Build a new draft from supported containers and native text/image/button elements. Cards are editable groups of these elements.
3. Preview an update with its affected block paths and expected hash.
4. Apply through the existing permission, operation replay, revision and transaction guards.
5. Return the editor link, preview link and current hash.
6. Reopen in the visual editor, change text, replace an image, reorder a section, save and reopen again.
7. Publish through the existing separate publishing permission only when instructed.

Supported controls for the proof: text, links, media IDs and alt text, section order, spacing, colours, typography, column count and mobile stacking. Each control must map to the actual installed schema and be visually editable after saving. If a control is not supported for the tested generation, report it explicitly rather than store a private approximation.

## Existing pages and redesigns

Read existing blocks before editing. Preserve attributes and content outside the requested paths. Generate fresh builder identifiers for cloned sections where the builder requires unique identifiers; generic duplication is insufficient when CSS is keyed by an ID.

A redesign starts as a separate draft and retains the original page. Replacing live content uses an explicit live-change confirmation and a current hash. Preserve the existing URL unless Bilal requests a URL change. Do not overwrite Elementor, Divi, shortcodes or unsupported builder content.

## Astra boundary

The first proof uses Astra's inherited styles and page layout. Read theme identity and relevant settings. Astra header/footer controls, global Customizer settings and Pro features are a separate adapter milestone, not implied by successful Spectra page creation. Do not rewrite theme files to simulate visual builder settings.

## Security and consistency

Keep existing OAuth scopes and local capability gates. No executable scripts, arbitrary CSS, arbitrary HTML attributes or unrestricted option writes through block descriptors. Validate text, link protocols, colour values, dimensions, media access, enums, nesting and payload size.

If editor serialization is used, requests must be bound to user, site, page, expected hash and operation ID with expiry. Authenticated nonce-protected callbacks may only acknowledge the matching request. Refuse unsaved local edits, a wrong page, stale hashes or expired jobs. Retry must not duplicate sections or posts. Never interpret queue submission as a saved page.

## Verification and release gate

Test against pinned installable WordPress, Spectra and Astra versions. Record the version matrix in the release notes.

Behavioural coverage: missing builder, unsupported version, unknown attributes, unsafe links, unauthorized writes, stale hash, retry replay, nested insert/update/move/duplicate and unique IDs.

Real editor acceptance: create the proof draft through MCP; open it with the actual builder; verify no invalid/missing blocks; edit text; replace media; duplicate/reorder; save/reopen; inspect desktop and mobile rendering. Also update a pre-existing builder page without changing unrelated content. Test publish and revision restore on disposable content.

Browser checks are concentrated at acceptance and visual review; ordinary supported content operations use MCP. This reduces repeated screenshot-driven work but does not promise zero browser usage or a specific token saving.

Only package an upgrade as builder-compatible after these checks pass. The current 0.4.0 download remains the previous release until then.
