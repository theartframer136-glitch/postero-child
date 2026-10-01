/**
 * Checkout upgrade, live (owner, 30 Sep: "on checkout continue to give option
 * to add stretcher bar and frame"). Adds a 3x4 ft "Painting only" and a
 * digital download, opens checkout with a New York address, then clicks each
 * choice in the "Upgrade this piece" block and reads back the line price,
 * "You receive", "Frame Type", delivery, fees and total after checkout
 * redraws. Expected figures come from the owner's price rules. Prints a small
 * picture of the list. Never places an order.
 *
 *   node tools/diag-checkout-upgrade.mjs
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
let fails = 0, passes = 0;
const ok = (c, w, d = '') => { c ? passes++ : fails++; console.log((c ? '  PASS ' : '  FAIL ') + w + (d ? '  [' + d + ']' : '')); };
const money = (s) => { const all = [...String(s || '').replace(/,/g, '').matchAll(/([-−]?)\s*\$\s*(\d+(?:\.\d+)?)/g)]; if (!all.length) return NaN; const m = all[all.length - 1]; return (m[1] ? -1 : 1) * parseFloat(m[2]); };
const errs = [];
function shrink(dataUrl, targetW, quality) {
  return new Promise((resolve) => { const im = new Image(); im.onload = function () { const sc = Math.min(1, targetW / im.naturalWidth); const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * sc); c.height = Math.round(im.naturalHeight * sc); c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); resolve(c.toDataURL('image/jpeg', quality)); }; im.onerror = () => resolve(''); im.src = dataUrl; });
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
async function settle(p) { await sleep(700); try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay') && !document.querySelector('.af-co-up.is-busy'), { timeout: 30000 }); } catch {} await sleep(1500); }
async function picture(p, label, w) {
  const el = await p.$('#order_review'); if (!el) return;
  await el.evaluate(e => e.scrollIntoView({ block: 'start' })); await sleep(600);
  const raw = await el.screenshot({ encoding: 'base64' });
  const small = await p.evaluate(shrink, 'data:image/png;base64,' + raw, w, 0.62);
  const b64 = small.replace(/^data:image\/jpeg;base64,/, '');
  console.log(`=== PICTURE ${label} (${b64.length} chars) ===`);
  for (let i = 0; i < b64.length; i += 180) console.log('B64 ' + b64.slice(i, i + 180));
  console.log('=== END PICTURE ' + label + ' ===');
}
async function state(p) {
  return p.evaluate(() => {
    const t = document.querySelector('.woocommerce-checkout-review-order-table'); if (!t) return null;
    // the editor lives in its own row (tr.af-co-up-row) right after its line
    const edRow = (r) => (r.nextElementSibling && r.nextElementSibling.classList.contains('af-co-up-row')) ? r.nextElementSibling : null;
    const row = [...t.querySelectorAll('tr.cart_item')].find(r => edRow(r) || r.querySelector('.af-co-up')) || t.querySelector('tr.cart_item');
    const txt = (e) => e ? e.innerText.replace(/\s+/g, ' ').trim() : '';
    // read the stored value even where the editor hides the row on this view
    const dd = (cls) => { const e = row.querySelector('dd.variation-' + cls); return e ? e.textContent.replace(/\s+/g, ' ').trim() : ''; };
    const up = (edRow(row) && edRow(row).querySelector('.af-co-up')) || row.querySelector('.af-co-up');
    return {
      price: txt(row.querySelector('td.product-total')), receive: dd('Youreceive'), frame: dd('FrameType'), color: dd('FrameColor'),
      options: up ? [...up.querySelectorAll('.af-co-up-opt')].map(b => (b.classList.contains('is-on') ? '*' : '') + txt(b)) : null,
      colors: up ? [...up.querySelectorAll('.af-co-up-color')].map(b => (b.classList.contains('is-on') ? '*' : '') + txt(b)) : [],
      upAfterDetails: up ? !!(edRow(row) && edRow(row).contains(up) && up.classList.contains('is-placed')) : null,
      hiddenRows: [...row.querySelectorAll('dl.variation dt')].filter(d => getComputedStyle(d).display === 'none').map(d => d.textContent.trim()),
      buttonLook: up ? (() => { const b = up.querySelector('.af-co-up-size') || up.querySelector('.af-co-up-opt'); const c = getComputedStyle(b); return c.textTransform + '|' + c.backgroundColor + '|' + Math.round(b.getBoundingClientRect().height); })() : '',
      msg: up ? txt(up.querySelector('.af-co-up-msg')) : '',
      shipping: txt(t.querySelector('tr.shipping td, tr.woocommerce-shipping-totals td')),
      fees: [...t.querySelectorAll('tr.fee')].map(txt), total: txt(t.querySelector('tr.order-total td')),
      rowsWithUpgrade: t.querySelectorAll('.af-co-up').length, rows: t.querySelectorAll('tr.cart_item').length, styleShipped: !!t.querySelector('style#af-co-up-css'),
    };
  });
}
async function choose(p, sel) {
  const done = await p.evaluate((sel) => { const btn = document.querySelector(sel); if (!btn) return false; btn.click(); return true; }, sel);
  await settle(p); return done;
}

const ctx = await b.createBrowserContext(); const p = await ctx.newPage();
p.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 160)));
p.on('console', m => { if (m.type() === 'error' && !/403|favicon|google|facebook|pinterest|doubleclick|clarity/i.test(m.text())) errs.push('console: ' + m.text().slice(0, 160)); });
p.on('dialog', async d => { errs.push('dialog: ' + d.message()); try { await d.dismiss(); } catch {} });
await p.setViewport({ width: 1366, height: 900 });
await go(p, PRODUCT);
await p.select('#af-size-select', '3×4 ft (36×48 in)').catch(() => {}); await sleep(700);
await p.evaluate(() => { const r = document.querySelector('input[name="af_kit"][value="painting"]'); if (r) r.closest('label').click(); }); await sleep(1200);
await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
await sleep(2000);
// a download as well: it must get no upgrade choice
await go(p, PRODUCT);
await p.evaluate(async () => { const i = document.querySelector('form.cart [name="add-to-cart"]'); await fetch('/?wc-ajax=add_to_cart', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(i ? i.value : '') + '&quantity=1&af_digital=1' }); });
await go(p, S + '/checkout/');
await p.evaluate(() => { const b = [...document.querySelectorAll('button, a')].find(x => /necessary only/i.test(x.textContent || '')); if (b) b.click(); });
await p.evaluate(() => {
  const set = (id, v) => { const e = document.getElementById(id); if (!e) return; if (window.jQuery) jQuery(e).val(v).trigger('change'); else e.value = v; };
  set('billing_country', 'US'); set('billing_address_1', '350 5th Ave'); set('billing_city', 'New York'); set('billing_state', 'NY'); set('billing_postcode', '10001');
  if (window.jQuery) jQuery(document.body).trigger('update_checkout');
});
await settle(p);

console.log('=== CHECKOUT UPGRADE: 3x4 ft Painting only, plus a download ===');
let s = await state(p);
console.log('  start: ' + JSON.stringify(s));
ok(s && s.rows === 2 && s.rowsWithUpgrade === 1, 'one upgrade block: on the canvas, none on the download', s ? s.rowsWithUpgrade + ' of ' + s.rows : '');
ok(s && s.options && s.options.length === 3 && /^\*Painting only/.test(s.options[0]), 'three choices, "Painting only" selected', s && s.options ? s.options.join(' | ') : '');
ok(s && s.options && money(s.options[0]) === 80 && money(s.options[1]) === 128 && money(s.options[2]) === 176, 'choice prices $80 / $128 / $176', s && s.options ? s.options.map(money).join(' / ') : '');
ok(s && s.upAfterDetails === true, 'the editor sits in its own full-width row under its line');
ok(s && s.styleShipped, 'the editor\'s styles arrive with the list');
ok(s && /^none\|rgb\(255, 255, 255\)/.test(s.buttonLook), 'buttons styled by the editor, not the theme (no capitals, white)', s ? s.buttonLook : '');
ok(s && ['Size:', 'Frame Type:', 'You receive:'].every(x => s.hiddenRows.includes(x)), 'details the editor shows are not repeated', s ? s.hiddenRows.join(' ') : '');
const del0 = money(s && s.shipping);
await picture(p, 'start-1366', 520);

ok(await choose(p, '.af-co-up-opt[data-kit="painting_bar"]'), 'clicked "Painting + structure bars + DIY kit"');
s = await state(p); console.log('  bars: ' + JSON.stringify(s));
ok(s && money(s.price) === 128 && /structure bars/i.test(s.receive) && /Without Frame/i.test(s.frame), 'line $128, You receive: bars, no frame', s ? s.price + ' | ' + s.receive + ' | ' + s.frame : '');
ok(s && money(s.shipping) === del0, 'delivery unchanged (still rolled in a tube)', s ? s.shipping + ' vs ' + del0 : '');

ok(await choose(p, '.af-co-up-opt[data-kit="painting_bar_frame"]'), 'clicked "Painting + structure bars + frame + DIY kit"');
s = await state(p); console.log('  frame: ' + JSON.stringify(s));
ok(s && money(s.price) === 176 && /frame/i.test(s.receive) && /Aluminium/i.test(s.frame), 'line $176, You receive: frame, Frame Type: Aluminium', s ? s.price + ' | ' + s.receive + ' | ' + s.frame : '');
ok(s && s.colors.length === 4, 'frame colour choices shown', s ? s.colors.join(' | ') : '');
ok(s && money(s.shipping) > del0, 'delivery goes up (framed ships flat in a crate)', s ? s.shipping + ' vs ' + del0 : '');
ok(s && s.fees.some(f => /Oversize/i.test(f) && money(f) === 20), 'oversize fee $20 for the 54 in crate', s ? s.fees.join(' | ') : '');
await picture(p, 'frame-1366', 520);

ok(await choose(p, '.af-co-up-color[data-color="Gold"]'), 'clicked Gold');
s = await state(p); console.log('  gold: ' + JSON.stringify(s));
ok(s && money(s.price) === 186 && /Gold/i.test(s.color), 'line $186, Frame Color: Gold', s ? s.price + ' | ' + s.color : '');

await p.setViewport({ width: 390, height: 844 }); await sleep(1200);
await picture(p, 'gold-390', 360);
await p.setViewport({ width: 1366, height: 900 }); await sleep(800);

ok(await choose(p, '.af-co-up-opt[data-kit="painting"]'), 'clicked "Painting only"');
s = await state(p); console.log('  back: ' + JSON.stringify(s));
ok(s && money(s.price) === 80 && /Painting only/i.test(s.receive) && money(s.shipping) === del0 && !s.fees.some(f => /Oversize/i.test(f)), 'back to $80, tube delivery, no oversize fee', s ? s.price + ' | ' + s.shipping + ' | ' + s.fees.join(',') : '');
ok(await choose(p, '.af-co-up-size[data-size="3×5 ft (36×60 in)"]'), 'clicked size 3x5');
s = await state(p); console.log('  size: ' + JSON.stringify(s));
ok(s && money(s.price) === 100 && /Painting only/i.test(s.receive), 'size 3x5 painting only = $100', s ? s.price : '');
ok(await choose(p, '.af-co-up-size[data-size="3×4 ft (36×48 in)"]'), 'clicked size 3x4 back');
s = await state(p);
const sum = money(s.total), parts = [...(await p.evaluate(() => [...document.querySelectorAll('.woocommerce-checkout-review-order-table tr.cart_item td.product-total')].map(td => td.innerText)))].reduce((a, x) => a + money(x), 0) + money(s.shipping) + s.fees.reduce((a, f) => a + money(f), 0);
ok(Math.abs(sum - parts) < 0.015, 'total = lines + delivery + fees', sum + ' vs ' + parts.toFixed(2));

console.log('\n=== console / page errors ===\n' + ([...new Set(errs)].join('\n') || 'none'));
console.log(`\nSUMMARY checkout-upgrade: ${passes} passed, ${fails} failed`);
await ctx.close(); await b.close();
