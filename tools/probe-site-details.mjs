/**
 * READ-ONLY. The details behind the open items of the 2026-10-23 full site
 * test, measured in a real Chromium so each fix targets the element that is
 * actually wrong:
 *
 *   home      oversized images (file width vs shown width, srcset, sizes),
 *             links and buttons with no accessible name, tap targets under
 *             32px, form fields with no label
 *   product   uncaught errors with their stack, console errors with the file
 *             and line they came from
 *   login     the same, for the "hook name" console error
 *   search    what a search for a script tag shows
 *   heads     title, robots, canonical, H1 and description of a sample of the
 *             category and tag pages the crawl flagged
 *
 * Nothing is submitted. Pages are 5 s apart, so the host's bot protection is
 * not provoked.
 *
 * Usage: node tools/probe-site-details.mjs https://theartframer.us
 */
import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const PAUSE = 5000;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const out = (...a) => console.log(...a);

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1366, height: 900 }, locale: 'en-US' });
const page = await ctx.newPage();
const logs = [];
page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') { const l = m.location(); logs.push(`${m.type()}: ${m.text().slice(0, 200)}  @ ${l.url ? l.url.replace(SITE, '') : '?'}:${l.lineNumber}`); } });
page.on('pageerror', e => logs.push(`PAGEERROR: ${String(e.message).slice(0, 200)}\n      ${String(e.stack || '').split('\n').slice(1, 6).map(s => s.trim()).join('\n      ')}`));

async function go(url) {
  logs.length = 0;
  const r = await page.goto(url, { waitUntil: 'load', timeout: 60000 }).catch(e => null);
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  // Scroll the whole page so lazy images load and are measured at their real size.
  await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } window.scrollTo(0, 0); }).catch(() => {});
  await page.waitForTimeout(1500);
  return r ? r.status() : 0;
}

// ── only the hook-name error, when asked: PROBE_ONLY=hooks ──────────────────
if (process.env.PROBE_ONLY === 'hooks') {
  const hp = await ctx.newPage();
  // Record the stack of the call that prints the error, then read the bundled
  // file around the frame that called into wp.hooks.
  await hp.addInitScript(() => {
    const orig = console.error;
    window.__afHookStacks = [];
    console.error = function (...a) {
      try { if (String(a[0]).includes('hook name')) window.__afHookStacks.push(new Error().stack); } catch (e) {}
      return orig.apply(this, a);
    };
  });
  for (const p of ['/login/', '/sign-up/']) {
    await hp.goto(SITE + p, { waitUntil: 'load', timeout: 60000 }).catch(() => {});
    await hp.waitForTimeout(4000);
    const stacks = await hp.evaluate(() => window.__afHookStacks || []);
    out(`=== ${p}: ${stacks.length} hook-name error(s) ===`);
    const seen = new Set();
    for (const st of stacks.slice(0, 3)) {
      out(st.split('\n').slice(0, 8).map(l => '   ' + l.trim()).join('\n'));
      for (const m of st.matchAll(/(https?:\/\/[^\s)]+\.js[^\s):]*):(\d+):(\d+)/g)) {
        const key = m[1] + ':' + m[2] + ':' + m[3];
        if (seen.has(key) || seen.size >= 6) continue;
        seen.add(key);
        const src = await (await fetch(m[1])).text().catch(() => '');
        const lines = src.split('\n');
        const line = lines[+m[2] - 1] || '';
        const col = +m[3];
        out(`   --- ${m[1].replace(SITE, '')} line ${m[2]} col ${col} ---\n   …${line.slice(Math.max(0, col - 400), col + 200)}…`);
      }
    }
    // Which source file each bundle was combined from, when LiteSpeed says.
    const scripts = await hp.evaluate(() => [...document.scripts].map(s => s.src).filter(Boolean).filter(u => !u.includes('/litespeed/')).map(u => u.replace(location.origin, '')).slice(0, 60));
    out('   uncombined scripts on the page: ' + scripts.join(' '));
    await sleep(PAUSE);
  }
  await browser.close();
  process.exit(0);
}

// ── home ──────────────────────────────────────────────────────────────────
out(`=== HOME ${SITE}/  status ${await go(SITE + '/')} ===`);
const home = await page.evaluate(() => {
  const path = el => { const p = []; for (let e = el; e && e !== document.body && p.length < 4; e = e.parentElement) { p.unshift(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '')); } return p.join(' > '); };
  const imgs = [...document.images].filter(i => i.complete && i.naturalWidth && i.getBoundingClientRect().width > 0);
  const over = imgs.filter(i => i.naturalWidth > i.getBoundingClientRect().width * 3)
    .map(i => ({ file: (i.currentSrc || i.src).replace(location.origin, '').slice(0, 110), natural: i.naturalWidth, shown: Math.round(i.getBoundingClientRect().width), srcset: !!i.getAttribute('srcset'), sizes: i.getAttribute('sizes') || '', where: path(i) }));
  const name = el => (el.getAttribute('aria-label') || el.getAttribute('title') || el.innerText || [...el.querySelectorAll('img[alt]')].map(i => i.alt).join(' ') || '').trim();
  const unnamed = [...document.querySelectorAll('a[href], button, [role="button"]')].filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && !name(el); })
    .map(el => ({ tag: el.tagName.toLowerCase(), href: (el.getAttribute('href') || '').replace(location.origin, '').slice(0, 70), cls: (el.className && typeof el.className === 'string' ? el.className : '').slice(0, 60), inner: el.innerHTML.replace(/\s+/g, ' ').slice(0, 90), where: path(el) }));
  const small = [...document.querySelectorAll('a,button,[role="button"],input[type="submit"]')].filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && (r.height < 32 || r.width < 32); })
    .map(el => ({ size: `${Math.round(el.getBoundingClientRect().width)}x${Math.round(el.getBoundingClientRect().height)}`, label: name(el).slice(0, 40), cls: (el.className && typeof el.className === 'string' ? el.className : '').slice(0, 50), where: path(el) }));
  const unlabelled = [...document.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea')].filter(el => {
    if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('placeholder') || el.getAttribute('title')) return false;
    if (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return false;
    if (el.closest('label')) return false; return true;
  }).map(el => ({ html: el.outerHTML.slice(0, 160), where: path(el) }));
  return { total: imgs.length, over, unnamed, small, unlabelled };
});
out(`  images measured: ${home.total}, oversized (file 3x+ wider than shown): ${home.over.length}`);
const group = (arr, key) => { const m = new Map(); for (const x of arr) { const k = key(x); if (!m.has(k)) m.set(k, []); m.get(k).push(x); } return [...m].sort((a, b) => b[1].length - a[1].length); };
for (const [w, xs] of group(home.over, x => x.where)) { out(`   ${xs.length}x  in ${w}`); for (const x of xs.slice(0, 3)) out(`        ${x.natural}px shown at ${x.shown}px  srcset=${x.srcset} sizes="${x.sizes}"  ${x.file}`); }
out(`\n  links/buttons with no accessible name: ${home.unnamed.length}`);
for (const [w, xs] of group(home.unnamed, x => x.where + ' | ' + x.cls)) { out(`   ${xs.length}x  ${w}`); for (const x of xs.slice(0, 2)) out(`        <${x.tag} href="${x.href}">  ${x.inner}`); }
out(`\n  tap targets under 32px: ${home.small.length}`);
for (const [w, xs] of group(home.small, x => x.where + ' | ' + x.cls)) out(`   ${xs.length}x  ${xs[0].size} "${xs[0].label}"  ${w}`);
out(`\n  form fields with no label: ${home.unlabelled.length}`);
for (const x of home.unlabelled) out(`   ${x.html}\n        in ${x.where}`);
out('\n  console / errors on home:'); for (const l of [...new Set(logs)].slice(0, 15)) out('   ' + l);
await sleep(PAUSE);

// ── phone: tap targets and unnamed controls, where the checklist saw them ────
{
  const mctx = await browser.newContext({ ...devices['iPhone 13'], locale: 'en-US' });
  const mp = await mctx.newPage();
  await mp.goto(SITE + '/', { waitUntil: 'load', timeout: 60000 }).catch(() => {});
  await mp.waitForTimeout(2500);
  const small = await mp.evaluate(() => {
    const path = el => { const p = []; for (let e = el; e && e !== document.body && p.length < 4; e = e.parentElement) p.unshift(e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '')); return p.join(' > '); };
    return [...document.querySelectorAll('a,button,[role="button"],input[type="submit"]')].filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.height < 32; })
      .map(el => ({ size: `${Math.round(el.getBoundingClientRect().width)}x${Math.round(el.getBoundingClientRect().height)}`, label: (el.getAttribute('aria-label') || el.innerText || '').trim().slice(0, 40), where: path(el) }));
  });
  out(`\n=== PHONE HOME: controls under 32px tall: ${small.length} ===`);
  for (const [w, xs] of group(small, x => x.where)) out(`   ${xs.length}x  ${xs[0].size} "${xs[0].label}"  ${w}`);
  await mctx.close();
  await sleep(PAUSE);
}

// ── a product page: the Google Pay error ─────────────────────────────────
out(`\n=== PRODUCT ===`);
await go(SITE + '/shop/');
const prod = await page.locator('a[href*="/product/"]').first().getAttribute('href').catch(() => null);
await sleep(PAUSE);
if (prod) {
  out(`  ${prod.replace(SITE, '')}  status ${await go(prod)}`);
  const pay = await page.evaluate(() => [...document.querySelectorAll('[id*="google" i],[class*="google-pay" i],[id*="wallet" i],[class*="wallet" i],[class*="express" i]')].map(e => `${e.tagName.toLowerCase()}#${e.id}.${(e.className || '').toString().slice(0, 60)}`).slice(0, 10));
  out('  wallet / express elements: ' + JSON.stringify(pay));
  out('  console / errors:'); for (const l of [...new Set(logs)].slice(0, 15)) out('   ' + l);
  const qty = await page.evaluate(() => { const q = document.querySelector('form.cart input.qty, input[name="quantity"]'); return q ? q.outerHTML.slice(0, 220) : null; });
  out('  quantity input: ' + qty);
}
await sleep(PAUSE);

// ── login: the "hook name" error ──────────────────────────────────────────
for (const p of ['/login/', '/sign-up/']) {
  out(`\n=== ${p}  status ${await go(SITE + p)} ===`);
  for (const l of [...new Set(logs)].slice(0, 15)) out('   ' + l);
  await sleep(PAUSE);
}

// ── search for a script tag ───────────────────────────────────────────────
{
  const term = '<script>alert(1)</script>';
  out(`\n=== SEARCH ${term}  status ${await go(SITE + '/?s=' + encodeURIComponent(term))} ===`);
  const s = await page.evaluate(() => ({ h1: [...document.querySelectorAll('h1')].map(h => h.innerText.trim()).join(' | '), main: (document.querySelector('main, #main, .af-sr, .site-main, #content') || document.body).innerText.replace(/\s+/g, ' ').slice(0, 400), total: document.body.innerText.length, alert: document.querySelectorAll('script').length }));
  out(`  h1: ${s.h1}\n  body text length: ${s.total}\n  main: ${s.main}`);
  await sleep(PAUSE);
}

// ── heads of flagged archive pages ────────────────────────────────────────
out('\n=== ARCHIVE HEADS ===');
const heads = [
  '/product-category/framed-canvases/', '/product-category/framed-canvases/standard-framed-canvas/',
  '/product-category/direct-from-artists/', '/product-category/direct-from-artists/ananya-sengupta/',
  '/product-category/art-accessories/frame-sizes/2x3-ft/', '/product-category/art-accessories/frame-sizes/',
  '/product-category/digital-canvas-prints/radha-krishna/', '/product-category/radha-krishna/',
  '/product-category/digital-canvas-prints/', '/category/tips-tricks/', '/terms-of-use/', '/all-artists/',
];
await go(SITE + '/shop/');
const tags = await page.evaluate(() => [...new Set([...document.querySelectorAll('a[href*="/product-tag/"]')].map(a => a.getAttribute('href')))].slice(0, 2));
for (const t of tags) heads.push(t.replace(/^https?:\/\/[^/]+/, ''));
if (!tags.length) heads.push('/product-tag/modern-portrait/', '/product-tag/cubist-art/');
for (const h of heads) {
  const st = await go(SITE + h);
  const d = await page.evaluate(() => ({
    title: document.title.slice(0, 70),
    robots: (document.querySelector('meta[name="robots"]') || {}).content || '',
    canonical: (document.querySelector('link[rel="canonical"]') || {}).href || '',
    desc: ((document.querySelector('meta[name="description"]') || {}).content || '').slice(0, 60),
    h1: [...document.querySelectorAll('h1')].map(x => x.innerText.trim().slice(0, 40)),
    products: document.querySelectorAll('li.product, .product.type-product').length,
    visibleTitle: [...document.querySelectorAll('.page-title, .woocommerce-products-header__title, .entry-title, .archive-title, .breadcrumb-title, h2')].slice(0, 2).map(x => x.tagName + ':' + x.innerText.trim().slice(0, 30)),
  }));
  out(`  ${st} ${h}\n      title="${d.title}" robots="${d.robots}" canonical=${d.canonical.replace(SITE, '') || '-'} desc="${d.desc}"\n      h1=${JSON.stringify(d.h1)} products=${d.products} visible=${JSON.stringify(d.visibleTitle)}`);
  await sleep(PAUSE);
}

await browser.close();
out('\nDONE');
