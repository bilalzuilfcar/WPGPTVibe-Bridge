# Spectra Blocks Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create and update genuine visually editable Spectra Blocks pages over MCP.

**Architecture:** A small versioned adapter supplies safe native descriptors and canonical dynamic block serialization to the existing guarded block engine. Spectra Legacy remains readable but is not rewritten. No editor worker is needed for the verified dynamic Spectra Blocks subset.

**Tech Stack:** WordPress PHP, Spectra Blocks 1.0.11, native Gutenberg, Astra, Playwright.

**Spec:** docs/superpowers/specs/2026-10-16-spectra-visual-builder-design.md

## Global Constraints

Draft first. Preserve old pages. Require current hashes, revisions, existing OAuth and capability gates. No arbitrary HTML/CSS/options. Only explicitly tested builder versions accept writes. Keep plugin independent of any one builder. Run inline as already authorized by Bilal's request to upgrade and try Spectra; do not repeat approval prompts.

## Review Focus

- Leaf blocks with children must be rejected.
- Buttons may only occur within their registered parent.
- Unknown existing settings must survive an unrelated edit.
- Native dynamic containers must accept insertions when empty.
- A stale hash, retry or unsupported version must never overwrite content.

### Task 1: Native adapter and engine integration

Files: includes/class-spectra-adapter.php, includes/class-block-manager.php, includes/class-mcp-tools.php, wpgptvibe-bridge.php, tests/spectra.php.
Interfaces: capabilities(): array; supports(string): bool; create(array, int): array; update(array, array): array; patterns(): array; pattern(string): array.

- [x] Write failing behavioural tests for native container/content/buttons/button creation, update, layout/style validation, missing/unsupported builder, parent rules, empty insertion, existing attributes, leaf children, stale hash and replay.
- [x] Run tests and confirm unsupported native Spectra creation fails.
- [x] Implement the adapter with exact allowlists and preserve parsed settings on updates. Integrate new_block/preview and builder discovery/pattern MCP tools.
- [x] Run the complete PHP harness and syntax checks; commit passing implementation.

### Task 2: Real builder and editor acceptance

Files: tests/spectra-integration.php, tests/spectra-browser.mjs, .github/workflows/ci.yml.

- [x] Install pinned Spectra Blocks and Astra into the disposable CI WordPress site.
- [x] Test create/read/update/preview/publish/revision/retry through actual tool execution and rendering.
- [x] Open the real editor, verify native blocks, edit text, replace native image, duplicate/reorder, save/reopen and check mobile rendering.
- [x] Fix failures, run CI and commit.

### Task 3: Review and package

Files: wordpress-plugin/README.md and release documentation.

- [x] Document exact tested support, setup and Legacy/Astra boundaries.
- [x] Review changes, verify green CI, package an installable upgrade and persist source to the repository.
- [x] Deliver the tested ZIP with concise installation instructions; do not claim a live customer connection.
