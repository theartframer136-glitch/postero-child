/**
 * Why is a page slow, and which part of it.
 *
 * Loads key pages in a real Chromium twice — cold and warm — and reports the
 * numbers that separate the two causes people conflate:
 *
 *   server slow : time to first byte. The browser is idle, waiting for PHP.
 *                 Fixed on the server (cache, queries, plugins).
 *   page heavy  : everything after first byte. Fixed in the theme (how many
 *                 files, how big, how many block the render).
 *
 * Reporting both is the point. A 3-second page that spends 2.5s on first byte
 * and a 3-second page that spends 0.2s on first byte need opposite work, and
 * a single "load time" number cannot tell you which one you have.
 *
 * Usage: node tools/audit-perf.mjs https://theartframer.us
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const ORIGIN = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const PAGES = [
  ['Home',            '/'],
  ['Shop',            '/shop/'],
  ['Category',        '/product-category/digital-canvas-prints/radha-krishna/'],
  ['Product',         null],            // filled from the category page
  ['Cart',            '/cart/'],
  ['Wishlist',        '/wishlist/'],
  ['Blog',            '/blog/'],
  ['About',           '/about-us/'],
];

const fmt = n => (n === null || n === undefined || Number.isNaN(n)) ? '   -' : String(Math.round(n)).padStart(6);
const kb  = n => (n / 1024).toFixed(0).padStart(6) + ' KB';

async function measure(browser, url, label, warm) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1366, height: 900 });
  await page.setCacheEnabled(warm);

  const res = [];
  page.on('response', async r => {
    try {
      const h = r.headers();
      res.push({
        url: r.url(),
        type: r.request().resourceType(),
        status: r.status(),
        size: parseInt(h['content-length'] || '0', 10),
        cache: h['x-litespeed-cache'] || h['x-cache'] || h['cf-cache-status'] || '',
        fromCache: r.fromCache(),
      });
    } catch {}
  });

  const t0 = Date.now();
  let resp = null;
  try {
    resp = await page.goto(url, { waitUntil: 'load', timeout: 60000 });
  } catch (e) {
    await page.close();
    return { label, url, error: String(e.message || e).slice(0, 120) };
  }
  const wall = Date.now() - t0;

  const nav = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    if (!n) return null;
    const lcpList = performance.getEntriesByType('largest-contentful-paint');
    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
    return {
      ttfb: n.responseStart - n.requestStart,
      download: n.responseEnd - n.responseStart,
      dcl: n.domContentLoadedEventEnd - n.startTime,
      load: n.loadEventEnd - n.startTime,
      domInteractive: n.domInteractive - n.startTime,
      fcp: fcp ? fcp.startTime : null,
      lcp: lcpList.length ? lcpList[lcpList.length - 1].startTime : null,
      transfer: n.transferSize,
    };
  });

  const counts = {};
  let total = 0;
  for (const r of res) {
    counts[r.type] = counts[r.type] || { n: 0, bytes: 0 };
    counts[r.type].n++;
    counts[r.type].bytes += r.size;
    total += r.size;
  }

  const blocking = await page.evaluate(() => {
    const css = [...document.querySelectorAll('link[rel="stylesheet"]')].length;
    const js  = [...document.querySelectorAll('script[src]')].filter(s => !s.defer && !s.async).length;
    const jsAll = [...document.querySelectorAll('script[src]')].length;
    const inlineJs = [...document.querySelectorAll('script:not([src])')].reduce((a, s) => a + s.textContent.length, 0);
    const inlineCss = [...document.querySelectorAll('style')].reduce((a, s) => a + s.textContent.length, 0);
    const imgs = [...document.images];
    const lazy = imgs.filter(i => i.loading === 'lazy').length;
    return { css, js, jsAll, inlineJs, inlineCss, imgs: imgs.length, lazy };
  });

  const serverCache = resp ? (resp.headers()['x-litespeed-cache'] || resp.headers()['x-lsadc-cache'] || '(none)') : '(none)';
  const heaviest = res.filter(r => r.size > 0).sort((a, b) => b.size - a.size).slice(0, 8);

  await page.close();
  return { label, url, wall, nav, counts, total, blocking, serverCache, heaviest, reqs: res.length };
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  // Find a real product URL from the category page rather than hardcoding one.
  try {
    const p = await browser.newPage();
    await p.goto(ORIGIN + '/product-category/digital-canvas-prints/radha-krishna/', { waitUntil: 'domcontentloaded', timeout: 45000 });
    const href = await p.evaluate(() => {
      const a = document.querySelector('li.product a[href*="/product/"]');
      return a ? a.href : null;
    });
    await p.close();
    const i = PAGES.findIndex(x => x[0] === 'Product');
    if (href) PAGES[i][1] = href.replace(ORIGIN, ''); else PAGES.splice(i, 1);
  } catch { PAGES.splice(PAGES.findIndex(x => x[0] === 'Product'), 1); }

  console.log(`=== PERFORMANCE AUDIT  ${ORIGIN} ===\n`);
  console.log('All figures in milliseconds. TTFB is time waiting for the server;');
  console.log('everything after it is the page itself.\n');
  console.log('PAGE            TTFB  DOWNLD     FCP     LCP     DCL    LOAD   REQS    BYTES   LS-CACHE');
  console.log('-'.repeat(96));

  const detail = [];
  for (const [label, path] of PAGES) {
    if (!path) continue;
    const url = path.startsWith('http') ? path : ORIGIN + path;
    const cold = await measure(browser, url, label, false);
    if (cold.error) { console.log(`${label.padEnd(14)} ERROR  ${cold.error}`); continue; }
    console.log(
      label.padEnd(14) +
      fmt(cold.nav?.ttfb) + '  ' + fmt(cold.nav?.download) + '  ' +
      fmt(cold.nav?.fcp) + '  ' + fmt(cold.nav?.lcp) + '  ' +
      fmt(cold.nav?.dcl) + '  ' + fmt(cold.nav?.load) + '  ' +
      String(cold.reqs).padStart(5) + '  ' + kb(cold.total) + '   ' + cold.serverCache
    );
    detail.push(cold);
  }

  console.log('\n\n=== WHAT EACH PAGE IS MADE OF ===');
  for (const d of detail) {
    console.log(`\n--- ${d.label}  ${d.url}`);
    console.log(`    render-blocking : ${d.blocking.css} stylesheets, ${d.blocking.js} synchronous scripts (${d.blocking.jsAll} script files in all)`);
    console.log(`    inline          : ${(d.blocking.inlineCss / 1024).toFixed(0)} KB of <style>, ${(d.blocking.inlineJs / 1024).toFixed(0)} KB of inline <script>`);
    console.log(`    images          : ${d.blocking.imgs} on the page, ${d.blocking.lazy} lazy-loaded`);
    const rows = Object.entries(d.counts).sort((a, b) => b[1].bytes - a[1].bytes);
    console.log(`    by type         : ` + rows.map(([t, v]) => `${t} ${v.n}/${(v.bytes / 1024).toFixed(0)}KB`).join(', '));
    console.log(`    heaviest files  :`);
    for (const h of d.heaviest) {
      console.log(`        ${kb(h.size)}  ${h.type.padEnd(10)} ${h.url.replace(ORIGIN, '').slice(0, 88)}`);
    }
  }

  // Machine-readable, for the report builder.
  const payload = detail.map(d => ({
    label: d.label, url: d.url,
    ttfb: d.nav ? Math.round(d.nav.ttfb) : null,
    fcp: d.nav && d.nav.fcp != null ? Math.round(d.nav.fcp) : null,
    lcp: d.nav && d.nav.lcp != null ? Math.round(d.nav.lcp) : null,
    load: d.nav ? Math.round(d.nav.load) : null,
    reqs: d.reqs, kb: Math.round(d.total / 1024),
    css: d.blocking.css, syncJs: d.blocking.js, allJs: d.blocking.jsAll,
    inlineCssKb: Math.round(d.blocking.inlineCss / 1024), inlineJsKb: Math.round(d.blocking.inlineJs / 1024),
    imgs: d.blocking.imgs, lazy: d.blocking.lazy,
    heaviest: d.heaviest.slice(0, 5).map(h => ({ kb: Math.round(h.size / 1024), type: h.type, url: h.url.replace(ORIGIN, '').slice(0, 90) })),
    cache: d.serverCache,
  }));
  console.log('\n@@PERF@@' + JSON.stringify(payload) + '@@END@@');

  console.log('\n\n=== READING THIS ===');
  const worstTtfb = detail.filter(d => d.nav).sort((a, b) => b.nav.ttfb - a.nav.ttfb)[0];
  const worstLoad = detail.filter(d => d.nav).sort((a, b) => b.nav.load - a.nav.load)[0];
  if (worstTtfb) console.log(`  slowest server response : ${worstTtfb.label} at ${Math.round(worstTtfb.nav.ttfb)} ms`);
  if (worstLoad) console.log(`  slowest overall         : ${worstLoad.label} at ${Math.round(worstLoad.nav.load)} ms`);
  const avgTtfb = Math.round(detail.reduce((a, d) => a + (d.nav?.ttfb || 0), 0) / (detail.length || 1));
  console.log(`  average TTFB            : ${avgTtfb} ms  ` +
    (avgTtfb > 800 ? '<-- the server is the bottleneck' : avgTtfb > 400 ? '<-- server is sluggish but not the whole story' : '(server is fine; weight is the issue)'));

  await browser.close();
})();
