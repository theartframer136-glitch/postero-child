/**
 * Whole-site test of the live shop: every page the sitemap knows about, plus
 * every internal page those pages link to, read and checked one by one.
 *
 * Read-only and gentle: three requests at a time with a pause between waves,
 * because this host is CPU-bound and parallel load is what produces its 508s.
 * Pages run through PHP; images and the external links are checked afterwards
 * with range requests, which the static layer answers without PHP.
 *
 * What is checked on every page
 *   - HTTP status, redirect chain, time to first byte
 *   - PHP notices / warnings / fatals printed into the page
 *   - the host's "Checking your browser" interstitial (retried, then reported)
 *   - soft 404s: a 200 that is really "page not found"
 *   - placeholder text left behind by the page builder, lorem ipsum,
 *     unrendered shortcodes, "undefined" / "NaN" / "[object Object]" in copy
 *   - WooCommerce error notices rendered into the page
 *   - title, meta description, canonical, H1 count, noindex on a sitemap URL
 *   - mixed content (http:// assets on an https page), dev-host links,
 *     anchors with no href or href="#"
 *   - product pages: a price, an add-to-cart button, a product image
 *   - category pages: at least one product
 *   - duplicate titles across pages
 *
 * Then, once, across the whole site
 *   - every unique image URL seen (src, data-src, srcset) — does it answer
 *   - every unique external link — does it answer
 *
 * Usage: node tools/audit-full.mjs https://theartframer.us [maxPages] [outDir]
 * Env:   AF_CONC (default 3)  AF_PAUSE_MS (default 250)
 *        AF_MAX_IMAGES (default 3000)  AF_MAX_EXTERNAL (default 300)
 */
import fs from 'node:fs';
import path from 'node:path';

const ORIGIN  = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const MAX     = parseInt(process.argv[3] || '1500', 10);
const OUT     = process.argv[4] || 'audit-out';
const CONC    = parseInt(process.env.AF_CONC || '3', 10);
const PAUSE   = parseInt(process.env.AF_PAUSE_MS || '250', 10);
const MAX_IMG = parseInt(process.env.AF_MAX_IMAGES || '3000', 10);
const MAX_EXT = parseInt(process.env.AF_MAX_EXTERNAL || '300', 10);
const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36 AF-SiteAudit';

const host = new URL(ORIGIN).host;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const t0all = Date.now();

// ── URL hygiene ──────────────────────────────────────────────────────────────
function norm(href, base) {
  try {
    const u = new URL(href, base);
    u.hash = '';
    if (!/^https?:$/.test(u.protocol)) return null;
    return u.toString();
  } catch { return null; }
}
const internal = u => { try { return new URL(u).host === host; } catch { return false; } };

// Not pages, or they would act on the shop, or they are translated copies of
// a page already in the crawl (Transposh ?lang= / /hi/).
const SKIP = /\.(jpe?g|png|gif|webp|avif|svg|ico|css|js|mp4|webm|pdf|zip|woff2?|ttf|xml|txt|json)(\?|$)|\/wp-admin|\/wp-json|\/wp-login|\/xmlrpc|\/feed\/?$|\/embed\/?$|add-to-cart=|\?add_to_cart|add[-_]to[-_]wishlist=|remove_item|woosw|woosc|customer-logout|\?orderby=|\?filter_|\?paged=|\?s=|\?lang=|\?replytocom=|\?share=|\/page\/\d+\/?\?|^https?:\/\/[^/]+\/[a-z]{2}\/|\/wp-content\//i;

// ── fetch with a manual redirect walk ────────────────────────────────────────
async function fetchDoc(url, { timeout = 30000 } = {}) {
  const t0 = Date.now();
  const chain = [];
  let cur = url, status = 0, body = '', ctype = '', ttfb = 0, size = 0;
  for (let hop = 0; hop < 6; hop++) {
    let r;
    const ac = new AbortController();
    const tm = setTimeout(() => ac.abort(), timeout);
    try {
      r = await fetch(cur, { redirect: 'manual', signal: ac.signal,
        headers: { 'User-Agent': UA, 'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language': 'en-US,en;q=0.9' } });
    } catch (e) {
      clearTimeout(tm);
      return { status: -1, ms: Date.now() - t0, ttfb: 0, chain, body: '', final: cur, err: String(e.cause?.code || e.message || e).slice(0, 120) };
    }
    ttfb = Date.now() - t0;
    status = r.status;
    if (status >= 300 && status < 400) {
      clearTimeout(tm);
      const loc = r.headers.get('location');
      await r.arrayBuffer().catch(() => {});
      if (!loc) break;
      const next = norm(loc, cur) || loc;
      chain.push({ from: cur, to: next, status });
      cur = next;
      if (!/^https?:/.test(cur)) break;
      continue;
    }
    ctype = r.headers.get('content-type') || '';
    try {
      if (/text\/html|xml|text\/plain/.test(ctype)) body = await r.text();
      else { const b = await r.arrayBuffer(); size = b.byteLength; }
    } catch (e) {
      clearTimeout(tm);
      return { status, ms: Date.now() - t0, ttfb, chain, body: '', final: cur, ctype, err: 'body: ' + String(e.message).slice(0, 80) };
    }
    clearTimeout(tm);
    size = size || Buffer.byteLength(body);
    break;
  }
  return { status, ms: Date.now() - t0, ttfb, chain, body, final: cur, ctype, size };
}

// One cheap request per asset: ask for the first byte only. Fall back to a
// plain GET for servers that refuse ranges or HEAD-like probes.
async function probe(url, timeout = 15000) {
  const t0 = Date.now();
  for (const hdr of [{ Range: 'bytes=0-0' }, {}]) {
    const ac = new AbortController();
    const tm = setTimeout(() => ac.abort(), timeout);
    try {
      const r = await fetch(url, { redirect: 'follow', signal: ac.signal,
        headers: { 'User-Agent': UA, 'Accept': '*/*', ...hdr } });
      const ctype = r.headers.get('content-type') || '';
      const len = parseInt(r.headers.get('content-range')?.split('/')[1] || r.headers.get('content-length') || '0', 10);
      r.body?.cancel().catch(() => {});
      clearTimeout(tm);
      if (r.status === 416 || (r.status >= 400 && hdr.Range)) continue;
      return { status: r.status, ctype, len, final: r.url, ms: Date.now() - t0 };
    } catch (e) {
      clearTimeout(tm);
      if (!hdr.Range) return { status: -1, err: String(e.cause?.code || e.message || e).slice(0, 100), ms: Date.now() - t0 };
    }
  }
  return { status: -1, err: 'unreachable', ms: Date.now() - t0 };
}

const isChallenge = b => typeof b === 'string' && /Checking your browser|cf-browser-verification|Just a moment\.\.\./.test(b);

// ── sitemap ──────────────────────────────────────────────────────────────────
async function readSitemaps() {
  const found = new Map(); // url -> sitemap it came from
  const index = [`${ORIGIN}/sitemap_index.xml`, `${ORIGIN}/sitemap.xml`, `${ORIGIN}/wp-sitemap.xml`];
  const subs = [];
  let indexUsed = null;
  for (const u of index) {
    const r = await fetchDoc(u);
    if (r.status === 200 && /<(sitemapindex|urlset)/.test(r.body)) {
      indexUsed = u;
      if (/<sitemapindex/.test(r.body)) for (const m of r.body.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)) subs.push(m[1]);
      else subs.push(u);
      break;
    }
  }
  const subStatus = [];
  for (const s of subs) {
    const r = await fetchDoc(s);
    const n = r.status === 200 ? [...r.body.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/g)].length : 0;
    subStatus.push({ sitemap: s, status: r.status, urls: n });
    if (r.status !== 200) continue;
    // image:loc entries live inside <image:image>; only plain <url><loc> are pages.
    for (const blk of r.body.matchAll(/<url>([\s\S]*?)<\/url>/g)) {
      const loc = /<loc>\s*([^<\s]+)\s*<\/loc>/.exec(blk[1]);
      if (loc) { const u = norm(loc[1], ORIGIN); if (u && internal(u) && !found.has(u)) found.set(u, s); }
    }
    await sleep(PAUSE);
  }
  return { indexUsed, subStatus, urls: found };
}

// ── page analysis ────────────────────────────────────────────────────────────
const attr = (tag, name) => { const m = new RegExp(name + '\\s*=\\s*("([^"]*)"|\'([^\']*)\'|([^\\s>]+))', 'i').exec(tag); return m ? (m[2] ?? m[3] ?? m[4] ?? '').trim() : null; };
const decode = s => s.replace(/&amp;/g, '&').replace(/&#0?39;|&apos;/g, "'").replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;|&#160;/g, ' ').replace(/&#8211;/g, '–').replace(/&#8217;/g, '’');

function analyse(url, html) {
  const issues = [];      // {sev, kind, detail}
  const I = (sev, kind, detail) => issues.push({ sev, kind, detail: String(detail).slice(0, 220) });

  // PHP errors are printed before any markup is stripped — keep the raw text.
  const php = html.match(/(?:<b>)?(Notice|Warning|Fatal error|Deprecated|Parse error|Uncaught [A-Za-z]*Error)(?:<\/b>)?:\s+[^<\n]{5,200}?\bin\s+(?:<b>)?\/[^\s<]+\.php(?:<\/b>)?\s+on line\s+(?:<b>)?\d+/g) || [];
  for (const p of [...new Set(php)].slice(0, 5)) I('FAIL', 'php-error', p.replace(/<[^>]+>/g, ''));
  if (/There has been a critical error on this website/.test(html)) I('FAIL', 'php-error', 'WordPress critical error screen');

  // Stripped copy: what a visitor reads.
  const noScript = html.replace(/<script\b[\s\S]*?<\/script>/gi, ' ').replace(/<style\b[\s\S]*?<\/style>/gi, ' ').replace(/<template\b[\s\S]*?<\/template>/gi, ' ').replace(/<noscript\b[\s\S]*?<\/noscript>/gi, ' ').replace(/<!--[\s\S]*?-->/g, ' ');
  const text = decode(noScript.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ');

  const title = decode((/<title[^>]*>([\s\S]*?)<\/title>/i.exec(html) || [, ''])[1].replace(/\s+/g, ' ').trim());
  const desc = (() => { const m = /<meta\b[^>]*name\s*=\s*["']description["'][^>]*>/i.exec(html); return m ? attr(m[0], 'content') : null; })();
  const canonical = (() => { const m = /<link\b[^>]*rel\s*=\s*["']canonical["'][^>]*>/i.exec(html); return m ? attr(m[0], 'href') : null; })();
  const robots = (() => { const m = /<meta\b[^>]*name\s*=\s*["']robots["'][^>]*>/i.exec(html); return m ? (attr(m[0], 'content') || '') : ''; })();
  const h1s = [...noScript.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1>/gi)].map(m => decode(m[1].replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim()).filter(Boolean);
  const viewport = /<meta\b[^>]*name\s*=\s*["']viewport["']/i.test(html);
  const lang = /<html\b[^>]*\blang\s*=/i.test(html);

  if (!title) I('FAIL', 'seo', 'no <title>');
  else if (title.length > 70) I('INFO', 'seo', `title is ${title.length} chars: "${title.slice(0, 60)}…"`);
  if (!desc) I('WARN', 'seo', 'no meta description');
  if (!canonical) I('WARN', 'seo', 'no canonical link');
  if (h1s.length === 0) I('WARN', 'seo', 'no H1');
  if (h1s.length > 1) I('INFO', 'seo', `${h1s.length} H1s: ${h1s.slice(0, 3).map(h => `"${h.slice(0, 40)}"`).join(', ')}`);
  if (!viewport) I('FAIL', 'mobile', 'no viewport meta — cannot be responsive');
  if (!lang) I('INFO', 'a11y', 'no lang attribute on <html>');

  // Soft 404 / error pages served as 200
  if (/error404|\bpage-not-found\b|<title>[^<]*(not found|404)/i.test(html) && /not (be )?found|doesn.t exist|404/i.test(text.slice(0, 4000))) I('FAIL', 'soft-404', `"${title.slice(0, 60)}" rendered as a normal 200`);

  // Builder leftovers and broken rendering
  for (const [re, what] of [
    [/Add Your Heading Text Here/i, 'Elementor default heading left on the page'],
    [/Lorem ipsum/i, 'lorem ipsum placeholder text'],
    [/Your content goes here|Click edit button to change this text|This is a sample page|Sample Page/i, 'placeholder/sample copy'],
    [/\[object Object\]/, '"[object Object]" printed into the page'],
  ]) if (re.test(text)) I('WARN', 'placeholder', what);
  const undef = text.match(/(?:^|[\s(>])(undefined|NaN)(?=[\s.,)<]|$)/g);
  if (undef) {
    const i = text.search(/(?:^|[\s(>])(undefined|NaN)(?=[\s.,)<]|$)/);
    I('WARN', 'placeholder', `"${undef[0].trim()}" appears in the copy: …${text.slice(Math.max(0, i - 50), i + 40)}…`);
  }
  const sc = text.match(/\[(elementor-template|products|product_categor(?:y|ies)|woocommerce_[a-z_]+|vc_[a-z_]+|contact-form-7|et_pb_[a-z_]+|rev_slider|gallery|wpforms|woosw|woosc|woosq|yith_[a-z_]+|hfe_[a-z_]+|instagram-feed|trustindex|ti-widget)[^\]]{0,120}\]/i);
  if (sc) I('WARN', 'shortcode', `unrendered shortcode: ${sc[0].slice(0, 100)}`);
  const wcErr = [...noScript.matchAll(/<(?:ul|div|li)\b[^>]*class\s*=\s*["'][^"']*\b(?:woocommerce-error|is-error)\b[^"']*["'][^>]*>([\s\S]*?)<\/(?:ul|div|li)>/gi)]
    .map(m => decode(m[1].replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim()).filter(t => t.length > 3);
  for (const e of wcErr.slice(0, 2)) I('WARN', 'woo-notice', `error notice rendered: "${e.slice(0, 120)}"`);
  if (text.length < 120) I('WARN', 'empty', `almost no text on the page (${text.length} chars)`);

  // Links and assets
  const internalLinks = new Set(), externalLinks = new Set(), images = new Set();
  const badHref = [];   // {why,label,href}
  const navBlocks = [...noScript.matchAll(/<(nav|header|footer)\b[^>]*>([\s\S]*?)<\/\1>/gi)].map(m => m[2]);
  for (const m of noScript.matchAll(/<a\b([^>]*)>([\s\S]*?)<\/a>/gi)) {
    const label = decode(m[2].replace(/<[^>]*>/g, ' ')).replace(/\s+/g, ' ').trim().slice(0, 48);
    const href = attr(m[1], 'href');
    const inNav = navBlocks.some(b => b.includes(m[0]));
    if (href === null) { badHref.push({ why: 'anchor with no href', label, href: '' }); continue; }
    if (href === '') { badHref.push({ why: 'empty href', label, href }); continue; }
    if (href === '#') { if (!inNav || !/^(menu|english|categories|all category|\+?\d[\d ]+)$/i.test(label)) badHref.push({ why: 'href="#"', label, href }); continue; }
    if (/^javascript:\s*(void\(0\);?|;)?$/i.test(href)) { badHref.push({ why: 'javascript: placeholder', label, href }); continue; }
    if (/^(mailto|tel|sms|whatsapp):/i.test(href)) continue;
    if (/localhost|127\.0\.0\.1|\.local\b|staging\.|\.test\b|wp-staging|\.ddev\./i.test(href)) { badHref.push({ why: 'dev/staging host', label, href }); continue; }
    const u = norm(href, url);
    if (!u) { badHref.push({ why: 'unparseable href', label, href }); continue; }
    if (internal(u)) { if (/^http:\/\//.test(u)) badHref.push({ why: 'plain http:// link to own site', label, href }); internalLinks.add(u); }
    else externalLinks.add(u);
  }
  // Mixed content: anything a browser would fetch over plain http.
  const mixed = [...html.matchAll(/<(img|script|link|iframe|source|video|audio)\b[^>]*\b(?:src|href|data-src|poster)\s*=\s*["']http:\/\/[^"']+["']/gi)]
    .filter(m => m[1].toLowerCase() !== 'link' || /\brel\s*=\s*["'][^"']*(stylesheet|preload|icon|prefetch)/i.test(m[0]))
    .map(m => m[0].slice(0, 120));
  if (mixed.length) I('FAIL', 'mixed-content', `${mixed.length} http:// asset(s), e.g. ${mixed[0]}`);
  for (const m of html.matchAll(/<img\b([^>]*)>/gi)) {
    const a = m[1];
    for (const n of ['src', 'data-src', 'data-lazy-src']) { const v = attr(a, n); if (v && !/^data:/.test(v)) { const u = norm(v, url); if (u) images.add(u); } }
    for (const n of ['srcset', 'data-srcset']) { const v = attr(a, n); if (v) for (const c of v.split(',').slice(0, 2)) { const s = c.trim().split(/\s+/)[0]; if (s && !/^data:/.test(s)) { const u = norm(s, url); if (u) images.add(u); } } }
  }
  for (const m of html.matchAll(/<(?:source|video)\b[^>]*\bposter\s*=\s*["']([^"']+)["']/gi)) { const u = norm(m[1], url); if (u) images.add(u); }
  const imgTotal = (html.match(/<img\b/gi) || []).length;
  const imgNoAlt = (html.match(/<img\b(?![^>]*\balt\s*=)[^>]*>/gi) || []).length;
  if (imgTotal && imgNoAlt / imgTotal > 0.5 && imgNoAlt > 5) I('INFO', 'a11y', `${imgNoAlt} of ${imgTotal} images have no alt attribute`);

  // Page type checks
  const p = new URL(url).pathname;
  const isProduct = /^\/product\//.test(p), isCat = /^\/product-category\//.test(p) || p === '/shop/';
  let product = null;
  if (isProduct) {
    const price = /class="[^"]*\bprice\b[^"]*"|woocommerce-Price-amount/.test(html);
    const atc = /single_add_to_cart_button|add_to_cart_button|\bname="add-to-cart"|variations_form/.test(html);
    const outOfStock = /\b(out-of-stock|Out of stock)\b/.test(html);
    const img = /woocommerce-product-gallery__image|wp-post-image/.test(html);
    const sku = /class="sku"[^>]*>([^<]*)</.exec(html);
    product = { price, atc, outOfStock, img, sku: sku ? sku[1].trim() : '' };
    if (!price) I('FAIL', 'product', 'no price on the product page');
    if (!atc && !outOfStock) I('FAIL', 'product', 'no add-to-cart form or button');
    if (outOfStock) I('INFO', 'product', 'shown as out of stock');
    if (!img) I('WARN', 'product', 'no product gallery image');
    if (/woocommerce-Price-amount[^>]*>[^<]*<bdi>\s*(?:<span[^>]*>[^<]*<\/span>)?\s*0(?:\.00)?\s*<\/bdi>/.test(html) && !/<del\b/.test(html)) I('WARN', 'product', 'price shows as 0');
  }
  if (isCat) {
    const n = (noScript.match(/<li\b[^>]*class="[^"]*\bproduct\b[^"]*type-product/gi) || noScript.match(/\bpost-\d+\b[^"]*\btype-product\b/gi) || []).length;
    if (/No products were found|No products found/i.test(text) || (n === 0 && !/product-category|elementor-widget-wc-archive-products|products columns/.test(html))) I('WARN', 'category', 'no products shown in this category');
  }

  return { title, desc, canonical, robots, h1s, issues, internalLinks: [...internalLinks], externalLinks: [...externalLinks], images: [...images], badHref, product, textLen: text.length, isProduct, isCat };
}

// ── crawl ────────────────────────────────────────────────────────────────────
const pages = new Map();        // url -> record
const linkedFrom = new Map();   // url -> Set(pages)
const allImages = new Map();    // url -> Set(pages)
const allExternal = new Map();  // url -> Set(pages)
const noteLink = (map, u, from) => { if (!map.has(u)) map.set(u, new Set()); const s = map.get(u); if (s.size < 5) s.add(from); };

async function crawlOne(u, fromSitemap) {
  let r = await fetchDoc(u);
  if (r.status === 200 && isChallenge(r.body)) { await sleep(2500); r = await fetchDoc(u); }
  const rec = { url: u, fromSitemap, status: r.status, ms: r.ms, ttfb: r.ttfb, chain: r.chain, final: r.final, ctype: r.ctype || '', size: r.size || 0, err: r.err || '', challenge: false, issues: [], badHref: [] };
  if (r.status === 200 && isChallenge(r.body)) { rec.challenge = true; rec.issues.push({ sev: 'WARN', kind: 'challenge', detail: 'host answered with the "Checking your browser" page twice' }); }
  else if (r.status === 200 && /text\/html/.test(rec.ctype) && r.body) {
    const a = analyse(r.final || u, r.body);
    Object.assign(rec, a);
    if (fromSitemap && /noindex/i.test(a.robots)) rec.issues.push({ sev: 'WARN', kind: 'seo', detail: 'in the sitemap but marked noindex' });
    if (a.canonical && norm(a.canonical, u) && norm(a.canonical, u) !== (r.final || u) && !r.chain.length) rec.issues.push({ sev: 'INFO', kind: 'seo', detail: `canonical points elsewhere: ${a.canonical}` });
    for (const l of a.internalLinks) noteLink(linkedFrom, l, u);
    for (const l of a.images) noteLink(allImages, l, u);
    for (const l of a.externalLinks) noteLink(allExternal, l, u);
  }
  if (r.chain.length >= 2) rec.issues.push({ sev: 'INFO', kind: 'redirect', detail: `${r.chain.length} hops: ${r.chain.map(h => `${h.status}→${h.to}`).join(' ')}` });
  pages.set(u, rec);
  return rec;
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  console.log(`=== FULL SITE TEST  ${ORIGIN}  (max ${MAX} pages, ${CONC} at a time, ${new Date().toISOString()}) ===\n`);

  const sm = await readSitemaps();
  console.log(`Sitemap index : ${sm.indexUsed || 'NOT FOUND'}`);
  for (const s of sm.subStatus) console.log(`  ${String(s.status).padEnd(4)} ${String(s.urls).padStart(5)} urls  ${s.sitemap}`);
  console.log(`  ${sm.urls.size} distinct page URLs in the sitemaps\n`);

  const queue = [ORIGIN + '/', ...sm.urls.keys()].filter((u, i, a) => a.indexOf(u) === i);
  const queued = new Set(queue);
  let waves = 0;
  while (queue.length && pages.size < MAX) {
    const wave = [];
    while (wave.length < CONC && queue.length) { const u = queue.shift(); if (!pages.has(u)) wave.push(u); }
    if (!wave.length) break;
    await Promise.all(wave.map(async u => {
      const rec = await crawlOne(u, sm.urls.has(u));
      for (const nxt of rec.internalLinks || []) {
        if (!queued.has(nxt) && !SKIP.test(nxt) && queued.size < MAX * 3) { queued.add(nxt); queue.push(nxt); }
      }
    }));
    if (++waves % 50 === 0) console.log(`  … ${pages.size} pages checked, ${queue.length} queued, ${Math.round((Date.now() - t0all) / 1000)}s`);
    await sleep(PAUSE);
  }
  // Pages linked but not crawled (cap reached): still worth a status.
  const leftover = [...linkedFrom.keys()].filter(u => !pages.has(u) && !SKIP.test(u)).slice(0, 150);
  for (let i = 0; i < leftover.length; i += CONC) {
    await Promise.all(leftover.slice(i, i + CONC).map(async u => {
      const r = await fetchDoc(u);
      pages.set(u, { url: u, fromSitemap: false, status: r.status, ms: r.ms, ttfb: r.ttfb, chain: r.chain, final: r.final, err: r.err || '', issues: [], badHref: [], statusOnly: true });
    }));
    await sleep(PAUSE);
  }
  // Internal links that the crawl skipped as "acting" URLs still get a status
  // when they are plain pages behind a query string we do not follow.
  console.log(`Pages checked  : ${pages.size}  (${Math.round((Date.now() - t0all) / 1000)}s)\n`);

  // ── images ──
  const imgList = [...allImages.keys()].slice(0, MAX_IMG);
  const imgRes = new Map();
  for (let i = 0; i < imgList.length; i += CONC + 1) {
    await Promise.all(imgList.slice(i, i + CONC + 1).map(async u => { imgRes.set(u, await probe(u)); }));
    if (i % 400 === 0 && i) console.log(`  … ${i} images probed`);
    await sleep(Math.round(PAUSE / 2));
  }
  console.log(`Images probed  : ${imgRes.size} of ${allImages.size} unique\n`);

  // ── external links ──
  const extList = [...allExternal.keys()].filter(u => !/^https?:\/\/(www\.)?(facebook|instagram|twitter|x|linkedin|pinterest|youtube|tiktok|wa\.me|api\.whatsapp)\.(com|me)\b/i.test(u) || true).slice(0, MAX_EXT);
  const extRes = new Map();
  for (let i = 0; i < extList.length; i += 4) {
    await Promise.all(extList.slice(i, i + 4).map(async u => { extRes.set(u, await probe(u, 12000)); }));
  }
  console.log(`External links : ${extRes.size} of ${allExternal.size} unique probed\n`);

  // ── report ──
  const rows = [...pages.values()];
  const broken = rows.filter(r => r.status === -1 || r.status >= 400);
  const brokenSitemap = broken.filter(r => r.fromSitemap);
  const serverErr = rows.filter(r => r.status >= 500);
  const challenged = rows.filter(r => r.challenge);
  const multiHop = rows.filter(r => r.chain && r.chain.length >= 2);
  const redirSitemap = rows.filter(r => r.fromSitemap && r.chain && r.chain.length);
  const phpErr = rows.filter(r => r.issues.some(i => i.kind === 'php-error'));
  const soft404 = rows.filter(r => r.issues.some(i => i.kind === 'soft-404'));
  const mixed = rows.filter(r => r.issues.some(i => i.kind === 'mixed-content'));
  const placeholders = rows.filter(r => r.issues.some(i => ['placeholder', 'shortcode', 'woo-notice', 'empty'].includes(i.kind)));
  const productIssues = rows.filter(r => r.issues.some(i => i.kind === 'product' && i.sev !== 'INFO'));
  const catIssues = rows.filter(r => r.issues.some(i => i.kind === 'category'));
  const seoFail = rows.filter(r => r.issues.some(i => i.kind === 'seo' && i.sev === 'FAIL'));
  const noDesc = rows.filter(r => r.issues.some(i => i.detail === 'no meta description'));
  const noCanon = rows.filter(r => r.issues.some(i => i.detail === 'no canonical link'));
  const noH1 = rows.filter(r => r.issues.some(i => i.detail === 'no H1'));
  const multiH1 = rows.filter(r => r.issues.some(i => /^\d+ H1s/.test(i.detail)));
  const noindexSm = rows.filter(r => r.issues.some(i => /marked noindex/.test(i.detail)));
  const noViewport = rows.filter(r => r.issues.some(i => i.kind === 'mobile'));
  const slow = rows.filter(r => r.status === 200 && !r.statusOnly).sort((a, b) => b.ttfb - a.ttfb);
  const over3s = slow.filter(r => r.ttfb > 3000);
  const okRows = rows.filter(r => r.status === 200);
  const avgTtfb = okRows.length ? Math.round(okRows.reduce((a, r) => a + (r.ttfb || 0), 0) / okRows.length) : 0;
  const titles = new Map();
  for (const r of rows) if (r.title && !r.chain?.length) { if (!titles.has(r.title)) titles.set(r.title, []); titles.get(r.title).push(r.url); }
  const dupTitles = [...titles].filter(([, l]) => l.length > 1);
  const badImgs = [...imgRes].filter(([, r]) => r.status === -1 || r.status >= 400);
  const notImg = [...imgRes].filter(([, r]) => r.status >= 200 && r.status < 300 && r.ctype && !/image|octet-stream/.test(r.ctype));
  const badExt = [...extRes].filter(([, r]) => r.status === -1 || r.status >= 400);
  const extHard = badExt.filter(([, r]) => r.status === 404 || r.status === 410 || r.status >= 500 || r.status === -1);
  const extSoft = badExt.filter(([, r]) => ![404, 410, -1].includes(r.status) && r.status < 500);
  const dead = new Map();
  for (const r of rows) for (const b of r.badHref || []) { const k = b.why + ' | ' + (b.label || '(no text)'); if (!dead.has(k)) dead.set(k, { why: b.why, label: b.label, n: 0, pages: new Set() }); const d = dead.get(k); d.n++; if (d.pages.size < 3) d.pages.add(r.url); }
  const deadGroups = new Map();
  for (const d of dead.values()) { if (!deadGroups.has(d.why)) deadGroups.set(d.why, []); deadGroups.get(d.why).push(d); }

  const from = u => [...(linkedFrom.get(u) || [])].slice(0, 3);
  const sec = (t) => console.log(`\n--- ${t} ---`);
  const list = (arr, f, cap = 40) => { if (!arr.length) return console.log('  none'); arr.slice(0, cap).forEach(x => console.log(f(x))); if (arr.length > cap) console.log(`  … and ${arr.length - cap} more (full list in the JSON artifact)`); };

  sec(`BROKEN PAGES (${broken.length}, ${brokenSitemap.length} of them in the sitemap)`);
  list(broken, r => `  ${String(r.status === -1 ? 'ERR' : r.status).padEnd(4)} ${r.url}${r.err ? '  (' + r.err + ')' : ''}\n        linked from: ${from(r.url).join(' | ') || (r.fromSitemap ? 'sitemap' : '?')}`, 80);
  sec(`SERVER ERRORS 5xx (${serverErr.length})`); list(serverErr, r => `  ${r.status} ${r.url}`);
  sec(`PHP ERRORS PRINTED INTO PAGES (${phpErr.length} pages)`); list(phpErr, r => `  ${r.url}\n        ${r.issues.filter(i => i.kind === 'php-error').map(i => i.detail).join('\n        ')}`);
  sec(`SOFT 404s — "not found" served as 200 (${soft404.length})`); list(soft404, r => `  ${r.url}  ${r.issues.find(i => i.kind === 'soft-404').detail}`);
  sec(`BOT CHALLENGE PAGES (${challenged.length})`); list(challenged, r => `  ${r.url}`);
  sec(`MIXED CONTENT (${mixed.length} pages)`); list(mixed, r => `  ${r.url}\n        ${r.issues.find(i => i.kind === 'mixed-content').detail}`);
  sec(`BROKEN IMAGES (${badImgs.length} of ${imgRes.size} probed)`); list(badImgs, ([u, r]) => `  ${String(r.status === -1 ? 'ERR' : r.status).padEnd(4)} ${u}${r.err ? '  (' + r.err + ')' : ''}\n        on: ${[...(allImages.get(u) || [])].slice(0, 3).join(' | ')}`, 80);
  if (notImg.length) { sec(`IMAGE URLS THAT ANSWER WITH SOMETHING ELSE (${notImg.length})`); list(notImg, ([u, r]) => `  ${r.status} ${r.ctype.split(';')[0]}  ${u}`); }
  sec(`BROKEN EXTERNAL LINKS (${extHard.length} dead, ${extSoft.length} refused the bot — verify by hand)`);
  list(extHard, ([u, r]) => `  ${String(r.status === -1 ? 'ERR' : r.status).padEnd(4)} ${u}${r.err ? '  (' + r.err + ')' : ''}\n        on: ${[...(allExternal.get(u) || [])].slice(0, 3).join(' | ')}`);
  if (extSoft.length) { console.log('  refused / blocked (403, 405, 429, 999 …):'); list(extSoft, ([u, r]) => `  ${String(r.status).padEnd(4)} ${u}\n        on: ${[...(allExternal.get(u) || [])].slice(0, 2).join(' | ')}`, 30); }
  sec(`PRODUCT PAGE PROBLEMS (${productIssues.length})`); list(productIssues, r => `  ${r.url}\n        ${r.issues.filter(i => i.kind === 'product' && i.sev !== 'INFO').map(i => i.detail).join('; ')}`, 60);
  sec(`EMPTY CATEGORIES (${catIssues.length})`); list(catIssues, r => `  ${r.url}`);
  sec(`PLACEHOLDER TEXT / BROKEN RENDERING (${placeholders.length})`); list(placeholders, r => `  ${r.url}\n        ${r.issues.filter(i => ['placeholder', 'shortcode', 'woo-notice', 'empty'].includes(i.kind)).map(i => i.detail).join('\n        ')}`, 60);
  sec(`LINKS THAT CANNOT WORK (grouped; menu toggles excluded)`);
  if (!deadGroups.size) console.log('  none');
  for (const [why, ds] of deadGroups) {
    ds.sort((a, b) => b.n - a.n);
    console.log(`  ${why}  — ${ds.reduce((a, d) => a + d.n, 0)} occurrences, ${ds.length} distinct labels`);
    for (const d of ds.slice(0, 12)) console.log(`      ${String(d.n).padStart(5)}×  "${d.label || '(no text)'}"  e.g. ${[...d.pages][0]}`);
    if (ds.length > 12) console.log(`      … and ${ds.length - 12} more labels`);
  }
  sec(`REDIRECTS: ${multiHop.length} multi-hop, ${redirSitemap.length} sitemap URLs that redirect`);
  list(multiHop, r => `  ${r.url}\n        ${r.chain.map(h => `${h.status} → ${h.to}`).join('\n        ')}`, 25);
  list(redirSitemap, r => `  sitemap: ${r.url} → ${r.chain[r.chain.length - 1].to}`, 25);
  sec(`SEO / HEAD (${seoFail.length} missing title, ${noDesc.length} no description, ${noCanon.length} no canonical, ${noH1.length} no H1, ${multiH1.length} multiple H1, ${noindexSm.length} noindex-in-sitemap, ${noViewport.length} no viewport)`);
  list(seoFail, r => `  no title: ${r.url}`, 20);
  list(noindexSm, r => `  noindex but in sitemap: ${r.url}`, 20);
  list(noH1, r => `  no H1: ${r.url}`, 20);
  list(noDesc, r => `  no description: ${r.url}`, 20);
  list(noCanon, r => `  no canonical: ${r.url}`, 20);
  list(noViewport, r => `  no viewport: ${r.url}`, 20);
  sec(`DUPLICATE TITLES (${dupTitles.length})`); list(dupTitles, ([t, l]) => `  "${t.slice(0, 70)}"  ×${l.length}\n        ${l.slice(0, 4).join('\n        ')}`, 25);
  sec(`SLOW PAGES — time to first byte over 3 s (${over3s.length}); slowest 15`);
  list(slow.slice(0, 15), r => `  ${String(r.ttfb).padStart(6)} ms  ${r.url}`);

  const byType = { page: 0, product: 0, category: 0, post: 0, other: 0 };
  for (const r of rows) { if (r.status !== 200) continue; const p = new URL(r.url).pathname; byType[r.isProduct ? 'product' : r.isCat ? 'category' : /^\/(blog|\d{4})\//.test(p) || /\/blog\//.test(p) ? 'post' : /^\/[^/]+\/?$/.test(p) ? 'page' : 'other']++; }

  const summary = {
    origin: ORIGIN, at: new Date().toISOString(), seconds: Math.round((Date.now() - t0all) / 1000),
    sitemap: { index: sm.indexUsed, subs: sm.subStatus, urls: sm.urls.size },
    pages: rows.length, ok: okRows.length, byType, broken: broken.length, brokenSitemap: brokenSitemap.length, serverErrors: serverErr.length,
    phpErrors: phpErr.length, soft404: soft404.length, challenged: challenged.length, mixedContent: mixed.length,
    imagesProbed: imgRes.size, imagesUnique: allImages.size, brokenImages: badImgs.length,
    externalProbed: extRes.size, externalUnique: allExternal.size, externalDead: extHard.length, externalRefused: extSoft.length,
    productIssues: productIssues.length, emptyCategories: catIssues.length, placeholders: placeholders.length,
    deadHrefs: [...dead.values()].reduce((a, d) => a + d.n, 0), multiHop: multiHop.length, sitemapRedirects: redirSitemap.length,
    noTitle: seoFail.length, noDesc: noDesc.length, noCanonical: noCanon.length, noH1: noH1.length, multiH1: multiH1.length, noindexInSitemap: noindexSm.length,
    dupTitles: dupTitles.length, over3s: over3s.length, avgTtfb,
  };
  console.log(`\n--- SUMMARY ---`);
  for (const [k, v] of Object.entries(summary)) if (typeof v !== 'object') console.log(`  ${k.padEnd(18)}: ${v}`);
  console.log(`  byType            : ${JSON.stringify(byType)}`);

  fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
  fs.writeFileSync(path.join(OUT, 'pages.json'), JSON.stringify(rows.map(r => ({ ...r, internalLinks: undefined, images: undefined, externalLinks: undefined })), null, 1));
  fs.writeFileSync(path.join(OUT, 'images.json'), JSON.stringify(badImgs.map(([u, r]) => ({ url: u, ...r, on: [...(allImages.get(u) || [])] })), null, 1));
  fs.writeFileSync(path.join(OUT, 'external.json'), JSON.stringify([...extRes].map(([u, r]) => ({ url: u, ...r, on: [...(allExternal.get(u) || [])] })), null, 1));
  // A page sample the browser pass can use: URLs of each kind that answered 200.
  const pick = (f, n) => rows.filter(r => r.status === 200 && !r.statusOnly && f(r)).map(r => r.url).slice(0, n);
  const sample = {
    pages: pick(r => !r.isProduct && !r.isCat && /^\/[^/]+\/?$/.test(new URL(r.url).pathname), 60),
    categories: pick(r => r.isCat, 20),
    products: pick(r => r.isProduct, 30),
    posts: pick(r => !r.isProduct && !r.isCat && /\/blog\/|\/\d{4}\/\d{2}\//.test(new URL(r.url).pathname), 10),
    flagged: rows.filter(r => r.issues.some(i => i.sev === 'FAIL')).map(r => r.url).slice(0, 20),
  };
  fs.writeFileSync(path.join(OUT, 'sample.json'), JSON.stringify(sample, null, 1));
  console.log(`\nWrote ${OUT}/summary.json, pages.json, images.json, external.json, sample.json`);
})().catch(e => { console.error('AUDIT CRASHED:', e); process.exit(1); });
