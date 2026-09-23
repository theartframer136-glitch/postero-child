// DEF-11: invalid quantities are rejected silently.
//
// Reported:
//   qty "0"    -> coerced to 1, added, success message
//   qty "1.5"  -> coerced to 1, added, success message
//   qty "-1"   -> nothing added, NO message
//   qty "abc"  -> nothing added, NO message
//
// The report puts this down to WooCommerce's notice output missing from the
// cart template. WooCommerce's own source says otherwise, and this probe is
// what decides it. WC_Form_Handler turns "abc" into 0 with intval and passes
// -1 through, and WC_Cart::add_to_cart() then returns false for any quantity
// of 0 or less WITHOUT adding a notice. If that is what happens here, there
// is no message anywhere to be output, so no template change could make one
// appear. "0" is a different case: empty("0") is true in PHP, so the form
// handler treats it as blank and substitutes 1.
//
// Two ways in, per value:
//   url   the product URL with ?add-to-cart=ID&quantity=V, which is the
//         request the product form makes and lands on the product page,
//         where notices DO render (the report says so, and DEF-05's cap
//         message is shown there)
//   form  the real product form, with the input's type, min and max
//         stripped so the browser sends exactly what was typed
// After each, /cart/ is read too: a notice nobody printed on the landing
// page is still queued in the session and would appear there.
//
// SAFETY: wc-ajax=checkout is in NEVER_SEND, so no order can be placed. The
// cart is emptied before each case and at the end.
//
// Run: node tools/probe-qty-invalid.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 950 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true}' });
  }
  return route.continue();
});
const page = await ctx.newPage();

const go = async (path, wait = 2200) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

const emptyCart = async () => {
  for (let i = 0; i < 6; i++) {
    await go('/cart/', 1400);
    const h = await page.evaluate(() => {
      const a = document.querySelector('a.remove[href]');
      return a ? a.href : '';
    }).catch(() => '');
    if (!h) break;
    await go(h, 1100);
  }
};

// Every WooCommerce notice on the page, classic and block, by kind.
const notices = () => page.evaluate(() => {
  const out = [];
  const sel = '.woocommerce-error li, .woocommerce-error, .woocommerce-message, .woocommerce-info, '
    + '.wc-block-components-notice-banner';
  const seen = new Set();
  document.querySelectorAll(sel).forEach(el => {
    const t = ((el.innerText || '') + '').replace(/\s+/g, ' ').trim();
    if (!t || seen.has(t)) return;
    // A <ul class="woocommerce-error"> and its <li> carry the same text.
    for (const s of seen) if (s.includes(t) || t.includes(s)) return;
    seen.add(t);
    const kind = el.closest('.woocommerce-error') ? 'error'
      : el.classList.contains('woocommerce-message') ? 'success'
      : el.classList.contains('woocommerce-info') ? 'info' : 'notice';
    out.push(kind + ': ' + t.slice(0, 110));
  });
  return out;
}).catch(() => []);

const cartQty = () => page.evaluate(() => {
  const body = ((document.body && document.body.innerText) || '');
  if (/your cart is currently empty/i.test(body)) return 0;
  const q = document.querySelector('input[name^="cart"][name$="[qty]"], input.qty');
  return q ? Number(q.value || 0) : null;
}).catch(() => null);

console.log('probe-qty-invalid: ' + SITE + '   ' + new Date().toISOString() + '\n');

try {
  // ── a buyable product and its id ──────────────────────────────────────────
  await go('/shop/', 2500);
  let picks = [];
  try {
    picks = await page.evaluate(() => [...document.querySelectorAll('li.product, .product.type-product, .product')]
      .map(c => ({ t: ((c.innerText || '') + ''), href: ((c.querySelector('a[href]') || {}).href) || '' }))
      .filter(x => x.href && /\/product\//.test(x.href) && !/price on request/i.test(x.t) && /\$\s?[\d,.]+/.test(x.t))
      .map(x => x.href).slice(0, 8));
  } catch (e) {}
  let product = '', pid = 0;
  for (const url of picks) {
    await go(url, 2000);
    const id = await page.evaluate(() => {
      const hidden = document.querySelector('input[name="add-to-cart"]');
      const btn = document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]');
      const m = ((document.body && document.body.className) || '').match(/postid-(\d+)/);
      return Number((hidden && hidden.value) || (btn && btn.value) || (m && m[1]) || 0);
    }).catch(() => 0);
    if (id) { product = url.split('?')[0]; pid = id; break; }
  }
  if (!pid) throw new Error('no buyable product with a readable id');
  console.log('product: ' + product.replace(SITE, '') + '   (id ' + pid + ')\n');

  // ── path 1: the request the product form makes ────────────────────────────
  console.log('— url: ' + '<product>?add-to-cart=ID&quantity=V, landing on the product page —');
  console.log('  sent     cart after   on the product page                          then on /cart/');
  const rows = [];
  for (const v of ['1', '-1', 'abc', '0', '1.5']) {
    await emptyCart();
    await go(product + '?add-to-cart=' + pid + '&quantity=' + encodeURIComponent(v), 3200);
    const onPage = await notices();
    await go('/cart/', 2600);
    const qty = await cartQty();
    const onCart = await notices();
    rows.push({ path: 'url', v, qty, onPage, onCart });
    console.log('  ' + JSON.stringify(v).padEnd(8) + ' ' + String(qty ?? '?').padEnd(12)
      + (onPage.join(' | ') || 'NO MESSAGE').slice(0, 60).padEnd(61)
      + (onCart.join(' | ') || '—').slice(0, 60));
  }

  // ── path 2: the real form, the browser's own checks removed ───────────────
  console.log('\n— form: the product page form, input type/min/max stripped —');
  console.log('  typed    cart after   after submitting                             then on /cart/');
  for (const v of ['-1', 'abc']) {
    await emptyCart();
    await go(product, 2600);
    const submitted = await page.evaluate((val) => {
      const q = document.querySelector('form.cart input.qty, form.cart input[name="quantity"], input.qty');
      const f = q ? q.closest('form') : document.querySelector('form.cart');
      if (!q || !f) return 'no quantity input in a form';
      q.setAttribute('type', 'text');
      q.removeAttribute('min'); q.removeAttribute('max'); q.removeAttribute('pattern');
      q.value = val;
      f.setAttribute('novalidate', 'novalidate');
      const b = f.querySelector('.single_add_to_cart_button, button[type="submit"], [name="add-to-cart"][type="submit"]');
      if (b) { b.disabled = false; b.click(); return 'clicked'; }
      f.submit(); return 'submitted';
    }, v).catch(e => 'error: ' + String(e.message).slice(0, 60));
    await page.waitForTimeout(5000);
    const onPage = await notices();
    await go('/cart/', 2600);
    const qty = await cartQty();
    const onCart = await notices();
    rows.push({ path: 'form', v, qty, onPage, onCart });
    console.log('  ' + JSON.stringify(v).padEnd(8) + ' ' + String(qty ?? '?').padEnd(12)
      + ((onPage.join(' | ') || 'NO MESSAGE') + '  [' + submitted + ']').slice(0, 60).padEnd(61)
      + (onCart.join(' | ') || '—').slice(0, 60));
  }

  // ── verdict ───────────────────────────────────────────────────────────────
  console.log('\n— verdict —');
  for (const r of rows) {
    const msgs = [...r.onPage, ...r.onCart];
    const err = msgs.some(m => m.startsWith('error'));
    const ok = msgs.some(m => m.startsWith('success'));
    let verdict;
    if (r.v === '1') verdict = (r.qty === 1 && ok) ? 'control OK — added, success shown' : 'CONTROL FAILED — nothing below can be trusted';
    else if (r.qty === null) verdict = 'NO DATA — cart could not be read';
    else if (r.qty > 0) verdict = err ? 'added AND an error shown' : 'ADDED (' + r.qty + ')' + (ok ? ' with a success message' : '');
    else verdict = err ? 'refused, with an error message' : 'REFUSED SILENTLY';
    console.log('  ' + (r.path + ' ' + JSON.stringify(r.v)).padEnd(14) + verdict);
  }
} catch (e) {
  console.log('probe stopped: ' + String(e.message).slice(0, 120));
} finally {
  try { await emptyCart(); console.log('\ncart emptied at the end: ' + ((await cartQty()) === 0 ? 'yes' : 'NOT CONFIRMED')); } catch (e) {}
  await browser.close();
  console.log('done ' + new Date().toISOString());
}
