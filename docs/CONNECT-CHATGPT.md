# Connect WPGPTVibe Bridge to ChatGPT

## WordPress

Install and activate the 0.6.0 ZIP using Plugins > Add New > Upload Plugin. Use a test site first. Open Tools > WPGPTVibe, enable the required read/content editing capabilities and copy the MCP URL displayed there. Use a public HTTPS site whose WordPress REST and OAuth routes are reachable.

## ChatGPT

Open the custom MCP connection setup available to your account/workspace, enter the displayed MCP URL, and choose OAuth. This plugin supports dynamic client registration; leave optional static client credentials blank. Authorize through your own WordPress login and consent screen. Install/select the resulting connection and refresh its tools after upgrades. Interface labels and access can vary by workspace.

WordPress hosts both OAuth and MCP. You do not need to deploy the optional Node server or add an OpenAI API key.

## First trial

Ask: “Inspect the site and builder capabilities. Create an unpublished partnerships page using supported native Spectra blocks, then return its WordPress editor and preview links. Submit it for my review.”

Open the editor, change a heading and move a section using the native controls. Preview desktop and mobile, then publish in WordPress when ready.

For redesigns: “Copy page [ID] for review, preserve the original, redesign the copy with supported blocks, and submit it for review.” The copy does not automatically replace the original URL.

## Current boundaries

API edits are limited to draft/pending content. Existing synced sections require WordPress editing; usage reports exclude theme files. Theme writes are CSS only. Existing executable theme changes cannot be previewed or approved through the Bridge. Astra global settings and unsupported builders still require native editor work.

If a connection fails, check HTTPS, REST access, enabled Bridge capabilities, WordPress user permissions and OAuth consent. Do not send passwords or tokens in chat.
