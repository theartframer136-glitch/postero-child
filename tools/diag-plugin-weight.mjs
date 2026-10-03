/**
 * What each plugin costs the browser on each key page, live.
 *
 * Owner, 3 Oct: the plugins "take too much time to load". For each page this
 * loads it twice in a real Chrome with the browser cache off: once clean, for
 * the timings (first byte, first paint, largest paint, DOM ready, load) and
 * Chrome's own accounting of script, style and layout time; once with
 * coverage on, to learn how much of each stylesheet and script the page
 * actually used. Every file is charged to its owner (plugin, theme, core,
 * external) so the heavy and the unused ones have a name.
 *
 * Read-only: a fresh guest session, one canvas added to the basket so the
 * cart and checkout have a line, no order.
 *
 *   node tools/diag-plugin-weight.mjs
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const PAGES = [
  ['home', '/'], ['shop', '/shop/'], ['category', '/product-category/digital-canvas-prints/radha-krishna/'],
  ['product', PRODUCT], ['ADD'], ['cart', '/cart/'], ['checkout', '/checkout/'], ['wishlist', '/wishlist/'], ['about', '/about-us/'], ['blog', '/blog/'],
];
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const kb = (b) => (b / 1024).toFixed(0);
const pct = (u, t) => t ? Math.round(100 * u / t) + '%' : '-';
function owner(u) {
  if (!u) return 'unknown';
  let m;
  if ((m = u.match(/\/wp-content\/mu-plugins\/([^/]+)/))) return 'mu-plugin: ' + m[1];
  if ((m = u.match(/\/wp-content\/plugins\/([^/]+)\//))) return 'plugin: ' + m[1];
  if ((m = u.match(/\/wp-content\/themes\/([^/]+)\//))) return 'theme: ' + m[1];
  if (/\/wp-includes\//.test(u)) return 'core';
  if (/\/wp-content\/uploads\/elementor\//.test(u)) return 'plugin: elementor (generated css)';
  if (/\/wp-content\/uploads\/essential-addons/.test(u)) return 'plugin: essential-addons (generated css)';
  if (/\/wp-content\/uploads\//.test(u)) return 'uploads (images, files)';
  if (u.startsWith(S)) return 'site (html, ajax, other)';
  try { return 'external: ' + new URL(u).host; } catch { return 'other'; }
}
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const ctx = await b.createBrowserContext();
async function go(p, u, wait = 'networkidle2') {
  for (let i = 0; i < 3; i++) { try { return await p.goto(u, { waitUntil: wait, timeout: 90000 }); } catch { await sleep(3000); } }
  return null;
}
async function passBotCheck(p) {
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) return; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
}
// warm the session (bot check, cookies) on a page that is not measured
{ const p = await ctx.newPage(); await p.setViewport({ width: 1366, height: 900 }); await go(p, S + '/contact/'); await passBotCheck(p); await p.evaluate(() => { const b = [...document.querySelectorAll('button, a')].find(x => /necessary only/i.test(x.textContent || '')); if (b) b.click(); }); await sleep(800); await p.close(); }

const site = {};   // owner -> totals across pages
async function measure(label, url) {
  // pass 1: clean timings and transfer sizes
  const p = await ctx.newPage(); await p.setViewport({ width: 1366, height: 900 }); await p.setCacheEnabled(false);
  const cdp = await p.createCDPSession(); await cdp.send('Network.enable'); await cdp.send('Performance.enable');
  const req = new Map(), res = [];
  cdp.on('Network.requestWillBeSent', e => req.set(e.requestId, { url: e.request.url, type: e.type }));
  cdp.on('Network.responseReceived', e => { const r = req.get(e.requestId); if (r) { r.status = e.response.status; r.mime = e.response.mimeType; r.cache = e.response.headers['x-litespeed-cache'] || e.response.headers['x-lsadc-cache'] || ''; r.fromCache = !!e.response.fromDiskCache; } });
  cdp.on('Network.loadingFinished', e => { const r = req.get(e.requestId); if (r) { r.bytes = e.encodedDataLength; res.push(r); } });
  const t0 = Date.now();
  const nav = await go(p, url, 'load');
  await sleep(2500);
  const wall = Date.now() - t0;
  const timing = await p.evaluate(() => new Promise(resolve => {
    const n = performance.getEntriesByType('navigation')[0] || {};
    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
    let lcp = null;
    try { const po = new PerformanceObserver(l => { const e = l.getEntries(); if (e.length) lcp = e[e.length - 1].startTime; }); po.observe({ type: 'largest-contentful-paint', buffered: true }); } catch {}
    setTimeout(() => resolve({ ttfb: Math.round(n.responseStart - n.requestStart), dcl: Math.round(n.domContentLoadedEventEnd), load: Math.round(n.loadEventEnd), fcp: fcp ? Math.round(fcp.startTime) : null, lcp: lcp ? Math.round(lcp) : null,
      blocking: [...document.querySelectorAll('head script[src]')].filter(s => !s.defer && !s.async).map(s => s.src),
      sheets: [...document.querySelectorAll('link[rel="stylesheet"]')].length, inlineCss: [...document.querySelectorAll('style')].reduce((a, s) => a + s.textContent.length, 0), inlineJs: [...document.querySelectorAll('script:not([src])')].reduce((a, s) => a + s.textContent.length, 0) }), 300);
  }));
  const m = (await cdp.send('Performance.getMetrics')).metrics.reduce((a, x) => { a[x.name] = x.value; return a; }, {});
  const serverCache = nav ? (nav.headers()['x-litespeed-cache'] || nav.headers()['x-lsadc-cache'] || '(none)') : '(no response)';
  await p.close();
  // pass 2: coverage (how much of each file the page used)
  const p2 = await ctx.newPage(); await p2.setViewport({ width: 1366, height: 900 }); await p2.setCacheEnabled(false);
  await Promise.all([p2.coverage.startJSCoverage({ resetOnNavigation: false, includeRawScriptCoverage: false }), p2.coverage.startCSSCoverage({ resetOnNavigation: false })]);
  await go(p2, url, 'load'); await sleep(2500);
  // a little interaction so lazy and hover code gets a chance to run
  try { await p2.mouse.move(400, 500); await p2.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2)); await sleep(800); await p2.evaluate(() => window.scrollTo(0, 0)); await sleep(500); } catch {}
  const [js, css] = await Promise.all([p2.coverage.stopJSCoverage(), p2.coverage.stopCSSCoverage()]);
  await p2.close();
  const cov = {};
  const add = (kind, e) => { const u = e.url && !e.url.startsWith(S + url.replace(S, '')) ? e.url : (e.url === url || e.url === url.replace(/\/$/, '') ? 'inline' : e.url); const o = u === 'inline' ? 'inline <' + kind + '> in the page' : owner(u); const used = e.ranges.reduce((a, r) => a + (r.end - r.start), 0); const tot = e.text.length; cov[o] = cov[o] || { css: [0, 0], js: [0, 0], files: [] }; cov[o][kind][0] += used; cov[o][kind][1] += tot; cov[o].files.push({ kind, url: u, used, tot }); };
  js.forEach(e => add('js', e)); css.forEach(e => add('css', e));
  // table by owner
  const rows = {};
  for (const r of res) { const o = owner(r.url); rows[o] = rows[o] || { n: 0, bytes: 0, blocking: 0, kinds: {} }; rows[o].n++; rows[o].bytes += r.bytes || 0; const k = (r.type || '?').toLowerCase(); rows[o].kinds[k] = (rows[o].kinds[k] || 0) + 1; if (timing.blocking.includes(r.url)) rows[o].blocking++; }
  const total = res.reduce((a, r) => a + (r.bytes || 0), 0);
  console.log(`\n=== ${label}  ${url}`);
  console.log(`  server cache ${serverCache} | first byte ${timing.ttfb} ms | first paint ${timing.fcp} ms | largest paint ${timing.lcp} ms | DOM ready ${timing.dcl} ms | load ${timing.load} ms | wall ${wall} ms`);
  console.log(`  Chrome main thread: script ${Math.round(m.ScriptDuration * 1000)} ms, style ${Math.round(m.RecalcStyleDuration * 1000)} ms, layout ${Math.round(m.LayoutDuration * 1000)} ms, tasks ${Math.round(m.TaskDuration * 1000)} ms | JS heap ${kb(m.JSHeapUsedSize)} KB | ${res.length} requests, ${kb(total)} KB | ${timing.sheets} stylesheets, ${timing.blocking.length} blocking scripts in <head>, inline css ${kb(timing.inlineCss)} KB, inline js ${kb(timing.inlineJs)} KB`);
  console.log('  ' + 'owner'.padEnd(46) + 'req'.padStart(5) + 'KB'.padStart(8) + 'block'.padStart(7) + 'css used'.padStart(10) + 'js used'.padStart(9) + '  kinds');
  const owners = new Set([...Object.keys(rows), ...Object.keys(cov)]);
  const list = [...owners].map(o => ({ o, r: rows[o] || { n: 0, bytes: 0, blocking: 0, kinds: {} }, c: cov[o] || { css: [0, 0], js: [0, 0], files: [] } })).sort((a, b) => b.r.bytes - a.r.bytes);
  for (const { o, r, c } of list) {
    console.log('  ' + o.slice(0, 45).padEnd(46) + String(r.n).padStart(5) + kb(r.bytes).padStart(8) + String(r.blocking || '').padStart(7) + pct(c.css[0], c.css[1]).padStart(10) + pct(c.js[0], c.js[1]).padStart(9) + '  ' + Object.entries(r.kinds).map(([k, v]) => k + ':' + v).join(' '));
    site[o] = site[o] || { pages: 0, bytes: 0, n: 0, cssU: 0, cssT: 0, jsU: 0, jsT: 0, block: 0 }; const s = site[o]; s.pages++; s.bytes += r.bytes; s.n += r.n; s.cssU += c.css[0]; s.cssT += c.css[1]; s.jsU += c.js[0]; s.jsT += c.js[1]; s.block += r.blocking || 0;
  }
  const files = Object.values(cov).flatMap(c => c.files).filter(f => f.tot > 20000).sort((a, b) => (b.tot - b.used) - (a.tot - a.used)).slice(0, 12);
  console.log('  biggest unused parts: ' + files.map(f => `${f.kind} ${f.url.replace(S, '').replace(/\?.*$/, '').slice(-60)} ${kb(f.tot)}KB ${pct(f.used, f.tot)} used`).join(' | '));
}
for (const [label, url] of PAGES) {
  if (label === 'ADD') {
    const p = await ctx.newPage(); await p.setViewport({ width: 1366, height: 900 }); await go(p, PRODUCT); await passBotCheck(p);
    await p.select('#af-size-select', '3×4 ft (36×48 in)').catch(() => {}); await sleep(600);
    await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => { const b = document.querySelector('form.cart .single_add_to_cart_button'); if (b) b.click(); })]);
    await sleep(1500); await p.close(); continue;
  }
  try { await measure(label, url.startsWith('http') ? url : S + url); } catch (e) { console.log(`\n=== ${label} failed: ${String(e.message).slice(0, 160)}`); }
}
console.log('\n=== ACROSS ALL PAGES (sum of transfer, mean use) ===');
console.log('  ' + 'owner'.padEnd(46) + 'pages'.padStart(6) + 'req'.padStart(6) + 'KB'.padStart(8) + 'block'.padStart(7) + 'css used'.padStart(10) + 'js used'.padStart(9));
for (const [o, s] of Object.entries(site).sort((a, b) => b[1].bytes - a[1].bytes)) console.log('  ' + o.slice(0, 45).padEnd(46) + String(s.pages).padStart(6) + String(s.n).padStart(6) + kb(s.bytes).padStart(8) + String(s.block || '').padStart(7) + pct(s.cssU, s.cssT).padStart(10) + pct(s.jsU, s.jsT).padStart(9));
await b.close();
