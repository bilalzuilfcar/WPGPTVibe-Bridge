# Spectra execution ledger

First integration targets Spectra Blocks 1.0.11. Spectra Legacy pages remain preserved; their attribute adapter is deferred. Astra global/header/footer controls remain in WordPress.

Local behavioural harness: 85 assertions, real WordPress parser/serializer; separate unsupported-version tests and editor registration checks pass. PHP syntax passes.
Fresh review found native background mapping and responsive precedence issues. Both fixed with failing regression tests before implementation. Conflicting advanced responsive styles explicitly require the visual editor, avoiding accepted-but-invisible changes. Invalid five-digit hex and link relation replacement also have passing regressions.

Actual CI integration verified draft creation, replay, previews, native rendering (including container background), existing page preservation, media insertion, revisions/restore and separate publishing. Original OAuth/HTTP MCP and editor tests pass under Astra.

Browser acceptance exposed two separate issues. Vendor first-run redirect was disabled only in the disposable fixture. Generated containers then showed Spectra's layout picker instead of saved children: upstream editor requires variationSelected=true, which its built-in variations also supply. Added a failing regression and set the native flag during creation. No customer site has been modified. Final real-editor run is in progress.

Temporary source checkout was removed by workspace maintenance; recovered latest implementation from the feature branch. No product changes were lost. Test runtime rebuilt from pinned WordPress and official PHP package.

CI 39001787296: actual Spectra text editing, native Media Library replacement, section duplication/reorder and save/reopen all passed with no invalid blocks or JavaScript errors. Mobile overflow assertion passed. Final cards assertion used `.spectra-container`, but the vendor emits `.wp-block-spectra-container`; corrected the test selector based on the controller and WordPress wrapper, with no product code change.
