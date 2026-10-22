/**
 * Tests for tools/cloudflare-setup.mjs against a local stand-in for
 * Cloudflare's v4 API (nothing real is called):
 *   node tools/test-cloudflare-setup.mjs
 *
 * The stand-in keeps a zone in memory the way Cloudflare leaves it right after
 * "Add a domain": pending, with the records it imported (Hostinger's CDN), the
 * default settings, a rule somebody added by hand, and the Free Managed
 * Ruleset. It answers the few calls the script makes, echoes back extra
 * default fields as Cloudflare does, and refuses what the Free plan refuses
 * (regex, a rate limit period other than 10 s).
 */
import http from 'node:http';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const SCRIPT = fileURLToPath(new URL('./cloudflare-setup.mjs', import.meta.url));
const FUNCS = fileURLToPath(new URL('../functions.php', import.meta.url));
const src = fs.readFileSync(FUNCS, 'utf8');
const RANGES = [...src.match(/function af_cloudflare_ranges\(\)\s*\{([\s\S]*?)\n\}/)[1].matchAll(/'([0-9a-f.:]+\/\d+)'/gi)].map(m => m[1]);

let pass = 0, fail = 0;
const t = (name, cond, extra) => { cond ? pass++ : fail++; console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}${!cond && extra !== undefined ? '\n      ' + String(extra).slice(0, 600) : ''}`); };

function freshState() {
  return {
    zone: { id: 'z1', name: 'theartframer.us', status: 'pending', plan: { name: 'Free Website' }, account: { id: 'acc1' }, name_servers: ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com'], original_name_servers: ['ns1.dns-parking.com', 'ns2.dns-parking.com'] },
    dns: [
      { id: 'd1', type: 'A', name: 'theartframer.us', content: '2.57.91.6', proxied: false },
      { id: 'd2', type: 'A', name: 'theartframer.us', content: '84.32.84.236', proxied: false },
      { id: 'd3', type: 'CNAME', name: 'www.theartframer.us', content: 'www.theartframer.us.cdn.hstgr.net', proxied: false },
      { id: 'd4', type: 'TXT', name: 'theartframer.us', content: 'google-site-verification=abc', proxied: false },
    ],
    settings: { ssl_automatic_mode: 'auto', ssl: 'full', always_use_https: 'off', automatic_https_rewrites: 'on', min_tls_version: '1.0', tls_1_3: 'on', rocket_loader: 'off', email_obfuscation: 'on',
      security_header: { strict_transport_security: { enabled: false, max_age: 0 } } },
    rulesets: {
      http_request_firewall_custom: [{ id: 'r-owner', version: '1', ref: 'owner1', description: "Owner's own rule", action: 'managed_challenge', expression: '(ip.src.country eq "T1")', enabled: true, last_updated: 'x' }],
      http_request_firewall_managed: [{ id: 'm1', action: 'execute', action_parameters: { id: '77454fe2d30c4220b5701f6fdfb893ba', version: 'latest' }, expression: 'true', description: 'Free Managed Ruleset', enabled: true }],
    },
    ips: { ipv4_cidrs: RANGES.filter(r => !r.includes(':')), ipv6_cidrs: RANGES.filter(r => r.includes(':')) },
    deny: new Set(),   // "METHOD path" answered 403
    writes: [],
  };
}

function serve(S) {
  let n = 100;
  return http.createServer((req, res) => {
    let body = '';
    req.on('data', d => body += d);
    req.on('end', () => {
      const send = (status, result, errors = []) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ success: status < 400, result, errors, messages: [] })); };
      const u = new URL(req.url, 'http://x');
      const path = u.pathname.replace(/^\/client\/v4/, '');
      const key = `${req.method} ${path}`;
      if (req.method !== 'GET') S.writes.push(key);
      if (S.deny.has(key)) return send(403, null, [{ code: 10000, message: 'Authentication error' }]);
      if ((req.headers.authorization || '') !== 'Bearer test-token' && path !== '/ips') return send(403, null, [{ code: 9109, message: 'Invalid access token' }]);
      const b = body ? JSON.parse(body) : {};
      let m;
      if (path === '/ips') return send(200, S.ips);
      if (path === '/user/tokens/verify') return send(200, { status: 'active' });
      if (path === '/zones') return send(200, u.searchParams.get('name') === S.zone.name ? [S.zone] : []);
      if (path === '/zones/z1/dns_records' && req.method === 'GET') return send(200, S.dns);
      if (path === '/zones/z1/dns_records' && req.method === 'POST') { const r = { id: 'd' + (n++), ...b }; S.dns.push(r); return send(200, r); }
      if ((m = path.match(/^\/zones\/z1\/dns_records\/(\w[\w-]*)$/))) {
        const i = S.dns.findIndex(x => x.id === m[1]); if (i < 0) return send(404, null, [{ message: 'not found' }]);
        if (req.method === 'PATCH') { Object.assign(S.dns[i], b); return send(200, S.dns[i]); }
        if (req.method === 'DELETE') { S.dns.splice(i, 1); return send(200, { id: m[1] }); }
      }
      if ((m = path.match(/^\/zones\/z1\/settings\/(\w+)$/))) {
        if (!(m[1] in S.settings)) return send(400, null, [{ message: 'Undefined zone setting' }]);
        if (req.method === 'GET') return send(200, { id: m[1], value: S.settings[m[1]], editable: true });
        if (req.method === 'PATCH') {
          if (m[1] === 'ssl' && S.settings.ssl_automatic_mode === 'auto') return send(400, null, [{ message: 'Automatic SSL/TLS is on' }]);
          S.settings[m[1]] = b.value; return send(200, { id: m[1], value: b.value });
        }
      }
      if ((m = path.match(/^\/zones\/z1\/rulesets\/phases\/(\w+)\/entrypoint$/))) {
        const ph = m[1];
        if (req.method === 'GET') return S.rulesets[ph] ? send(200, { id: 'rs-' + ph, phase: ph, rules: S.rulesets[ph] }) : send(404, null, [{ message: 'not found' }]);
        if (req.method === 'PUT') {
          const rules = b.rules || [];
          const refs = rules.map(r => r.ref).filter(Boolean);
          if (new Set(refs).size !== refs.length) return send(400, null, [{ message: 'duplicate ref' }]);
          for (const r of rules) {
            if (/\bmatches\b/.test(r.expression || '')) return send(400, null, [{ message: 'not entitled to use the regex operator' }]);
            if (ph === 'http_ratelimit' && (r.ratelimit?.period !== 10 || r.ratelimit?.mitigation_timeout !== 10)) return send(400, null, [{ message: 'not entitled: period' }]);
            if (ph === 'http_request_firewall_custom' && rules.length > 5) return send(400, null, [{ message: 'exceeded maximum number of rules' }]);
          }
          S.rulesets[ph] = rules.map(r => {
            const o = { id: r.id || 'r' + (n++), version: '1', last_updated: 'now', enabled: true, ...r };
            if (o.ratelimit) o.ratelimit = { requests_to_origin: false, counting_expression: '', ...o.ratelimit }; // Cloudflare's defaults echoed back
            return o;
          });
          return send(200, { id: 'rs-' + ph, phase: ph, rules: S.rulesets[ph] });
        }
      }
      if (path === '/zones/z1/security-center/insights') return send(200, { count: 1, issues: [{ severity: 'Moderate', issue_type: 'exposed_infrastructure', subject: 'theartframer.us', dismissed: false }] });
      send(404, null, [{ message: 'no such route in the stand-in: ' + key }]);
    });
  });
}

async function run(S, mode, env = {}) {
  const srv = serve(S);
  await new Promise(r => srv.listen(0, '127.0.0.1', r));
  const port = srv.address().port;
  const p = await new Promise((resolve) => {
    import('node:child_process').then(({ spawn }) => {
      const c = spawn(process.execPath, [SCRIPT, mode], { env: { PATH: process.env.PATH, CF_API: `http://127.0.0.1:${port}/client/v4`, CF_IPS_URL: `http://127.0.0.1:${port}/client/v4/ips`, CF_ORIGIN_CHECK: '0', CF_FUNCTIONS_PHP: FUNCS, CLOUDFLARE_API_TOKEN: 'test-token', ...env } });
      let out = ''; c.stdout.on('data', d => out += d); c.stderr.on('data', d => out += d);
      c.on('close', (code) => resolve({ code, out }));
    });
  });
  srv.close();
  return p;
}

const S = freshState();

// 1. audit on the fresh pending zone: reports, writes nothing
let r = await run(S, 'audit');
t('audit: exits 1 while things differ', r.code === 1, r.out);
t('audit: writes nothing', S.writes.length === 0, S.writes.join(', '));
t('audit: names the nameservers to use', /ada\.ns\.cloudflare\.com, bob\.ns\.cloudflare\.com/.test(r.out));
t('audit: reports the DNS and settings to fix', /FIX\s+theartframer\.us should be one A record/.test(r.out) && /FIX\s+always_use_https = off, wanted on/.test(r.out));
t('audit: address list matches functions.php', /ok\s+the same \d+ ranges/.test(r.out), r.out);
t('audit: Free Managed Ruleset seen', /Free Managed Ruleset deployed \(on\)/.test(r.out));
t('audit: insights listed', /exposed_infrastructure/.test(r.out));

// 2. apply: DNS, settings and rules as planned; other records and rules kept
r = await run(S, 'apply');
t('apply: exits 0', r.code === 0, r.out);
const web = S.dns.filter(x => ['A', 'AAAA', 'CNAME'].includes(x.type));
t('apply: one apex A → origin, proxied', web.filter(x => x.name === 'theartframer.us').length === 1 && web.some(x => x.type === 'A' && x.name === 'theartframer.us' && x.content === '82.197.82.189' && x.proxied), JSON.stringify(web));
t('apply: www CNAME → apex, proxied', web.some(x => x.type === 'CNAME' && x.name === 'www.theartframer.us' && x.content === 'theartframer.us' && x.proxied), JSON.stringify(web));
t('apply: the TXT record is untouched', S.dns.some(x => x.type === 'TXT' && x.content === 'google-site-verification=abc'));
t('apply: encryption mode fixed, Full (strict)', S.settings.ssl_automatic_mode === 'custom' && S.settings.ssl === 'strict');
t('apply: other settings', S.settings.always_use_https === 'on' && S.settings.min_tls_version === '1.2' && S.settings.email_obfuscation === 'off' && S.settings.rocket_loader === 'off');
const custom = S.rulesets.http_request_firewall_custom || [];
t('apply: custom rules = owner rule kept + our 4 (the optional 5th left out)', custom.length === 5 && custom[0].id === 'r-owner' && custom[0].description === "Owner's own rule" && custom.filter(x => x.description.startsWith('AF:')).length === 4 && !custom.some(x => x.ref === 'af_sg_bots'), JSON.stringify(custom.map(x => x.description)));
t('apply: the left-out rule is named', /left out, the Free plan allows 5 custom rules and 1 are the owner's: AF: bot traffic from Singapore/.test(r.out), r.out);
t('apply: no regex anywhere', !JSON.stringify(S.rulesets).includes(' matches '));
t('apply: /.well-known/ is never blocked', /not starts_with\(http\.request\.uri\.path, "\/\.well-known\/"\)/.test(custom.find(x => x.ref === 'af_block_private')?.expression || ''));
t('apply: rate limit rule, path only, 10 s', (S.rulesets.http_ratelimit || []).length === 1 && !/method/.test(S.rulesets.http_ratelimit[0].expression) && S.rulesets.http_ratelimit[0].ratelimit.period === 10);
t('apply: headers rule', (S.rulesets.http_response_headers_transform || [])[0]?.action_parameters?.headers?.['X-Content-Type-Options']?.value === 'nosniff');
t('apply: www redirect rule', (S.rulesets.http_request_dynamic_redirect || [])[0]?.action_parameters?.from_value?.status_code === 301);
t('apply: managed phase not written', !S.writes.some(w => w.includes('http_request_firewall_managed')));
t('apply: the re-check finds everything as planned', /=== all as planned/.test(r.out), r.out.split('re-reading')[1]);

// 3. a second apply writes nothing
S.writes = [];
r = await run(S, 'apply');
t('apply again: exits 0 and writes nothing', r.code === 0 && S.writes.length === 0, S.writes.join(', ') + '\n' + r.out);

// 3b. without the owner's rule there is room: the Singapore rule is written too
S.rulesets.http_request_firewall_custom = S.rulesets.http_request_firewall_custom.filter(x => x.id !== 'r-owner');
r = await run(S, 'apply');
t('no owner rule: our 5 written, Singapore challenge included', r.code === 0 && S.rulesets.http_request_firewall_custom.length === 5 && S.rulesets.http_request_firewall_custom.some(x => x.ref === 'af_sg_bots' && x.action === 'managed_challenge' && !('optional' in x)), JSON.stringify(S.rulesets.http_request_firewall_custom.map(x => [x.ref, x.action])));
S.writes = [];
r = await run(S, 'apply');
t('no owner rule: a second apply writes nothing', r.code === 0 && S.writes.length === 0, S.writes.join(', '));
S.rulesets.http_request_firewall_custom.unshift({ id: 'r-owner', version: '1', ref: 'owner1', description: "Owner's own rule", action: 'managed_challenge', expression: '(ip.src.country eq "T1")', enabled: true, last_updated: 'x' });
S.rulesets.http_request_firewall_custom.push({ id: 'r-owner2', version: '1', ref: 'owner2', description: "Owner's second rule", action: 'block', expression: '(ip.src eq 192.0.2.1)', enabled: true, last_updated: 'x' });
S.writes = [];
r = await run(S, 'apply');
t('two owner rules: 4 required ones cannot fit, so nothing is written and the reason is named', r.code === 1 && !S.writes.some(w => w.includes('http_request_firewall_custom')) && /leave room for only 3 of ours; 4 are needed/.test(r.out), r.out.split('=== rules')[1] || r.out);
S.rulesets.http_request_firewall_custom = S.rulesets.http_request_firewall_custom.filter(x => x.id !== 'r-owner2');

// 4. a missing permission is named and the audit carries on
S.deny.add('GET /zones/z1/settings/rocket_loader');
r = await run(S, 'audit');
t('403: named as a missing permission', /rocket_loader: HTTP 403.*lacks the permission/.test(r.out), r.out);
t('403: the audit still reads the rules after it', /ok\s+http_ratelimit: AF:/.test(r.out));
S.deny.clear();

// 5. Cloudflare's address list moved on: a blocker
S.ips.ipv4_cidrs = S.ips.ipv4_cidrs.concat(['198.18.0.0/15']);
r = await run(S, 'audit');
t('address list differs: reported as a fix', r.code === 1 && /missing in functions\.php: 198\.18\.0\.0\/15/.test(r.out), r.out);
S.ips.ipv4_cidrs = S.ips.ipv4_cidrs.filter(x => x !== '198.18.0.0/15');

// 6. once live, DNS is left alone unless asked
S.zone.status = 'active';
S.dns.find(x => x.type === 'A').proxied = false;
S.writes = [];
r = await run(S, 'apply');
t('active zone: DNS not changed without APPLY_DNS=1', !S.writes.some(w => w.includes('dns_records')) && /DNS is left alone/.test(r.out), S.writes.join(', '));
r = await run(S, 'apply', { APPLY_DNS: '1' });
t('active zone: DNS changed with APPLY_DNS=1', S.dns.find(x => x.type === 'A').proxied === true);

// 7. without a token: only the checks that need none
r = await run(S, 'audit', { CLOUDFLARE_API_TOKEN: '' });
t('no token: runs the address check and stops there', r.code === 0 && /the same \d+ ranges/.test(r.out) && /no CLOUDFLARE_API_TOKEN/.test(r.out), r.out);

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
