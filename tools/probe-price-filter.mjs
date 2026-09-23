// DEF-17: an inverted price filter returns a blank page.
//
// Reported: /shop/?min_price=500&max_price=10 shows 0 products, no result
// count and no "nothing matched" message.
//
// WooCommerce reads both bounds straight from the query string when it builds
// the product query (WC_Query::price_filter_post_clauses), as
//     NOT (max < product.min_price OR min > product.max_price)
// so a minimum above the maximum can match nothing. Reading that code turns up
// a second way to the same page: a bound that is PRESENT BUT EMPTY counts as
// 0. The shop's own Price form always submits both fields, so a shopper who
// types only a minimum sends ?min_price=100&max_price= and asks for products
// costing at most $0.
//
// So this loads, in a real browser (the crawl guard answers a client without
// Sec-Fetch headers with a 302 to the bare archive, so a plain HTTP check
// measures the guard, not the page):
//   the report's URL, the same range the right way round, min only through
//   the form (max empty), both empty, a valid range with nothing in it (the
//   empty state a fix cannot swap away), the report's URL on a category, and
//   the plain shop as a control
// and, per page: where it landed, the products in the grid (not the "You may
// also like" row), the result count, any "nothing found" message and whether
// it can be seen, whether the filter toolbar is there to change the price,
// and the values the Price form shows.
//
// Then it uses the Price form itself, as a shopper would: Min 500 and Max 10,
// and Min 100 with Max left blank.
//
// Read-only. No cart, no writes.
//
// Run: node tools/probe-price-filter.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 950 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

const read = () => page.evaluate(() => {
  const seen = el => {
    if (!el) return false;
    const cs = getComputedStyle(el), r = el.getBoundingClientRect();
    return cs.display !== 'none' && cs.visibility !== 'hidden' && +cs.opacity > 0 && r.width > 2 && r.height > 2;
  };
  const txt = el => ((el && (el.innerText || el.textContent)) || '').replace(/\s+/g, ' ').trim();
  // the listing's own grid, not the cross-sell row under it
  const cards = [...document.querySelectorAll('ul.products li.product, .products .product-card')]
    .filter(li => !li.closest('.af-xsell, .related, .upsells, .cross-sells'));
  const count = document.querySelector('.woocommerce-result-count');
  // every place a "nothing found" message could come from
  const msgSel = '.woocommerce-no-products-found, .woocommerce-info, .woocommerce-message, .elementor-products-nothing-found, .elementor-nothing-found, .af-empty-filter';
  const msgs = [...document.querySelectorAll(msgSel)]
    .filter(el => !el.closest('.af-xsell'))
    .map(el => ({ cls: ((el.className || '') + '').split(/\s+/).slice(0, 2).join('.'), text: txt(el).slice(0, 90), seen: seen(el) }));
  // anything else on the page that says nothing matched
  const said = /no products were found|nothing (matched|found)|no (artworks|pieces|products) (match|found|in this price)/i;
  const loose = [...document.querySelectorAll('main p, main div, #primary p, #primary div, .site-main p, .site-main div')]
    .filter(el => el.children.length < 3 && said.test(txt(el)) && !el.closest('.af-xsell')).slice(0, 2)
    .map(el => ({ text: txt(el).slice(0, 90), seen: seen(el) }));
  const toolbar = document.querySelector('.af-listing-toolbar');
  const minI = document.querySelector('.af-lt-price input[name="min_price"]');
  const maxI = document.querySelector('.af-lt-price input[name="max_price"]');
  const main = document.querySelector('main, #primary, .site-main, #content') || document.body;
  return {
    url: location.pathname + location.search,
    cards: cards.length,
    count: count ? txt(count) : null,
    msgs, loose,
    toolbar: !!toolbar && seen(toolbar),
    form: minI || maxI ? '[' + (minI ? minI.value : '—') + ' – ' + (maxI ? maxI.value : '—') + ']' : '(no form)',
    clear: !![...document.querySelectorAll('a')].find(a => /clear (all |the )?filters?|reset filters?|show all|remove (only )?(the )?price/i.test(txt(a)) && seen(a)),
    xsell: !!document.querySelector('.af-xsell'),
    h1: [...document.querySelectorAll('h1')].filter(seen).map(txt).slice(0, 1)[0] || '(none)',
    mainText: txt(main).length,
    // which Elementor widget renders the products, if one does
    widgets: [...new Set([...document.querySelectorAll('[data-widget_type]')].map(e => e.getAttribute('data-widget_type'))
      .filter(t => /product|loop|archive/i.test(t || '')))].slice(0, 4).join(' ') || '(none)',
    body: ((document.body && document.body.className) || '').split(/\s+/)
      .filter(c => /elementor-(template|page|location)|archive|woocommerce-shop|tax-product|post-type-archive/.test(c)).slice(0, 5).join(' '),
  };
}).catch(e => ({ error: String(e.message).slice(0, 120) }));

const hops = [];
page.on('response', r => {
  try { if (r.request().isNavigationRequest() && r.request().frame() === page.mainFrame() && r.status() >= 300 && r.status() < 400) hops.push(r.status() + ' ' + (r.headers()['location'] || '')); } catch (e) {}
});

const show = (label, status, d) => {
  console.log('── ' + label + '   HTTP ' + status + (hops.length ? '   via ' + hops.join(' → ') : ''));
  if (!d || d.error) { console.log('    NO DATA ' + (d && d.error || '') + '\n'); return; }
  console.log('    landed on    : ' + d.url);
  console.log('    products     : ' + d.cards + (d.count ? '   ("' + d.count + '")' : '   (no result count)'));
  if (d.msgs.length) d.msgs.forEach(m => console.log('    message      : [' + (m.seen ? 'seen' : 'HIDDEN') + '] .' + m.cls + '  "' + m.text + '"'));
  else if (d.loose.length) d.loose.forEach(m => console.log('    message      : [' + (m.seen ? 'seen' : 'HIDDEN') + '] "' + m.text + '"'));
  else console.log('    message      : none');
  console.log('    price toolbar: ' + (d.toolbar ? 'yes, form shows ' + d.form : 'NOT ON THE PAGE') + '   clear-filters link: ' + (d.clear ? 'yes' : 'no'));
  console.log('    also         : "You may also like" row ' + (d.xsell ? 'yes' : 'no') + ' · h1 "' + d.h1 + '" · main text ' + d.mainText + ' chars');
  console.log('    template     : ' + (d.body || '(no telling classes)') + '   widgets: ' + d.widgets + '\n');
};

console.log('probe-price-filter: ' + SITE + '   ' + new Date().toISOString() + '\n');

// a category to repeat the report's case on
let cat = '/product-category/all-art-prints/';
{
  await page.goto(SITE + '/shop/', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {});
  const c = await page.evaluate(() => ([...document.querySelectorAll('a[href*="/product-category/"]')].map(a => new URL(a.href).pathname)[0] || '')).catch(() => '');
  if (c) cat = c;
}

const CASES = [
  ['inverted (the report)', '/shop/?min_price=500&max_price=10'],
  ['same range, right way', '/shop/?min_price=10&max_price=500'],
  ['min only (form, max blank)', '/shop/?min_price=100&max_price='],
  ['both blank (Apply pressed)', '/shop/?min_price=&max_price='],
  ['valid range, nothing in it', '/shop/?min_price=90000&max_price=99000'],
  ['inverted on a category', cat + '?min_price=500&max_price=10'],
  ['plain shop (control)', '/shop/'],
];
const out = [];
for (const [label, path] of CASES) {
  hops.length = 0;
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  await page.waitForTimeout(2500);
  const d = r ? await read() : null;
  show(label + '   ' + path, r ? r.status() : 0, d);
  out.push({ label, path, status: r ? r.status() : 0, hops: hops.slice(), d });
}

// the Price form, used as a shopper uses it
const viaForm = async (label, min, max) => {
  hops.length = 0;
  await page.goto(SITE + '/shop/', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {});
  await page.waitForTimeout(2000);
  const ok = await page.evaluate(([mn, mx]) => {
    const f = document.querySelector('.af-lt-price form');
    if (!f) return false;
    f.querySelector('input[name="min_price"]').value = mn;
    f.querySelector('input[name="max_price"]').value = mx;
    return true;
  }, [min, max]).catch(() => false);
  if (!ok) { console.log('── form: ' + label + '   NO PRICE FORM ON /shop/\n'); out.push({ label: 'form: ' + label, d: null }); return; }
  const nav = page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  await page.evaluate(() => { const f = document.querySelector('.af-lt-price form'); f.requestSubmit ? f.requestSubmit() : f.submit(); }).catch(() => {});
  const r = await nav;
  await page.waitForTimeout(2500);
  const d = await read();
  show('form: ' + label, r ? r.status() : 0, d);
  out.push({ label: 'form: ' + label, status: r ? r.status() : 0, hops: hops.slice(), d });
};
await viaForm('Min 500, Max 10', '500', '10');
await viaForm('Min 100, Max left blank', '100', '');

console.log('— verdict —');
const ctrl = out.find(o => o.label === 'same range, right way');
for (const o of out) {
  if (!o.d || o.d.error) { console.log('  ' + o.label.padEnd(30) + 'NO DATA'); continue; }
  const d = o.d;
  const msg = d.msgs.some(m => m.seen) || d.loose.some(m => m.seen);
  let v;
  if (d.cards > 0) v = d.cards + ' products' + (o.hops.length ? ' (after ' + o.hops[0].split(' ')[0] + ')' : '');
  else if (msg && d.toolbar) v = 'empty, but SAYS SO and the price can be changed';
  else if (msg) v = 'empty, says so, but NO TOOLBAR to change the price';
  else v = 'BLANK — no products, no message' + (d.toolbar ? '' : ', no toolbar');
  console.log('  ' + o.label.padEnd(30) + v);
}
if (ctrl && ctrl.d && !ctrl.d.error) console.log('\n  (the right-way-round range $10–$500 shows ' + ctrl.d.cards + ' products on page 1)');

await browser.close();
console.log('\ndone ' + new Date().toISOString());
