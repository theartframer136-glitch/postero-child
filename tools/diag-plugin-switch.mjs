/**
 * Does the site look and work the same before and after a plugin is switched
 * off (its work moved into the theme)? Owner, 3 Oct: plugins to custom code,
 * "anything will not be effect".
 *
 *   node tools/diag-plugin-switch.mjs snap <out-dir>
 *   node tools/diag-plugin-switch.mjs compare <beforeA> <beforeB> <after>
 *
 * snap: every published page (from the REST API) plus a category, two
 * products, and cart / checkout with a canvas in the basket, at 1366 and 390
 * wide, as a fresh guest. For each it keeps the HTTP status, title, visible
 * text, a signature of every visible element (tag, id and classes, with
 * counts), the stylesheets and scripts loaded, console errors, and a
 * screenshot (PNG on disk).
 *
 * compare: two "before" snapshots show what changes by itself between two
 * visits (sliders, random product grids, live counters); only differences
 * that never appeared between them count. Prints, per page, the text lines
 * and element kinds that appeared or vanished, new console errors, status
 * changes, and how many screenshot pixels changed beyond the before-noise.
 * Exits 1 (FAIL) when a page breaks (status, errors, lost text or elements),
 * 2 (REVIEW) when only small differences remain, 0 (PASS) otherwise.
 * Read-only: a fresh guest basket, no order.
 *
 * Preview mode (preview-switch.yml): with AF_PV (the run's one-time secret)
 * and AF_SKIP (plugin folders, comma separated, or "none") set, every request
 * the browser and this script make to the site carries ?af_pv=&af_skip=, so
 * tools/port-preview-dropin.php (in place as wp-content/db.php for that run)
 * answers it with those plugins unloaded and their theme copies doing the
 * work - for these requests only, never cached; visitors are untouched. A
 * page that does not come back marked as a preview stops the run (exit 4)
 * rather than compare the live site with itself.
 *
 * Re-check: compare writes the page views that FAIL to <after>/fails.json.
 * AF_ONLY=<that file> makes snap visit only those (same sessions, same
 * basket) and makes compare look only at them, so a break that shows twice
 * is told apart from a third-party widget (YouTube, Instagram) that was slow
 * once.
 */
import { createRequire } from 'module';
import fs from 'fs'; import path from 'path';
const req = createRequire(import.meta.url);
const S = 'https://theartframer.us';
const [, , mode, ...args] = process.argv;
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const WIDTHS = [1366, 390];
const EXTRA = [
  '/product-category/digital-canvas-prints/radha-krishna/',
  '/shop/',
  '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/',
  // the three products with a featured video (yith-woocommerce-featured-video)
  '/?p=7802', '/?p=7811', '/?p=8301',
];
const SKIP = /\/(cart|checkout|my-account|order-received|dashboard)\/?$/;
// Hindi (Transposh) and Canadian-dollar (currency switcher) views of the
// same shop pages, each in a browser session of its own so their cookies
// never reach the English / US-dollar pages
const VARIANTS = [
  { tag: 'hi', q: 'lang=hi', pages: ['/', '/shop/', EXTRA[0], EXTRA[2]] },
  { tag: 'cad', q: 'currency=CAD', pages: ['/', '/shop/', EXTRA[0], EXTRA[2]] },
];
// fetched without following redirects: status, Location and who redirected
const REDIRECTS = ['/sitemap.xml', '/wp-sitemap.xml', '/hi/', '/hi/shop/', '/2025/', '/2026/'];
// fetched as they are: robots.txt, sitemaps, the currency switcher's public API
const RAW = ['/robots.txt', '/sitemap_index.xml', '/page-sitemap.xml', '/post-sitemap.xml', '/product-sitemap.xml', '/product_cat-sitemap.xml', '/category-sitemap.xml', '/main-sitemap.xsl', '/wp-json/woocs/v3/currency'];
const withQ = (u, q) => u + (u.includes('?') ? '&' : '?') + q;
const ONLY = process.env.AF_ONLY ? new Set(JSON.parse(fs.readFileSync(process.env.AF_ONLY, 'utf8'))) : null;
const want = (key) => !ONLY || ONLY.has(key);
const PV = process.env.AF_PV || '', PV_SKIP = process.env.AF_SKIP || 'none';
const pvUrl = (u) => (PV && u.startsWith(S) && !u.includes('af_pv=')) ? withQ(u, 'af_pv=' + PV + '&af_skip=' + PV_SKIP) : u;
// the secret and the skip list never count as a difference
const unPv = (x) => JSON.parse(JSON.stringify(x).replace(/[?&]af_pv=[0-9a-f]+(&af_skip=[a-z0-9,-]+)?/g, '').replace(/af_skip=[a-z0-9,-]+/g, ''));

async function snap(out) {
  const puppeteer = req('puppeteer-core');
  fs.mkdirSync(out, { recursive: true });
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'], protocolTimeout: 120000 });
  const ctx = await b.createBrowserContext();
  const go = async (p, u) => {
    let r = null;
    if (PV && !p.__afPv) {
      p.__afPv = true;
      await p.setRequestInterception(true);
      p.on('request', q => { const u2 = pvUrl(q.url()); if (u2 !== q.url()) q.continue({ url: u2 }).catch(() => {}); else q.continue().catch(() => {}); });
    }
    for (let i = 0; i < 3; i++) { try { r = await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
    for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { r = await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
    return r;
  };
  // the page list: every published page, the extras, the shop pages with a basket
  let pages = [];
  try {
    const p = await ctx.newPage(); await go(p, S + '/');
    pages = await p.evaluate(async () => { const o = []; for (let pg = 1; pg < 4; pg++) { const r = await fetch('/wp-json/wp/v2/pages?per_page=100&_fields=link,status&page=' + pg); if (!r.ok) break; const j = await r.json(); if (!j.length) break; j.forEach(x => o.push(new URL(x.link).pathname)); } return o; });
    await p.evaluate(() => { const b = [...document.querySelectorAll('button, a')].find(x => /necessary only/i.test(x.textContent || '')); if (b) b.click(); });
    await p.close();
  } catch (e) { console.log('page list failed: ' + e.message); }
  pages = [...new Set(['/', ...pages.filter(u => !SKIP.test(u)), ...EXTRA])];
  const results = {};
  const notPreview = [];
  const visit = async (u, w, key, c = ctx) => {
    const p = await c.newPage(); await p.setViewport({ width: w, height: 900 });
    const errs = [];
    p.on('pageerror', e => errs.push('pageerror: ' + String(e.message).slice(0, 140)));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|google|facebook|doubleclick|clarity|gtag|404 \(\)/i.test(m.text())) errs.push('console: ' + m.text().slice(0, 140)); });
    const assets = [];
    p.on('requestfinished', r => { const t = r.resourceType(); if (t === 'stylesheet' || t === 'script') assets.push(t[0] + ' ' + r.url().replace(S, '').replace(/\?.*$/, '')); });
    // a stylesheet or script of the site's own that does not load is a break,
    // even though the console line for it ("404 ()") is filtered above
    p.on('response', r => { try { const t = r.request().resourceType(); if ((t === 'stylesheet' || t === 'script') && r.url().startsWith(S) && r.status() >= 400) errs.push(`asset ${r.status()}: ` + r.url().replace(S, '').replace(/\?.*$/, '')); } catch {} });
    p.on('requestfailed', r => { try { const t = r.resourceType(); if ((t === 'stylesheet' || t === 'script') && r.url().startsWith(S) && !/ERR_ABORTED/.test((r.failure() || {}).errorText || '')) errs.push('asset failed: ' + r.url().replace(S, '').replace(/\?.*$/, '')); } catch {} });
    // the checkout's order-summary request itself (wc-ajax=update_order_review):
    // what the server sent back, so a summary that does not refresh says why
    // (a broken answer) or shows it did and only the page's signal was missed
    const review = [], asked = new Map();
    p.on('request', q => { if (/[?&]wc-ajax=update_order_review/.test(q.url())) asked.set(q, Date.now()); });
    p.on('response', r => {
      if (!/[?&]wc-ajax=update_order_review/.test(r.url())) return;
      const st = r.status(), ms = Date.now() - (asked.get(r.request()) || Date.now());
      r.text().then(body => {
        let ok = false, note = '';
        try { const j = JSON.parse(body); ok = !!(j && j.fragments) && j.result !== 'failure'; if (!ok) note = 'answer without the summary: ' + body.replace(/\s+/g, ' ').slice(0, 160); }
        catch { note = 'answer is not JSON: ' + body.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200); }
        review.push({ st, ok, note, ms });
      }).catch(() => review.push({ st, ok: false, note: 'answer unreadable', ms }));
    });
    // WooCommerce's checkout refreshes its order summary by itself once the
    // page is up (updated_checkout): counted, so a checkout that never does
    // with the plugins unloaded shows
    await p.evaluateOnNewDocument(() => {
      window.__afWc = 0;
      let n = 0;
      const hook = () => { if (window.jQuery) { window.jQuery(document.body).on('updated_checkout', () => { window.__afWc++; }); return; } if (++n < 400) setTimeout(hook, 50); };
      hook();
    }).catch(() => {});
    const r = await go(p, S + u);
    await sleep(1500);
    // the Instagram feed fills itself from two REST calls after load (slow on
    // a freshly purged cache): wait until it says it is done before measuring
    const feedDone = () => p.waitForFunction(() => [...document.querySelectorAll('[id^="instagram-gallery-feed-"]')].every(e => e.classList.contains('loaded') || e.querySelector('.instagram-gallery__alert, [class*="alert"]')), { timeout: 30000 }).catch(() => {});
    await feedDone();
    // let lazy parts load, then come back to the top
    try { await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); }); await sleep(800); } catch {}
    await feedDone();
    // a checkout or basket still behind WooCommerce's grey loading shade is
    // measured once it has lifted (and the checkout once its order summary
    // has refreshed); one that never does is recorded as such
    const checkout = await p.evaluate(async () => {
      if (!document.querySelector('form.checkout, form.woocommerce-cart-form')) return null;
      const shade = () => [...document.querySelectorAll('.blockUI.blockOverlay')].filter(e => e.getBoundingClientRect().width > 0).length;
      const isCheckout = !!document.querySelector('form.checkout');
      for (let i = 0; i < 40 && (shade() || (isCheckout && !window.__afWc)); i++) await new Promise(r => setTimeout(r, 500));
      return { refreshed: isCheckout ? window.__afWc || 0 : null, shade: shade() };
    }).catch(() => null);
    if (checkout && checkout.refreshed !== null) {
      await sleep(300);
      const okN = review.filter(x => x.ok).length, bad = review.find(x => !x.ok);
      checkout.asked = Math.max(asked.size, review.length); checkout.answered = okN; checkout.ms = review.map(x => x.ms);
      if (bad) checkout.bad = (bad.st !== 200 ? 'HTTP ' + bad.st + ', ' : '') + bad.note;
      // the server sent the refreshed summary: the page's signal was only missed
      if (!checkout.refreshed && okN) checkout.refreshed = okN;
    }
    // Related, upsell and cross-sell carousels show other random products on
    // every build: not part of the comparison (hidden before measuring).
    // The "Recently Viewed" strip (.af-recent) lists whatever this browser
    // session opened before, so it depends on the order the pages were visited
    // in: hidden too.
    await p.evaluate(() => document.querySelectorAll('section.related, .related.products, .up-sells, .upsells, .cross-sells, .af-wl-related, .af-recent').forEach(e => e.style.setProperty('display', 'none', 'important'))).catch(() => {});
    // back at the top for real before measuring: on a long page and a busy
    // server the page can still be on its way back (5 Oct, phone home page:
    // header still stuck, more of the page lazy-loaded, screenshot from
    // further down), so it is asked again until it stays at 0 and a frame
    // has been drawn (the page's own scroll handlers have seen it). Where it
    // will not stay, the comparison says so.
    const top = await p.evaluate(async () => {
      const frame = () => Promise.race([new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r))), new Promise(r => setTimeout(r, 500))]);
      for (let i = 0; i < 10; i++) {
        if (window.scrollY === 0) { await frame(); if (window.scrollY === 0) break; }
        window.scrollTo(0, 0); await new Promise(r => setTimeout(r, 200));
      }
      return Math.round(window.scrollY);
    }).catch(() => -1);
    const data = await p.evaluate(() => {
      const vis = (el) => { const s = getComputedStyle(el); if (s.display === 'none' || s.visibility === 'hidden' || +s.opacity === 0) return false; const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0; };
      const sig = {};
      let n = 0;
      for (const el of document.body.querySelectorAll('*')) {
        if (['SCRIPT', 'STYLE', 'LINK', 'META', 'NOSCRIPT', 'BR'].includes(el.tagName)) continue;
        if (!vis(el)) continue;
        n++;
        // state classes (slider position, lazy-load done, animation run) and
        // ids made fresh on every page build are not part of what is shown
        const cls = [...el.classList].filter(c => !/^(swiper-slide-(active|next|prev|duplicate|visible|fully-visible)|slick-(active|current|cloned)|is-|active$|elementor-element-[0-9a-f]{6,}|e-con-inner|lazy|loaded|animated|fadeIn|e-lazyloaded|e-lazy|elementor-invisible|woosq-btn-\d|woosw-btn-\d|post-\d)/.test(c)).sort().join('.');
        const k = el.tagName.toLowerCase() + (el.id && !/\d/.test(el.id) ? '#' + el.id : '') + (cls ? '.' + cls : '');
        sig[k] = (sig[k] || 0) + 1;
      }
      const text = document.body.innerText.split('\n').map(s => s.replace(/\s+/g, ' ').trim()).filter(Boolean);
      // what search engines read: title, meta tags, canonical/alternate links, JSON-LD
      const head = [];
      head.push('title=' + document.title);
      document.querySelectorAll('meta[name], meta[property], meta[itemprop]').forEach(m => {
        const k = m.getAttribute('name') || m.getAttribute('property') || m.getAttribute('itemprop');
        if (/^(viewport|generator|csrf|google-site-verification|msapplication|theme-color|af-port-preview|translation-stats)$/i.test(k)) return;
        head.push('meta ' + k + '=' + (m.getAttribute('content') || '').replace(/\s+/g, ' ').trim());
      });
      document.querySelectorAll('link[rel="canonical"], link[rel="alternate"], link[rel="prev"], link[rel="next"], link[rel="shortlink"]').forEach(l => head.push('link ' + l.rel + (l.hreflang ? '[' + l.hreflang + ']' : '') + (l.type ? '(' + l.type + ')' : '') + '=' + l.getAttribute('href')));
      document.querySelectorAll('script[type="application/ld+json"]').forEach(sc => head.push('ld+json ' + sc.textContent.replace(/\s+/g, ' ').trim()));
      head.sort();
      // a server file path in the page (a URL built from a path that did not map)
      const leak = (document.documentElement.outerHTML.match(/\/home\/u\d+\/[^"' <)]*/) || [''])[0].slice(0, 120);
      const pvm = document.querySelector('meta[name="af-port-preview"]');
      // a page whose gallery is a YouTube video: the player is a third party
      // and does not always come up on the test machine
      const yt = !!document.querySelector('iframe[src*="youtube"], .ywcfav-video, [class*="ywcfav"], #ywcfav_video');
      // each top-level page section shown (Elementor containers and
      // sections, header and footer templates included): where it is, how
      // tall, how many pictures, which widgets, so a change can be placed
      const sections = [...document.querySelectorAll('.elementor-element.e-parent, .elementor-top-section')].filter(vis).map(e => {
        const b = e.getBoundingClientRect();
        return { id: e.getAttribute('data-id') || '', y: Math.round(b.top + window.scrollY), h: Math.round(b.height),
          img: [...e.querySelectorAll('img')].filter(vis).length,
          w: [...new Set([...e.querySelectorAll('[data-widget_type]')].filter(vis).map(x => x.getAttribute('data-widget_type').replace(/\.default$/, '')))].sort().join(',').slice(0, 160) };
      });
      return { pv: pvm ? pvm.getAttribute('content') : '', yt, title: document.title, head, sig, n, text, leak, sections, lang: document.documentElement.getAttribute('lang') || '', bodyClass: document.body.className, h: document.documentElement.scrollHeight, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
    }).catch(e => ({ error: e.message }));
    if (data && data.leak) errs.push('server path in page: ' + data.leak);
    // a preview is marked by the meta tag (naming what was unloaded) or, on
    // pages without a <head> of WordPress's own, the response header; an
    // access-denied page (wp_die 401/403) is the same whatever is loaded
    const st = r ? r.status() : 0, pvHdr = r ? (r.headers()['x-af-port-preview'] || '') : '';
    if (PV && data && !data.error && data.pv !== PV_SKIP && !(data.pv === '' && (pvHdr === 'on' || st === 401 || st === 403 || st === 508))) notPreview.push(key + ' (marker: ' + (data.pv || 'none found') + ', status ' + st + ')');
    if (data && data.lang) data.head = [...(data.head || []), 'html lang=' + data.lang].sort();
    const file = path.join(out, key.replace(/[^a-z0-9]+/gi, '_') + '.png');
    try { await p.screenshot({ path: file, fullPage: true, captureBeyondViewport: true }); } catch {}
    results[key] = unPv({ url: u, w, status: r ? r.status() : 0, top, checkout, ...data, assets: [...new Set(assets)].sort(), errs: [...errs], shot: file });
    await p.close();
  };
  // three pages at a time: the whole site in about a third of the time
  const pool = async (jobs, n, c = ctx) => { jobs = jobs.filter(j => want(j[0] + '@' + j[1])); let i = 0; await Promise.all(Array.from({ length: n }, async () => { while (i < jobs.length) { const j = jobs[i++]; try { await visit(j[0], j[1], j[0] + '@' + j[1], c); } catch (e) { results[j[0] + '@' + j[1]] = { url: j[0], w: j[1], status: 0, error: String(e.message).slice(0, 120) }; } } })); };
  await pool(pages.flatMap(u => WIDTHS.map(w => [u, w])), 3);
  // basket pages: one canvas in the basket
  const basket = async (c, q) => {
    if (!['/cart/', '/checkout/'].some(u => WIDTHS.some(w => want((q ? withQ(u, q) : u) + '@' + w)))) return;
    const p = await c.newPage(); await p.setViewport({ width: 1366, height: 900 });
    await go(p, S + (q ? withQ(EXTRA[2], q) : EXTRA[2]));
    await p.select('#af-size-select', '3×4 ft (36×48 in)').catch(() => {}); await sleep(600);
    await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => { const b = document.querySelector('form.cart .single_add_to_cart_button'); if (b) b.click(); })]);
    await sleep(1500); await p.close();
    await pool(['/cart/', '/checkout/'].map(u => q ? withQ(u, q) : u).flatMap(u => WIDTHS.map(w => [u, w])), 2, c);
  };
  try { await basket(ctx, ''); } catch (e) { console.log('basket pages failed: ' + e.message); }
  // the Hindi and Canadian-dollar views, each with its own cookies and basket
  for (const v of VARIANTS) {
    if (ONLY && ![...ONLY].some(k => k.includes(v.q))) continue;
    const vc = await b.createBrowserContext();
    try {
      await pool(v.pages.map(u => withQ(u, v.q)).flatMap(u => WIDTHS.map(w => [u, w])), 3, vc);
      await basket(vc, v.q);
    } catch (e) { console.log(v.tag + ' pages failed: ' + e.message); }
    await vc.close().catch(() => {});
  }
  // what search engines and apps fetch besides pages
  const UA = { 'user-agent': 'Mozilla/5.0 (X11; Linux x86_64) Chrome/124 Safari/537.36' };
  for (const u of RAW.filter(u => want('raw ' + u))) {
    try { const r = await fetch(pvUrl(S + withQ(u, 'afsw=' + Date.now())), { headers: UA }); results['raw ' + u] = unPv({ url: u, w: 0, raw: true, status: r.status, body: (await r.text()).replace(/[?&]afsw=\d+/g, '') }); }
    catch (e) { results['raw ' + u] = { url: u, w: 0, raw: true, status: 0, body: '' }; }
  }
  for (const u of REDIRECTS.filter(u => want('redirect ' + u))) {
    try {
      const r = await fetch(pvUrl(S + u), { headers: UA, redirect: 'manual' });
      results['redirect ' + u] = unPv({ url: u, w: 0, raw: true, status: r.status, body: [r.headers.get('location') || '', r.headers.get('x-redirect-by') || ''].join(' | ') });
    } catch (e) { results['redirect ' + u] = { url: u, w: 0, raw: true, status: 0, body: '' }; }
  }
  fs.writeFileSync(path.join(out, 'snap.json'), JSON.stringify(results));
  console.log(`snap: ${Object.keys(results).length} page views (${pages.length} pages) into ${out}` + (PV ? ` (preview, plugins unloaded: ${PV_SKIP})` : ''));
  await b.close();
  if (PV && notPreview.length) { console.log(`NOT A PREVIEW (${notPreview.length}): ` + notPreview.slice(0, 10).join(', ')); process.exit(4); }
}

function pixels(fa, fb) {
  try {
    const { PNG } = req('pngjs'); const pm = req('pixelmatch'); const pixelmatch = pm.default || pm;
    const a = PNG.sync.read(fs.readFileSync(fa)), b = PNG.sync.read(fs.readFileSync(fb));
    const w = Math.min(a.width, b.width), h = Math.min(a.height, b.height);
    const crop = (img) => { const o = new PNG({ width: w, height: h }); PNG.bitblt(img, o, 0, 0, w, h, 0, 0); return o; };
    const A = crop(a), B = crop(b), D = new PNG({ width: w, height: h });
    const n = pixelmatch(A.data, B.data, D.data, w, h, { threshold: 0.12 });
    // changed rows, as bands, to say where on the page
    const rows = []; for (let y = 0; y < h; y++) { let c = 0; for (let x = 0; x < w; x++) { const i = (y * w + x) * 4; if (D.data[i] === 255 && D.data[i + 1] === 0) c++; } rows.push(c); }
    return { n, total: w * h, hDiff: a.height - b.height, rows };
  } catch (e) { return { error: e.message }; }
}

function compare(fa, fb, fc) {
  const A = JSON.parse(fs.readFileSync(path.join(fa, 'snap.json'))), B = JSON.parse(fs.readFileSync(path.join(fb, 'snap.json'))), C = JSON.parse(fs.readFileSync(path.join(fc, 'snap.json')));
  let fail = 0, review = 0;
  const lines = [], failed = [];
  for (const k of Object.keys(A).filter(want)) {
    const a = A[k], b = B[k] || a, c = C[k];
    if (!c) { lines.push(`FAIL ${k}: missing after`); fail++; failed.push(k); continue; }
    const out = [];
    let verdict = 'PASS';
    if (c.status !== a.status) { out.push(`status ${a.status} -> ${c.status}`); verdict = 'FAIL'; }
    if (a.raw) {
      // robots.txt / sitemaps: identical in both befores but different after = FAIL
      if ((a.body || '') === (b.body || '') && (a.body || '') !== (c.body || '')) {
        const la = (a.body || '').split('\n'), lc = (c.body || '').split('\n');
        const gone = la.filter(x => !lc.includes(x)).slice(0, 4), added = lc.filter(x => !la.includes(x)).slice(0, 4);
        out.push('content changed: -' + JSON.stringify(gone).slice(0, 300) + ' +' + JSON.stringify(added).slice(0, 300)); verdict = 'FAIL';
      }
      if (verdict === 'FAIL') fail++; lines.push(`${verdict} ${k}` + (out.length ? '\n    ' + out.join('\n    ') : '')); continue;
    }
    // head tags search engines read: stable in both befores but different after = FAIL
    if (a.head && b.head && c.head) {
      const ha = new Set(a.head), hb = new Set(b.head), hc = new Set(c.head);
      const hg = [...ha].filter(x => hb.has(x) && !hc.has(x)), hn = [...hc].filter(x => !ha.has(x) && !hb.has(x));
      if (hg.length || hn.length) { out.push('head (SEO) changed: -' + JSON.stringify(hg.slice(0, 4)).slice(0, 400) + ' +' + JSON.stringify(hn.slice(0, 4)).slice(0, 400)); verdict = 'FAIL'; }
    }
    // text: lines present in both befores but gone after, or new after (not in either before)
    const ta = new Set(a.text || []), tb = new Set(b.text || []), tc = new Set(c.text || []);
    const gone = [...ta].filter(t => tb.has(t) && !tc.has(t));
    const added = [...tc].filter(t => !ta.has(t) && !tb.has(t));
    if (gone.length) { out.push(`text gone (${gone.length}): ` + gone.slice(0, 8).map(t => JSON.stringify(t.slice(0, 70))).join(' | ')); if (gone.length > 2) verdict = 'FAIL'; else if (verdict === 'PASS') verdict = 'REVIEW'; }
    // a page whose text changes by itself between two visits (random products,
    // live counters) may show that many new lines after too
    const dyn = [...ta].filter(t => !tb.has(t)).length + [...tb].filter(t => !ta.has(t)).length;
    if (added.length > dyn) { out.push(`text new (${added.length}, ${dyn} change by themselves): ` + added.slice(0, 8).map(t => JSON.stringify(t.slice(0, 70))).join(' | ')); if (verdict === 'PASS') verdict = 'REVIEW'; }
    // element kinds: counts stable between the befores that changed after
    const sa = a.sig || {}, sb = b.sig || {}, sc = c.sig || {};
    const keys = new Set([...Object.keys(sa), ...Object.keys(sc)]);
    const sigd = [];
    for (const s of keys) { const x = sa[s] || 0, y = sb[s] || 0, z = sc[s] || 0; if (x === y && z !== x) sigd.push(`${s} ${x}->${z}`); }
    if (sigd.length) { out.push(`elements changed (${sigd.length}): ` + sigd.slice(0, 12).join(', ')); if (sigd.length > 6 && verdict !== 'FAIL') verdict = 'REVIEW'; }
    // which page sections those are: same in both befores, different after
    // (height, pictures shown, widgets shown), or shown only on one side
    const secOf = (x) => new Map((x.sections || []).filter(q => q.id).map(q => [q.id, q]));
    const ma = secOf(a), mb = secOf(b), mc = secOf(c), secd = [];
    for (const [id, x] of ma) {
      const y = mb.get(id), z = mc.get(id);
      if (!y || x.h !== y.h || x.img !== y.img || x.w !== y.w) continue;
      if (!z) { secd.push(`#${id} at y=${x.y} (${x.h}px, ${x.w || 'no widgets'}) not shown after`); continue; }
      const ch = [];
      if (z.h !== x.h) ch.push(`height ${x.h}->${z.h}`);
      if (z.img !== x.img) ch.push(`pictures ${x.img}->${z.img}`);
      if (z.w !== x.w) { const wa = new Set(x.w.split(',')), wz = new Set(z.w.split(',')); ch.push(`widgets -[${[...wa].filter(q => q && !wz.has(q)).join(' ')}] +[${[...wz].filter(q => q && !wa.has(q)).join(' ')}]`); }
      if (ch.length) secd.push(`#${id} at y=${z.y}: ${ch.join(', ')} (${z.w || 'no widgets'})`);
    }
    for (const [id, z] of mc) if (!ma.has(id) && !mb.has(id)) secd.push(`#${id} at y=${z.y} (${z.h}px, ${z.w || 'no widgets'}) shown only after`);
    if (secd.length) out.push(`sections changed (${secd.length}): ` + secd.slice(0, 6).join(' | '));
    // a kind of element that was on the page in both befores and is gone
    // completely after (not just restyled: no element of that tag and first
    // class / id is left at all) is a section that stopped rendering
    const vanished = [];
    for (const s of keys) {
      const x = sa[s] || 0, y = sb[s] || 0, z = sc[s] || 0;
      if (!(x > 0 && x === y && z === 0)) continue;
      // scroll position and carousel state, not page content: a stuck header,
      // the back-to-top button, slider arrows and dots, screen-reader labels
      if (/stuck|sticky|blockUI|blockOverlay|blockMsg|af-qp|swiper-button|swiper-pagination|slick-|elementor-screen-only|chevron|lightbox|tooltip|af-recent/.test(s)) continue;
      // on a page with a YouTube product video the gallery box is sized by the
      // player; when YouTube did not answer the test machine it measures 0px
      if (a.yt && /woocommerce-product-gallery|ywcfav|^div\.images\b/.test(s)) continue;
      const m = s.match(/^([a-z0-9]+)(#[^.]+)?(?:\.([^.]+))?/); if (!m) continue;
      const base = m[1] + (m[2] || '') , cls = m[3] || '';
      const still = Object.keys(sc).some(k => (sc[k] || 0) > 0 && k.startsWith(base) && (cls === '' || k.split('.').includes(cls)));
      if (!still) vanished.push(s);
    }
    if (vanished.length) { out.push(`gone entirely (${vanished.length}): ` + vanished.slice(0, 8).join(', ')); verdict = 'FAIL'; }
    // a page that would not stay at the top when measured: its element and
    // screenshot differences may be that, not the plugins
    // (a few pixels is the page settling, not moving: left out)
    const tops = [a.top, b.top, c.top].map(t => t || 0);
    if (tops.some(t => t === -1 || t > 20)) {
      const far = t => t === -1 || t > 120, before = far(tops[0]) || far(tops[1]), after = far(tops[2]);
      out.push(`not at the top when measured (before, before, after): ${tops.map(t => t === -1 ? '?' : t + 'px').join(', ')}` + (before && after ? ' (the page moves itself down either way)' : ''));
      if (after && !before && verdict === 'PASS') verdict = 'REVIEW';
    }
    // the checkout's order summary: refreshed itself in both befores, not after;
    // or the loading shade still up after 20s where it had lifted before
    if (a.checkout && b.checkout && c.checkout) {
      if (a.checkout.refreshed && b.checkout.refreshed && !c.checkout.refreshed) { out.push('the checkout\'s order summary did not refresh (it did in both befores)' + (c.checkout.asked === undefined ? '' : !c.checkout.asked ? ': the page never asked the server for it' : c.checkout.bad ? ': the server\'s answer: ' + c.checkout.bad : ': the server answered ' + c.checkout.answered + ' of ' + c.checkout.asked + ' times' + (c.checkout.ms && c.checkout.ms.length ? ' (in ' + c.checkout.ms.join(', ') + ' ms)' : ''))); verdict = 'FAIL'; }
      else if (c.checkout.bad && !a.checkout.bad && !b.checkout.bad) out.push('one order-summary answer was not usable (the summary still refreshed): ' + c.checkout.bad);
      if (!a.checkout.shade && !b.checkout.shade && c.checkout.shade) { out.push('WooCommerce\'s loading shade still up after 20s (it had lifted in both befores)'); verdict = 'FAIL'; }
    } else if (a.checkout && b.checkout && !c.checkout && c.status === 200) { out.push('no checkout or basket form after (there was before)'); verdict = 'FAIL'; }
    if ((a.bodyClass || '') !== (c.bodyClass || '') && (a.bodyClass || '') === (b.bodyClass || '')) { const x = new Set((a.bodyClass || '').split(/\s+/)), z = new Set((c.bodyClass || '').split(/\s+/)); out.push(`body classes: -[${[...x].filter(q => !z.has(q)).join(' ')}] +[${[...z].filter(q => !x.has(q)).join(' ')}]`); }
    const newErr = (c.errs || []).filter(e => !(a.errs || []).includes(e) && !(b.errs || []).includes(e));
    if (newErr.length) { out.push('new errors: ' + newErr.slice(0, 4).join(' | ')); verdict = 'FAIL'; }
    if ((c.overflow || 0) > 2 && (a.overflow || 0) <= 2) { out.push(`sideways scroll ${c.overflow}px`); verdict = 'FAIL'; }
    const assetsGone = (a.assets || []).filter(x => !(c.assets || []).includes(x)), assetsNew = (c.assets || []).filter(x => !(a.assets || []).includes(x) && !(b.assets || []).includes(x));
    if (assetsGone.length || assetsNew.length) out.push(`files: -${assetsGone.length} +${assetsNew.length}` + (assetsGone.length ? ' (gone: ' + assetsGone.slice(0, 6).join(', ') + ')' : '') + (assetsNew.length ? ' (new: ' + assetsNew.slice(0, 4).join(', ') + ')' : ''));
    // pixels beyond what changes between two visits anyway
    if (a.shot && b.shot && c.shot && fs.existsSync(a.shot) && fs.existsSync(c.shot)) {
      const noise = pixels(a.shot, b.shot), d = pixels(a.shot, c.shot);
      if (!d.error && !noise.error) {
        let extra = 0; const bands = [];
        for (let y = 0; y < d.rows.length; y++) { const ex = Math.max(0, d.rows[y] - (noise.rows[y] || 0)); if (ex > 3) { extra += ex; if (!bands.length || y - bands[bands.length - 1][1] > 20) bands.push([y, y]); else bands[bands.length - 1][1] = y; } }
        const pct = 100 * extra / d.total;
        if (pct > 0.05 || d.hDiff !== noise.hDiff) out.push(`screenshot: ${pct.toFixed(2)}% pixels changed beyond noise, height ${d.hDiff === 0 ? 'same' : (d.hDiff > 0 ? 'shorter by ' : 'taller by ') + Math.abs(d.hDiff) + 'px'}, at y=` + bands.slice(0, 6).map(([s, e]) => s + '-' + e).join(','));
        if (pct > 0.6 && verdict === 'PASS') verdict = 'REVIEW';
      }
    }
    if (verdict === 'FAIL') { fail++; failed.push(k); } else if (verdict === 'REVIEW') review++;
    lines.push(`${verdict} ${k}` + (out.length ? '\n    ' + out.join('\n    ') : ''));
  }
  console.log(lines.join('\n'));
  fs.writeFileSync(path.join(fc, 'fails.json'), JSON.stringify(failed));
  console.log(`\nSUMMARY switch-compare: ${Object.keys(A).filter(want).length} page views, ${fail} FAIL, ${review} REVIEW`);
  process.exit(fail ? 1 : review ? 2 : 0);
}

if (mode === 'snap') await snap(args[0]);
else if (mode === 'compare') compare(args[0], args[1], args[2]);
else { console.log('usage: snap <dir> | compare <beforeA> <beforeB> <after>'); process.exit(3); }
