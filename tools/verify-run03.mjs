// Re-measure "Art Framer Test Run 03" against the live site.
//
// Run 03 retested 50 items. This re-takes the ones that are new, the ones
// that contradict measurements taken here on 23 Sep, and the one not yet
// measured here, and prints each measured value beside the claim, so every
// verdict comes from a number:
//
//   N-01    a product called "test", $1.00, live, indexable, first on price sort
//   N-02    the Digital Download licence checkbox on a physical-only order
//   N-03    an empty product search shows a stray art code and "collection
//           is empty" copy
//   DEF-17  "the inverted range is silently ignored" (measured here: swapped)
//   DEF-12  "debug logging still served" (measured here: 0 lines for visitors)
//   DEF-13  "six currency cookies still set" (measured here: two written)
//   H-04    "no visible focus indicator"
//   DEF-04  "product pages never cache"
//
// Every check runs as a fresh visitor: a new browser context with no cookies,
// no localStorage and no af_debug flag.
//
// SAFETY: one item is added to the cart for N-02. wc-ajax=checkout is in
// NEVER_SEND, so no order can be placed, and the cart is emptied at the end.
//
// Run: node tools/verify-run03.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save'];
const YES = 'CONFIRMED', NO = 'CONTRADICTED', PART = 'PARTLY', NA = 'NO DATA';
const verdicts = [];
const say = (id, v, claim, measured) => { verdicts.push([id, v]); console.log('  ' + v.padEnd(13) + id.padEnd(8) + claim + '\n' + ' '.repeat(23) + '→ ' + measured); };

const browser = await chromium.launch({ headless: true });
const fresh = async (w = 1280) => {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, ignoreHTTPSErrors: true });
  await ctx.route('**/*', route => {
    const req = route.request();
    const body = (req.postData() || '') + ' ' + req.url();
    if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure","messages":"blocked by probe"}' });
    }
    return route.continue();
  });
  return ctx;
};
const go = async (page, path, wait = 2500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r;
};
const txt = s => String(s || '').replace(/\s+/g, ' ').trim();

console.log('verify-run03: ' + SITE + '   ' + new Date().toISOString() + '\n');

// ── N-01 · the "test" product ─────────────────────────────────────────────
console.log('— N-01 · a product called "test" —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  const r = await go(page, '/product/test-canvas-wall-art/');
  const d = r ? await page.evaluate(() => ({
    url: location.pathname,
    h1: ((document.querySelector('h1.product_title, h1') || {}).textContent || '').trim(),
    robots: (document.querySelector('meta[name="robots"]') || {}).content || '(none)',
    price: ((document.querySelector('.summary .price, .product .price') || {}).innerText || '').replace(/\s+/g, ' ').trim(),
    atc: !!document.querySelector('.single_add_to_cart_button'),
    pid: ((document.body.className || '').match(/postid-(\d+)/) || [])[1] || '',
  })).catch(() => null) : null;
  // anything else in the catalogue named like a placeholder
  const found = await page.evaluate(async () => {
    const out = [];
    for (const q of ['test', 'sample', 'dummy', 'placeholder']) {
      try {
        const r = await fetch('/wp-json/wc/store/v1/products?search=' + q + '&per_page=50');
        const j = r.ok ? await r.json() : [];
        for (const p of j) if (new RegExp('\\b' + q + '\\b', 'i').test(p.name)) out.push({ id: p.id, name: p.name, sku: p.sku, price: p.prices ? Number(p.prices.price) / Math.pow(10, p.prices.currency_minor_unit || 0) : null, link: p.permalink });
      } catch (e) {}
    }
    return out;
  }).catch(() => []);
  const s = await go(page, '/shop/?orderby=price', 3000);
  const first = s ? await page.evaluate(() => {
    const c = [...document.querySelectorAll('ul.products li.product, .products .product-card')].filter(x => !x.closest('.af-xsell'))[0];
    if (!c) return null;
    const name = ((c.querySelector('.woocommerce-loop-product__title, h2, h3') || {}).textContent || '').trim();
    return { name, price: ((c.querySelector('.price') || {}).innerText || '').replace(/\s+/g, ' ').trim() };
  }).catch(() => null) : null;
  if (!r) say('N-01', NA, 'the product page', 'no answer');
  else {
    const live = r.status() === 200 && d;
    say('N-01', live && /\btest\b/i.test(d.h1) ? YES : NO, '"test" product live, indexable, buyable at $1.00',
      'HTTP ' + r.status() + (d ? ' · id ' + d.pid + ' · H1 "' + d.h1 + '" · price "' + d.price + '" · robots "' + d.robots + '" · Add to Cart ' + (d.atc ? 'yes' : 'no') : ''));
  }
  say('N-01', first && /\btest\b/i.test(first.name) ? YES : (first ? NO : NA), 'first result on /shop/?orderby=price',
    first ? '"' + first.name + '" at ' + first.price : 'no card read');
  console.log('                       placeholder-named products in the Store API: ' + (found.length ? found.map(p => p.id + ' "' + p.name + '" sku ' + (p.sku || '-') + ' $' + p.price).join('; ') : 'none'));
  await ctx.close();
}

// ── N-03 · empty product search ───────────────────────────────────────────
console.log('\n— N-03 · empty product search —');
for (const q of ['krishan', 'zzqxv']) {
  const ctx = await fresh(); const page = await ctx.newPage();
  const r = await go(page, '/?s=' + q + '&post_type=product', 3500);
  const d = r ? await page.evaluate(() => {
    const block = document.querySelector('.af-empty-filter');
    const codes = [...document.querySelectorAll('.af-empty-filter .af-art-code, .af-empty-filter [class*="art-code"]')].map(e => (e.textContent || '').replace(/\s+/g, ' ').trim());
    return {
      block: block ? (block.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 160) : null,
      codes,
      woo: ((document.querySelector('.woocommerce-no-products-found, .woocommerce-info') || {}).innerText || '').replace(/\s+/g, ' ').trim().slice(0, 100),
      body: (document.body.className || '').split(/\s+/).filter(c => /search|archive|post-type-archive|woocommerce-shop/.test(c)).join(' '),
      cards: document.querySelectorAll('ul.products li.product').length,
    };
  }).catch(() => null) : null;
  if (!d) { say('N-03', NA, 'search "' + q + '"', 'no answer'); await ctx.close(); continue; }
  const stray = d.codes.filter(c => /\S/.test(c.replace(/art code:?/i, '')));
  say('N-03', d.block && (/collection is empty/i.test(d.block) || stray.length) ? YES : NO, 'search "' + q + '": stray art code / "collection is empty" copy',
    'HTTP ' + r.status() + ' · products ' + d.cards + ' · empty-state block: ' + (d.block ? '"' + d.block + '"' : 'none') + ' · art codes inside it: ' + (stray.join(', ') || 'none')
    + (d.woo ? ' · Woo notice "' + d.woo + '"' : '') + ' · body: ' + d.body);
  await ctx.close();
}

// ── DEF-17 · "the inverted range is silently ignored" ─────────────────────
console.log('\n— DEF-17 · inverted price range —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  const read = async (path) => {
    const r = await go(page, path, 2500);
    return r ? await page.evaluate(() => {
      const cards = [...document.querySelectorAll('ul.products li.product, .products .product-card')].filter(x => !x.closest('.af-xsell'));
      const prices = cards.map(c => { const m = ((c.querySelector('.price ins .amount, .price > .amount, .price .amount') || {}).textContent || '').replace(/[^\d.]/g, ''); return m ? parseFloat(m) : null; }).filter(x => x !== null);
      return { url: location.pathname + location.search, count: ((document.querySelector('.woocommerce-result-count') || {}).textContent || '').trim(), prices };
    }).catch(() => null) : null;
  };
  const inv = await read('/shop/?min_price=500&max_price=10');
  const all = await read('/shop/');
  const tot = s => { const m = String(s && s.count || '').match(/of\s+([\d,]+)/i) || String(s && s.count || '').match(/all\s+([\d,]+)/i); return m ? parseInt(m[1].replace(/,/g, ''), 10) : null; };
  if (!inv || !all) say('DEF-17', NA, 'inverted range', 'no answer');
  else {
    const filtered = tot(inv) !== null && tot(all) !== null && tot(inv) < tot(all);
    const inRange = inv.prices.length ? inv.prices.every(p => p >= 10 && p <= 500) : null;
    say('DEF-17', filtered ? NO : YES, '"filter discarded rather than corrected"',
      'landed on ' + inv.url + ' · "' + inv.count + '" vs the whole shop "' + all.count + '" · page-1 prices ' + (inv.prices.length ? '$' + Math.min(...inv.prices) + '–$' + Math.max(...inv.prices) : 'unread')
      + (inRange === null ? '' : inRange ? ' (all within 10–500)' : ' (NOT all within 10–500)'));
  }
  await ctx.close();
}

// ── DEF-12 · debug logging for a visitor ──────────────────────────────────
console.log('\n— DEF-12 · debug logging —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  const lines = [];
  page.on('console', m => { const t = m.text(); if (/\[af-header-row\]|\[AF\] product-card|\[af-cp\]/.test(t)) lines.push(t.slice(0, 60)); });
  await go(page, '/', 3500);
  for (const w of [796, 1100, 1280]) { await page.setViewportSize({ width: w, height: 900 }); await page.waitForTimeout(700); }
  await go(page, '/shop/', 3000);
  const prod = await page.evaluate(() => ([...document.querySelectorAll('a[href*="/product/"]')].map(a => a.href)[0] || '')).catch(() => '');
  if (prod) await go(page, prod, 3000);
  const served = await page.evaluate(() => [...document.scripts].some(s => /af-header-row/.test(s.textContent || ''))).catch(() => null);
  say('DEF-12', lines.length ? YES : PART, '"af-header-row and [AF] product-card still served"',
    lines.length + ' debug lines in a fresh visitor\'s console across home (3 widths), shop and a product'
    + (served ? ' · the gated logger code is still in the page source, printing only for ?af_debug=1' : ''));
  await ctx.close();
}

// ── DEF-13 · currency cookies for a fresh visitor ─────────────────────────
console.log('\n— DEF-13 · currency cookies —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  await go(page, '/', 3000); await go(page, '/shop/', 3000);
  const NAMES = ['woocs_current_currency', 'woocs_session_currency', 'wmc_current_currency', 'wmc-currency', 'currency', 'chosen_currency'];
  const c = (await ctx.cookies(SITE)).filter(k => NAMES.includes(k.name)).map(k => k.name + '=' + k.value);
  say('DEF-13', c.length >= 6 ? YES : NO, '"six currency cookies still set (woocs + wmc + generic)"',
    c.length + ' of the six on a fresh visit: ' + (c.join(', ') || 'none'));
  await ctx.close();
}

// ── H-04 · keyboard focus indicator ───────────────────────────────────────
console.log('\n— H-04 · focus indicator —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  await go(page, '/shop/', 3500);
  const seen = [];
  for (let i = 0; i < 14; i++) {
    await page.keyboard.press('Tab'); await page.waitForTimeout(120);
    const f = await page.evaluate(() => {
      const el = document.activeElement; if (!el || el === document.body) return null;
      const s = getComputedStyle(el);
      return { tag: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : ''),
        text: (el.innerText || el.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim().slice(0, 24),
        outline: s.outlineStyle + ' ' + s.outlineWidth + ' ' + s.outlineColor, ring: s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0,
        shadow: s.boxShadow !== 'none', fv: el.matches(':focus-visible') };
    }).catch(() => null);
    if (f) seen.push(f);
  }
  const skip = await page.evaluate(() => !![...document.querySelectorAll('a[href^="#"]')].find(a => /skip/i.test(a.textContent || ''))).catch(() => false);
  const ringed = seen.filter(f => f.ring || f.shadow).length;
  say('H-04', seen.length && ringed === 0 ? YES : (ringed ? NO : NA), '"computed outline-style none; no :focus-visible rule; no skip link"',
    ringed + ' of ' + seen.length + ' keyboard-focused elements drew a ring (e.g. ' + (seen[0] ? seen[0].tag + ' "' + seen[0].text + '": ' + seen[0].outline : '-') + ') · skip link: ' + (skip ? 'yes' : 'no'));
  await ctx.close();
}

// ── DEF-04 · product page caching ─────────────────────────────────────────
console.log('\n— DEF-04 · product page caching —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  await go(page, '/shop/', 2500);
  const prod = await page.evaluate(() => ([...document.querySelectorAll('a[href*="/product/"]')].map(a => a.href)[3] || '')).catch(() => '');
  const res = [];
  for (let i = 0; i < 3 && prod; i++) {
    const t0 = Date.now();
    const r = await page.goto(prod, { waitUntil: 'commit', timeout: 45000 }).catch(() => null);
    const h = r ? await r.allHeaders().catch(() => ({})) : {};
    res.push((h['x-litespeed-cache'] || 'none') + ' ' + ((Date.now() - t0) / 1000).toFixed(2) + 's');
    await page.waitForTimeout(1500);
  }
  say('DEF-04', res.length && res.every(x => /^miss|^none/.test(x)) ? YES : (res.length ? NO : NA), '"product pages never enter page cache"',
    (prod ? prod.replace(SITE, '') + ': ' : '') + res.join(' · '));
  await ctx.close();
}

// ── N-02 · licence checkbox on a physical-only order ──────────────────────
console.log('\n— N-02 · Digital Download licence on a physical order —');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  await go(page, '/shop/', 2500);
  const product = await page.evaluate(() => {
    const c = [...document.querySelectorAll('li.product, .product-card, .product')]
      .find(x => /\$\s?[\d,.]+/.test(x.innerText || '') && !/price on request/i.test(x.innerText || '') && x.querySelector('a[href*="/product/"]'));
    return c ? c.querySelector('a[href*="/product/"]').href.split('?')[0] : '';
  }).catch(() => '');
  let added = false, youReceive = '';
  if (product) {
    await go(page, product, 3000);
    // a shopper's default choice, with "Painting only" picked if the page offers it
    await page.evaluate(() => {
      for (const sel of document.querySelectorAll('form.cart select')) {
        const o = [...sel.options].find(x => /painting only/i.test(x.textContent || ''));
        if (o) { sel.value = o.value; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      }
      for (const r of document.querySelectorAll('form.cart input[type=radio]')) {
        const lab = (r.closest('label') || {}).textContent || '';
        if (/painting only/i.test(lab)) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
      }
    }).catch(() => {});
    await page.waitForTimeout(800);
    await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(4500);
    await go(page, '/cart/', 2500);
    const cart = await page.evaluate(() => ({ n: document.querySelectorAll('.woocommerce-cart-form .cart_item, .cart_item').length,
      meta: [...document.querySelectorAll('.cart_item .variation, .cart_item dl')].map(e => e.innerText.replace(/\s+/g, ' ').trim()).join(' | ').slice(0, 200) })).catch(() => ({ n: 0, meta: '' }));
    added = cart.n > 0; youReceive = cart.meta;
  }
  if (!added) say('N-02', NA, 'licence checkbox on a physical order', 'could not put an item in the cart' + (product ? ' (' + product.replace(SITE, '') + ')' : ''));
  else {
    await go(page, '/checkout/', 4500);
    const d = await page.evaluate(() => {
      const cb = document.querySelector('#af_dl_license, input[name="af_dl_license"]');
      return { present: !!cb, required: cb ? cb.required : null,
        label: cb ? ((cb.closest('label') || cb.parentElement).innerText || '').replace(/\s+/g, ' ').trim().slice(0, 110) : '' };
    }).catch(() => null);
    const physical = /painting only/i.test(youReceive) || !/digital|download|jpg|file/i.test(youReceive);
    say('N-02', d && d.present && physical ? YES : (d && !d.present ? NO : PART), 'licence checkbox shown for a physical-only line; marked * but not required',
      'cart line: "' + (youReceive || '(no option text)') + '" · checkbox ' + (d && d.present ? 'PRESENT, required attr ' + d.required + ', "' + d.label + '"' : 'absent'));
    // empty the cart
    for (let i = 0; i < 6; i++) {
      await go(page, '/cart/', 1500);
      const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
      if (!h) break;
      await go(page, h, 1200);
    }
  }
  await ctx.close();
}

console.log('\n— summary —');
for (const v of [YES, PART, NO, NA]) console.log('  ' + v.padEnd(13) + verdicts.filter(x => x[1] === v).map(x => x[0]).join(', '));
await browser.close();
console.log('\ndone ' + new Date().toISOString());
