# Internal WordPress MCP product release

## Intent and authority

Bilal asked to inspect the newly added updates and finish an installable internal product that can be added as a ChatGPT MCP app. Prior decisions establish WordPress-hosted OAuth/MCP, native visually editable pages and inline execution. Preserve the newer manual review branch as the current publication policy. No repeated planning approval prompts; choices below are implementation decisions within that authorization.

## Approach

Combine the tested direct-MCP/Spectra branch and feature/manual-review-gate on an isolated integration branch. WordPress remains the server; the optional Node control plane remains available. No new hosting bill or required SaaS account. A public ChatGPT directory listing, billing platform, universal builder support and live customer deployment are outside this internal release.

## Release behaviour

All app/API page edits are draft-only. Publication remains in the ordinary WordPress editor. API status changes, block changes/restores, SEO/meta, batches and calculators must respect the same rule; confirm_live must never bypass it. Existing synced sections require native WordPress editing because theme-file references and concurrent publication cannot be proven absent.

Create an editable draft copy of an accessible published page for redesign. Preserve the original URL, content and status. Copy content and the page template, not arbitrary plugin metadata or credentials. Return original/draft editor and preview links, source hash and review instructions. Do not replace the original or automatically migrate builder formats. Mark copies so the administrator can find them. Stale source hashes must be rejected.

Theme writes are CSS only. Preview and approval compare against the trusted source and reject changed non-CSS files and parent-theme headers before theme execution. Theme publication requires the exact copied release to match a current, one-use, 15-minute WordPress-admin approval. Enforce this below both REST and MCP handlers. Do not expose a tool that approves its own publication. Changed files and revoked administrator rights invalidate approval. Rollback remains separately permission gated.

The setup screen must show MCP URL, connection diagnostics, installed builder support and the draft-only policy. Provide recommended scopes, callback instructions and a short trial prompt. Tests must distinguish disposable automated testing from an actual user ChatGPT connection.

## Verification

Unit regressions for every former bypass and for clone preservation/staleness. Real WordPress/MySQL tests for both transports and existing app checks. Real browser tests for native editing, media replacement, save/reopen, human publishing and final connection diagnostics. Spectra stays version gated to tested versions; Legacy and Astra theme-builder adapters remain explicitly unsupported for API design writes.

## Delivery

Installable versioned ZIP, setup instructions and a source PR on the integration branch. Never claim complete WordPress coverage, a public app listing or a real site connection without verifying it. A live site URL and its authorization are still needed for the final deployment/linking step.
