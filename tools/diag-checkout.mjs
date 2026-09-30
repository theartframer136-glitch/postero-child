/**
 * Full checkout, as a first-time guest, live. Owner, 30 Sep: the cart said
 * "Shipping to 1268 Madison Ln, test, DE 34444" - the studio's side, not the
 * customer's. NEVER places an order: Place order is only pressed with data the
 * site must reject (empty fields, ZIP/state mismatch), which WooCommerce
 * refuses before any order exists.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const errs = [];
async function page(w, h) {
  const ctx = await b.createBrowserContext(); const p = await ctx.newPage(); await p.setViewport({ width: w, height: h });
  p.on('console', m => { if (m.type() === 'error') errs.push(w + ' console: ' + m.text().slice(0, 160)); });
  p.on('pageerror', e => errs.push(w + ' pageerror: ' + e.message.slice(0, 160)));
  return p;
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2500);
}
const text = (p, sel) => p.evaluate((sel) => { const e = document.querySelector(sel); return e ? e.innerText.replace(/\s+/g, ' ').trim().slice(0, 400) : '(none)'; }, sel);
const review = (p) => p.evaluate(() => {
  const t = document.querySelector('.woocommerce-checkout-review-order-table, #order_review table');
  if (!t) return '(no order review)';
  return [...t.querySelectorAll('tr')].map(r => r.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean).join(' | ').slice(0, 900);
});
async function waitUpdate(p) {
  try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 20000 }); } catch {}
  await sleep(2500);
}
async function setAddr(p, a) {
  await p.evaluate((a) => {
    const set = (id, v) => { const e = document.getElementById(id); if (!e) return; e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); e.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) jQuery(e).val(v).trigger('change'); };
    set('billing_first_name', 'Test'); set('billing_last_name', 'Checkout');
    set('billing_country', 'US');
    set('billing_address_1', a.a1); set('billing_address_2', ''); set('billing_city', a.city);
    set('billing_state', a.st); set('billing_postcode', a.zip);
    set('billing_phone', '2125550142'); set('billing_email', 'checkout-test@example.com');
    if (window.jQuery) jQuery(document.body).trigger('update_checkout');
  }, a);
  await waitUpdate(p);
}

// ---------- desktop, the owner's screen ----------
const p = await page(1918, 1078);
await go(p, PRODUCT);
await p.evaluate(() => { const r = document.querySelector('input[name="af_kit"][value="painting_bar"]'); if (r) r.closest('label').click(); });
await sleep(1500);
console.log('PRODUCT live price: ' + await text(p, '#af-live-price'));
await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
await sleep(3000);
await go(p, S + '/cart/');
console.log('\n=== CART (guest, nothing entered) ===');
console.log('shipping row: ' + await text(p, '.cart_totals tr.shipping, .cart_totals .woocommerce-shipping-totals'));
console.log('destination text: ' + await text(p, '.woocommerce-shipping-destination'));
console.log('totals: ' + await text(p, '.cart_totals'));
console.log('calculator fields: ' + await p.evaluate(() => [...document.querySelectorAll('.shipping-calculator-form select, .shipping-calculator-form input')].map(e => e.name + '=' + e.value).join(', ')));

await go(p, S + '/checkout/');
console.log('\n=== CHECKOUT (guest, on arrival) ===');
console.log('url: ' + p.url());
console.log('notices: ' + await text(p, '.woocommerce-notices-wrapper, .woocommerce-error, .woocommerce-info'));
console.log('fields: ' + await p.evaluate(() => [...document.querySelectorAll('.woocommerce-billing-fields .form-row, .woocommerce-shipping-fields .form-row, .woocommerce-additional-fields .form-row')].map(r => {
  const i = r.querySelector('input,select,textarea'); if (!i) return null;
  const vis = r.offsetParent !== null; const req = r.classList.contains('validate-required') || !!r.querySelector('.required');
  return (i.id || i.name) + (req ? '*' : '') + (vis ? '' : '(hidden)') + '=' + JSON.stringify((i.type === 'checkbox' ? String(i.checked) : i.value || '').slice(0, 30));
}).filter(Boolean).join(', ')));
console.log('ship to different address box: ' + await p.evaluate(() => { const c = document.getElementById('ship-to-different-address-checkbox'); return c ? ('present, checked=' + c.checked) : 'absent'; }));
console.log('order review: ' + await review(p));
console.log('payment methods: ' + await p.evaluate(() => [...document.querySelectorAll('.wc_payment_methods > li')].map(l => l.innerText.replace(/\s+/g, ' ').trim().slice(0, 60) + (l.querySelector('input:checked') ? ' [selected]' : '')).join(' || ') || '(none listed)'));
console.log('terms checkbox: ' + await p.evaluate(() => !!document.getElementById('terms')) + ' | place order button: ' + await text(p, '#place_order'));
console.log('any "Madison" text on page: ' + await p.evaluate(() => /Madison/i.test(document.body.innerText)));

const ADDR = [
  { tag: 'New York NY 10118', a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '10118' },
  { tag: 'Mountain View CA 94043', a1: '1600 Amphitheatre Pkwy', city: 'Mountain View', st: 'CA', zip: '94043' },
  { tag: 'Anchorage AK 99501', a1: '632 W 6th Ave', city: 'Anchorage', st: 'AK', zip: '99501' },
];
for (const a of ADDR) {
  await setAddr(p, a);
  console.log('\n--- ' + a.tag + ' ---\norder review: ' + await review(p));
  console.log('notices: ' + await text(p, '.woocommerce-NoticeGroup, .woocommerce-error'));
}

console.log('\n=== VALIDATION (Place order with data the site must refuse) ===');
// 1. ZIP of another state
await setAddr(p, { a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '94043' });
await p.evaluate(() => { const t = document.getElementById('terms'); if (t) t.checked = false; document.getElementById('place_order').click(); });
await waitUpdate(p); await sleep(3000);
console.log('ZIP of another state -> ' + await text(p, '.woocommerce-NoticeGroup-checkout, .woocommerce-error') + ' | url: ' + p.url());
// 2. street with no number
await setAddr(p, { a1: 'Fifth Avenue', city: 'New York', st: 'NY', zip: '10118' });
await p.evaluate(() => { const t = document.getElementById('terms'); if (t) t.checked = false; document.getElementById('place_order').click(); });
await waitUpdate(p); await sleep(3000);
console.log('street without number -> ' + await text(p, '.woocommerce-NoticeGroup-checkout, .woocommerce-error') + ' | url: ' + p.url());
// 3. everything empty
await p.evaluate(() => { ['billing_first_name','billing_last_name','billing_address_1','billing_city','billing_postcode','billing_phone','billing_email'].forEach(id => { const e = document.getElementById(id); if (e) e.value = ''; }); const t = document.getElementById('terms'); if (t) t.checked = false; document.getElementById('place_order').click(); });
await waitUpdate(p); await sleep(3000);
console.log('all empty -> ' + await text(p, '.woocommerce-NoticeGroup-checkout, .woocommerce-error') + ' | url: ' + p.url());
console.log('still on checkout (no order made): ' + /\/checkout\/?$/.test(new URL(p.url()).pathname) + ' | order-received in url: ' + /order-received/.test(p.url()));

// ---------- phone ----------
console.log('\n=== PHONE 390x844 ===');
const m = await page(390, 844);
await go(m, PRODUCT);
await Promise.all([m.waitForNavigation({ timeout: 45000 }).catch(() => {}), m.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
await sleep(3000);
await go(m, S + '/checkout/');
console.log('horizontal overflow: ' + await m.evaluate(() => document.documentElement.scrollWidth + ' vs ' + window.innerWidth));
console.log('wide elements: ' + await m.evaluate(() => [...document.querySelectorAll('#customer_details *, #order_review *, .wc_payment_methods *')].filter(e => e.getBoundingClientRect().right > window.innerWidth + 1).slice(0, 6).map(e => e.tagName.toLowerCase() + '.' + String(e.className).split(/\s+/)[0] + ' right=' + Math.round(e.getBoundingClientRect().right)).join(', ') || 'none'));
console.log('place order visible width: ' + await m.evaluate(() => { const b = document.getElementById('place_order'); if (!b) return 'no button'; const r = b.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); }));
console.log('destination text: ' + await text(m, '.woocommerce-shipping-destination'));

// ---------- digital only ----------
console.log('\n=== DIGITAL-ONLY ORDER ===');
const d = await page(1280, 900);
await go(d, PRODUCT);
await d.evaluate(() => { const r = document.querySelector('input[name="af_kit"][value="digital"]'); if (r) r.closest('label').click(); });
await sleep(1500);
await Promise.all([d.waitForNavigation({ timeout: 45000 }).catch(() => {}), d.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
await sleep(3000);
await go(d, S + '/checkout/');
console.log('order review: ' + await review(d));
console.log('shipping section present: ' + await d.evaluate(() => !!document.querySelector('.woocommerce-shipping-fields, tr.shipping, .woocommerce-shipping-totals')));
console.log('billing address fields shown: ' + await d.evaluate(() => [...document.querySelectorAll('#billing_address_1_field, #billing_city_field, #billing_postcode_field')].filter(e => e.offsetParent).length));

console.log('\n=== CONSOLE / PAGE ERRORS ===\n' + (errs.length ? [...new Set(errs)].slice(0, 20).join('\n') : 'none'));
await b.close();
