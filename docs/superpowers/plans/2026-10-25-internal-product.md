# Internal WordPress MCP Product Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline and request one final fresh review.

**Goal:** Deliver one installable plugin combining native visual pages, direct ChatGPT MCP and the latest manual publishing policy.

**Architecture:** Integrate both branches on feat/internal-product-v060. Enforce policy in managers shared by REST/MCP. Draft-copy tools make protected existing pages editable without touching originals.

**Tech Stack:** WordPress/PHP, OAuth2, MCP Streamable HTTP, Spectra Blocks, Playwright, MySQL.

**Spec:** docs/superpowers/specs/2026-10-25-internal-product-design.md

## Global Constraints

Draft-only content operations. WordPress-admin publication. No required Node hosting. Preserve Spectra source/version gates. No customer-site deployment or directory publication assumed.

## Review Focus

- MCP direct-manager calls cannot bypass the new REST policy.
- Existing public synced sections cannot change live pages through references.
- Theme approval applies to the immutable copied release and is consumed once.
- Draft copies preserve originals and reject stale source hashes.
- Existing native editor saves/publishing still work normally.

### Task 1: Integrate policy

Files: includes/class-review-gate.php, class-content-manager.php, class-block-manager.php, class-rest.php, class-mcp-tools.php, class-theme-manager.php, admin/class-admin.php, tests/review.php.

- [x] Write/run failing tests for draft-only block/MCP publication, SEO/meta, shared references and theme approval.
- [x] Merge existing manual-review changes, resolve conflicts without dropping direct MCP/Spectra.
- [x] Enforce the review rules in shared managers and verify passing behavioural tests.

### Task 2: Usable redesign and setup

Files: new draft-copy manager, MCP definitions, admin setup, README and connection guide.

- [x] Write/run failing tests for copy creation, source immutability, stale hashes and restricted metadata.
- [x] Implement copy_page_for_review and review links under existing scopes, permissions and operation replay.
- [x] Show setup/readiness and manual-review instructions without requiring code from the user.

### Task 3: Acceptance and release

Files: real WordPress and Playwright tests, CI, release docs.

- [x] Update integration assertions to expect blocked API publication and preserved originals; test ordinary WordPress publication.
- [x] Run complete local/CI checks, browser editing and one independent review.
- [x] Package v0.6.0 in CI, publish source in draft PR #3 and provide connection steps. Direct file delivery was blocked by environment loss; the tested GitHub Actions artifact is available. Actual site installation/linking requires the target URL.
