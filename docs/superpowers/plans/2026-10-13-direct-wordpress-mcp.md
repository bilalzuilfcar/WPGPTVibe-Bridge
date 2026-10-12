# Direct WordPress MCP Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Build an installable plugin with a direct ChatGPT connection and easily editable WordPress pages.

**Architecture:** Reuse existing Bridge managers; add separate block editing, OAuth storage, MCP transport and editor modules. The direct connection runs inside WordPress with administrator consent and scoped tokens.

**Tech Stack:** PHP 8.1+, WordPress 6.4+, bundled MIT bshaffer/oauth2-server-php v1.14.2, WordPress editor packages supplied by WordPress.

**Spec:** ../specs/2026-10-13-direct-wordpress-mcp-design.md

## Global Constraints
- No required Node deployment; preserve existing Bridge compatibility.
- HTTPS, administrator consent, object-level authorization and expiring/revocable credentials.
- Native blocks and editable patterns; no silent conversion of unsupported builders.
- Drafts by default, explicit live changes, revision hashes and operation replay protection.
- No live connection claim without a ChatGPT-to-WordPress round trip.

## Review Focus
- Concurrent edits must never silently overwrite saved human work.
- A retry must not create a second page or repeat a mutation.
- Callback URLs must match the registered HTTPS client exactly.
- Unsupported editor content must remain intact.
- Content-only mode must preserve editing of text and images while protecting layout.

### Task 1: Editable page engine
**Files:** includes/class-block-manager.php, includes/class-patterns.php, tests/blocks.php, assets/editor.js.
**Interfaces:** get(id), create(args), change(id, expected_hash, operations, confirm_live), revisions(id), restore(id, revision_id, expected_hash, confirm_live).
- [ ] Write and run failing tests for stale hashes, nested changes, duplicate/move/remove, unsupported blocks and disabled revisions.
- [ ] Implement block tree operations using parse_blocks/serialize_blocks and a per-post database lock.
- [ ] Provide native editable starter patterns and scoped editor assets.
- [ ] Run the PHP tests and editor serialization tests; commit.

### Task 2: OAuth connection
**Files:** includes/class-oauth-storage.php, includes/class-oauth.php, vendor/oauth2-server-php/, tests/oauth.php.
**Interfaces:** routes(), authenticate(request), metadata(), authorize(), token(), revoke().
- [ ] Test PKCE S256, exact redirects, expired/revoked tokens, wrong resource and code replay.
- [ ] Bundle and isolate the OAuth library with license/provenance; implement durable WordPress storage.
- [ ] Add discovery, registration, administrator consent, token exchange and revocation. Only authorization-code and refresh grants are supported.
- [ ] Run tests; commit.

### Task 3: Direct MCP tools
**Files:** includes/class-mcp.php, includes/class-mcp-tools.php, includes/class-operation-store.php, tests/mcp.php.
**Interfaces:** dispatch(message, identity), catalog(identity), execute(name, arguments, identity).
- [ ] Test initialize/list/call, notification responses, malformed requests, scope denial and operation replay.
- [ ] Implement stateless Streamable HTTP JSON responses and correctly annotated tools.
- [ ] Bind every call to the authenticated WordPress user, enabled Bridge capabilities and object permissions.
- [ ] Run tests; commit.

### Task 4: Setup and usability
**Files:** admin/class-admin.php, assets/editor.js, includes/class-patterns.php, README.md.
- [ ] Add MCP URL, HTTPS/REST/editor diagnostics, authorization controls and recent activity.
- [ ] Add pattern chooser, content-only toggle and revision/preview guidance using native WordPress controls.
- [ ] Verify keyboard access, no frontend asset loading, and unsupported-builder notices.
- [ ] Document installation and test-site acceptance; commit.

### Task 5: Release verification
**Files:** .github/workflows/ci.yml, wordpress-plugin/tests/, plugin version header.
- [ ] Run PHP syntax and behavioral tests plus existing Node tests/check/build.
- [ ] Review security boundaries and fix any important findings with regression tests.
- [ ] Package plugin ZIP; publish development branch and pull request.
- [ ] Keep production publishing and live test-site acceptance visibly pending until verified.
