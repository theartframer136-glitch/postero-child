/**
 * Checkout "Your order" list, live and read-only. Owner, 30 Sep: the product
 * photo should show in that list. Before changing it: its real markup, how
 * the name cell is laid out at the owner's 1918px, a laptop and a phone,
 * whether anything else on the checkout page lists cart items (a header
 * mini-cart must not get a second picture), and a small picture of the list
 * printed into the log as base64 JPEG. Never places an order.
 *
 *   node tools/diag-review-order.mjs
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });

function shrink(dataUrl, targetW, quality) {
  return new Promise((resolve) => {
    const im = new Image();
    im.onload = function () {
      const scale = Math.min(1, targetW / im.naturalWidth);
      const c = document.createElement('canvas');
      c.width = Math.round(im.naturalWidth * scale); c.height = Math.round(im.naturalHeight * scale);
      c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
      resolve(c.toDataURL('image/jpeg', quality));
    };
    im.onerror = () => resolve('');
    im.src = dataUrl;
  });
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
async function add(p, size, kit) {
  await go(p, PRODUCT);
  await p.select('#af-size-select', size).catch(() => {}); await sleep(700);
  await p.evaluate((k) => { const r = document.querySelector('input[name="af_kit"][value="' + k + '"]'); if (r) r.closest('label').click(); }, kit); await sleep(1200);
  await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => { const bt = document.querySelector('form.cart .single_add_to_cart_button'); if (bt) bt.click(); })]);
  await sleep(2000);
}

// one cart, looked at in three widths
const ctx = await b.createBrowserContext(); const p = await ctx.newPage();
p.on('dialog', async d => { console.log('DIALOG ' + d.message()); try { await d.dismiss(); } catch {} });
await p.setViewport({ width: 1366, height: 900 });
await add(p, '3×4 ft (36×48 in)', 'painting');
await add(p, '2×3 ft (24×36 in)', 'painting_bar');
await go(p, S + '/checkout/');
await p.evaluate(() => {
  const set = (id, v) => { const e = document.getElementById(id); if (!e) return; if (window.jQuery) jQuery(e).val(v).trigger('change'); else e.value = v; };
  set('billing_country', 'US'); set('billing_address_1', '350 5th Ave'); set('billing_city', 'New York'); set('billing_state', 'NY'); set('billing_postcode', '10001');
  if (window.jQuery) jQuery(document.body).trigger('update_checkout');
});
try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 25000 }); } catch {}
await sleep(2500);

console.log('=== MARKUP: first product row of the order list ===');
console.log(await p.evaluate(() => { const r = document.querySelector('.woocommerce-checkout-review-order-table tr.cart_item'); return r ? r.outerHTML.replace(/\s+/g, ' ').slice(0, 2200) : 'no tr.cart_item'; }));
console.log('\n=== table head and wrappers ===');
console.log(await p.evaluate(() => {
  const t = document.querySelector('.woocommerce-checkout-review-order-table'); if (!t) return 'no review table';
  const chain = []; let e = t; for (let i = 0; i < 5 && e; i++) { chain.push(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/).join('.') : '')); e = e.parentElement; }
  return 'thead: ' + (t.tHead ? t.tHead.innerText.replace(/\s+/g, ' ') : '-') + '\nparents: ' + chain.join(' < ') + '\nimages already in the table: ' + t.querySelectorAll('img').length;
}));
console.log('\n=== anything else on the checkout page that lists cart items ===');
console.log(await p.evaluate(() => {
  const sels = ['.woocommerce-mini-cart-item', '.mini_cart_item', '.widget_shopping_cart_content', '.cart-dropdown', '.af-mini-cart', '[class*="mini-cart"]', '[class*="minicart"]'];
  return sels.map(s => { const n = document.querySelectorAll(s); return s + ': ' + n.length + (n.length ? ' (imgs ' + [...n].reduce((a, x) => a + x.querySelectorAll('img').length, 0) + ', visible ' + [...n].filter(x => x.offsetParent !== null).length + ')' : ''); }).join('\n');
}));

for (const [w, h] of [[1918, 1078], [1366, 900], [390, 844]]) {
  await p.setViewport({ width: w, height: h }); await sleep(1500);
  console.log('\n=== ' + w + 'px ===');
  console.log(await p.evaluate(() => {
    const t = document.querySelector('.woocommerce-checkout-review-order-table'); if (!t) return 'no table';
    const td = t.querySelector('tr.cart_item td.product-name'), tt = t.querySelector('tr.cart_item td.product-total');
    const cs = td ? getComputedStyle(td) : null;
    const dl = td ? td.querySelector('dl, .variation, .wc-item-meta') : null;
    const box = document.getElementById('order_review') || t;
    return [
      'order box ' + Math.round(box.getBoundingClientRect().width) + 'px, table ' + Math.round(t.getBoundingClientRect().width) + 'px, layout ' + getComputedStyle(t).tableLayout,
      td ? 'name cell ' + Math.round(td.getBoundingClientRect().width) + 'x' + Math.round(td.getBoundingClientRect().height) + ' pad ' + cs.padding + ' valign ' + cs.verticalAlign + ' font ' + cs.fontSize + '/' + cs.lineHeight + ' ' + cs.fontFamily.slice(0, 40) + ' pos ' + cs.position + ' display ' + cs.display : 'no name cell',
      tt ? 'total cell ' + Math.round(tt.getBoundingClientRect().width) + ' valign ' + getComputedStyle(tt).verticalAlign + ' align ' + getComputedStyle(tt).textAlign : 'no total cell',
      dl ? 'meta list: ' + dl.tagName + '.' + dl.className + ' display ' + getComputedStyle(dl).display + ' width ' + Math.round(dl.getBoundingClientRect().width) : 'no meta list',
      'page overflow ' + (document.documentElement.scrollWidth - window.innerWidth) + 'px',
    ].join('\n');
  }));
  const el = await p.$('#order_review') || await p.$('.woocommerce-checkout-review-order-table');
  if (el) {
    await el.evaluate(e => e.scrollIntoView({ block: 'start' })); await sleep(800);
    const bx = await el.boundingBox();
    const clip = { x: Math.max(0, bx.x), y: Math.max(0, bx.y), width: Math.min(bx.width, w), height: Math.min(bx.height, 1400) };
    const raw = await p.screenshot({ clip, encoding: 'base64', captureBeyondViewport: true });
    const small = await p.evaluate(shrink, 'data:image/png;base64,' + raw, 380, 0.6);
    const b64 = small.replace(/^data:image\/jpeg;base64,/, '');
    console.log(`=== PICTURE ${w} (${Math.round(clip.width)}x${Math.round(clip.height)}, jpeg, ${b64.length} chars) ===`);
    for (let i = 0; i < b64.length; i += 180) console.log('B64 ' + b64.slice(i, i + 180));
    console.log('=== END PICTURE ' + w + ' ===');
  }
}
await ctx.close();
await b.close();
