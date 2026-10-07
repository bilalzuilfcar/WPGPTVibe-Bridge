import type { ActivityRecord } from '../storage/activity.js';
import type { SiteRecord } from '../storage/sites.js';

function esc(value: unknown): string {
  return String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}

const css = `
:root{color-scheme:dark;--bg:#0b1020;--panel:#12192b;--panel2:#172036;--line:#26324f;--text:#eef3ff;--muted:#9cabca;--accent:#6ea8fe;--good:#61d095;--bad:#ff7b7b}
*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:linear-gradient(180deg,#0b1020 0%,#0d1324 100%);color:var(--text)}
a{color:inherit;text-decoration:none}.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.side{border-right:1px solid var(--line);padding:28px 20px;background:#0b1020;position:sticky;top:0;height:100vh}.brand{font-weight:800;font-size:20px;letter-spacing:-.03em}.sub{color:var(--muted);font-size:12px;margin-top:4px}.nav{margin-top:28px;display:grid;gap:8px}.nav a{padding:10px 12px;border-radius:10px;color:var(--muted)}.nav a:hover,.nav a.active{background:var(--panel2);color:var(--text)}.main{padding:30px;max-width:1400px;width:100%}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px}.title h1{margin:0;font-size:28px}.title p{margin:5px 0 0;color:var(--muted)}.btn{border:1px solid var(--line);background:var(--panel2);color:var(--text);padding:10px 14px;border-radius:10px;cursor:pointer;font-weight:650}.btn.primary{background:var(--accent);border-color:var(--accent);color:#08111f}.btn.danger{background:#35181d;border-color:#703039;color:#ffb7b7}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:18px}.metric{font-size:28px;font-weight:800;margin-top:8px}.muted{color:var(--muted)}.status{display:inline-flex;align-items:center;gap:7px;padding:6px 9px;border-radius:999px;background:#11261e;color:#89e2b0;font-size:12px;font-weight:700}.status.off{background:#2b191b;color:#ffadad}.dot{width:7px;height:7px;border-radius:999px;background:currentColor}.section{margin-top:22px}.section h2{font-size:18px;margin:0 0 12px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:14px}.table{width:100%;border-collapse:collapse;background:var(--panel)}.table th,.table td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--line);font-size:13px;vertical-align:top}.table th{color:var(--muted);font-weight:700}.table tr:last-child td{border-bottom:0}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:12px;color:var(--muted);font-weight:700}.input,textarea{width:100%;background:#0d1426;color:var(--text);border:1px solid var(--line);border-radius:10px;padding:11px 12px;outline:none}.input:focus,textarea:focus{border-color:var(--accent)}.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}.flash{padding:12px 14px;border:1px solid #294a3a;background:#11261e;border-radius:12px;color:#a8e9c4;margin-bottom:16px}.flash.error{border-color:#6d3238;background:#30191d;color:#ffc2c2}.code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;word-break:break-all}.login{min-height:100vh;display:grid;place-items:center;padding:24px}.login-card{width:min(430px,100%);background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:26px}.login-card h1{margin:0 0 6px}.login-card p{margin:0 0 20px;color:var(--muted)}.empty{padding:28px;text-align:center;color:var(--muted)}
@media(max-width:900px){.shell{grid-template-columns:1fr}.side{height:auto;position:relative;border-right:0;border-bottom:1px solid var(--line)}.nav{grid-template-columns:repeat(3,1fr);margin-top:16px}.main{padding:20px}.grid{grid-template-columns:repeat(2,1fr)}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}}
@media(max-width:560px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.nav{grid-template-columns:1fr 1fr}}
`;

function layout(title: string, body: string, active='dashboard'): string {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(title)} · WPGPTVibe</title><style>${css}</style></head><body>
  <div class="shell"><aside class="side"><div class="brand">WPGPTVibe</div><div class="sub">WordPress Control Plane</div><nav class="nav">
  <a class="${active==='dashboard'?'active':''}" href="/admin">Dashboard</a><a class="${active==='sites'?'active':''}" href="/admin/sites">Sites</a><a class="${active==='activity'?'active':''}" href="/admin/activity">Activity</a><a href="/admin/logout">Sign out</a>
  </nav></aside><main class="main">${body}</main></div></body></html>`;
}

export function loginPage(error?: string): string {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>WPGPTVibe Login</title><style>${css}</style></head><body><div class="login"><div class="login-card"><h1>WPGPTVibe</h1><p>Sign in to the WordPress control plane.</p>${error?`<div class="flash error">${esc(error)}</div>`:''}<form method="post" action="/admin/login"><div class="field"><label>Username</label><input class="input" name="username" autocomplete="username" required></div><div class="field" style="margin-top:12px"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required></div><button class="btn primary" style="width:100%;margin-top:16px" type="submit">Sign in</button></form></div></div></body></html>`;
}

function activityTable(activity: ActivityRecord[]): string {
  return `<div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>Operation</th><th>Site</th><th>Status</th><th>Duration</th></tr></thead><tbody>${activity.length?activity.map(a=>`<tr><td>${esc(a.createdAt)}</td><td><strong>${esc(a.operation)}</strong><div class="muted">${esc(a.target||'')}</div></td><td class="code">${esc(a.siteId||'—')}</td><td><span class="status ${a.status==='error'?'off':''}"><span class="dot"></span>${esc(a.status)}</span>${a.errorMessage?`<div class="muted">${esc(a.errorMessage)}</div>`:''}</td><td>${a.durationMs===undefined?'—':esc(a.durationMs)+' ms'}</td></tr>`).join(''):`<tr><td colspan="5" class="empty">No activity recorded yet.</td></tr>`}</tbody></table></div>`;
}

export function dashboardPage(input:{sites: Omit<SiteRecord,'encryptedApiToken'>[];activity: ActivityRecord[];databaseMode:boolean;browserTesting:boolean;}): string {
  const online=input.sites.filter(s=>s.lastSeen).length; const errors=input.activity.filter(a=>a.status==='error').length;
  return layout('Dashboard', `<div class="top"><div class="title"><h1>Control plane</h1><p>Manage connected WordPress sites, MCP activity, releases and operational health.</p></div><a class="btn primary" href="/admin/sites/new">Add site</a></div><div class="grid"><div class="card"><div class="muted">Sites</div><div class="metric">${input.sites.length}</div></div><div class="card"><div class="muted">Seen by MCP</div><div class="metric">${online}</div></div><div class="card"><div class="muted">Recent errors</div><div class="metric">${errors}</div></div><div class="card"><div class="muted">Storage</div><div class="metric" style="font-size:19px">${input.databaseMode?'MySQL':'Encrypted file'}</div><div class="muted" style="margin-top:8px">Browser QA: ${input.browserTesting?'enabled':'disabled'}</div></div></div><div class="section"><h2>Sites</h2>${sitesTable(input.sites)}</div><div class="section"><h2>Recent activity</h2>${activityTable(input.activity.slice(0,10))}</div>`);
}

function sitesTable(sites: Omit<SiteRecord,'encryptedApiToken'>[]): string {
  return `<div class="table-wrap"><table class="table"><thead><tr><th>Site</th><th>Status</th><th>Bridge</th><th>Last seen</th><th></th></tr></thead><tbody>${sites.length?sites.map(s=>`<tr><td><strong>${esc(s.displayName)}</strong><div class="muted">${esc(s.siteUrl)}</div></td><td><span class="status ${s.lastSeen?'':'off'}"><span class="dot"></span>${s.lastSeen?'Connected':'Not tested'}</span></td><td>${esc(s.pluginVersion||'—')}</td><td>${esc(s.lastSeen||'—')}</td><td><a class="btn" href="/admin/sites/${esc(s.siteId)}">Manage</a></td></tr>`).join(''):`<tr><td colspan="5" class="empty">No sites registered yet.</td></tr>`}</tbody></table></div>`;
}

export function sitesPage(sites: Omit<SiteRecord,'encryptedApiToken'>[]): string {
  return layout('Sites', `<div class="top"><div class="title"><h1>Sites</h1><p>Every site has its own encrypted Bridge token and permission profile.</p></div><a class="btn primary" href="/admin/sites/new">Add site</a></div>${sitesTable(sites)}`,'sites');
}

export function siteFormPage(csrf:string,site?:Omit<SiteRecord,'encryptedApiToken'>,message?:string,error?:string): string {
  const editing=Boolean(site);
  return layout(editing?'Manage site':'Add site', `<div class="top"><div class="title"><h1>${editing?'Manage site':'Add WordPress site'}</h1><p>${editing?'Update connection metadata or rotate the stored Bridge token.':'Install WPGPTVibe Bridge first, then enter the Site ID and one-time Bridge token.'}</p></div><a class="btn" href="/admin/sites">Back</a></div>${message?`<div class="flash">${esc(message)}</div>`:''}${error?`<div class="flash error">${esc(error)}</div>`:''}<div class="card"><form method="post" action="${editing?'/admin/sites/'+esc(site!.siteId):'/admin/sites'}"><input type="hidden" name="csrf" value="${esc(csrf)}"><div class="form-grid"><div class="field"><label>Display name</label><input class="input" name="display_name" value="${esc(site?.displayName||'')}" required></div><div class="field"><label>Site ID</label><input class="input code" name="site_id" value="${esc(site?.siteId||'')}" ${editing?'readonly':''} required></div><div class="field full"><label>WordPress URL</label><input class="input" type="url" name="site_url" value="${esc(site?.siteUrl||'')}" placeholder="https://example.com/" required></div><div class="field full"><label>${editing?'New Bridge token (leave blank to keep existing token)':'Bridge API token'}</label><input class="input code" type="password" name="api_token" ${editing?'':'required'} autocomplete="off"><div class="muted">Stored encrypted with AES-256-GCM. It is never displayed again.</div></div></div><div class="actions"><button class="btn primary" type="submit">${editing?'Save changes':'Register site'}</button></div></form></div>${editing?`<div class="section"><h2>Operations</h2><div class="card"><div class="actions"><form method="post" action="/admin/sites/${esc(site!.siteId)}/test"><input type="hidden" name="csrf" value="${esc(csrf)}"><button class="btn" type="submit">Test connection</button></form><form method="post" action="/admin/sites/${esc(site!.siteId)}/delete" onsubmit="return confirm('Remove this site from WPGPTVibe?')"><input type="hidden" name="csrf" value="${esc(csrf)}"><button class="btn danger" type="submit">Remove site</button></form></div></div></div>`:''}`,'sites');
}

export function activityPage(activity: ActivityRecord[]): string {
  return layout('Activity', `<div class="top"><div class="title"><h1>Activity</h1><p>Central MCP and Bridge request history. Secrets are never logged.</p></div></div>${activityTable(activity)}`,'activity');
}

export function siteDetailPage(input:{
  csrf:string;
  site:Omit<SiteRecord,'encryptedApiToken'>;
  info?:any;
  releases?:any[];
  bridgeAudit?:any[];
  message?:string;
  error?:string;
}): string {
  const s=input.site;
  const info=input.info||{};
  const releases=Array.isArray(input.releases)?input.releases:[];
  const bridgeAudit=Array.isArray(input.bridgeAudit)?input.bridgeAudit:[];
  const cap=Array.isArray(info.capabilities)?info.capabilities:[];

  return layout('Manage site', `
  <div class="top"><div class="title"><h1>${esc(s.displayName)}</h1><p>${esc(s.siteUrl)}</p></div><a class="btn" href="/admin/sites">Back to sites</a></div>
  ${input.message?`<div class="flash">${esc(input.message)}</div>`:''}
  ${input.error?`<div class="flash error">${esc(input.error)}</div>`:''}

  <div class="grid">
    <div class="card"><div class="muted">Connection</div><div class="metric" style="font-size:18px">${input.info?'Healthy':'Unavailable'}</div></div>
    <div class="card"><div class="muted">WordPress</div><div class="metric" style="font-size:18px">${esc(info.wordpress_version||'—')}</div></div>
    <div class="card"><div class="muted">Bridge</div><div class="metric" style="font-size:18px">${esc(info.plugin_version||s.pluginVersion||'—')}</div></div>
    <div class="card"><div class="muted">Permissions</div><div class="metric" style="font-size:18px">${cap.length}</div></div>
  </div>

  <div class="section"><h2>Site diagnostics</h2><div class="card"><div class="form-grid">
    <div><div class="muted">Site ID</div><div class="code">${esc(s.siteId)}</div></div>
    <div><div class="muted">Theme</div><div>${esc(info.active_theme?.name||'—')} ${esc(info.active_theme?.version||'')}</div></div>
    <div><div class="muted">PHP</div><div>${esc(info.php_version||'—')}</div></div>
    <div><div class="muted">SEO provider</div><div>${esc(info.seo_provider||'—')}</div></div>
    <div class="field full"><div class="muted">Enabled Bridge capabilities</div><div class="code">${esc(cap.join(', ')||'—')}</div></div>
  </div><div class="actions"><form method="post" action="/admin/sites/${esc(s.siteId)}/test"><input type="hidden" name="csrf" value="${esc(input.csrf)}"><button class="btn primary" type="submit">Test connection</button></form></div></div></div>

  <div class="section"><h2>Theme releases & rollback points</h2><div class="table-wrap"><table class="table"><thead><tr><th>Release</th><th>Published theme</th><th>Previous theme</th><th>Created</th></tr></thead><tbody>
  ${releases.length?releases.map(r=>`<tr><td class="code">${esc(r.release_id||'—')}</td><td>${esc(r.published_stylesheet||'—')}</td><td>${esc(r.previous_stylesheet||'—')}</td><td>${esc(r.created_at||'—')}</td></tr>`).join(''):`<tr><td colspan="4" class="empty">No release records yet.</td></tr>`}
  </tbody></table></div></div>

  <div class="section"><h2>Connection settings</h2><div class="card"><form method="post" action="/admin/sites/${esc(s.siteId)}"><input type="hidden" name="csrf" value="${esc(input.csrf)}">
    <div class="form-grid"><div class="field"><label>Display name</label><input class="input" name="display_name" value="${esc(s.displayName)}" required></div>
    <div class="field"><label>Site ID</label><input class="input code" value="${esc(s.siteId)}" readonly></div>
    <div class="field full"><label>WordPress URL</label><input class="input" type="url" name="site_url" value="${esc(s.siteUrl)}" required></div>
    <div class="field full"><label>Rotate Bridge token</label><input class="input code" type="password" name="api_token" autocomplete="off"><div class="muted">Leave blank to keep the existing encrypted token.</div></div></div>
    <div class="actions"><button class="btn" type="submit">Save settings</button></div></form></div></div>

  <div class="section"><h2>Recent Bridge audit</h2><div class="table-wrap"><table class="table"><thead><tr><th>Time</th><th>Operation</th><th>Target</th><th>Result</th></tr></thead><tbody>
  ${bridgeAudit.length?bridgeAudit.slice(0,25).map(a=>`<tr><td>${esc(a.timestamp||'—')}</td><td>${esc(a.operation||a.op||'—')}</td><td class="code">${esc(a.target||'—')}</td><td>${esc(a.result||'—')}</td></tr>`).join(''):`<tr><td colspan="4" class="empty">No Bridge audit entries available.</td></tr>`}
  </tbody></table></div></div>

  <div class="section"><h2>Danger zone</h2><div class="card"><form method="post" action="/admin/sites/${esc(s.siteId)}/delete" onsubmit="return confirm('Remove this site from WPGPTVibe?')"><input type="hidden" name="csrf" value="${esc(input.csrf)}"><button class="btn danger" type="submit">Remove site</button></form></div></div>
  `,'sites');
}
