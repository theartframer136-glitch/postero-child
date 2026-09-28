/** Stretcher bars at $4/sq ft, live: the bar products and the art page's bar options. */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
let ok = true;
// 1. a bar product
await p.goto('https://theartframer.us/?p=8337', { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {}); await sleep(6000);
const retry = async (fn) => { for (let i = 0; i < 4; i++) { try { return await fn(); } catch (e) { await sleep(3000); } } return fn(); };
const bar = await retry(() => p.evaluate(() => ({ url: location.pathname.slice(0, 60),
  sizes: [...document.querySelectorAll('#af-bar-size-select option')].map(o => o.textContent.trim()),
  live: (document.getElementById('af-bar-live') || {}).textContent, head: ((document.querySelector('.summary .price, p.price') || {}).innerText || '').trim(),
  atc: !!document.querySelector('form.cart .single_add_to_cart_button') })));
console.log('BAR PRODUCT ' + JSON.stringify(bar));
if (bar.sizes.length !== 5 || !bar.atc || !/24\.00/.test(bar.live || '')) ok = false;
await p.select('#af-bar-size-select', '3×4 ft (36×48 in)'); await sleep(500);
console.log('3x4 chosen: ' + await p.evaluate(() => document.getElementById('af-bar-live').textContent + ' | head ' + document.querySelector('.summary .price, p.price').innerText));
await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]); await sleep(3000);
await p.goto('https://theartframer.us/cart/', { waitUntil: 'domcontentloaded', timeout: 90000 }); await sleep(5000);
const cart = await p.evaluate(() => [...document.querySelectorAll('.cart_item, tr.woocommerce-cart-form__cart-item')].map(r => r.innerText.replace(/\s+/g, ' ').slice(0, 220)));
console.log('CART ' + JSON.stringify(cart));
if (!cart.some(c => /48\.00/.test(c) && /3×4/.test(c))) ok = false;
// 2. the art page's bar options
const U = 'https://theartframer.us/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const q = await b.newPage(); await q.setViewport({ width: 1280, height: 900 });
await q.goto(U, { waitUntil: 'domcontentloaded', timeout: 90000 }); await sleep(8000);
const st = () => q.evaluate(() => ({ chip: [...document.querySelectorAll('.af-frame-chips .af-chip-opt')].map(b => b.textContent.replace(/\s+/g, ' ').trim()).join(' | '), live: document.getElementById('af-live-price').textContent, adds: [...document.querySelectorAll('.af-kit-add')].map(s => s.getAttribute('data-kit') + '=' + s.textContent).join(' '), note: (document.querySelector('.af-kit-note') || {}).textContent }));
const pick = async (k) => { await q.evaluate((k) => document.querySelector('input[name="af_kit"][value="' + k + '"]').closest('label').click(), k); await sleep(1500); };
const s0 = await st(); console.log('ART painting only ' + JSON.stringify(s0));
await pick('painting_bar'); const s1 = await st(); console.log('ART painting+bars  ' + JSON.stringify(s1));
await pick('painting_bar_frame'); const s2 = await st(); console.log('ART bars+frame kit ' + JSON.stringify(s2));
await pick('painting'); const s3 = await st(); console.log('ART painting again ' + JSON.stringify(s3));
if (!/80\.00/.test(s0.live) || !/128\.00/.test(s1.live) || !/176\.00/.test(s2.live) || !/painting_bar_frame=\+\$96\.00/.test(s2.adds) || !/ALUMINIUM FRAME \+\$48|Aluminium Frame \+\$48/i.test(s2.chip) || !/80\.00/.test(s3.live) || !/painting_bar=\+\$48\.00/.test(s1.adds)) ok = false;

// 3. every size: the Aluminium chip, Painting only + frame, and the framed kit
{
  const sizes = await q.evaluate(() => [...document.querySelectorAll('#af-size-select option')].map(o => o.value));
  const rows = [];
  for (const sz of sizes) {
    await q.select('#af-size-select', sz); await sleep(700);
    await pick('painting'); await sleep(300);
    const plain = await q.evaluate(() => document.getElementById('af-live-price').textContent);
    await pick('painting_bar_frame'); await sleep(600);
    const r = await q.evaluate(() => ({
      chip: ([...document.querySelectorAll('.af-frame-chips .af-chip-opt')].find(b => /alumin/i.test(b.textContent)) || {}).textContent,
      active: (document.querySelector('.af-frame-chips .af-chip-opt.active') || {}).textContent,
      live: document.getElementById('af-live-price').textContent,
      kitAdd: (document.querySelector('.af-kit-add[data-kit="painting_bar_frame"]') || {}).textContent }));
    rows.push(sz.padEnd(22) + ' painting=' + plain + ' | alu chip=' + (r.chip || '').replace(/\s+/g, ' ').trim() + ' | framed kit ' + r.kitAdd + ' => ' + r.live);
    const m = sz.match(/\((\d+(?:\.\d+)?)×(\d+(?:\.\d+)?) in\)/); const sq = m[1] * m[2] / 144;
    const exp = parseFloat(plain.replace(/[^0-9.]/g, '')) + sq * 4 * 2;
    if (Math.abs(parseFloat(r.live.replace(/[^0-9.]/g, '')) - exp) > 0.01) { rows.push('   EXPECTED ' + exp.toFixed(2)); ok = false; }
  }
  console.log('SIZES\n' + rows.join('\n'));
  // cart: 3x4 framed kit
  await q.select('#af-size-select', '3×4 ft (36×48 in)'); await sleep(700);
  await pick('painting_bar_frame'); await sleep(700);
  await Promise.all([q.waitForNavigation({ timeout: 45000 }).catch(() => {}), q.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]); await sleep(3000);
  await q.goto('https://theartframer.us/cart/', { waitUntil: 'domcontentloaded', timeout: 90000 }); await sleep(5000);
  const c2 = await q.evaluate(() => [...document.querySelectorAll('.cart_item, tr.woocommerce-cart-form__cart-item')].map(r => r.innerText.replace(/\s+/g, ' ').slice(-160)));
  console.log('CART (framed kit 3x4) ' + JSON.stringify(c2));
  if (!c2.some(c => /176\.00/.test(c))) ok = false;
}
console.log('VERDICT ' + (ok ? 'PASS' : 'FAIL'));
await b.close();
