# WPGPTVibe Bridge 0.6.0

Internal WordPress MCP connection for ChatGPT, with native visually editable pages and human publication review. WordPress hosts the MCP endpoint and OAuth authorization; the Node application is optional.

## Connect and use

1. Install the plugin ZIP in WordPress and activate it.
2. Open **Tools > WPGPTVibe**. Enable only the capabilities you need and copy the displayed MCP URL.
3. Add that URL as a custom MCP connection in ChatGPT, select OAuth, and authorize your WordPress account.
4. Ask for a draft page, inspect it in the WordPress visual editor, and publish there after review.

See [connection guide](docs/CONNECT-CHATGPT.md) and [plugin documentation](wordpress-plugin/README.md).

## Supported workflow

- Native Gutenberg sections and supported Spectra Blocks remain editable in WordPress without coding.
- Detect builder capabilities before editing. Tested Spectra Blocks version: **1.0.11**; Astra: **4.14.1**. Legacy Spectra/UAG and arbitrary third-party builders are not automatically supported.
- Copy an existing page into a separate redesign draft, preserving the live original. Copies preserve native content and page template, not arbitrary plugin metadata.
- Edit drafts with current-content hashes, operation replay protection, revisions, and audit records.
- Submit drafts to the WordPress review queue with editor and preview links. Publication happens in WordPress.
- Create new synced sections and insert references into draft pages. Existing synced sections must be edited in WordPress because theme-file and concurrent publication references cannot be proven absent.
- Theme API changes are **CSS only**. Preview, approval and activation reject changed executable files or parent-theme headers. An administrator approves an exact theme fingerprint for one use within 15 minutes.

## Boundaries

This is an internal product candidate, not a public ChatGPT directory listing. Full Astra header/footer settings, unsupported builders, existing synced section edits and executable theme changes require native WordPress work or a future staging system. A redesign copy does not automatically replace the original page or its URL.

No separate Node hosting or OpenAI API key is required for the direct connection. ChatGPT account/workspace access to custom MCP connections is still required.

## Repository and validation

- `wordpress-plugin/`: installable plugin and disposable WordPress integration/browser tests.
- `mcp-server/`: optional central multi-site application.
- `docs/`: connection instructions, design and implementation records.

GitHub Actions runs PHP behavior tests, real WordPress/MySQL integration, OAuth/MCP checks, Spectra visual editing and manual publication browser checks, plus the optional application's tests and build. Never commit production credentials.
