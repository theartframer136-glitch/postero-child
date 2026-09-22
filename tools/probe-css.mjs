// Which copy of a stylesheet does the site hand out, and who hands it over?
//
// Three fixes (C-03, H-04, M-07) are correct in this repository and correct on
// the server, and still fail about half the time they are measured. The same
// URL came back twice within five minutes as two different files — once the
// version this repo ships, once a 122,417-byte copy from before the change.
// A static file cannot do that on its own, so something in front of it is
// holding two answers.
//
// This asks the only questions that separate the candidates:
//
//   how many addresses does the name resolve to   — more than one origin?
//   which address answered THIS request           — does the stale copy come
//                                                   from one particular node?
//   what do Etag / Last-Modified / Age / the cache
//   headers say on the stale answer vs the fresh one
//   does a cache-buster change the answer         — is the layer keyed on the
//                                                   query string or not?
//
// Read-only: GETs of two stylesheets and the front page. It sends nothing, and
// it cannot: there is no form, no cart, no POST anywhere in this file.

import https from 'node:https';
import dns from 'node:dns/promises';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const HOST = new URL(SITE).hostname;
const ROUNDS = Number(process.env.AF_PROBE_ROUNDS || 12);

// What tells us, from the bytes alone, which copy we were handed.
const FILES = [
  { name: 'custom.css',   path: '/wp-content/themes/postero-child/assets/css/custom.css',
    markers: { focusRing: /:focus-visible[^{]*\{[^}]*outline:\s*3px solid #c9a84c/i,
               atcColor:  /single_add_to_cart_button[^{]*\{[^}]*color:\s*#1a1206/i } },
  { name: 'checkout.css', path: '/wp-content/themes/postero-child/assets/css/checkout.css',
    markers: { errStrong: /woocommerce-error[^{]*strong[^{]*\{[^}]*color:\s*inherit/i,
               tableFixed: /table-layout:\s*fixed/i } },
];

// Every header that could name the layer holding the stale copy.
const HEADERS = ['etag', 'last-modified', 'content-length', 'age', 'date', 'server',
  'x-litespeed-cache', 'x-litespeed-cache-control', 'x-qc-cache', 'x-qc-pop',
  'cf-cache-status', 'cf-ray', 'x-cache', 'x-cache-hits', 'x-served-by', 'via',
  'x-proxy-cache', 'x-varnish', 'cache-control', 'vary', 'x-powered-by'];

function get(path) {
  return new Promise(resolve => {
    const started = Date.now();
    const req = https.get(SITE + path, { agent: false, headers: { 'User-Agent': 'af-css-probe', 'Accept-Encoding': 'identity' } }, res => {
      let body = '';
      const ip = res.socket.remoteAddress;
      res.setEncoding('utf8');
      res.on('data', c => { body += c; });
      res.on('end', () => resolve({ status: res.statusCode, ip, body, headers: res.headers, ms: Date.now() - started }));
    });
    req.on('error', e => resolve({ status: 0, ip: '-', body: '', headers: {}, err: e.message, ms: Date.now() - started }));
    req.setTimeout(20000, () => { req.destroy(new Error('timeout')); });
  });
}

const pad = (s, n) => String(s).padEnd(n).slice(0, n);
const uniq = a => [...new Set(a)];

console.log('probe: ' + SITE + '   ' + new Date().toISOString());

// ── who is behind the name ──────────────────────────────────────────────────
try {
  const [a, aaaa, cname] = await Promise.all([
    dns.resolve4(HOST).catch(() => []),
    dns.resolve6(HOST).catch(() => []),
    dns.resolveCname(HOST).catch(() => []),
  ]);
  console.log('\n— dns —');
  console.log('  A     : ' + (a.join(', ') || '(none)') + (a.length > 1 ? '   ← more than one origin address' : ''));
  console.log('  AAAA  : ' + (aaaa.join(', ') || '(none)'));
  console.log('  CNAME : ' + (cname.join(', ') || '(none)'));
} catch (e) { console.log('— dns — failed: ' + e.message); }

// ── the same URL, over and over, on a fresh connection each time ────────────
for (const f of FILES) {
  console.log('\n— ' + f.name + ' · ' + ROUNDS + ' requests, fresh connection each, no cache-buster —');
  const seen = [];
  for (let i = 0; i < ROUNDS; i++) {
    const r = await get(f.path);
    const hit = Object.fromEntries(Object.entries(f.markers).map(([k, re]) => [k, re.test(r.body)]));
    seen.push({ r, hit });
    console.log('  ' + pad(i + 1, 3) + pad('HTTP ' + r.status, 10) + pad(r.body.length + 'b', 10)
      + pad(r.ip, 18)
      + Object.entries(hit).map(([k, v]) => k + '=' + (v ? 'Y' : 'n')).join(' ')
      + '  etag=' + pad(r.headers.etag || '-', 22)
      + ' lm=' + pad(r.headers['last-modified'] || '-', 30)
      + ' age=' + (r.headers.age || '-'));
  }

  const sizes = uniq(seen.map(s => s.r.body.length));
  const ips = uniq(seen.map(s => s.r.ip));
  const etags = uniq(seen.map(s => s.r.headers.etag || '-'));
  const lms = uniq(seen.map(s => s.r.headers['last-modified'] || '-'));
  console.log('  → distinct bodies by size: ' + sizes.join(', '));
  console.log('  → distinct addresses     : ' + ips.join(', '));
  console.log('  → distinct etags         : ' + etags.join(', '));
  console.log('  → distinct last-modified : ' + lms.join(', '));

  // If it alternates, say whether the split follows the address or not: that is
  // the whole question. Same address, two answers = a cache in front. Two
  // addresses, one answer each = two nodes out of sync.
  if (sizes.length > 1) {
    console.log('  → THE FILE ALTERNATES. Which address gave which body:');
    for (const size of sizes) {
      const rows = seen.filter(s => s.r.body.length === size);
      console.log('     ' + pad(size + 'b', 10) + ' ×' + pad(rows.length, 4)
        + ' from ' + uniq(rows.map(s => s.r.ip)).join(', ')
        + '   ' + Object.keys(f.markers).map(k => k + '=' + uniq(rows.map(s => s.hit[k] ? 'Y' : 'n')).join('/')).join(' '));
    }
  } else {
    console.log('  → one body only across ' + ROUNDS + ' requests'
      + (Object.entries(seen[0].hit).every(([, v]) => v) ? ' — and it carries every marker' : ' — markers: '
        + Object.entries(seen[0].hit).map(([k, v]) => k + '=' + (v ? 'Y' : 'n')).join(' ')));
  }

  // Full headers of one fresh answer and, if there is one, one stale answer.
  const fresh = seen.find(s => Object.values(s.hit).every(Boolean)) || seen[0];
  const stale = seen.find(s => s.r.body.length !== fresh.r.body.length);
  for (const [label, s] of [['carrying our markers', fresh], ['the other copy', stale]]) {
    if (!s) continue;
    console.log('  headers · ' + label + ' (' + s.r.body.length + 'b from ' + s.r.ip + '):');
    for (const h of HEADERS) if (s.r.headers[h] !== undefined) console.log('     ' + pad(h, 28) + s.r.headers[h]);
  }

  // Does a cache-buster reach past whatever is holding the copy?
  console.log('  — same file, unique query string each time —');
  const busted = [];
  for (let i = 0; i < 4; i++) {
    const r = await get(f.path + '?probe=' + Date.now() + '-' + i);
    busted.push(r.body.length);
    console.log('     ' + pad('HTTP ' + r.status, 10) + pad(r.body.length + 'b', 10) + pad(r.ip, 18)
      + Object.entries(f.markers).map(([k, re]) => k + '=' + (re.test(r.body) ? 'Y' : 'n')).join(' ')
      + '  age=' + (r.headers.age || '-'));
  }
  console.log('     → ' + (uniq(busted).length > 1 ? 'the buster does NOT settle it — still ' + uniq(busted).join(', ')
    : 'the buster always returns ' + busted[0] + 'b'));
}

// ── and the page that links them ────────────────────────────────────────────
console.log('\n— front page · which stylesheets it names —');
for (let i = 0; i < 4; i++) {
  const r = await get('/');
  const links = [...r.body.matchAll(/<link[^>]+href=["']([^"']+\.css[^"']*)["']/gi)].map(m => m[1]);
  // 87 hashed LiteSpeed bundles is not something to print 87 times; count them
  // and name only the files this repository owns.
  const bundles = links.filter(h => /\/litespeed\/css\//i.test(h));
  const ours = links.filter(h => /postero-child|custom\.css|checkout\.css/i.test(h));
  console.log('  ' + pad(i + 1, 3) + pad('HTTP ' + r.status, 10) + pad(r.body.length + 'b', 10) + pad(r.ip, 18)
    + 'ls-cache=' + pad(r.headers['x-litespeed-cache'] || '-', 8)
    + ' css links=' + links.length + ' (litespeed bundles=' + bundles.length + ', ours=' + ours.length + ')');
  for (const h of ours) console.log('        ' + h.replace(SITE, ''));
}

console.log('\ndone ' + new Date().toISOString());
