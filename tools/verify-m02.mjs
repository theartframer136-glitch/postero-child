// M-02: does a price sort list what can be bought, cheapest first, without
// losing the products that have no price?
//
// Test Run 01: /shop/?orderby=price opened on three pages of "Price on
// request". The first fix (21 Sep) took those products out of any price sort,
// and Test Run 03 found page 1 of the shop right. inc/price-sort.php sorts
// them last instead, because taking them out also emptied the categories
// that are mostly them (Art Accessories) and changed the number of results
// with the sort.
//
// As a first-time visitor, checks:
//   1  /shop/?orderby=price, page 1: no "Price on request", cheapest first,
//      prices rising
//   2  the same after "Load More Artworks" twice: still none, still rising
//   3  /shop/?orderby=price-desc, page 1: no "Price on request", falling
//   4  the shop lists as many products sorted by price as sorted by default
//   5  Art Accessories, Corporate Printing and Banners & Signage: the same
//      number sorted by price as by default, and never "This collection is
//      empty right now."
// It also reports where the products without a price are in the price sort.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-m02.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const results = [];
const say = (ok, what, seen) => { results.push(ok); console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + what.padEnd(64) + seen); };

// Every card's price (the lowest current amount, struck-through prices left
// out) or 'POR', and the page's stated count.
const read = () => {
  const t = s => String(s || '').replace(/\s+/g, ' ').trim();
  const cards = [...document.querySelectorAll('ul.products li.product')].map(li => {
    const name = t((li.querySelector('.woocommerce-loop-product__title, h2, h3') || {}).textContent).slice(0, 36);
    if (li.querySelector('.af-por') || /price on request/i.test(li.textContent)) return { name, price: 'POR' };
    const amounts = [...li.querySelectorAll('.price .woocommerce-Price-amount')].filter(a => !a.closest('del'))
      .map(a => parseFloat(t(a.textContent).replace(/[^0-9.]/g, ''))).filter(n => !isNaN(n));
    return { name, price: amounts.length ? Math.min(...amounts) : null };
  });
  const rc = t((document.querySelector('.woocommerce-result-count') || {}).textContent);
  const m = rc.match(/of\s+([\d,]+)/i) || rc.match(/all\s+([\d,]+)/i);
  const total = m ? parseInt(m[1].replace(/,/g, ''), 10) : (/single result/i.test(rc) ? 1 : cards.length);
  const lastPage = Math.max(1, ...[...document.querySelectorAll('.woocommerce-pagination a.page-numbers, .woocommerce-pagination span.page-numbers')]
    .map(a => parseInt(t(a.textContent), 10)).filter(n => !isNaN(n)));
  const empty = /This collection is empty right now|No products were found/i.test(document.body.innerText);
  return { cards, total, lastPage, empty };
};
const open = async (page, path) => {
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) return null;
  await page.waitForTimeout(2500);
  return page.evaluate(read).catch(() => null);
};
const fresh = async () => { const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); return { ctx, page: await ctx.newPage() }; };
const rising = xs => xs.every((x, i) => i === 0 || x >= xs[i - 1]);
const falling = xs => xs.every((x, i) => i === 0 || x <= xs[i - 1]);
const prices = d => d.cards.map(c => c.price).filter(p => typeof p === 'number');
const por = d => d.cards.filter(c => c.price === 'POR').length;

console.log('verify-m02: ' + SITE + '   ' + new Date().toISOString() + '\n');

// 1, 2: low to high, and Load More
{
  const { ctx, page } = await fresh();
  const d = await open(page, '/shop/?orderby=price');
  if (!d) { say(false, '1  /shop/?orderby=price answers', 'no answer'); }
  else {
    const p = prices(d);
    console.log('  shop, low to high, page 1: ' + d.cards.slice(0, 6).map(c => c.name + ' ' + c.price).join(' | '));
    say(por(d) === 0 && p.length > 0 && rising(p), '1  page 1 low to high: no "Price on request", prices rising',
      por(d) + ' POR of ' + d.cards.length + ' · first $' + p[0] + ' · ' + (rising(p) ? 'rising' : 'NOT rising: ' + p.join(', ')));
    for (let i = 0; i < 2; i++) {
      const btn = await page.$('.af-inf-btn');
      if (!btn) break;
      await btn.click().catch(() => {});
      await page.waitForTimeout(4000);
    }
    const more = await page.evaluate(read).catch(() => null);
    if (more) {
      const mp = prices(more);
      say(more.cards.length > d.cards.length && por(more) === 0 && rising(mp), '2  after Load More twice: none, still rising',
        more.cards.length + ' cards · ' + por(more) + ' POR · ' + (rising(mp) ? 'rising to $' + mp[mp.length - 1] : 'NOT rising'));
    }
  }
  await ctx.close();
}

// 3: high to low
{
  const { ctx, page } = await fresh();
  const d = await open(page, '/shop/?orderby=price-desc');
  if (d) {
    const p = prices(d);
    say(por(d) === 0 && p.length > 0 && falling(p), '3  page 1 high to low: no "Price on request", prices falling',
      por(d) + ' POR · first $' + p[0] + ' · ' + (falling(p) ? 'falling' : 'NOT falling: ' + p.join(', ')));
  } else say(false, '3  /shop/?orderby=price-desc answers', 'no answer');
  await ctx.close();
}

// 4: the shop's count, default vs price; and where the unpriced ones are
{
  const { ctx, page } = await fresh();
  const def = await open(page, '/shop/');
  const byPrice = await open(page, '/shop/?orderby=price');
  say(!!def && !!byPrice && def.total === byPrice.total, '4  the shop lists as many sorted by price as by default',
    (def ? def.total : '?') + ' by default · ' + (byPrice ? byPrice.total : '?') + ' by price');
  if (byPrice && byPrice.lastPage > 1) {
    const last = await open(page, '/shop/page/' + byPrice.lastPage + '/?orderby=price');
    if (last) console.log('                    last page of the price sort (' + byPrice.lastPage + '): ' + por(last) + ' of ' + last.cards.length + ' "Price on request"');
  }
  await ctx.close();
}

// 5: the categories that are mostly products without a price
{
  const { ctx, page } = await fresh();
  await open(page, '/shop/');
  const links = await page.evaluate(() => {
    const out = {};
    for (const a of document.querySelectorAll('a[href*="/product-category/"]')) {
      const n = (a.textContent || '').replace(/\s+/g, ' ').trim();
      for (const want of ['Art Accessories', 'Corporate Printing', 'Banners & Signage']) {
        if (!out[want] && n.toLowerCase().startsWith(want.toLowerCase())) out[want] = new URL(a.href).pathname;
      }
    }
    return out;
  }).catch(() => ({}));
  const fallback = { 'Art Accessories': '/product-category/art-accessories/', 'Corporate Printing': '/product-category/corporate-printing/', 'Banners & Signage': '/product-category/banners-signage/' };
  const rows = [];
  for (const name of Object.keys(fallback)) {
    const path = links[name] || fallback[name];
    const def = await open(page, path);
    const byPrice = await open(page, path + '?orderby=price');
    const ok = !!def && !!byPrice && def.total > 0 && byPrice.total === def.total && !byPrice.empty;
    rows.push(ok);
    console.log('  ' + name.padEnd(20) + (def ? def.total + ' by default (' + por(def) + ' POR on page 1)' : 'no answer')
      + ' · ' + (byPrice ? byPrice.total + ' by price' + (byPrice.empty ? ', says it is EMPTY' : '') + ' (' + por(byPrice) + ' POR on page 1)' : 'no answer') + '   ' + path);
  }
  say(rows.length === 3 && rows.every(Boolean), '5  those three categories: as many by price as by default, never "empty"', rows.map(r => r ? 'right' : 'wrong').join(' · '));
  await ctx.close();
}

const right = results.filter(Boolean).length;
console.log('\n' + (right === results.length ? 'M-02 FIXED' : 'M-02 NOT FIXED') + ': ' + right + ' of ' + results.length + ' checks right');
console.log('done ' + new Date().toISOString());
await browser.close();
