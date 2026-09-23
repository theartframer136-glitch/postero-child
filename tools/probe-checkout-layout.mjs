// DEF-16: the checkout's order summary clips in two width bands, and the
// card field shrinks to 157 px.
//
// Reported, with one item in the cart:
//   width  table/container  total clipped  card iframe
//   320    321 / 218         103 px        —
//   375    321 / 273          48 px        —
//   414    321 / 312           9 px        —
//   768    651 / 651           0 px        567×48  ok
//   790    321 / 244          77 px        157×97  too narrow
//   900    321 / 287          33 px        203×97  too narrow
//   1024   339 / 339           0 px        255×97  cramped
// and the floating WhatsApp and wishlist buttons sit over the price column
// at 790–900.
//
// Before changing any CSS this reads, per width, what is actually there:
//   - the order summary's container and table widths, and how far the TOTAL
//     amount's right edge runs past what can be seen (the container, or the
//     viewport, whichever is nearer)
//   - which element makes the two columns, with its computed display and
//     grid-template-columns / widths, because that rule comes from the parent
//     theme or WooCommerce, not from this repo, and the fix has to meet it
//   - the card iframe's size
//   - any position:fixed control overlapping the totals
//   - horizontal page scroll
//
// SAFETY: one item is added to the cart; wc-ajax=checkout is in NEVER_SEND,
// so no order can be placed, and Place Order is never clicked. The cart is
// emptied at the end.
//
// Run: node tools/probe-checkout-layout.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const WIDTHS = [320, 375, 414, 768, 790, 900, 1000, 1024, 1100, 1280];
const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save'];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure","messages":"blocked by probe"}' });
  }
  return route.continue();
});
const page = await ctx.newPage();
const go = async (path, wait = 3000) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

console.log('probe-checkout-layout: ' + SITE + '   ' + new Date().toISOString() + '\n');

// one buyable item into the cart
await go('/shop/', 2500);
const product = await page.evaluate(() => {
  const c = [...document.querySelectorAll('li.product, .product-card, .product')]
    .find(x => /\$\s?[\d,.]+/.test(x.innerText || '') && !/price on request/i.test(x.innerText || '') && x.querySelector('a[href*="/product/"]'));
  return c ? c.querySelector('a[href*="/product/"]').href.split('?')[0] : '';
}).catch(() => '');
let pid = 0;
if (product) {
  await go(product, 2500);
  pid = await page.evaluate(() => {
    const h = document.querySelector('input[name="add-to-cart"]'), b = document.querySelector('button[name="add-to-cart"], .single_add_to_cart_button');
    const m = ((document.body && document.body.className) || '').match(/postid-(\d+)/);
    return Number((h && h.value) || (b && b.value) || (m && m[1]) || 0);
  }).catch(() => 0);
  if (pid) await go(product + '?add-to-cart=' + pid + '&quantity=1', 3500);
}
console.log('item: ' + (product ? product.replace(SITE, '') + ' (id ' + pid + ')' : 'NONE FOUND') + '\n');

const measure = () => page.evaluate(() => {
  const r = el => { if (!el) return null; const b = el.getBoundingClientRect(); return { l: Math.round(b.left), r: Math.round(b.right), w: Math.round(b.width), h: Math.round(b.height), t: Math.round(b.top) }; };
  const vw = document.documentElement.clientWidth;
  const review = document.querySelector('#order_review');
  const table = document.querySelector('.woocommerce-checkout-review-order-table, #order_review table.shop_table');
  const total = document.querySelector('.woocommerce-checkout-review-order-table .order-total td .amount, .order-total .amount')
    || document.querySelector('.order-total td');
  const cust = document.querySelector('#customer_details');
  const form = document.querySelector('form.checkout, form.woocommerce-checkout');
  // the nearest element holding both columns
  let holder = null;
  if (cust && review) { for (let n = review.parentElement; n; n = n.parentElement) { if (n.contains(cust)) { holder = n; break; } } }
  const cs = el => { if (!el) return null; const s = getComputedStyle(el); return { display: s.display, gtc: s.gridTemplateColumns, float: s.float, width: s.width, flex: s.flex }; };
  const rv = r(review), tb = r(table), tt = r(total);
  const visibleRight = Math.min(vw, rv ? rv.r : vw);
  const clipped = tt ? Math.max(0, tt.r - visibleRight) : null;
  const iframe = [...document.querySelectorAll('iframe')].find(f => /squareup|squarecdn|sq-|card/i.test((f.src || '') + ' ' + (f.name || '') + ' ' + (f.id || '') + ' ' + (f.title || '')));
  const fr = r(iframe);
  // fixed-position controls over the totals
  const overlaps = [];
  if (tt) {
    document.querySelectorAll('body *').forEach(el => {
      const s = getComputedStyle(el);
      if (s.position !== 'fixed' || s.display === 'none' || s.visibility === 'hidden' || +s.opacity === 0) return;
      const b = el.getBoundingClientRect();
      if (b.width < 8 || b.height < 8 || b.width > vw * 0.8) return;
      // compare against the whole totals column's vertical range at this scroll
      const tr = table ? table.getBoundingClientRect() : null;
      if (!tr) return;
      const hit = b.left < tr.right && b.right > tr.left && b.top < tr.bottom && b.bottom > tr.top;
      if (hit) overlaps.push(((el.id ? '#' + el.id : '') || (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : el.tagName)).slice(0, 40));
    });
  }
  return {
    vw, hscroll: document.documentElement.scrollWidth > vw + 1,
    review: rv, table: tb ? { ...tb, sw: table.scrollWidth } : null, total: tt, clipped,
    tableLayout: table ? getComputedStyle(table).tableLayout : null,
    cust: r(cust), holder: holder ? (holder.tagName.toLowerCase() + (holder.id ? '#' + holder.id : '') + (holder.className && typeof holder.className === 'string' ? '.' + holder.className.trim().split(/\s+/).slice(0, 2).join('.') : '')) : null,
    holderCss: cs(holder), custCss: cs(cust), reviewCss: cs(review),
    iframe: fr ? fr.w + '×' + fr.h : '(none)',
    overlaps: [...new Set(overlaps)].slice(0, 4),
    twoCol: !!(cust && review && r(cust).t < r(review).t + 40 && Math.abs(r(cust).l - r(review).l) > 50),
  };
}).catch(e => ({ error: String(e.message).slice(0, 100) }));

console.log('width  cols  container  table(sw)   total right  clipped  card iframe  hscroll  over the totals');
const rows = [];
for (const w of WIDTHS) {
  await page.setViewportSize({ width: w, height: 900 });
  const s = await go('/checkout/', 4500);
  // scroll the summary into view so fixed controls are compared where a shopper sees them
  await page.evaluate(() => { const t = document.querySelector('#order_review'); if (t) t.scrollIntoView({ block: 'center' }); }).catch(() => {});
  await page.waitForTimeout(900);
  const m = await measure();
  rows.push({ w, s, ...m });
  if (m.error || !m.review) { console.log(String(w).padEnd(7) + 'HTTP ' + s + '  ' + (m.error || 'no #order_review — cart empty or redirected')); continue; }
  console.log(String(w).padEnd(7) + (m.twoCol ? '2' : '1').padEnd(6)
    + String(m.review.w).padEnd(11) + (m.table ? m.table.w + '(' + m.table.sw + ')' : '?').padEnd(12)
    + String(m.total ? m.total.r : '?').padEnd(13) + (m.clipped === null ? '?' : m.clipped + ' px').padEnd(9)
    + String(m.iframe).padEnd(13) + (m.hscroll ? 'YES' : 'no').padEnd(9) + (m.overlaps.join(', ') || '—'));
}

const two = rows.find(x => x.twoCol) || rows[rows.length - 1];
if (two && two.holderCss) {
  console.log('\n— what makes the columns (at ' + two.w + ' px) —');
  console.log('  holder   ' + two.holder + '   display ' + two.holderCss.display + '   grid-template-columns ' + two.holderCss.gtc);
  console.log('  #customer_details  width ' + (two.custCss && two.custCss.width) + '  float ' + (two.custCss && two.custCss.float) + '  flex ' + (two.custCss && two.custCss.flex));
  console.log('  #order_review       width ' + (two.reviewCss && two.reviewCss.width) + '  float ' + (two.reviewCss && two.reviewCss.float) + '  flex ' + (two.reviewCss && two.reviewCss.flex));
  console.log('  table-layout ' + two.tableLayout);
}
const first2 = rows.find(x => x.twoCol);
console.log('\n— verdict —');
const bad = rows.filter(x => x.clipped > 0);
console.log('  two columns from: ' + (first2 ? first2.w + ' px' : 'never'));
console.log('  widths where the total is clipped: ' + (bad.length ? bad.map(x => x.w + ' (' + x.clipped + ' px)').join(', ') : 'none'));
const narrow = rows.filter(x => /^\d+×/.test(x.iframe) && parseInt(x.iframe, 10) < 300);
console.log('  card field under 300 px wide: ' + (narrow.length ? narrow.map(x => x.w + ' (' + x.iframe + ')').join(', ') : 'none'));
const over = rows.filter(x => x.overlaps && x.overlaps.length);
console.log('  fixed controls over the totals: ' + (over.length ? over.map(x => x.w + ' ' + x.overlaps.join('/')).join('; ') : 'none'));
console.log('  horizontal page scroll: ' + (rows.filter(x => x.hscroll).map(x => x.w).join(', ') || 'none'));

// empty the cart
for (let i = 0; i < 6; i++) {
  await go('/cart/', 1500);
  const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
  if (!h) break;
  await go(h, 1200);
}
await browser.close();
console.log('\ndone ' + new Date().toISOString());
