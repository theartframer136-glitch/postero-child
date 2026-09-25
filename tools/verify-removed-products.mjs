// Can a visitor still reach the products taken off the website?
//
// inc/placeholder-products.php makes them private: #11491 "test" (N-01), and
// from 25 Sep the five theme demo posters the owner asked to remove (art
// codes TMP-1000 to TMP-1004). This checks, for each, every way a shopper or
// a search engine could reach it, as a first-time visitor with no cookies:
//   - its own address, and ?p=<id>
//   - the Store API, by id and by searching its name
//   - a product search for its name and for its art code
//   - the shop, the shop sorted by price, and the home page
//   - the product sitemaps
// tools/verify-n01.mjs is the same check for #11491 alone.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-removed-products.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCTS = [
  { id: 115, slug: 'the-penguin-show-poster', name: 'The Penguin Show Poster', code: 'TMP-1000' },
  { id: 123, slug: 'il-lemone-poster', name: 'IL Lemone Poster', code: 'TMP-1001' },
  { id: 177, slug: 'geometric-shapes-poster', name: 'Geometric Shapes poster', code: 'TMP-1002' },
  { id: 199, slug: 'balance-poster', name: 'Balance Poster', code: 'TMP-1003' },
  { id: 211, slug: 'japanese-butterfly-ii-poster', name: 'Japanese Butterfly II Poster', code: 'TMP-1004' },
  { id: 11491, slug: 'test-canvas-wall-art', name: 'test', code: 'TMP-1055' },
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async path => page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
const same = (a, b) => (a || '').replace(/\s+/g, ' ').trim().toLowerCase() === (b || '').replace(/\s+/g, ' ').trim().toLowerCase();

console.log('verify-removed-products: ' + SITE + '   ' + new Date().toISOString() + '\n');

// Listings shared by every product: which of them does each link to?
const LISTINGS = ['/shop/', '/shop/?orderby=price', '/'];
const linkedFrom = {};
for (const path of LISTINGS) {
  const r = await go(path);
  await page.waitForTimeout(2500);
  const hrefs = r ? await page.evaluate(() => [...document.querySelectorAll('a[href]')].map(a => a.href)).catch(() => null) : null;
  linkedFrom[path] = hrefs;
}

// The product sitemaps, read once.
await go('/');
const sitemaps = await page.evaluate(async () => {
  const idx = await fetch('/sitemap_index.xml').then(r => r.ok ? r.text() : '').catch(() => '');
  const out = [];
  for (const m of [...idx.matchAll(/<loc>([^<]*product-sitemap[^<]*)<\/loc>/g)].map(m => m[1])) {
    const path = new URL(m).pathname;
    out.push({ path, text: await fetch(path).then(r => r.ok ? r.text() : '').catch(() => '') });
  }
  return out;
}).catch(() => []);

let removed = 0;
for (const p of PRODUCTS) {
  console.log('#' + p.id + ' ' + p.code + ' "' + p.name + '"');
  const ways = [];
  const say = (gone, what, measured) => { ways.push(gone); console.log('  ' + (gone ? 'GONE   ' : 'STILL  ') + what.padEnd(44) + ' ' + measured); };

  // Its own address: a 404, or a page that is not this product.
  for (const path of ['/product/' + p.slug + '/', '/?p=' + p.id]) {
    const r = await go(path);
    if (!r) { say(false, path, 'no answer'); continue; }
    await page.waitForTimeout(1200);
    const d = await page.evaluate(() => ({
      h1: ((document.querySelector('h1.product_title, h1') || {}).textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60),
      atc: !!document.querySelector('.single_add_to_cart_button'),
    })).catch(() => ({ h1: '', atc: false }));
    const shown = r.status() === 200 && same(d.h1, p.name);
    say(!shown, path, 'HTTP ' + r.status() + ' · H1 "' + d.h1 + '" · Add to Cart ' + (d.atc ? 'yes' : 'no')
      + ' · x-litespeed-cache ' + (r.headers()['x-litespeed-cache'] || '-'));
  }

  // The Store API: what every widget and app reads.
  await go('/');
  const api = await page.evaluate(async ({ id, name }) => {
    const one = await fetch('/wp-json/wc/store/v1/products/' + id).then(r => r.status).catch(() => 0);
    const hits = await fetch('/wp-json/wc/store/v1/products?per_page=50&search=' + encodeURIComponent(name)).then(r => r.ok ? r.json() : []).catch(() => []);
    return { one, found: hits.some(h => h.id === id) };
  }, p).catch(() => ({ one: 0, found: true }));
  say(api.one !== 200, 'Store API /products/' + p.id, 'HTTP ' + api.one);
  say(!api.found, 'Store API search "' + p.name + '"', api.found ? 'listed' : 'not listed');

  // Product search by name and by art code.
  for (const q of [p.name, p.code]) {
    const path = '/?s=' + encodeURIComponent(q) + '&post_type=product';
    const r = await go(path);
    if (!r) { say(false, 'search "' + q + '"', 'no answer'); continue; }
    await page.waitForTimeout(1500);
    const d = await page.evaluate(({ slug, name }) => ({
      linked: [...document.querySelectorAll('a[href]')].some(a => a.href.includes('/product/' + slug)),
      h1: ((document.querySelector('h1.product_title') || {}).textContent || '').trim(),
    }), p).catch(() => null);
    // A search with one hit can redirect straight to the product page.
    const shown = !d || d.linked || same(d.h1, p.name);
    say(!shown, 'search "' + q + '"', 'HTTP ' + r.status() + ' · ' + (d ? (d.linked ? 'links to it' : same(d.h1, p.name) ? 'went to its page' : 'no link') : 'unreadable'));
  }

  // The listings read above.
  for (const path of LISTINGS) {
    const hrefs = linkedFrom[path];
    if (!hrefs) { say(false, path, 'page unreadable'); continue; }
    const linked = hrefs.some(h => h.includes('/product/' + p.slug + '/'));
    say(!linked, path, linked ? 'links to it' : 'no link');
  }

  if (!sitemaps.length) console.log('  NO DATA product sitemaps                             the sitemap index could not be read');
  else {
    const listed = sitemaps.filter(s => s.text.includes('/product/' + p.slug + '/')).map(s => s.path);
    say(!listed.length, 'product sitemaps', sitemaps.length + ' read · listed in: ' + (listed.join(', ') || 'none'));
  }

  const gone = ways.filter(Boolean).length;
  if (gone === ways.length) removed++;
  console.log('  ' + (gone === ways.length ? 'REMOVED' : 'STILL REACHABLE') + ': ' + gone + ' of ' + ways.length + ' ways in are closed\n');
}

console.log('removed from the website: ' + removed + ' of ' + PRODUCTS.length);
console.log('done ' + new Date().toISOString());
await browser.close();
