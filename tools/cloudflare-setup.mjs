/**
 * theartframer.us behind Cloudflare: the security settings, applied and
 * checked through Cloudflare's API (security audit, 12 Oct, Part B).
 *
 *   node tools/cloudflare-setup.mjs audit   reads everything, changes nothing
 *   node tools/cloudflare-setup.mjs apply   sets what differs, then audits
 *
 * env:
 *   CLOUDFLARE_API_TOKEN  the owner's token (GitHub secret). Without it,
 *                         audit runs the checks that need no token: the
 *                         origin's certificate and answer, and Cloudflare's
 *                         address list against functions.php.
 *   APPLY_DNS=1           allow DNS changes on a zone that is already active.
 *                         Without it, DNS is only written while the zone is
 *                         still pending (before the nameservers change), when
 *                         nothing is live yet.
 *   CF_API, CF_IPS_URL, CF_ORIGIN_CHECK=0, CF_FUNCTIONS_PHP: for the test
 *                         (tools/test-cloudflare-setup.mjs), which runs this
 *                         against a local stand-in for the API.
 *
 * What is ours is recognised by the rule description starting "AF:" (and its
 * ref). Rules anybody adds by hand in the dashboard are kept as they are.
 * Every phase is written in one request, so a phase is either fully updated
 * or untouched; a refusal (bad expression, missing permission) is printed and
 * the run stops before the next phase.
 *
 * Plan facts (Cloudflare docs, read 12 Oct): Free allows 5 custom rules
 * without regex, 1 rate limiting rule whose expression may use the path only
 * (10 s period and timeout), and the Free Managed Ruleset.
 */
import fs from 'node:fs';
import tls from 'node:tls';
import https from 'node:https';

const MODE = process.argv[2] === 'apply' ? 'apply' : 'audit';
let WRITE = MODE === 'apply'; // false again for the re-check after writing
const API = process.env.CF_API || 'https://api.cloudflare.com/client/v4';
const TOKEN = (process.env.CLOUDFLARE_API_TOKEN || '').trim();
const DOMAIN = 'theartframer.us';
const WWW = 'www.' + DOMAIN;
const ORIGIN = '82.197.82.189';
const T = '/wp-content/themes/postero-child/';

let problems = 0;
const say = (s) => console.log(s);
const ok = (s) => say('  ok    ' + s);
const bad = (s) => { problems++; say('  FIX   ' + s); };
const info = (s) => say('  info  ' + s);

/* ── what we want ───────────────────────────────────────────────────── */

const SETTINGS = {
  ssl: 'strict',                     // Full (strict): the origin's certificate is checked
  always_use_https: 'on',
  automatic_https_rewrites: 'on',
  min_tls_version: '1.2',
  tls_1_3: 'on',
  rocket_loader: 'off',              // nothing rewrites the shop's scripts
  email_obfuscation: 'off',          // ... or its HTML
};

const p = 'http.request.uri.path';
const RULES = {
  http_request_firewall_custom: [
    { ref: 'af_block_xmlrpc', description: 'AF: block xmlrpc.php', action: 'block',
      expression: `(${p} eq "/xmlrpc.php")` },
    { ref: 'af_block_private', description: 'AF: block private files and folders', action: 'block',
      expression: [
        `starts_with(${p}, "${T}tools/")`,
        `starts_with(${p}, "${T}ops/")`,
        `ends_with(lower(${p}), ".log")`,
        `ends_with(lower(${p}), ".sql")`,
        `ends_with(lower(${p}), ".bak")`,
        `(starts_with(${p}, "/wp-content/uploads/") and ends_with(lower(${p}), ".php"))`,
        // hidden files and folders (.git, .env, .htaccess ...), but never
        // /.well-known/, which certificate renewal uses
        `(${p} contains "/." and not starts_with(${p}, "/.well-known/"))`,
        `(lower(${p}) contains "wp-config")`,
        `(${p} in {"/readme.html" "/license.txt"})`,
      ].join(' or ') },
    { ref: 'af_block_plugin_php', description: 'AF: no direct PHP in plugin folders except the active plugins', action: 'block',
      expression: `(starts_with(${p}, "/wp-content/plugins/") and ends_with(lower(${p}), ".php")` +
        ['woocommerce', 'woocommerce-square', 'elementor', 'litespeed-cache'].map(d => ` and not starts_with(${p}, "/wp-content/plugins/${d}/")`).join('') + ')' },
    { ref: 'af_image_guard', description: 'AF: pictures are not handed out as files (mirrors tools/protect-images.php)', action: 'block',
      expression: `(starts_with(${p}, "/wp-content/uploads/") and not starts_with(${p}, "/wp-content/uploads/woocommerce_uploads/")` +
        ` and http.request.uri.path.extension in {"jpg" "jpeg" "png" "gif" "webp" "avif" "bmp" "tif" "tiff" "svg"}` +
        ` and not cf.client.bot` +
        ` and (any(http.request.headers["sec-fetch-dest"][*] eq "document")` +
        ` or (not any(http.request.headers["sec-fetch-dest"][*] eq "image") and http.referer ne ""` +
        ` and not starts_with(http.referer, "https://${DOMAIN}/") and not starts_with(http.referer, "https://${WWW}/"))))` },
  ],
  http_ratelimit: [
    { ref: 'af_rl_login', description: 'AF: login and sign-up pages, 10 requests per 10 s per address', action: 'block',
      expression: `(${p} in {"/wp-login.php" "/my-account/" "/my-account/lost-password/" "/login/" "/sign-up/" "/wp-comments-post.php"})`,
      ratelimit: { characteristics: ['ip.src', 'cf.colo.id'], period: 10, requests_per_period: 10, mitigation_timeout: 10 } },
  ],
  http_response_headers_transform: [
    { ref: 'af_security_headers', description: 'AF: security headers on every response', action: 'rewrite', expression: 'true',
      action_parameters: { headers: {
        'X-Content-Type-Options': { operation: 'set', value: 'nosniff' },
        'X-Frame-Options': { operation: 'set', value: 'SAMEORIGIN' },
        'Referrer-Policy': { operation: 'set', value: 'strict-origin-when-cross-origin' },
        'Permissions-Policy': { operation: 'set', value: 'geolocation=(), camera=(self), microphone=()' },
      } } },
  ],
  http_request_dynamic_redirect: [
    { ref: 'af_www_to_apex', description: 'AF: www to the bare domain', action: 'redirect',
      expression: `(http.host eq "${WWW}")`,
      action_parameters: { from_value: { status_code: 301, preserve_query_string: true,
        target_url: { expression: `concat("https://${DOMAIN}", ${p})` } } } },
  ],
};
const FREE_MANAGED = '77454fe2d30c4220b5701f6fdfb893ba';

/* ── API ────────────────────────────────────────────────────────────── */

async function cf(method, path, body) {
  for (let i = 0; i < 3; i++) {
    try {
      const res = await fetch(API + path, { method, headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json' }, body: body === undefined ? undefined : JSON.stringify(body) });
      const j = await res.json().catch(() => ({}));
      if (res.status === 429 || res.status >= 500) { await new Promise(r => setTimeout(r, 3000 * (i + 1))); continue; }
      return { status: res.status, ok: res.ok && j.success !== false, result: j.result, errors: j.errors || [] };
    } catch (e) {
      if (i === 2) return { status: 0, ok: false, result: null, errors: [{ message: String(e) }] };
    }
  }
  return { status: 429, ok: false, result: null, errors: [{ message: 'rate limited or server error three times' }] };
}
const why = (r) => `HTTP ${r.status}${r.errors.length ? ': ' + r.errors.map(e => (e.code ? e.code + ' ' : '') + e.message).join('; ') : ''}${r.status === 403 ? ' (the token lacks the permission for this)' : ''}`;

/* ── checks that need no token ──────────────────────────────────────── */

function tlsCheck(servername) {
  return new Promise((resolve) => {
    const s = tls.connect({ host: ORIGIN, port: 443, servername, rejectUnauthorized: false, timeout: 15000 }, () => {
      const c = s.getPeerCertificate();
      const sans = String(c.subjectaltname || '').split(',').map(x => x.trim().replace(/^DNS:/, ''));
      const covers = sans.some(n => n === servername || (n.startsWith('*.') && servername.endsWith(n.slice(1)) && servername.split('.').length === n.split('.').length));
      resolve({ authorized: s.authorized, err: s.authorizationError, covers, sans, issuer: c.issuer && (c.issuer.O || c.issuer.CN), until: c.valid_to });
      s.end();
    });
    s.on('error', (e) => resolve({ error: String(e.message || e) }));
    s.on('timeout', () => { s.destroy(); resolve({ error: 'timeout' }); });
  });
}
function originGet() {
  return new Promise((resolve) => {
    const req = https.request({ host: ORIGIN, port: 443, path: '/', method: 'GET', servername: DOMAIN, headers: { Host: DOMAIN, 'User-Agent': 'Mozilla/5.0 (af-cloudflare-setup)', Accept: 'text/html' }, timeout: 20000 }, (res) => {
      let b = ''; res.on('data', d => { if (b.length < 200000) b += d; }); res.on('end', () => resolve({ status: res.statusCode, title: (b.match(/<title>([^<]*)<\/title>/i) || [])[1] || '', server: res.headers.server || '' }));
    });
    req.on('error', (e) => resolve({ error: String(e.message || e) }));
    req.on('timeout', () => { req.destroy(); resolve({ error: 'timeout' }); });
    req.end();
  });
}
async function noTokenChecks() {
  say('\n=== the origin (82.197.82.189), asked directly');
  if (process.env.CF_ORIGIN_CHECK === '0') { info('skipped (test run)'); }
  else {
    for (const name of [DOMAIN, WWW]) {
      const r = await tlsCheck(name);
      if (r.error) { (name === DOMAIN ? bad : info)(`TLS for ${name}: ${r.error}`); continue; }
      const line = `certificate for ${name}: ${r.authorized ? 'trusted' : 'NOT trusted (' + r.err + ')'}, ${r.covers ? 'covers the name' : 'does NOT cover the name'}, issuer ${r.issuer}, valid until ${r.until}`;
      if (r.authorized && r.covers) ok(line);
      else if (name === WWW) info(line + ' — fine: www is redirected at Cloudflare\'s edge and never reaches the origin');
      else bad(line + ' — Full (strict) would answer 526');
    }
    const g = await originGet();
    if (g.error) bad(`GET / at the origin: ${g.error}`);
    else if (g.status === 200 && /Art Framer/i.test(g.title)) ok(`GET / at the origin: 200, "${g.title.slice(0, 70)}" (server ${g.server || '-'})`);
    else bad(`GET / at the origin: HTTP ${g.status}, title "${g.title.slice(0, 70)}"`);
  }

  say('\n=== Cloudflare\'s addresses against af_cloudflare_ranges() in functions.php');
  try {
    const j = await (await fetch(process.env.CF_IPS_URL || 'https://api.cloudflare.com/client/v4/ips')).json();
    const theirs = new Set([...(j.result.ipv4_cidrs || []), ...(j.result.ipv6_cidrs || [])]);
    const src = fs.readFileSync(process.env.CF_FUNCTIONS_PHP || new URL('../functions.php', import.meta.url), 'utf8');
    const m = src.match(/function af_cloudflare_ranges\(\)\s*\{([\s\S]*?)\n\}/);
    const ours = new Set(m ? [...m[1].matchAll(/'([0-9a-f.:]+\/\d+)'/gi)].map(x => x[1]) : []);
    const missing = [...theirs].filter(x => !ours.has(x)), extra = [...ours].filter(x => !theirs.has(x));
    if (!missing.length && !extra.length) ok(`the same ${ours.size} ranges`);
    else bad(`differs — missing in functions.php: ${missing.join(' ') || 'none'}; not Cloudflare's any more: ${extra.join(' ') || 'none'}. Update the list and deploy BEFORE the nameservers change.`);
  } catch (e) { bad('could not compare: ' + e); }
}

/* ── with the token ─────────────────────────────────────────────────── */

// Whether everything in `want` is in `have` (Cloudflare echoes back extra
// default fields, which are not differences).
const within = (want, have) => {
  if (want === have) return true;
  if (Array.isArray(want)) return Array.isArray(have) && want.length === have.length && want.every((w, i) => within(w, have[i]));
  if (want && typeof want === 'object') return !!have && typeof have === 'object' && Object.keys(want).every(k => within(want[k], have[k]));
  return false;
};
const same = (have, want) => have.enabled !== false && within({ ...want }, have);
const ours = (r) => String(r.description || '').startsWith('AF:') || String(r.ref || '').startsWith('af_');
// A rule that is not ours goes back exactly as Cloudflare returned it,
// without the fields Cloudflare sets itself.
const asReturned = (r) => { const o = { ...r }; delete o.version; delete o.last_updated; delete o.categories; return o; };

async function zone() {
  const r = await cf('GET', `/zones?name=${DOMAIN}`);
  if (!r.ok) { bad(`find the zone: ${why(r)}`); return null; }
  const z = (r.result || [])[0];
  if (!z) { bad(`${DOMAIN} is not in this Cloudflare account yet — Add a domain → ${DOMAIN} → Free`); return null; }
  const line = `zone ${z.name}: status ${z.status}, plan ${(z.plan && z.plan.name) || '-'}`;
  z.status === 'active' ? ok(line) : info(line + ' (pending until the nameservers change)');
  info(`Cloudflare's nameservers for it: ${(z.name_servers || []).join(', ')}`);
  if (z.original_name_servers) info(`nameservers it saw at the start: ${z.original_name_servers.join(', ')}`);
  return z;
}

async function doDns(z) {
  say('\n=== DNS');
  const r = await cf('GET', `/zones/${z.id}/dns_records?per_page=100`);
  if (!r.ok) { bad(`read DNS: ${why(r)}`); return; }
  const recs = r.result || [];
  for (const x of recs) info(`${x.type.padEnd(5)} ${x.name.padEnd(28)} ${String(x.content).slice(0, 60)}${['A', 'AAAA', 'CNAME'].includes(x.type) ? (x.proxied ? '  (proxied)' : '  (DNS only)') : ''}`);
  const apex = recs.filter(x => x.name === DOMAIN && ['A', 'AAAA', 'CNAME'].includes(x.type));
  const www = recs.filter(x => x.name === WWW && ['A', 'AAAA', 'CNAME'].includes(x.type));
  const apexOk = apex.length === 1 && apex[0].type === 'A' && apex[0].content === ORIGIN && apex[0].proxied;
  const wwwOk = www.length === 1 && www[0].type === 'CNAME' && www[0].content === DOMAIN && www[0].proxied;
  apexOk ? ok(`${DOMAIN}: one A record → ${ORIGIN}, proxied`) : bad(`${DOMAIN} should be one A record → ${ORIGIN}, proxied`);
  wwwOk ? ok(`${WWW}: CNAME → ${DOMAIN}, proxied`) : bad(`${WWW} should be a CNAME → ${DOMAIN}, proxied`);
  if (!WRITE || (apexOk && wwwOk)) return;
  if (z.status === 'active' && process.env.APPLY_DNS !== '1') { info('the zone is live: DNS is left alone (run with APPLY_DNS=1 to change it)'); return; }
  // apex: keep or create the origin record first, then remove the others
  let keep = apex.find(x => x.type === 'A' && x.content === ORIGIN);
  if (keep && !keep.proxied) { const u = await cf('PATCH', `/zones/${z.id}/dns_records/${keep.id}`, { proxied: true }); u.ok ? say('  set   apex A proxied') : bad('apex A proxied: ' + why(u)); }
  if (!keep) { const c = await cf('POST', `/zones/${z.id}/dns_records`, { type: 'A', name: DOMAIN, content: ORIGIN, proxied: true, ttl: 1 }); if (!c.ok) { bad('create apex A: ' + why(c)); return; } keep = c.result; say(`  set   created A ${DOMAIN} → ${ORIGIN} (proxied)`); }
  for (const x of apex) if (x.id !== keep.id) { const d = await cf('DELETE', `/zones/${z.id}/dns_records/${x.id}`); d.ok ? say(`  set   removed ${x.type} ${x.name} → ${x.content}`) : bad('remove ' + x.content + ': ' + why(d)); }
  // www
  const cn = www.find(x => x.type === 'CNAME');
  if (cn) { if (cn.content !== DOMAIN || !cn.proxied) { const u = await cf('PATCH', `/zones/${z.id}/dns_records/${cn.id}`, { content: DOMAIN, proxied: true }); u.ok ? say(`  set   ${WWW} CNAME → ${DOMAIN} (proxied)`) : bad('www CNAME: ' + why(u)); } }
  else { for (const x of www) { const d = await cf('DELETE', `/zones/${z.id}/dns_records/${x.id}`); d.ok ? say(`  set   removed ${x.type} ${x.name}`) : bad('remove www: ' + why(d)); }
    const c = await cf('POST', `/zones/${z.id}/dns_records`, { type: 'CNAME', name: WWW, content: DOMAIN, proxied: true, ttl: 1 }); c.ok ? say(`  set   created ${WWW} CNAME → ${DOMAIN} (proxied)`) : bad('create www CNAME: ' + why(c)); }
  for (const x of www) if (cn && x.id !== cn.id) { const d = await cf('DELETE', `/zones/${z.id}/dns_records/${x.id}`); d.ok ? say(`  set   removed ${x.type} ${x.name}`) : bad('remove www extra: ' + why(d)); }
}

async function doSettings(z) {
  say('\n=== SSL/TLS and content settings');
  // New zones choose the encryption mode themselves ("Automatic SSL/TLS");
  // a fixed Full (strict) is wanted, so a half-checked origin can never be
  // given a mode that loops or skips the certificate check.
  const auto = await cf('GET', `/zones/${z.id}/settings/ssl_automatic_mode`);
  if (auto.ok) {
    const v = auto.result && auto.result.value;
    if (v === 'custom') ok('encryption mode chosen by us (custom), not automatically');
    else if (WRITE) { const u = await cf('PATCH', `/zones/${z.id}/settings/ssl_automatic_mode`, { value: 'custom' }); u.ok ? say('  set   encryption mode: custom (ours)') : bad('ssl_automatic_mode custom: ' + why(u)); }
    else bad(`encryption mode is chosen automatically (${v}); apply sets it to Full (strict)`);
  } else info(`automatic SSL/TLS mode not readable (${why(auto)}); the ssl setting below decides`);
  for (const [name, want] of Object.entries(SETTINGS)) {
    const g = await cf('GET', `/zones/${z.id}/settings/${name}`);
    if (!g.ok) { bad(`${name}: ${why(g)}`); continue; }
    const have = g.result && g.result.value;
    if (String(have) === want) { ok(`${name} = ${want}`); continue; }
    if (!WRITE) { bad(`${name} = ${have}, wanted ${want}`); continue; }
    const u = await cf('PATCH', `/zones/${z.id}/settings/${name}`, { value: want });
    u.ok ? say(`  set   ${name}: ${have} → ${want}`) : bad(`${name}: ${why(u)}`);
  }
  const h = await cf('GET', `/zones/${z.id}/settings/security_header`);
  if (h.ok) { const s = (h.result && h.result.value && h.result.value.strict_transport_security) || {}; info(`HSTS at Cloudflare: ${s.enabled ? 'on, max-age ' + s.max_age : 'off'} (the origin already sends max-age=31536000; includeSubDomains)`); }
}

async function doPhase(z, phase, want) {
  const g = await cf('GET', `/zones/${z.id}/rulesets/phases/${phase}/entrypoint`);
  if (!g.ok && g.status !== 404) { bad(`${phase}: read: ${why(g)}`); return false; }
  const have = (g.ok && g.result && g.result.rules) || [];
  const theirs = have.filter(r => !ours(r));
  const mine = have.filter(ours);
  const differs = want.length !== mine.length || want.some(w => { const m = mine.find(r => r.ref === w.ref); return !m || !same(m, w); });
  for (const w of want) { const m = mine.find(r => r.ref === w.ref); (m && same(m, w)) ? ok(`${phase}: ${w.description}`) : (WRITE ? info(`${phase}: ${w.description} — to be written`) : bad(`${phase}: ${w.description} — ${m ? 'differs' : 'missing'}`)); }
  if (theirs.length) info(`${phase}: ${theirs.length} rule(s) not ours, kept: ${theirs.map(r => r.description || r.ref || r.id).join(' | ')}`);
  if (!differs || !WRITE) return true;
  const rules = [...theirs.map(asReturned), ...want.map(w => ({ enabled: true, ...w }))];
  const u = await cf('PUT', `/zones/${z.id}/rulesets/phases/${phase}/entrypoint`, { rules });
  if (!u.ok) { bad(`${phase}: write refused: ${why(u)}`); return false; }
  say(`  set   ${phase}: ${want.length} rule(s) of ours written, ${theirs.length} kept`);
  return true;
}

async function doManaged(z) {
  const g = await cf('GET', `/zones/${z.id}/rulesets/phases/http_request_firewall_managed/entrypoint`);
  if (!g.ok) { info(`managed rules: ${g.status === 404 ? 'no managed ruleset deployed' : why(g)} — Security → Settings → turn on the Cloudflare Free Managed Ruleset if it is offered`); return; }
  const rules = (g.result && g.result.rules) || [];
  const free = rules.find(r => r.action === 'execute' && r.action_parameters && r.action_parameters.id === FREE_MANAGED);
  free ? ok(`Free Managed Ruleset deployed (${free.enabled === false ? 'but switched OFF' : 'on'})`) : info(`managed rules: ${rules.map(r => r.description || r.action_parameters?.id).join(' | ') || 'none'}`);
}

async function doInsights(z) {
  say('\n=== Security Insights (Cloudflare\'s own scan)');
  let r = await cf('GET', `/zones/${z.id}/security-center/insights?per_page=50`);
  if (!r.ok) r = await cf('GET', `/accounts/${z.account.id}/security-center/insights?per_page=50`);
  if (!r.ok) { info(`not readable: ${why(r)}`); return; }
  const list = (r.result && (r.result.issues || r.result)) || [];
  if (!Array.isArray(list) || !list.length) { ok('no open insights'); return; }
  for (const x of list.slice(0, 40)) info(`${(x.severity || '').padEnd(8)} ${x.issue_type || x.type || ''}: ${x.subject || ''} ${x.dismissed ? '(dismissed)' : ''}`.trim());
}

/* ── run ────────────────────────────────────────────────────────────── */

say(`=== Cloudflare setup for ${DOMAIN}: ${MODE}`);
await noTokenChecks();
if (!TOKEN) {
  say('\n(no CLOUDFLARE_API_TOKEN: the checks that need Cloudflare\'s API were skipped)');
} else {
  say('\n=== token and zone');
  const v = await cf('GET', '/user/tokens/verify');
  v.ok ? ok(`token ${v.result && v.result.status}`) : bad(`token: ${why(v)}`);
  const z = v.ok ? await zone() : null;
  if (z) {
    await doDns(z);
    await doSettings(z);
    say('\n=== rules');
    for (const [phase, want] of Object.entries(RULES)) { if (!(await doPhase(z, phase, want))) break; }
    await doManaged(z);
    await doInsights(z);
    if (MODE === 'apply') {
      say('\n=== re-reading what is now set (nothing is written from here on)');
      WRITE = false;
      problems = 0;
      await noTokenChecks();
      const z2 = await zone();
      if (z2) { await doDns(z2); await doSettings(z2); for (const [phase, want] of Object.entries(RULES)) await doPhase(z2, phase, want); }
    }
  }
}
say(`\n=== ${problems ? problems + ' thing(s) to fix' : 'all as planned'}`);
process.exit(problems ? 1 : 0);

