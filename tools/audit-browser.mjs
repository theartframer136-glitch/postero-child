/**
 * The whole-site test, part two: a real Chromium over a sample of every kind
 * of page, because a crawl reads HTML and a visitor runs it.
 *
 * On every sampled page, desktop and phone
 *   - uncaught JavaScript exceptions and console errors
 *   - requests that failed or answered 4xx/5xx while the page loaded
 *   - images that rendered broken (loaded, zero pixels)
 *   - sideways scroll on a phone (content wider than the screen)
 *
 * Then the things a visitor actually does, once each
 *   - open the phone menu          - search for a product
 *   - quick view from a shop card  - add to wishlist from a card
 *   - choose a variation, add to cart, see it in the cart, reach checkout
 *   - login / register / contact forms are present and have their fields
 *   - a made-up URL answers 404 with a way out
 *
 * SAFETY. Live shop: nothing is ever submitted that would reach a person or a
 * ledger. The newsletter, contact, preview, gift-card and checkout-submit
 * endpoints are answered at the wire, exactly as tools/qa-veteran.mjs does.
 * The cart is per-session and is used.
 *
 * Usage: node tools/audit-browser.mjs https://theartframer.us [sample.json] [outDir]
 *   sample.json is what tools/audit-full.mjs writes; without it a built-in
 *   list of the key pages is used.
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const SAMPLE = process.argv[3] || '';
const OUT = process.argv[4] || 'audit-out';
const MAX_DESKTOP = parseInt(process.env.AF_BROWSER_PAGES || '70', 10);
const MAX_MOBILE = parseInt(process.env.AF_BROWSER_MOBILE || '30', 10);
fs.mkdirSync(path.join(OUT, 'shots'), { recursive: true });

const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply', 'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save', 'action=af_chat'];

const findings = [];
const F = (sev, area, what, detail = '') => findings.push({ sev, area, what, detail: String(detail).slice(0, 300) });
const rel = u => { try { const x = new URL(u); return x.host === new URL(SITE).host ? x.pathname + x.search : u; } catch { return u; } };

// Third-party noise a shop owner cannot fix and a visitor never sees.
const NOISE = /googletagmanager|google-analytics|doubleclick|facebook\.(net|com)\/tr|connect\.facebook|cdninstagram|instagram\.com|tiktok|clarity\.ms|hotjar|cloudflareinsights|challenges\.cloudflare|turnstile|recaptcha|gstatic\.com\/recaptcha|analytics|pixel|beacon|stats\.wp\.com|\/wp-json\/wp-site-health|cash\.app|kit\.cash|js\.stripe|paypal|klarna|afterpay|px\.ads|tr\?id=/i;

let sample = null;
if (SAMPLE && fs.existsSync(SAMPLE)) { try { sample = JSON.parse(fs.readFileSync(SAMPLE, 'utf8')); } catch {} }
const KEY = ['/', '/shop/', '/cart/', '/checkout/', '/wishlist/', '/my-account/', '/login/', '/sign-up/', '/about/', '/contact-us/', '/contact/', '/blog/', '/faq/', '/shipping-delivery/', '/returns-exchanges/', '/refund-policy/', '/privacy-policy/', '/terms-conditions/', '/help-support/', '/track-your-order/', '/gift-cards/', '/corporate/', '/wholesale-corporate-orders/', '/customize-your-picture/', '/deals/', '/new-arrivals/'];
const dedupe = a => [...new Set(a)];
const abs = u => u.startsWith('http') ? u : SITE + u;
let desktopList = sample
  ? dedupe([...KEY.map(abs), ...sample.pages, ...sample.categories, ...sample.products, ...sample.posts, ...(sample.flagged || [])])
  : KEY.map(abs);
desktopList = desktopList.slice(0, MAX_DESKTOP);
let mobileList = dedupe([abs('/'), abs('/shop/'), abs('/cart/'), abs('/checkout/'), ...(sample ? [...sample.pages.slice(0, 10), ...sample.categories.slice(0, 6), ...sample.products.slice(0, 8), ...sample.posts.slice(0, 2)] : KEY.slice(4, 12).map(abs))]).slice(0, MAX_MOBILE);

// The built-in key-page list is a guess at slugs; drop the ones this site
// does not have (noted once) so a missing /contact/ is not a failed page.
if (!process.env.AF_SKIP_PRECHECK) {
  const missing = [];
  const known = new Set(sample ? [...sample.pages, ...sample.categories, ...sample.products, ...sample.posts] : []);
  for (const u of KEY.map(abs)) {
    if (known.has(u)) continue;
    let s = 0; try { const r = await fetch(u, { method: 'GET', redirect: 'follow', headers: { 'User-Agent': 'AF-SiteAudit', Range: 'bytes=0-0' } }); s = r.status; r.body?.cancel().catch(() => {}); } catch {}
    if (s === 404 || s === 410) missing.push(rel(u));
  }
  if (missing.length) F('INFO', 'Key pages', 'not on this site (skipped)', missing.join(' '));
  desktopList = desktopList.filter(u => !missing.includes(rel(u)));
  mobileList = mobileList.filter(u => !missing.includes(rel(u)));
}

const browser = await chromium.launch({ headless: true });

async function makeContext(mobile) {
  const ctx = await browser.newContext(mobile
    ? { ...devices['iPhone 13'], locale: 'en-US' }
    : { viewport: { width: 1366, height: 900 }, locale: 'en-US', userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-SiteAudit' });
  await ctx.route('**/*', route => {
    const req = route.request();
    const body = (req.postData() || '') + ' ' + req.url();
    if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"message":"(intercepted by QA — not sent)"}}' });
    }
    return route.continue();
  });
  return ctx;
}

// Per-page collectors, attributed to the page that was open.
function watch(page) {
  const log = { js: [], console: [], failed: [], http: [] };
  page.on('pageerror', e => log.js.push(String(e.message || e).slice(0, 200)));
  page.on('console', m => { if (m.type() === 'error') { const t = m.text(); if (!NOISE.test(t)) log.console.push(t.slice(0, 200)); } });
  page.on('requestfailed', r => { const u = r.url(); const why = r.failure()?.errorText || ''; if (!NOISE.test(u) && !/ERR_ABORTED|ERR_BLOCKED_BY_CLIENT/.test(why)) log.failed.push(`${why} ${u}`.slice(0, 200)); });
  page.on('response', r => { const s = r.status(); const u = r.url(); if (s >= 400 && !NOISE.test(u)) log.http.push(`${s} ${u}`.slice(0, 200)); });
  return log;
}
const pageSnapshot = () => ({
  w: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth,
  broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.getBoundingClientRect().width > 20 && !i.loading?.includes('lazy')).map(i => i.currentSrc || i.src).slice(0, 6),
  title: document.title, text: (document.body?.innerText || '').length,
  h1: document.querySelectorAll('h1').length,
});

async function visit(page, url, log) {
  log.js.length = log.console.length = log.failed.length = log.http.length = 0;
  let res = null, err = '';
  try { res = await page.goto(url, { waitUntil: 'load', timeout: 60000 }); } catch (e) { err = String(e.message).split('\n')[0].slice(0, 120); }
  try { await page.waitForLoadState('networkidle', { timeout: 8000 }); } catch {}
  // Lazy images: scroll once so the ones near the fold load, then look.
  try { await page.evaluate(() => window.scrollTo(0, Math.min(1500, document.body.scrollHeight))); await page.waitForTimeout(600); await page.evaluate(() => window.scrollTo(0, 0)); } catch {}
  let snap = null; try { snap = await page.evaluate(pageSnapshot); } catch {}
  return { status: res ? res.status() : 0, err, snap, js: [...log.js], console: [...log.console], failed: [...log.failed], http: [...log.http] };
}

const perPage = [];
async function sweep(list, mobile) {
  const ctx = await makeContext(mobile);
  const page = await ctx.newPage();
  const log = watch(page);
  let n = 0;
  for (const url of list) {
    const r = await visit(page, url, log);
    const row = { url, mobile, ...r };
    perPage.push(row);
    const where = `${mobile ? '[phone] ' : ''}${rel(url)}`;
    if (r.err) F('FAIL', 'Load', `${where} did not load`, r.err);
    else if (r.status >= 500) F('FAIL', 'Load', `${where} answered ${r.status}`);
    else if (r.status >= 400) F('FAIL', 'Load', `${where} answered ${r.status}`);
    for (const m of dedupe(r.js)) F('FAIL', 'JS error', `${where}`, m);
    for (const m of dedupe(r.console).slice(0, 3)) F('WARN', 'Console error', `${where}`, m);
    for (const m of dedupe(r.http).filter(m => m.slice(4) !== url && m.slice(4) !== url.replace(/\/$/, '')).slice(0, 5)) F(/^5/.test(m) ? 'FAIL' : 'WARN', 'Failed request', `${where}`, m);
    for (const m of dedupe(r.failed).slice(0, 3)) F('WARN', 'Failed request', `${where}`, m);
    if (r.snap) {
      if (r.snap.broken.length) F('FAIL', 'Broken image', `${where}`, r.snap.broken.join(' | '));
      if (mobile && r.snap.w - r.snap.cw > 8) F('FAIL', 'Mobile layout', `${where} scrolls sideways`, `${r.snap.w}px of content in a ${r.snap.cw}px screen`);
      if (!r.err && r.status < 400 && r.snap.text < 150) F('WARN', 'Empty page', `${where}`, `only ${r.snap.text} characters of visible text`);
    }
    if (n < 6 || (mobile && n < 4)) { try { await page.screenshot({ path: path.join(OUT, 'shots', `${mobile ? 'phone' : 'desktop'}-${n}-${rel(url).replace(/[^a-z0-9]+/gi, '_').slice(0, 40)}.png`) }); } catch {} }
    n++;
  }
  await ctx.close();
}

async function flows() {
  const ctx = await makeContext(false);
  const page = await ctx.newPage();
  const log = watch(page);
  const step = async (area, what, fn) => { try { await fn(); } catch (e) { F('WARN', area, `${what} — could not be tested`, String(e.message).split('\n')[0].slice(0, 160)); } };

  // Search
  await step('Search', 'search for "canvas"', async () => {
    await page.goto(`${SITE}/?s=canvas&post_type=product`, { waitUntil: 'load', timeout: 60000 });
    const n = await page.locator('li.product, .product-card, .wd-product, .elementor-widget-wc-archive-products .product, article.product').count();
    const hasMsg = await page.locator('text=/no products|nothing found|no results/i').count();
    if (n > 0) F('PASS', 'Search', `"canvas" returns ${n} products`);
    else if (hasMsg) F('FAIL', 'Search', '"canvas" finds nothing on a canvas shop');
    else F('WARN', 'Search', 'results page shows no product cards and no "nothing found" message');
    await page.goto(`${SITE}/?s=zzqxv-not-a-product`, { waitUntil: 'load', timeout: 60000 });
    const txt = await page.evaluate(() => document.body.innerText);
    if (!/no products|nothing found|no results|not found|sorry/i.test(txt)) F('WARN', 'Search', 'no "nothing found" message for a nonsense term');
    else F('PASS', 'Search', 'nonsense term gets a "nothing found" message');
  });

  // Shop card interactions: quick view and wishlist
  await page.goto(`${SITE}/shop/`, { waitUntil: 'load', timeout: 60000 }).catch(() => {});
  await page.waitForTimeout(1500);
  await step('Quick view', 'open from a shop card', async () => {
    const btn = page.locator('.woosq-btn, .quick-view, [class*="quickview"], [class*="quick-view"], a:has-text("Quick View")').first();
    if (!await btn.count()) { F('INFO', 'Quick view', 'no quick view button on shop cards'); return; }
    await btn.hover().catch(() => {});
    await btn.click({ force: true, timeout: 5000 });
    const modal = page.locator('.woosq-popup, .fancybox__container, .quick-view-modal, [class*="quickview"][class*="popup"], .mfp-content');
    await modal.first().waitFor({ state: 'visible', timeout: 10000 });
    const title = await modal.locator('h1, h2, .product_title').first().innerText().catch(() => '');
    const price = await modal.locator('.price, .woocommerce-Price-amount').count();
    if (title && price) F('PASS', 'Quick view', `opens with "${title.slice(0, 40)}" and a price`);
    else F('FAIL', 'Quick view', 'opens but shows no title or no price', `title="${title}" price=${price}`);
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(500);
  });
  await step('Wishlist', 'add from a shop card', async () => {
    const btn = page.locator('.woosw-btn, [class*="wishlist"] a, a[class*="wishlist"], button[class*="wishlist"]').first();
    if (!await btn.count()) { F('INFO', 'Wishlist', 'no wishlist button on shop cards'); return; }
    await btn.hover().catch(() => {});
    await btn.click({ force: true, timeout: 5000 });
    await page.waitForTimeout(2500);
    await page.goto(`${SITE}/wishlist/`, { waitUntil: 'load', timeout: 60000 });
    const items = await page.locator('.woosw-item, .wishlist_table tr.wishlist_item, .woosw-items tr, .wishlist-item, li.product').count();
    const txt = await page.evaluate(() => document.body.innerText);
    if (items > 0) F('PASS', 'Wishlist', `item appears on /wishlist/ (${items})`);
    else if (/empty|no products/i.test(txt)) F('FAIL', 'Wishlist', 'clicked the wishlist button, but /wishlist/ is empty');
    else F('WARN', 'Wishlist', 'could not tell whether the item was saved');
  });

  // Mobile menu
  await step('Mobile menu', 'opens on a phone', async () => {
    const mctx = await makeContext(true);
    const mp = await mctx.newPage();
    await mp.goto(SITE + '/', { waitUntil: 'load', timeout: 60000 });
    await mp.waitForTimeout(1500);
    // Dismiss a popup if one covers the header.
    for (const sel of ['.pum-close', '.elementor-popup-modal .dialog-close-button', 'button[aria-label="Close"]', '.af-offer-close']) { const c = mp.locator(sel).first(); if (await c.count() && await c.isVisible().catch(() => false)) { await c.click({ timeout: 2000 }).catch(() => {}); break; } }
    const toggle = mp.locator('button[aria-label*="menu" i], .menu-toggle, .hamburger, [class*="menu-toggle"], [class*="mobile-menu"] button, .elementor-menu-toggle, a[href="#"]:has-text("Menu"), button:has-text("Menu")').first();
    if (!await toggle.count()) { F('FAIL', 'Mobile menu', 'no menu button found on the phone homepage'); await mctx.close(); return; }
    const box = await toggle.boundingBox();
    if (box && (box.width < 40 || box.height < 40)) F('WARN', 'Mobile menu', `menu button is ${Math.round(box.width)}×${Math.round(box.height)}px — under 44px finger size`);
    await toggle.click({ force: true, timeout: 5000 });
    await mp.waitForTimeout(1200);
    const links = await mp.evaluate(() => [...document.querySelectorAll('nav a, [class*="menu"] a, [class*="offcanvas"] a, [class*="drawer"] a')].filter(a => { const r = a.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.left >= 0 && r.left < window.innerWidth; }).length);
    if (links >= 3) F('PASS', 'Mobile menu', `opens with ${links} visible links`);
    else F('FAIL', 'Mobile menu', 'tapped the menu button and no navigation links became visible');
    await mp.screenshot({ path: path.join(OUT, 'shots', 'phone-menu-open.png') }).catch(() => {});
    await mctx.close();
  });

  // Buying journey
  let productUrl = sample?.products?.[0] || null;
  await step('Journey', 'find a product', async () => {
    if (productUrl) return;
    await page.goto(`${SITE}/shop/`, { waitUntil: 'load', timeout: 60000 });
    productUrl = await page.locator('a[href*="/product/"]').first().getAttribute('href');
  });
  await step('Product', 'variations, price, add to cart', async () => {
    if (!productUrl) { F('FAIL', 'Journey', 'no product link found — buying journey not tested'); return; }
    await page.goto(productUrl, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(1000);
    const selects = page.locator('form.variations_form select, .variations select');
    const nSel = await selects.count();
    for (let i = 0; i < nSel; i++) {
      const opts = await selects.nth(i).locator('option').evaluateAll(os => os.map(o => o.value).filter(Boolean));
      if (opts.length) await selects.nth(i).selectOption(opts[0]).catch(() => {});
      await page.waitForTimeout(500);
    }
    // Swatch-style variations
    const swatches = page.locator('.variations .swatch, .variable-items-wrapper li, .wcboost-variation-swatches__item, .tawcvs-swatches .swatch');
    if (!nSel && await swatches.count()) { await swatches.first().click({ force: true }).catch(() => {}); await page.waitForTimeout(600); }
    const price = await page.locator('.summary .price, .product .price, .elementor-widget-woocommerce-product-price .price, .price').first().innerText().catch(() => '');
    const atc = page.locator('button.single_add_to_cart_button, button[name="add-to-cart"], .single_add_to_cart_button').first();
    const atcOk = await atc.count() && await atc.isEnabled().catch(() => false);
    if (!price.trim()) F('FAIL', 'Product', `${rel(productUrl)} shows no price`);
    if (!atcOk) F(nSel ? 'WARN' : 'FAIL', 'Product', `${rel(productUrl)} add-to-cart button missing or disabled`, nSel ? 'after choosing the first option of each variation' : '');
    if (price.trim() && atcOk) F('PASS', 'Product', `price "${price.trim().replace(/\s+/g, ' ').slice(0, 40)}", add to cart enabled`);
    const gallery = await page.locator('.woocommerce-product-gallery img, .product-gallery img, .elementor-widget-woocommerce-product-images img').count();
    if (!gallery) F('WARN', 'Product', 'no gallery image found');
    if (atcOk) {
      await atc.click({ timeout: 10000 });
      await page.waitForTimeout(4000);
      await page.goto(`${SITE}/cart/`, { waitUntil: 'load', timeout: 60000 });
      const rows = await page.locator('.woocommerce-cart-form .cart_item, .wc-block-cart-items__row, tr.cart_item').count();
      const total = await page.locator('.order-total, .wc-block-components-totals-footer-item, .cart-subtotal').count();
      const txt = await page.evaluate(() => document.body.innerText);
      if (rows && total) F('PASS', 'Cart', `${rows} line(s) and a total`);
      else if (/cart is currently empty/i.test(txt)) F('FAIL', 'Cart', 'added to cart, but /cart/ says the cart is empty');
      else F('WARN', 'Cart', 'could not read the cart', `rows=${rows} total=${total}`);
      await page.screenshot({ path: path.join(OUT, 'shots', 'cart.png') }).catch(() => {});
      await page.goto(`${SITE}/checkout/`, { waitUntil: 'load', timeout: 60000 });
      await page.waitForTimeout(3000);
      const fields = await page.locator('form.checkout input:visible, .wc-block-checkout input:visible').count();
      const pay = await page.locator('#payment, .wc_payment_methods, .wc-block-checkout__payment-method, #place_order, .wc-block-components-checkout-place-order-button').count();
      const ctxt = await page.evaluate(() => document.body.innerText);
      if (/cart is currently empty/i.test(ctxt)) F('FAIL', 'Checkout', 'the cart did not carry through to checkout');
      else if (fields >= 5 && pay) F('PASS', 'Checkout', `renders with ${fields} fields and a payment section`);
      else F('FAIL', 'Checkout', 'missing fields or payment section', `fields=${fields} payment=${pay}`);
      if (!page.url().startsWith('https://')) F('FAIL', 'Checkout', 'not on HTTPS');
      await page.screenshot({ path: path.join(OUT, 'shots', 'checkout.png'), fullPage: true }).catch(() => {});
    }
  });

  // Forms exist and have their fields (never submitted)
  for (const [area, paths, need] of [
    ['Login form', ['/login/', '/my-account/'], ['input[name="username"], input[type="email"], input[name="log"]', 'input[type="password"]']],
    ['Register form', ['/sign-up/', '/my-account/'], ['input[type="email"], input[name="email"]']],
    ['Contact form', ['/contact-us/', '/contact/'], ['input[type="email"], input[name*="email" i]', 'textarea']],
  ]) {
    await step(area, 'present with its fields', async () => {
      let found = false;
      for (const p of paths) {
        const r = await page.goto(SITE + p, { waitUntil: 'load', timeout: 60000 }).catch(() => null);
        if (!r || r.status() >= 400) continue;
        found = true;
        const missing = [];
        for (const sel of need) if (!await page.locator(sel).count()) missing.push(sel.split(',')[0]);
        if (missing.length) F('FAIL', area, `${p} is missing ${missing.join(', ')}`);
        else F('PASS', area, `${p} has its fields`);
        break;
      }
      if (!found) F('WARN', area, `none of ${paths.join(' ')} answered 200`);
    });
  }

  // 404
  await step('404', 'made-up URL', async () => {
    const r = await page.goto(`${SITE}/this-page-does-not-exist-${Date.now()}/`, { waitUntil: 'load', timeout: 60000 });
    const s = r ? r.status() : 0;
    const out = await page.evaluate(() => ({ search: !!document.querySelector('input[type="search"], input[name="s"]'), home: !!document.querySelector('a[href="/"], a[href$="theartframer.us/"]') }));
    if (s !== 404) F('FAIL', '404', `a missing page answers ${s}, not 404`);
    else if (!out.search && !out.home) F('WARN', '404', 'returns 404 but offers no search box or home link');
    else F('PASS', '404', 'returns 404 with a way out');
    await page.screenshot({ path: path.join(OUT, 'shots', '404.png') }).catch(() => {});
  });

  for (const m of dedupe(log.js)) F('FAIL', 'JS error', 'during the flows', m);
  await ctx.close();
}

console.log(`=== BROWSER TEST  ${SITE}  (${desktopList.length} pages desktop, ${mobileList.length} phone) ===\n`);
await sweep(desktopList, false);
console.log(`desktop sweep done: ${desktopList.length} pages`);
await sweep(mobileList, true);
console.log(`phone sweep done: ${mobileList.length} pages`);
await flows();
await browser.close();

const order = { FAIL: 0, WARN: 1, INFO: 2, PASS: 3 };
findings.sort((a, b) => order[a.sev] - order[b.sev] || a.area.localeCompare(b.area));
// Collapse identical findings that repeat on every page (one theme bug, many pages).
const grouped = new Map();
const PER_PAGE = new Set(['Load', 'JS error', 'Console error', 'Failed request', 'Broken image', 'Mobile layout', 'Empty page']);
for (const f of findings) {
  const k = PER_PAGE.has(f.area) && f.detail ? `${f.sev}|${f.area}|${f.detail}` : `${f.sev}|${f.area}|${f.what}|${f.detail}`;
  if (!grouped.has(k)) grouped.set(k, { ...f, pages: [f.what] }); else grouped.get(k).pages.push(f.what);
}
console.log('\n--- FINDINGS ---');
for (const g of grouped.values()) {
  const where = g.pages.length > 1 ? `${g.pages.length} pages, e.g. ${g.pages.slice(0, 3).join(', ')}` : g.pages[0];
  console.log(`  ${g.sev.padEnd(4)} ${g.area.padEnd(16)} ${where}${g.detail ? '\n        ' + g.detail : ''}`);
}
const counts = findings.reduce((a, f) => (a[f.sev] = (a[f.sev] || 0) + 1, a), {});
console.log(`\n--- SUMMARY ---  FAIL ${counts.FAIL || 0}   WARN ${counts.WARN || 0}   INFO ${counts.INFO || 0}   PASS ${counts.PASS || 0}   pages loaded ${perPage.length}`);
fs.writeFileSync(path.join(OUT, 'browser-findings.json'), JSON.stringify({ site: SITE, at: new Date().toISOString(), counts, findings, perPage }, null, 1));
