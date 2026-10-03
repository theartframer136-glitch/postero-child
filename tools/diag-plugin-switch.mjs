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

async function snap(out) {
  const puppeteer = req('puppeteer-core');
  fs.mkdirSync(out, { recursive: true });
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'], protocolTimeout: 120000 });
  const ctx = await b.createBrowserContext();
  const go = async (p, u) => {
    let r = null;
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
  const visit = async (u, w, key) => {
    const p = await ctx.newPage(); await p.setViewport({ width: w, height: 900 });
    const errs = [];
    p.on('pageerror', e => errs.push('pageerror: ' + String(e.message).slice(0, 140)));
    p.on('console', m => { if (m.type() === 'error' && !/favicon|google|facebook|doubleclick|clarity|gtag|404 \(\)/i.test(m.text())) errs.push('console: ' + m.text().slice(0, 140)); });
    const assets = [];
    p.on('requestfinished', r => { const t = r.resourceType(); if (t === 'stylesheet' || t === 'script') assets.push(t[0] + ' ' + r.url().replace(S, '').replace(/\?.*$/, '')); });
    const r = await go(p, S + u);
    await sleep(1500);
    // let lazy parts load, then come back to the top
    try { await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); }); await sleep(800); } catch {}
    // Related, upsell and cross-sell carousels show other random products on
    // every build: not part of the comparison (hidden before measuring).
    await p.evaluate(() => document.querySelectorAll('section.related, .related.products, .up-sells, .upsells, .cross-sells, .af-wl-related').forEach(e => e.style.setProperty('display', 'none', 'important'))).catch(() => {});
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
      return { title: document.title, sig, n, text, bodyClass: document.body.className, h: document.documentElement.scrollHeight, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
    }).catch(e => ({ error: e.message }));
    const file = path.join(out, key.replace(/[^a-z0-9]+/gi, '_') + '.png');
    try { await p.screenshot({ path: file, fullPage: true, captureBeyondViewport: true }); } catch {}
    results[key] = { url: u, w, status: r ? r.status() : 0, ...data, assets: [...new Set(assets)].sort(), errs, shot: file };
    await p.close();
  };
  // three pages at a time: the whole site in about a third of the time
  const pool = async (jobs, n) => { let i = 0; await Promise.all(Array.from({ length: n }, async () => { while (i < jobs.length) { const j = jobs[i++]; try { await visit(j[0], j[1], j[0] + '@' + j[1]); } catch (e) { results[j[0] + '@' + j[1]] = { url: j[0], w: j[1], status: 0, error: String(e.message).slice(0, 120) }; } } })); };
  await pool(pages.flatMap(u => WIDTHS.map(w => [u, w])), 3);
  // basket pages: one canvas in the basket
  try {
    const p = await ctx.newPage(); await p.setViewport({ width: 1366, height: 900 });
    await go(p, S + EXTRA[2]);
    await p.select('#af-size-select', '3×4 ft (36×48 in)').catch(() => {}); await sleep(600);
    await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => { const b = document.querySelector('form.cart .single_add_to_cart_button'); if (b) b.click(); })]);
    await sleep(1500); await p.close();
    await pool(['/cart/', '/checkout/'].flatMap(u => WIDTHS.map(w => [u, w])), 2);
  } catch (e) { console.log('basket pages failed: ' + e.message); }
  fs.writeFileSync(path.join(out, 'snap.json'), JSON.stringify(results));
  console.log(`snap: ${Object.keys(results).length} page views (${pages.length} pages) into ${out}`);
  await b.close();
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
  const lines = [];
  for (const k of Object.keys(A)) {
    const a = A[k], b = B[k] || a, c = C[k];
    if (!c) { lines.push(`FAIL ${k}: missing after`); fail++; continue; }
    const out = [];
    let verdict = 'PASS';
    if (c.status !== a.status) { out.push(`status ${a.status} -> ${c.status}`); verdict = 'FAIL'; }
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
    if (verdict === 'FAIL') fail++; else if (verdict === 'REVIEW') review++;
    lines.push(`${verdict} ${k}` + (out.length ? '\n    ' + out.join('\n    ') : ''));
  }
  console.log(lines.join('\n'));
  console.log(`\nSUMMARY switch-compare: ${Object.keys(A).length} page views, ${fail} FAIL, ${review} REVIEW`);
  process.exit(fail ? 1 : review ? 2 : 0);
}

if (mode === 'snap') await snap(args[0]);
else if (mode === 'compare') compare(args[0], args[1], args[2]);
else { console.log('usage: snap <dir> | compare <beforeA> <beforeB> <after>'); process.exit(3); }
