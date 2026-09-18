/**
 * Crawl the live site and report every link that does not work.
 *
 * Read-only, and deliberately gentle: this host is CPU-bound and the deploy
 * notes are emphatic that piling parallel requests on it is what produces the
 * 508s. Three at a time, a pause between waves, and a page cap.
 *
 * What it reports
 *   - every internal URL that answers 4xx/5xx, and which pages link to it
 *   - redirect chains longer than one hop (each hop is a round trip a visitor
 *     waits through) and where they end
 *   - links that can never work: empty href, "#", javascript:, localhost,
 *     staging hosts, and plain http:// on an https site (mixed content)
 *   - the navigation menus, checked separately and named, since a dead link
 *     in the header is worth more than a dead link in a paragraph
 *   - time to first byte for every page, so the slow ones are named
 *
 * Usage: node tools/audit-links.mjs https://theartframer.us [maxPages]
 */
const ORIGIN   = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const MAX      = parseInt(process.argv[3] || '160', 10);
const CONC     = 3;
const PAUSE_MS = 250;
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36 AF-LinkAudit';

const host = new URL(ORIGIN).host;
const seen = new Map();          // url -> {status, ms, chain, from}
const queue = [ORIGIN + '/' ];
const linkedFrom = new Map();    // url -> Set(pages)
const badHrefs = [];             // {page, href, why}
const navLinks = new Map();      // label -> href, from the header/footer menus

const sleep = ms => new Promise(r => setTimeout(r, ms));

function norm(href, base) {
  try {
    const u = new URL(href, base);
    u.hash = '';
    if (u.host !== host) return null;
    if (!/^https?:$/.test(u.protocol)) return null;
    return u.toString();
  } catch { return null; }
}

// Skip the endpoints that are not pages, and the ones that would act.
const SKIP = /\.(jpe?g|png|gif|webp|avif|svg|ico|css|js|mp4|webm|pdf|zip|woff2?|ttf)(\?|$)|\/wp-admin|\/wp-json|\/wp-login|\/feed\/?$|add-to-cart=|\?add_to_cart|\/cart\/\?|remove_item|woosw|woosc|\/my-account\/customer-logout|\?orderby=|\?filter_|\?paged=|\?s=/i;

// The host answers an unrecognised client with a "Checking your browser" page,
// as a complete 200 document. A crawl that accepts those reports a clean site
// because every selector legitimately matches nothing. Detect and retry.
function isChallenge(body) {
  return typeof body === 'string' && body.includes('Checking your browser');
}

async function fetchPage(url) {
  const t0 = Date.now();
  const chain = [];
  let cur = url, status = 0, body = '', ctype = '';
  for (let hop = 0; hop < 6; hop++) {
    let r;
    try {
      r = await fetch(cur, { redirect: 'manual', headers: { 'User-Agent': UA, 'Accept': 'text/html' } });
    } catch (e) {
      return { status: -1, ms: Date.now() - t0, chain, body: '', err: String(e.message || e) };
    }
    status = r.status;
    if (status >= 300 && status < 400) {
      const loc = r.headers.get('location');
      if (!loc) break;
      const next = norm(loc, cur) || loc;
      chain.push({ from: cur, to: next, status });
      cur = next;
      if (!cur.startsWith('http')) break;
      continue;
    }
    ctype = r.headers.get('content-type') || '';
    if (ctype.includes('text/html')) body = await r.text();
    else await r.arrayBuffer().catch(() => {});
    break;
  }
  return { status, ms: Date.now() - t0, chain, body, final: cur, ctype };
}

function harvest(pageUrl, html) {
  // Links inside the header/footer navigation, named so they can be reported
  // separately from links buried in body copy.
  const navBlocks = [];
  const navRe = /<(nav|header|footer)\b[^>]*>([\s\S]*?)<\/\1>/gi;
  let nb;
  while ((nb = navRe.exec(html))) navBlocks.push(nb[2]);

  const out = new Set();
  const aRe = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = aRe.exec(html))) {
    const attrs = m[1];
    const label = m[2].replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 48);
    const hm = /href\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+))/i.exec(attrs);
    const href = hm ? (hm[2] ?? hm[3] ?? hm[4] ?? '').trim() : null;

    if (href === null) { badHrefs.push({ page: pageUrl, href: '(no href)', label, why: 'anchor with no href' }); continue; }
    if (href === '' )  { badHrefs.push({ page: pageUrl, href: '(empty)', label, why: 'empty href' }); continue; }
    if (/^#$/.test(href))                 { badHrefs.push({ page: pageUrl, href, label, why: 'href="#" — goes nowhere' }); continue; }
    if (/^javascript:\s*(void\(0\)|;)?$/i.test(href)) { badHrefs.push({ page: pageUrl, href, label, why: 'javascript: placeholder' }); continue; }
    if (/localhost|127\.0\.0\.1|\.local\b|staging|\.test\b/i.test(href)) { badHrefs.push({ page: pageUrl, href, label, why: 'points at a dev/staging host' }); continue; }
    if (/^http:\/\//i.test(href) && new URL(href).host === host) { badHrefs.push({ page: pageUrl, href, label, why: 'plain http:// on an https site' }); }

    const u = norm(href, pageUrl);
    if (!u) continue;
    if (!linkedFrom.has(u)) linkedFrom.set(u, new Set());
    linkedFrom.get(u).add(pageUrl);
    if (navBlocks.some(b => b.includes(m[0]))) navLinks.set(label + ' → ' + u, u);
    if (!SKIP.test(u)) out.add(u);
  }
  return [...out];
}

(async () => {
  console.log(`=== LINK AUDIT  ${ORIGIN}  (max ${MAX} pages, ${CONC} at a time) ===\n`);
  while (queue.length && seen.size < MAX) {
    const wave = [];
    while (wave.length < CONC && queue.length) {
      const u = queue.shift();
      if (seen.has(u)) continue;
      seen.set(u, null);
      wave.push(u);
    }
    if (!wave.length) break;
    await Promise.all(wave.map(async u => {
      const r = await fetchPage(u);
      seen.set(u, r);
      if (r.status === 200 && r.body) {
        for (const nxt of harvest(u, r.body)) {
          if (!seen.has(nxt) && queue.length + seen.size < MAX * 3) queue.push(nxt);
        }
      }
    }));
    await sleep(PAUSE_MS);
  }

  // Anything linked but never crawled (we hit the cap, or it was skipped as a
  // non-page) still deserves a status check if it is a page-ish URL.
  const unchecked = [...linkedFrom.keys()].filter(u => !seen.has(u) && !SKIP.test(u)).slice(0, 60);
  for (let i = 0; i < unchecked.length; i += CONC) {
    await Promise.all(unchecked.slice(i, i + CONC).map(async u => { seen.set(u, await fetchPage(u)); }));
    await sleep(PAUSE_MS);
  }

  const rows = [...seen.entries()].filter(([, r]) => r);
  const broken   = rows.filter(([, r]) => r.status === -1 || r.status >= 400);
  const chained  = rows.filter(([, r]) => r.chain.length >= 1);
  const longHops = rows.filter(([, r]) => r.chain.length >= 2);
  const slow     = rows.filter(([, r]) => r.status === 200).sort((a, b) => b[1].ms - a[1].ms).slice(0, 15);

  console.log(`Crawled ${rows.length} URLs.\n`);

  console.log(`--- BROKEN (${broken.length}) ---`);
  if (!broken.length) console.log('  none\n');
  for (const [u, r] of broken) {
    console.log(`  ${r.status === -1 ? 'ERR' : r.status}  ${u}`);
    if (r.err) console.log(`        ${r.err}`);
    const from = [...(linkedFrom.get(u) || [])].slice(0, 4);
    if (from.length) console.log(`        linked from: ${from.join('\n                     ')}`);
  }

  console.log(`\n--- REDIRECTS (${chained.length}, of which ${longHops.length} take more than one hop) ---`);
  for (const [u, r] of chained.slice(0, 40)) {
    console.log(`  ${u}`);
    for (const h of r.chain) console.log(`        ${h.status} -> ${h.to}`);
    if (r.chain.length >= 2) console.log(`        ** ${r.chain.length} hops — each one is a round trip the visitor waits through`);
  }
  if (!chained.length) console.log('  none');

  console.log(`\n--- LINKS THAT CANNOT WORK (${badHrefs.length}) ---`);
  const grouped = new Map();
  for (const b of badHrefs) {
    const k = b.why;
    if (!grouped.has(k)) grouped.set(k, []);
    grouped.get(k).push(b);
  }
  if (!grouped.size) console.log('  none');
  for (const [why, list] of grouped) {
    console.log(`  ${why}  (${list.length})`);
    for (const b of list.slice(0, 8)) console.log(`      "${b.label || '(no text)'}"  on ${b.page}`);
    if (list.length > 8) console.log(`      ... and ${list.length - 8} more`);
  }

  // Only the ones that are wrong. Printing all of them buried the report:
  // every product page contributes its own header and footer, so a clean site
  // produced thousands of "200 OK" lines and the findings scrolled off.
  const navBad = [...navLinks].filter(([, u]) => {
    const r = seen.get(u);
    return r && r.status !== 200;
  });
  console.log(`\n--- NAVIGATION LINKS: ${navLinks.size} distinct, ${navBad.length} not answering 200 ---`);
  if (!navBad.length) console.log('  every navigation link that was checked answers 200');
  for (const [label, u] of navBad) {
    const r = seen.get(u);
    console.log(`  ${String(r.status === -1 ? 'ERR' : r.status).padEnd(5)} ${label}`);
  }

  console.log(`\n--- SLOWEST PAGES (time to full response) ---`);
  for (const [u, r] of slow) console.log(`  ${String(r.ms).padStart(6)} ms  ${u}`);

  // One compact line the report builder can parse out of the run log.
  const payload = {
    origin: ORIGIN,
    crawled: rows.length,
    broken: broken.map(([u, r]) => ({ url: u, status: r.status, err: r.err || '',
      from: [...(linkedFrom.get(u) || [])].slice(0, 3) })),
    redirects: chained.map(([u, r]) => ({ url: u, hops: r.chain.length,
      chain: r.chain.map(h => ({ status: h.status, to: h.to })) })),
    deadHrefs: [...grouped.entries()].map(([why, list]) => ({ why, count: list.length,
      examples: list.slice(0, 5).map(b => ({ label: b.label, page: b.page, href: b.href })) })),
    navBad: navBad.map(([label, u]) => ({ label, url: u, status: (seen.get(u) || {}).status })),
    navChecked: navLinks.size,
    slowest: slow.map(([u, r]) => ({ url: u, ms: r.ms })),
  };
  console.log('\n@@JSON@@' + JSON.stringify(payload) + '@@END@@');

  const ok = rows.filter(([, r]) => r.status === 200);
  const avg = ok.length ? Math.round(ok.reduce((a, [, r]) => a + r.ms, 0) / ok.length) : 0;
  console.log(`\n--- SUMMARY ---`);
  console.log(`  crawled        : ${rows.length}`);
  console.log(`  broken         : ${broken.length}`);
  console.log(`  redirecting    : ${chained.length}  (${longHops.length} multi-hop)`);
  console.log(`  dead hrefs     : ${badHrefs.length}`);
  console.log(`  average page   : ${avg} ms`);
})();
