/**
 * "Check all connections": which of the owner's outside services this Claude
 * session (or a teammate's) can reach, read-only.
 *   node tools/check-connections.mjs
 *
 * Read-only: every call is a harmless "who am I / what can I see" read. It
 * prints whether each credential is present by NAME only; a value is never
 * printed, and any value that turns up inside an error message is masked.
 *
 * Where the credentials come from (owner's notes, 13 Oct): the cloud
 * environment's settings (title bar → environment → Edit), read when a
 * session starts.
 *   YOUTUBE_API_KEY  either an environment variable, or a Network secret the
 *                    proxy adds as X-Goog-Api-Key for youtube.googleapis.com
 *                    (then it is invisible here, and a keyless call works)
 *   YT_CLIENT_ID, YT_CLIENT_SECRET, YT_REFRESH_TOKEN
 *                    one Google sign-in for YouTube, Search Console,
 *                    Analytics and Business Profile; what it may do is the
 *                    scopes granted in the OAuth Playground
 *   META_ACCESS_TOKEN, META_PAGE_ID, IG_BUSINESS_ID
 *                    Facebook Page and Instagram by the Graph API; needs
 *                    graph.facebook.com allowed in the environment's network
 *                    settings
 * Connectors (claude.ai/customize/connectors) cannot be reached from a script:
 * the session lists its own tools for those.
 *
 * The API addresses can be pointed elsewhere for the test
 * (tools/test-check-connections.mjs): CC_GOOGLE, CC_YT_KEYLESS, CC_OAUTH,
 * CC_META, CC_GA, CC_GBP.
 */
const SITE = 'theartframer.us';
const SITE_CHANNEL = 'UC_GX4vXRQrN4GsvSfgmZxYw'; // the channel functions.php already reads
const GOOGLE = process.env.CC_GOOGLE || 'https://www.googleapis.com';
const YT_KEYLESS = process.env.CC_YT_KEYLESS || 'https://youtube.googleapis.com';
const OAUTH = process.env.CC_OAUTH || 'https://oauth2.googleapis.com/token';
const META = process.env.CC_META || 'https://graph.facebook.com/v21.0';
const GA = process.env.CC_GA || 'https://analyticsadmin.googleapis.com/v1beta';
const GBP = process.env.CC_GBP || 'https://mybusinessaccountmanagement.googleapis.com/v1';

const env = (k) => String(process.env[k] || '').trim();
const SECRETS = ['YOUTUBE_API_KEY', 'YT_CLIENT_ID', 'YT_CLIENT_SECRET', 'YT_REFRESH_TOKEN', 'META_ACCESS_TOKEN'].map(env).filter(v => v.length >= 6);
const mask = (s) => { let t = String(s); for (const v of SECRETS) t = t.split(v).join('[hidden]'); return t.slice(0, 220); };

const rows = [];
const row = (service, state, detail = '') => { rows.push({ service, state, detail: mask(detail) }); };
const has = (k) => env(k) !== '';

async function get(url, headers = {}) {
  try {
    const r = await fetch(url, { headers, signal: AbortSignal.timeout(20000) });
    let j = null; const text = await r.text(); try { j = JSON.parse(text); } catch (e) {}
    return { status: r.status, j, text };
  } catch (e) {
    return { status: 0, error: (e.cause && (e.cause.code || e.cause.message)) || e.message };
  }
}
const apiError = (r) => r.status === 0 ? `cannot reach it (${r.error})` : `HTTP ${r.status}: ${(r.j && r.j.error && (r.j.error.message || r.j.error.error_description || r.j.error)) || r.text.slice(0, 120)}`;

/* ── YouTube by API key ─────────────────────────────────────────────── */
async function youtubeKey() {
  const q = `/youtube/v3/channels?part=snippet,statistics&id=${SITE_CHANNEL}`;
  let r, how;
  if (has('YOUTUBE_API_KEY')) { r = await get(`${GOOGLE}${q}&key=${encodeURIComponent(env('YOUTUBE_API_KEY'))}`); how = 'environment variable'; }
  else { r = await get(`${YT_KEYLESS}${q}`); how = 'network secret'; }
  const ch = r.j && r.j.items && r.j.items[0];
  if (r.status === 200 && ch) row('YouTube (API key)', 'connected', `${how}; channel "${ch.snippet.title}": ${ch.statistics.subscriberCount ?? '?'} subscribers, ${ch.statistics.videoCount} videos`);
  else if (how === 'network secret' && (r.status === 403 || r.status === 400)) row('YouTube (API key)', 'not set', 'add YOUTUBE_API_KEY as a Network secret (host youtube.googleapis.com, header X-Goog-Api-Key) or an environment variable');
  else row('YouTube (API key)', 'failed', `${how}: ${apiError(r)}`);
}

/* ── Google sign-in: YouTube, Search Console, Analytics, Business Profile */
async function google() {
  const need = ['YT_CLIENT_ID', 'YT_CLIENT_SECRET', 'YT_REFRESH_TOKEN'];
  const missing = need.filter(k => !has(k));
  if (missing.length === need.length) { row('Google sign-in', 'not set', `add ${need.join(', ')} to the environment`); return; }
  if (missing.length) { row('Google sign-in', 'not set', `missing ${missing.join(', ')}`); return; }
  let tok;
  try {
    const r = await fetch(OAUTH, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, signal: AbortSignal.timeout(20000),
      body: new URLSearchParams({ grant_type: 'refresh_token', client_id: env('YT_CLIENT_ID'), client_secret: env('YT_CLIENT_SECRET'), refresh_token: env('YT_REFRESH_TOKEN') }) });
    tok = await r.json().catch(() => ({}));
    if (!r.ok || !tok.access_token) { row('Google sign-in', 'failed', `${r.status}: ${tok.error || ''} ${tok.error_description || ''}`.trim() + (tok.error === 'invalid_grant' ? ' (the refresh token was revoked or expired: run the OAuth Playground step again)' : '')); return; }
  } catch (e) { row('Google sign-in', 'failed', `cannot reach Google's sign-in (${e.cause?.code || e.message})`); return; }
  const scopes = String(tok.scope || '').split(/\s+/).filter(Boolean);
  const short = scopes.map(s => s.replace('https://www.googleapis.com/auth/', ''));
  row('Google sign-in', 'connected', `scopes granted: ${short.join(', ') || '(none listed)'}`);
  const H = { Authorization: `Bearer ${tok.access_token}` };
  const granted = (name) => short.some(s => s === name || s.startsWith(name + '.') || (name === 'youtube' && s.startsWith('youtube')));

  if (granted('youtube')) {
    const r = await get(`${GOOGLE}/youtube/v3/channels?part=snippet&mine=true`, H);
    const list = (r.j && r.j.items) || [];
    if (r.status !== 200) row('YouTube (signed in)', 'failed', apiError(r));
    else if (!list.length) row('YouTube (signed in)', 'no channel', 'this Google account manages no channel; the site channel needs the account added as a Brand Account manager');
    else row('YouTube (signed in)', list.some(c => c.id === SITE_CHANNEL) ? 'connected' : 'other channel', list.map(c => `"${c.snippet.title}" (${c.id === SITE_CHANNEL ? 'the site channel' : c.id})`).join(', '));
  } else row('YouTube (signed in)', 'not granted', 'scope youtube was not granted in the OAuth Playground');

  if (granted('webmasters')) {
    const r = await get(`${GOOGLE}/webmasters/v3/sites`, H);
    const sites = (r.j && r.j.siteEntry) || [];
    const ours = sites.filter(s => String(s.siteUrl).includes(SITE));
    if (r.status !== 200) row('Search Console', 'failed', apiError(r));
    else if (ours.length) row('Search Console', 'connected', ours.map(s => `${s.siteUrl} (${s.permissionLevel})`).join(', '));
    else row('Search Console', 'no access to the site', `this account sees ${sites.length} other propert${sites.length === 1 ? 'y' : 'ies'}; add it as a user of ${SITE} in Search Console`);
  } else row('Search Console', 'not granted', 'scope webmasters was not granted');

  if (granted('analytics')) {
    const r = await get(`${GA}/accountSummaries`, H);
    const props = ((r.j && r.j.accountSummaries) || []).flatMap(a => (a.propertySummaries || []).map(p => p.displayName));
    if (r.status !== 200) row('Google Analytics', 'failed', apiError(r));
    else row('Google Analytics', props.length ? 'connected' : 'no access', props.length ? `properties: ${props.join(', ')}` : 'this account sees no Analytics property');
  } else row('Google Analytics', 'not granted', 'scope analytics.readonly was not granted');

  if (granted('business.manage')) {
    const r = await get(`${GBP}/accounts`, H);
    if (r.status === 200) row('Business Profile', 'connected', `${((r.j && r.j.accounts) || []).length} account(s)`);
    else if (r.status === 403 || r.status === 429) row('Business Profile', 'waiting on Google', `${apiError(r)} — the API stays closed until Google approves the project's access request`);
    else row('Business Profile', 'failed', apiError(r));
  } else row('Business Profile', 'not granted', 'scope business.manage was not granted');
}

/* ── Facebook and Instagram by the Graph API ────────────────────────── */
async function meta() {
  const reach = await get(`${META}/`);
  const isBlocked = reach.status === 0;
  if (!has('META_ACCESS_TOKEN')) {
    row('Facebook / Instagram (API)', 'not set', 'add META_ACCESS_TOKEN, META_PAGE_ID, IG_BUSINESS_ID to the environment' + (isBlocked ? ', and allow graph.facebook.com in its network settings (blocked here today)' : ''));
    return;
  }
  if (isBlocked) { row('Facebook / Instagram (API)', 'blocked', `graph.facebook.com is not reachable (${reach.error}): allow it in the environment's network settings`); return; }
  const T = `access_token=${encodeURIComponent(env('META_ACCESS_TOKEN'))}`;
  const me = await get(`${META}/me?fields=id,name&${T}`);
  if (me.status !== 200) { row('Facebook (token)', 'failed', apiError(me) + (me.j?.error?.code === 190 ? ' (the token expired or was revoked: make a new one)' : '')); return; }
  row('Facebook (token)', 'connected', `token belongs to "${me.j.name}"`);
  if (has('META_PAGE_ID')) {
    const p = await get(`${META}/${encodeURIComponent(env('META_PAGE_ID'))}?fields=name,followers_count,instagram_business_account&${T}`);
    p.status === 200 ? row('Facebook Page', 'connected', `"${p.j.name}", ${p.j.followers_count ?? '?'} followers${p.j.instagram_business_account ? ', linked Instagram ' + p.j.instagram_business_account.id : ''}`) : row('Facebook Page', 'failed', apiError(p));
  } else row('Facebook Page', 'not set', 'add META_PAGE_ID');
  if (has('IG_BUSINESS_ID')) {
    const i = await get(`${META}/${encodeURIComponent(env('IG_BUSINESS_ID'))}?fields=username,followers_count,media_count&${T}`);
    i.status === 200 ? row('Instagram', 'connected', `@${i.j.username}, ${i.j.followers_count ?? '?'} followers, ${i.j.media_count ?? '?'} posts`) : row('Instagram', 'failed', apiError(i));
  } else row('Instagram', 'not set', 'add IG_BUSINESS_ID');
}

await youtubeKey();
await google();
await meta();
row('Connectors (MCP)', 'see the session', 'connectors added at claude.ai/customize/connectors appear as tools in sessions started after they were added; a script cannot see them');

const w = Math.max(...rows.map(r => r.service.length));
console.log(`=== connections, ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
for (const r of rows) console.log(`${r.service.padEnd(w)}  ${r.state.padEnd(20)}  ${r.detail}`);
const ok = rows.filter(r => r.state === 'connected').length;
console.log(`=== ${ok} connected`);
