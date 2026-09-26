// Is the product back in the shop?
//
// #3362 HD-080004-5030 "Divine Lord Ganesha" (Canva page 170) was published
// and buyable but set to "Catalog visibility: Hidden", so no listing showed
// it. inc/placeholder-products.php (revision 5) sets it to "Shop and search
// results". This checks, as a first-time visitor with no cookies:
//   - its page opens with Add to Cart
//   - the Store API lists it among catalog-visible and search-visible products
//   - a product search for its name, and for its art code, links to it
//   - each of its category pages links to it (every page of the category)
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-product-visible.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const P = { id: 3362, code: 'HD-080004-5030', name: 'Divine Lord Ganesha', slug: 'lord-ganesha-canvas-wall-art' };

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async path => page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
const linksToIt = () => page.evaluate(slug => [...document.querySelectorAll('ul.products a[href], .products a[href], main a[href]')]
  .some(a => a.href.includes('/product/' + slug + '/')), P.slug).catch(() => false);

console.log('verify-product-visible: #' + P.id + ' ' + P.code + ' "' + P.name + '"   ' + SITE + '   ' + new Date().toISOString() + '\n');
const results = [];
const say = (ok, what, measured) => { results.push(ok); console.log('  ' + (ok ? 'SHOWN  ' : 'MISSING') + ' ' + what.padEnd(44) + measured); };

// Its own page.
const r = await go('/product/' + P.slug + '/');
await page.waitForTimeout(1500);
const d = r ? await page.evaluate(() => ({
  atc: !!document.querySelector('.single_add_to_cart_button'),
  code: ((document.querySelector('.af-art-code--single') || {}).textContent || '').replace(/\s+/g, ' ').trim(),
})).catch(() => ({ atc: false, code: '' })) : { atc: false, code: '' };
say(!!r && r.status() === 200 && d.atc, 'its page', 'HTTP ' + (r ? r.status() : 0) + ' · Add to Cart ' + (d.atc ? 'yes' : 'no') + ' · "' + d.code + '"');

// The Store API, filtered the way the shop and search filter.
const api = await page.evaluate(async ({ id, name }) => {
  const q = v => fetch('/wp-json/wc/store/v1/products?per_page=100&catalog_visibility=' + v + '&search=' + encodeURIComponent(name))
    .then(r => r.ok ? r.json() : []).catch(() => []);
  const one = await fetch('/wp-json/wc/store/v1/products/' + id).then(r => r.ok ? r.json() : null).catch(() => null);
  const [cat, srch] = await Promise.all([q('catalog'), q('search')]);
  return { catalog: cat.some(p => p.id === id), search: srch.some(p => p.id === id),
           cats: one && one.categories ? one.categories.map(c => ({ name: c.name, link: c.link })) : [] };
}, P).catch(() => ({ catalog: false, search: false, cats: [] }));
say(api.catalog, 'Store API, catalog-visible products', api.catalog ? 'listed' : 'not listed');
say(api.search, 'Store API, search-visible products', api.search ? 'listed' : 'not listed');

// Product search, by name and by art code, every page of results: a search
// also matches descriptions, so other products can rank above this one.
const cards = () => page.evaluate(() => document.querySelectorAll('ul.products li.product').length).catch(() => 0);
for (const q of [P.name, P.code]) {
  let found = false, pages = 0, first = 0, status = 0;
  for (let n = 1; n <= 20 && !found; n++) {
    const s = await go((n > 1 ? '/page/' + n + '/' : '/') + '?s=' + encodeURIComponent(q) + '&post_type=product');
    if (!s || s.status() !== 200) { if (n === 1) status = s ? s.status() : 0; break; }
    status = 200; pages = n;
    await page.waitForTimeout(1200);
    if (n === 1) first = await cards();
    found = (await linksToIt()) || page.url().includes('/product/' + P.slug);
    if (!found && (await cards()) === 0) break;
  }
  say(found, 'search "' + q + '"', 'HTTP ' + status + ' · ' + first + ' results on page 1 · '
    + (found ? 'links to it, on page ' + pages : 'no link in ' + pages + ' page(s)'));
}

// Its category pages, every page of each.
for (const c of api.cats.filter(c => !/all art prints|deals|digital canvas prints/i.test(c.name))) {
  let found = false, pages = 0;
  for (let n = 1; n <= 40 && !found; n++) {
    const url = c.link.replace(/\/$/, '') + (n > 1 ? '/page/' + n + '/' : '/');
    const x = await go(url);
    if (!x || x.status() !== 200) break;
    pages = n;
    await page.waitForTimeout(800);
    found = await linksToIt();
  }
  say(found, 'category "' + c.name.replace(/&amp;/g, '&') + '"', (found ? 'links to it, on page ' + pages : 'no link in ' + pages + ' page(s)'));
}

const shown = results.filter(Boolean).length;
console.log('\n' + (shown === results.length ? 'IN THE SHOP' : 'NOT (FULLY) IN THE SHOP') + ': ' + shown + ' of ' + results.length);
console.log('done ' + new Date().toISOString());
await browser.close();
