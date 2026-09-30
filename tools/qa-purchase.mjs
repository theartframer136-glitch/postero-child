/**
 * Purchase-path QA, live, as a first-time guest. Owner, 30 Sep: "check the
 * delivery price and the full add to cart to checkout till order ... all
 * calculation ... and also check the payment ... test the full site".
 *
 *   node tools/qa-purchase.mjs pricing | delivery | payment | smoke
 *
 * Never places an order: Place order is only pressed with data the site must
 * refuse. Real orders are qa-orders.mjs, run separately on the owner's say-so.
 * Every expected figure is computed here from the owner's rules, not read
 * back from the site, so a wrong number on the site shows as a mismatch.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const MODE = process.argv[2] || 'pricing';
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const errs = [];
let fails = 0, passes = 0;
const ok = (cond, what, detail = '') => { if (cond) passes++; else fails++; console.log((cond ? '  PASS ' : '  FAIL ') + what + (detail ? '  [' + detail + ']' : '')); return cond; };
const money = (s) => { const m = String(s || '').replace(/,/g, '').match(/-?\$?\s*(\d+(?:\.\d+)?)/); return m ? parseFloat(m[1]) : NaN; };
const near = (a, e) => Math.abs(a - e) < 0.015;

async function newPage(w = 1366, h = 900) {
  const ctx = await b.createBrowserContext(); const p = await ctx.newPage(); await p.setViewport({ width: w, height: h });
  p.on('console', m => { if (m.type() === 'error' && !/403|favicon|google|facebook|pinterest|doubleclick|clarity/i.test(m.text())) errs.push(w + ' ' + p.url().replace(S, '').slice(0, 40) + ': ' + m.text().slice(0, 150)); });
  p.on('pageerror', e => errs.push(w + ' ' + p.url().replace(S, '').slice(0, 40) + ' pageerror: ' + e.message.slice(0, 150)));
  return p;
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
const txt = (p, sel) => p.evaluate((sel) => { const e = document.querySelector(sel); return e ? e.innerText.replace(/\s+/g, ' ').trim() : ''; }, sel);
async function waitAjax(p) { try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 25000 }); } catch {} await sleep(2000); }

// ---- the owner's rules -------------------------------------------------------
const SIZE_PRICE = { '2×3 ft (24×36 in)': 60, '3×2 ft (36×24 in)': 60, '2.5×3 ft (30×36 in)': 65, '3×4 ft (36×48 in)': 80, '3×5 ft (36×60 in)': 100 };
const sqft = (label) => { const m = label.match(/\((\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) in\)/); return m ? m[1] * m[2] / 144 : 0; };
const BAR = 4, ALU = 4, GOLD = 10;
function expectedPrice(size, kit, frame, colour) {
  let p = SIZE_PRICE[size];
  if (kit === 'painting_bar' || kit === 'painting_bar_frame') p += sqft(size) * BAR;
  if (frame === 'Aluminium Frame') p += sqft(size) * ALU + ((colour === 'Gold' || colour === 'Rose Gold') ? GOLD : 0);
  return Math.round(p * 100) / 100;
}
// inc/shipping.php + inc/shipping-distance.php, re-derived by hand
function parcelLbs(size, framed, qty = 1) {
  const m = size.match(/\((\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) in\)/); let [s, l] = [parseFloat(m[1]), parseFloat(m[2])].sort((a, c) => a - c);
  let pkg;
  if (!framed && s <= 36) pkg = { method: 'tube', l: l + 4, w: 6, h: 6, weight: Math.max(2, s * l / 720 + 1.5), cap: 5 };
  else pkg = { method: 'crate', l: l + 6, w: s + 6, h: framed ? 4 : 3, weight: Math.max(5, s * l / 240 + (framed ? 6 : 3)), cap: 4 };
  let left = qty, lbs = 0; const unit = Math.max(1, pkg.weight);
  while (left > 0) { const n = Math.min(pkg.cap, left); left -= n; const h = pkg.method === 'crate' ? pkg.h * n : pkg.h; lbs += Math.max(1, Math.max(unit * n, pkg.l * pkg.w * h / 139)); }
  return { lbs, pkg };
}
const TIERS = [[50, 8, 0.6], [150, 9, 0.9], [500, 10, 1.3], [1000, 11, 1.7], [1800, 12, 2.1], [99999, 14, 2.6]];
const band = (mi) => mi == null ? TIERS[2] : TIERS.find(t => mi <= t[0]);
const HOCKESSIN = [39.785685, -75.683640];
const hav = (a, c) => { const r = (x) => x * Math.PI / 180; const d1 = r(c[0] - a[0]), d2 = r(c[1] - a[1]); const h = Math.sin(d1 / 2) ** 2 + Math.cos(r(a[0])) * Math.cos(r(c[0])) * Math.sin(d2 / 2) ** 2; return 2 * 3958.8 * Math.asin(Math.min(1, Math.sqrt(h))); };
function expectedDelivery(lbs, latlng) { const t = band(latlng ? hav(HOCKESSIN, latlng) : null); return Math.round((t[1] + t[2] * lbs) * 100) / 100; }
function oversizeFee(size, framed) { const { pkg } = parcelLbs(size, framed); if (pkg.method !== 'crate') return 0; const long = Math.max(pkg.l, pkg.w); return long > 96 ? 85 : long > 72 ? 45 : long > 48 ? 20 : 0; }

async function pickOptions(p, { size, kit, colour }) {
  await p.evaluate(() => { const s = document.querySelector('.af-kit-group'); if (s) s.scrollIntoView({ block: 'center' }); });
  if (size) { await p.select('#af-size-select', size).catch(() => {}); await sleep(700); }
  if (kit) { await p.evaluate((k) => { const r = document.querySelector('input[name="af_kit"][value="' + k + '"]'); if (r) r.closest('label').click(); }, kit); await sleep(1300); }
  if (colour) { await p.evaluate((c) => { const s = document.querySelector('.af-color-chips .af-swatch[data-val="' + c + '"]'); if (s && !s.disabled) s.click(); }, colour); await sleep(900); }
  return p.evaluate(() => ({ live: (document.getElementById('af-live-price') || {}).textContent || '', frame: (document.querySelector('.af-frame-chips .af-chip-opt.active') || {}).getAttribute ? document.querySelector('.af-frame-chips .af-chip-opt.active').getAttribute('data-val') : '', kit: (document.querySelector('input[name="af_kit"]:checked') || {}).value }));
}
async function addToCart(p) {
  await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => { const bt = document.querySelector('form.cart .single_add_to_cart_button'); if (bt) bt.click(); })]);
  await sleep(2500);
}
async function cartLines(p) {
  return p.evaluate(() => [...document.querySelectorAll('tr.cart_item, tr.woocommerce-cart-form__cart-item')].map(r => ({
    text: r.innerText.replace(/\s+/g, ' ').slice(0, 200), price: (r.querySelector('.product-price') || {}).innerText || '', sub: (r.querySelector('.product-subtotal') || {}).innerText || '',
    qty: (r.querySelector('input.qty') || {}).value || '' })));
}
async function setAddress(p, a) {
  await p.evaluate((a) => {
    const set = (id, v) => { const e = document.getElementById(id); if (!e) return; if (window.jQuery) { jQuery(e).val(v).trigger('change'); } else { e.value = v; e.dispatchEvent(new Event('change', { bubbles: true })); } };
    set('billing_first_name', 'Qa'); set('billing_last_name', 'Tester'); set('billing_country', a.country || 'US');
    set('billing_address_1', a.a1 || '100 Main St'); set('billing_city', a.city || 'Town'); set('billing_state', a.st || '');
    set('billing_postcode', a.zip || ''); set('billing_phone', '2125550142'); set('billing_email', 'qa-test@example.com');
    if (window.jQuery) jQuery(document.body).trigger('update_checkout');
  }, a);
  await waitAjax(p);
}
async function reviewNumbers(p) {
  return p.evaluate(() => {
    const t = document.querySelector('.woocommerce-checkout-review-order-table'); if (!t) return null;
    const val = (sel) => { const e = t.querySelector(sel); return e ? e.innerText.replace(/\s+/g, ' ').trim() : ''; };
    const fees = [...t.querySelectorAll('tr.fee')].map(r => r.innerText.replace(/\s+/g, ' ').trim());
    return { subtotal: val('tr.cart-subtotal td'), shipping: val('tr.shipping td, tr.woocommerce-shipping-totals td'), fees, total: val('tr.order-total td'), all: t.innerText.replace(/\s+/g, ' ').slice(0, 500) };
  });
}

// =============================================================================
if (MODE === 'pricing') {
  console.log('=== PRICES: product page, cart and checkout agree with the owner\'s rules ===');
  const p = await newPage();
  await go(p, PRODUCT);
  const sizes = await p.evaluate(() => [...document.querySelectorAll('#af-size-select option')].map(o => o.value));
  ok(JSON.stringify(sizes) === JSON.stringify(Object.keys(SIZE_PRICE)), 'the 5 sizes on sale', sizes.join(' / '));
  const combos = [];
  for (const size of sizes) for (const kit of ['painting', 'painting_bar', 'painting_bar_frame']) combos.push({ size, kit });
  combos.push({ size: '3×4 ft (36×48 in)', kit: 'painting_bar_frame', colour: 'Gold' });
  for (const c of combos) {
    await go(p, PRODUCT);
    const got = await pickOptions(p, c);
    const frame = c.kit === 'painting_bar_frame' ? 'Aluminium Frame' : 'Without Frame';
    const exp = expectedPrice(c.size, c.kit, frame, c.colour);
    ok(near(money(got.live), exp), `${c.size} ${c.kit}${c.colour ? ' ' + c.colour : ''}: page $${money(got.live)}`, 'expected $' + exp.toFixed(2) + ', frame ' + got.frame);
  }
  // digital
  await go(p, PRODUCT); const dg = await pickOptions(p, { kit: 'digital' });
  ok(money(dg.live) > 8.99 && money(dg.live) < 10, 'digital download price $' + money(dg.live), 'expected $9.00-$9.99');

  console.log('\n--- into the cart and checkout: 3 lines, quantities, totals ---');
  const q = await newPage();
  const lines = [
    { size: '3×4 ft (36×48 in)', kit: 'painting_bar' },
    { size: '3×5 ft (36×60 in)', kit: 'painting_bar_frame', colour: 'Gold' },
    { kit: 'digital' },
  ];
  const want = [];
  for (const l of lines) {
    await go(q, PRODUCT); await pickOptions(q, l);
    if (l.kit === 'digital') { want.push({ l, price: null }); } else want.push({ l, price: expectedPrice(l.size, l.kit, l.kit === 'painting_bar_frame' ? 'Aluminium Frame' : 'Without Frame', l.colour) });
    await addToCart(q);
  }
  await go(q, S + '/cart/');
  const cl = await cartLines(q);
  console.log('  cart lines: ' + JSON.stringify(cl.map(c => c.price + ' x' + c.qty + ' = ' + c.sub)));
  ok(cl.length === 3, 'three separate lines in the cart', String(cl.length));
  for (const w of want.filter(w => w.price)) ok(cl.some(c => near(money(c.price), w.price)), `cart line at $${w.price.toFixed(2)} (${w.l.size} ${w.l.kit})`);
  // quantity 2 on the first line
  await q.evaluate(() => { const i = document.querySelector('tr.cart_item input.qty'); if (i) { i.value = 2; i.dispatchEvent(new Event('change', { bubbles: true })); } const u = document.querySelector('button[name="update_cart"]'); if (u) { u.disabled = false; u.click(); } });
  await waitAjax(q); await sleep(2500);
  const cl2 = await cartLines(q);
  ok(cl2[0] && near(money(cl2[0].sub), money(cl2[0].price) * 2), 'quantity 2 doubles the line', cl2[0] ? cl2[0].price + ' x2 = ' + cl2[0].sub : '');
  const tot = await q.evaluate(() => { const t = document.querySelector('.cart_totals'); return t ? t.innerText.replace(/\s+/g, ' ') : ''; });
  console.log('  cart totals: ' + tot.slice(0, 400));
  const sumLines = cl2.reduce((a, c) => a + money(c.sub), 0);
  ok(near(money((tot.match(/Subtotal\s*\$[\d,.]+/) || [''])[0].replace('Subtotal', '')), sumLines), 'subtotal = sum of lines', '$' + sumLines.toFixed(2));
  // quantity cap
  await q.evaluate(() => { const i = document.querySelector('tr.cart_item input.qty'); if (i) { i.value = 99; i.dispatchEvent(new Event('change', { bubbles: true })); } const u = document.querySelector('button[name="update_cart"]'); if (u) { u.disabled = false; u.click(); } });
  await waitAjax(q); await sleep(2500);
  const capMsg = await txt(q, '.woocommerce-error, .woocommerce-notices-wrapper');
  const cl3 = await cartLines(q);
  ok(cl3[0] && parseInt(cl3[0].qty, 10) <= 25, 'quantity 99 refused (cap 25)', 'qty now ' + (cl3[0] || {}).qty + ' | ' + capMsg.slice(0, 120));
  // coupon + gift card with junk codes
  await q.evaluate(() => { const c = document.getElementById('coupon_code'); if (c) { c.value = 'NOTACODE123'; const bt = document.querySelector('button[name="apply_coupon"]'); if (bt) bt.click(); } });
  await waitAjax(q); await sleep(2000);
  ok(/does not exist|not valid|invalid/i.test(await txt(q, '.woocommerce-error, .woocommerce-notices-wrapper')), 'junk coupon refused', (await txt(q, '.woocommerce-error')).slice(0, 100));
  await q.evaluate(() => { const i = document.querySelector('.af-gc-input'); if (i) { i.value = 'TAF-0000-0000-0000'; const bt = document.querySelector('.af-gc-apply'); if (bt) bt.click(); } });
  await sleep(4000);
  const gcm = await txt(q, '.af-gc-msg');
  ok(gcm && !/applied/i.test(gcm), 'junk gift card refused', gcm.slice(0, 100));

  console.log('\n--- checkout agrees with the cart ---');
  await go(q, S + '/checkout/');
  await setAddress(q, { a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '10001' });
  const rv = await reviewNumbers(q);
  console.log('  review: ' + (rv ? rv.all : 'none'));
  if (rv) {
    const sum = money(rv.subtotal) + money(rv.shipping || '0') + rv.fees.reduce((a, f) => a + money(f), 0);
    ok(near(money(rv.total), sum), 'checkout total = subtotal + delivery + fees', `${rv.subtotal} + ${rv.shipping} + ${rv.fees.join(',') || '0'} = ${rv.total}`);
    ok(rv.fees.some(f => /Oversize/i.test(f) && near(money(f), oversizeFee('3×5 ft (36×60 in)', true))), 'oversize fee for the framed 3x5 = $' + oversizeFee('3×5 ft (36×60 in)', true), rv.fees.join(' | '));
  }

  console.log('\n--- Buy Now keeps the chosen options ---');
  const bn = await newPage();
  await go(bn, PRODUCT); await pickOptions(bn, { size: '3×5 ft (36×60 in)', kit: 'painting_bar' });
  const exp = expectedPrice('3×5 ft (36×60 in)', 'painting_bar', 'Without Frame');
  await Promise.all([bn.waitForNavigation({ timeout: 45000 }).catch(() => {}), bn.evaluate(() => { const a = document.querySelector('.af-buynow'); if (a) a.click(); })]);
  await sleep(3000);
  const bnr = await reviewNumbers(bn);
  console.log('  landed on ' + bn.url().replace(S, '') + ' | ' + (bnr ? bnr.all.slice(0, 250) : 'no review'));
  ok(bnr && near(money(bnr.subtotal), exp), 'Buy Now charges the chosen 3x5 + bars = $' + exp.toFixed(2), bnr ? 'checkout subtotal ' + bnr.subtotal : '');
  await bn.reload({ waitUntil: 'networkidle2' }).catch(() => {}); await sleep(3000);
  const bnr2 = await reviewNumbers(bn);
  ok(bnr && bnr2 && near(money(bnr2.subtotal), money(bnr.subtotal)), 'reloading checkout does not add a second copy', bnr2 ? 'after reload ' + bnr2.subtotal : '');
}

// =============================================================================
if (MODE === 'delivery') {
  console.log('=== DELIVERY: every distance band, special ZIPs, and the parcel types ===');
  // approximate ZIP centres, picked well inside each band so rounding cannot move them
  const Z = [
    ['Newark DE', 'DE', '19711', [39.68, -75.75]],
    ['Philadelphia PA', 'PA', '19103', [39.952, -75.174]],
    ['New York NY', 'NY', '10001', [40.7506, -73.9972]],
    ['Empire State Bldg NY (not in table)', 'NY', '10118', [40.75, -73.99]],
    ['Boston MA', 'MA', '02108', [42.357, -71.064]],
    ['Atlanta GA', 'GA', '30303', [33.752, -84.39]],
    ['Chicago IL', 'IL', '60601', [41.8853, -87.6221]],
    ['Dallas TX', 'TX', '75201', [32.787, -96.799]],
    ['Denver CO', 'CO', '80202', [39.75, -104.998]],
    ['Mountain View CA', 'CA', '94043', [37.414, -122.0707]],
    ['Seattle WA', 'WA', '98101', [47.6109, -122.3364]],
    ['Anchorage AK', 'AK', '99501', [61.22, -149.8557]],
    ['Honolulu HI', 'HI', '96813', [21.3165, -157.845]],
    ['Adjuntas PR', 'PR', '00601', [18.1806, -66.75]],
    ['APO Europe (military)', 'AE', '09001', null],
  ];
  const carts = [
    { name: '1 x 3x4 rolled (Painting only)', add: [{ size: '3×4 ft (36×48 in)', kit: 'painting' }], lbs: parcelLbs('3×4 ft (36×48 in)', false).lbs, fee: 0 },
    { name: '1 x 2x3 rolled (Painting only)', add: [{ size: '2×3 ft (24×36 in)', kit: 'painting' }], lbs: parcelLbs('2×3 ft (24×36 in)', false).lbs, fee: 0 },
    { name: '1 x 3x5 framed crate (bars + aluminium)', add: [{ size: '3×5 ft (36×60 in)', kit: 'painting_bar_frame' }], lbs: parcelLbs('3×5 ft (36×60 in)', true).lbs, fee: oversizeFee('3×5 ft (36×60 in)', true) },
  ];
  for (const c of carts) {
    console.log('\n--- cart: ' + c.name + ' (billable ' + c.lbs.toFixed(2) + ' lb, oversize fee $' + c.fee + ') ---');
    const p = await newPage();
    for (const a of c.add) { await go(p, PRODUCT); await pickOptions(p, a); await addToCart(p); }
    await go(p, S + '/cart/');
    console.log('  cart on arrival: ' + (await txt(p, '.cart_totals')).slice(0, 260));
    await go(p, S + '/checkout/');
    const onArrival = await p.evaluate(() => ({ country: (document.getElementById('billing_country') || {}).value, state: (document.getElementById('billing_state') || {}).value }));
    ok(onArrival.country === 'US', 'checkout opens on United States', JSON.stringify(onArrival));
    for (const [name, st, zip, ll] of (c === carts[0] ? Z : Z.filter(z => ['19711', '10001', '60601', '94043', '99501', '10118'].includes(z[2])))) {
      await setAddress(p, { a1: '100 Main St', city: name.split(' ')[0], st, zip });
      const rv = await reviewNumbers(p);
      const got = rv ? money(rv.shipping) : NaN;
      const exp = expectedDelivery(c.lbs, ll);
      const mi = ll ? Math.round(hav(HOCKESSIN, ll)) : null;
      const notes = await txt(p, '.woocommerce-NoticeGroup, .woocommerce-error');
      ok(near(got, exp), `${name} ${zip} (~${mi == null ? '?' : mi} mi): delivery $${isNaN(got) ? '-' : got.toFixed(2)}`, 'expected $' + exp.toFixed(2) + (notes ? ' | notice: ' + notes.slice(0, 80) : ''));
      if (rv && c.fee) ok(rv.fees.some(f => near(money(f), c.fee)), '  oversize fee $' + c.fee + ' present', rv.fees.join(' | '));
    }
  }
  console.log('\n--- outside the US ---');
  const p = await newPage();
  await go(p, PRODUCT); await pickOptions(p, { size: '3×4 ft (36×48 in)', kit: 'painting' }); await addToCart(p);
  await go(p, S + '/checkout/');
  for (const [country, st, zip, city] of [['CA', 'ON', 'M5V 2T6', 'Toronto'], ['GB', '', 'SW1A 1AA', 'London'], ['IN', 'WB', '700024', 'Kolkata']]) {
    await setAddress(p, { country, st, zip, city, a1: '10 High Street' });
    const rv = await reviewNumbers(p);
    console.log(`  ${country} ${zip}: ${rv ? 'delivery ' + (rv.shipping || '(none)') + ' | total ' + rv.total : 'no review'} | notices: ${(await txt(p, '.woocommerce-NoticeGroup, .woocommerce-error')).slice(0, 100)}`);
  }
}

// =============================================================================
if (MODE === 'payment') {
  console.log('=== CHECKOUT FORM, VALIDATION AND PAYMENT METHODS (no order placed) ===');
  const p = await newPage(1918, 1078);
  await go(p, PRODUCT); await pickOptions(p, { size: '2×3 ft (24×36 in)', kit: 'painting' }); await addToCart(p);
  await go(p, S + '/checkout/');
  const methods = await p.evaluate(() => [...document.querySelectorAll('.wc_payment_methods > li')].map(l => ({ id: (l.querySelector('input') || {}).value, label: l.querySelector('label') ? l.querySelector('label').innerText.trim() : '', checked: !!l.querySelector('input:checked') })));
  console.log('  payment methods: ' + JSON.stringify(methods));
  ok(methods.length >= 1, 'payment methods offered', methods.map(m => m.id).join(', '));
  await setAddress(p, { a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '10001' });
  for (const m of methods) {
    await p.evaluate((id) => { const r = document.querySelector('.wc_payment_methods input[value="' + id + '"]'); if (r) { r.click(); if (window.jQuery) jQuery(r).trigger('change'); } }, m.id);
    await sleep(3500);
    const box = await p.evaluate((id) => { const li = document.querySelector('.wc_payment_methods li.payment_method_' + id) || [...document.querySelectorAll('.wc_payment_methods > li')].find(l => (l.querySelector('input') || {}).value === id);
      const d = li ? li.querySelector('.payment_box') : null; return d ? { text: d.innerText.replace(/\s+/g, ' ').slice(0, 220), iframes: d.querySelectorAll('iframe').length, visible: d.offsetParent !== null } : null; }, m.id);
    console.log(`  [${m.id}] ${m.label} -> ` + JSON.stringify(box));
    if (/square/i.test(m.id)) ok(box && box.iframes > 0, 'Square card form loads its secure card fields', box ? box.iframes + ' iframe(s)' : 'no box');
  }
  // Square: an invalid card number must be refused in the form, never charged
  const sq = methods.find(m => /square_credit_card/i.test(m.id));
  if (sq) {
    await p.evaluate((id) => { const r = document.querySelector('.wc_payment_methods input[value="' + id + '"]'); if (r) { r.click(); if (window.jQuery) jQuery(r).trigger('change'); } }, sq.id);
    await sleep(5000);
    let typed = false;
    for (const f of p.frames()) {
      if (!/squareup|square/i.test(f.url())) continue;
      try { const inp = await f.$('input[name="cardNumber"], input#cardNumber, input[autocomplete="cc-number"], input'); if (inp) { await inp.type('4111 1111 1111 1112', { delay: 30 }); typed = true; break; } } catch {}
    }
    console.log('  typed an invalid card number into the Square frame: ' + typed);
    await p.evaluate(() => { const t = document.getElementById('terms'); if (t) t.checked = true; document.getElementById('place_order').click(); });
    await sleep(8000);
    const after = { url: p.url(), notice: await txt(p, '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .wc-square-credit-card-payment-form-errors, .sq-card-message-error') };
    console.log('  after Place order with the invalid card: ' + JSON.stringify(after));
    ok(!/order-received/.test(after.url), 'invalid card: no order made, stayed on checkout', after.url.replace(S, ''));
  }
  // validation set
  const cases = [
    ['ZIP of another state', { a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '94043' }, /belongs to CA/i],
    ['street without a number', { a1: 'Fifth Avenue', city: 'New York', st: 'NY', zip: '10001' }, /house number|building/i],
    ['PO box (small piece allowed)', { a1: 'PO Box 123', city: 'New York', st: 'NY', zip: '10001' }, null],
    ['bad email', { a1: '350 5th Ave', city: 'New York', st: 'NY', zip: '10001', email: 'not-an-email' }, /email/i],
  ];
  const cod = methods.find(m => m.id === 'cod') || methods[0];
  for (const [name, a, expect] of cases) {
    await go(p, S + '/checkout/');
    await setAddress(p, a);
    if (a.email) await p.evaluate((v) => { const e = document.getElementById('billing_email'); e.value = v; }, a.email);
    if (!expect) { console.log('  ' + name + ': skipped pressing Place order (a valid address would create an order)'); continue; }
    await p.evaluate((id) => { const r = document.querySelector('.wc_payment_methods input[value="' + id + '"]'); if (r) r.click(); document.getElementById('place_order').click(); }, cod.id);
    await sleep(7000);
    const n = await txt(p, '.woocommerce-NoticeGroup-checkout, .woocommerce-error');
    ok(expect.test(n) && !/order-received/.test(p.url()), name + ' refused', n.slice(0, 120));
  }
  // phone
  const m = await newPage(390, 844);
  await go(m, PRODUCT); await addToCart(m); await go(m, S + '/checkout/');
  const ov = await m.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  ok(ov <= 1, 'phone checkout: nothing wider than the screen', 'overflow ' + ov + 'px');
}

// =============================================================================
if (MODE === 'smoke') {
  console.log('=== WHOLE SITE: every main page, desktop and phone ===');
  const pages = ['/', '/shop/', '/product-category/digital-canvas-prints/', PRODUCT.replace(S, ''), '/cart/', '/checkout/', '/my-account/', '/wishlist/', '/try-on-wall/', '/frame-the-moment/', '/customize-your-picture/', '/gift-cards/', '/about/', '/contact/', '/blog/', '/artists/', '/clearance/', '/shipping-delivery/', '/return-refund-policy/', '/privacy-policy/', '/track-your-order/', '/help-support/', '/wholesale-corporate/', '/?s=krishna&post_type=product', '/this-page-does-not-exist-qa/'];
  for (const [w, h] of [[1366, 900], [390, 844]]) {
    const p = await newPage(w, h);
    for (const path of pages) {
      let status = 0;
      try { const r = await p.goto(S + path, { waitUntil: 'networkidle2', timeout: 90000 }); status = r ? r.status() : 0; } catch { status = -1; }
      for (let i = 0; i < 4; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText)); } catch {} if (!bl) break; await sleep(4000); try { const r = await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); status = r ? r.status() : status; } catch {} }
      await sleep(1500);
      const info = await p.evaluate(() => {
        const imgs = [...document.images].filter(i => i.getBoundingClientRect().width > 20 && i.loading !== 'lazy');
        return { title: document.title.slice(0, 50), broken: imgs.filter(i => i.complete && i.naturalWidth === 0).map(i => (i.currentSrc || i.src).split('/').pop().slice(0, 40)).slice(0, 4),
          overflow: document.documentElement.scrollWidth - window.innerWidth, h1: (document.querySelector('h1') || {}).innerText ? document.querySelector('h1').innerText.slice(0, 40) : '' };
      }).catch(() => ({}));
      const bad = (status >= 400 && !/does-not-exist/.test(path)) || (info.broken && info.broken.length) || info.overflow > 1;
      ok(!bad, `${w} ${path.slice(0, 50)} -> ${status}`, `${info.title || ''} | overflow ${info.overflow}px${info.broken && info.broken.length ? ' | broken img: ' + info.broken.join(',') : ''}`);
    }
  }
}

console.log('\n=== console / page errors seen ===\n' + ([...new Set(errs)].slice(0, 25).join('\n') || 'none'));
console.log(`\nSUMMARY ${MODE}: ${passes} passed, ${fails} failed`);
await b.close();
