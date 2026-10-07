// N-01: can a visitor still reach the $1.00 product called "test" (id 11491)?
//
// Test Run 03 found it live: 200 at its own address, index,follow, Add to
// Cart, and first on /shop/?orderby=price. inc/placeholder-products.php makes
// it private. This checks every way a shopper or a search engine could reach
// it, as a first-time visitor with no cookies:
//   - its own address, and ?p=11491
//   - the Store API, by id and by searching "test"
//   - the shop sorted by price, a product search, the home page, clearance
//   - the product sitemaps
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-n01.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const ID = 11491, SLUG = 'test-canvas-wall-art';
const results = [];
const say = (ok, what, measured) => { results.push(ok); console.log('  ' + (ok ? 'GONE   ' : 'STILL  ') + what.padEnd(34) + measured); };

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async path => page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-n01: ' + SITE + '   ' + new Date().toISOString() + '\n');

// Its own address. A 404, or a redirect to somewhere that is not the product.
for (const path of ['/product/' + SLUG + '/', '/?p=' + ID, '/?post_type=product&p=' + ID]) {
  const r = await go(path);
  if (!r) { say(false, path, 'no answer'); continue; }
  await page.waitForTimeout(1500);
  const d = await page.evaluate(() => ({
    h1: ((document.querySelector('h1.product_title, h1') || {}).textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60),
    atc: !!document.querySelector('.single_add_to_cart_button'),
  })).catch(() => ({ h1: '', atc: false }));
  const product = r.status() === 200 && /^test$/i.test(d.h1);
  say(!product, path, 'HTTP ' + r.status() + ' · landed on ' + new URL(page.url()).pathname + ' · H1 "' + d.h1 + '" · Add to Cart ' + (d.atc ? 'yes' : 'no')
    + ' · x-litespeed-cache ' + (r.headers()['x-litespeed-cache'] || '-'));
}

// The Store API: the public product data every theme widget and app reads.
await go('/');
const api = await page.evaluate(async id => {
  const one = await fetch('/wp-json/wc/store/v1/products/' + id).then(r => r.status).catch(() => 0);
  const hits = await fetch('/wp-json/wc/store/v1/products?search=test&per_page=50').then(r => r.ok ? r.json() : []).catch(() => []);
  return { one, named: hits.filter(p => /^test$/i.test((p.name || '').trim())).map(p => p.id + ' $' + (p.prices ? Number(p.prices.price) / Math.pow(10, p.prices.currency_minor_unit || 0) : '?')) };
}, ID);
say(api.one !== 200, 'Store API /products/' + ID, 'HTTP ' + api.one);
say(!api.named.length, 'Store API search "test"', api.named.length ? 'named "test": ' + api.named.join(', ') : 'no product named "test"');

// Listings: is there a link to it, and what is first on the price sort?
for (const path of ['/shop/?orderby=price', '/?s=test&post_type=product', '/', '/clearance/']) {
  const r = await go(path);
  if (!r) { say(false, path, 'no answer'); continue; }
  await page.waitForTimeout(2500);
  const d = await page.evaluate(slug => {
    const linked = [...document.querySelectorAll('a[href]')].some(a => a.href.includes('/product/' + slug));
    const c = [...document.querySelectorAll('ul.products li.product')].filter(x => !x.closest('.af-xsell'))[0];
    const first = c ? ((c.querySelector('.woocommerce-loop-product__title, h2, h3') || {}).textContent || '').replace(/\s+/g, ' ').trim().slice(0, 50)
      + ' at ' + ((c.querySelector('.price') || {}).innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40) : '';
    return { linked, first, count: document.querySelectorAll('ul.products li.product').length };
  }, SLUG).catch(() => null);
  if (!d) { say(false, path, 'page unreadable'); continue; }
  say(!d.linked, path, 'HTTP ' + r.status() + ' · link to it: ' + (d.linked ? 'YES' : 'no') + ' · ' + d.count + ' cards'
    + (path.includes('orderby=price') ? ' · first card "' + d.first + '"' : ''));
}

// The product sitemaps: does a search engine still get told about it?
const sm = await page.evaluate(async slug => {
  const idx = await fetch('/sitemap_index.xml').then(r => r.ok ? r.text() : '').catch(() => '');
  const maps = [...idx.matchAll(/<loc>([^<]*product-sitemap[^<]*)<\/loc>/g)].map(m => m[1]);
  let listed = [], fresh = [];
  for (const m of maps) {
    const path = new URL(m).pathname;
    const r = await fetch(path).catch(() => null);
    const x = r && r.ok ? await r.text() : '';
    if (!x.includes('/product/' + slug + '/')) continue;
    // Listed. Is that the page cache serving an old copy? A query string the
    // page cache has never seen goes past it to WordPress. If that copy lists
    // it too, the old copy is Rank Math's own stored sitemap.
    const f = await fetch(path + '?af_nocache=' + Date.now()).catch(() => null);
    const fx = f && f.ok ? await f.text() : '';
    listed.push(path + ' (cache ' + (r.headers.get('x-litespeed-cache') || '-') + ', age ' + (r.headers.get('age') || '-') + ')');
    fresh.push(path + ' past the page cache: HTTP ' + (f ? f.status : 0) + ', cache ' + (f && f.headers.get('x-litespeed-cache') || '-')
      + ', ' + (fx.includes('/product/' + slug + '/') ? 'STILL lists it' : 'does not list it'));
  }
  return { maps: maps.length, listed, fresh };
}, SLUG).catch(() => ({ maps: 0, listed: [], fresh: [] }));
if (!sm.maps) console.log('  NO DATA ' + 'product sitemaps'.padEnd(34) + 'the sitemap index could not be read');
else say(!sm.listed.length, 'product sitemaps', sm.maps + ' read · listed in: ' + (sm.listed.join(', ') || 'none'));
for (const f of sm.fresh) console.log(' '.repeat(43) + f);

const gone = results.filter(Boolean).length;
console.log('\n' + (gone === results.length ? 'N-01 FIXED' : 'N-01 NOT FIXED') + ': ' + gone + ' of ' + results.length + ' ways in are closed');
console.log('done ' + new Date().toISOString());
await browser.close();
