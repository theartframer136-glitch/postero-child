/**
 * Product page, "What you receive: Digital download" (owner's recording, 25 Sep).
 * Picks it, checks size/frame/colour are hidden and the price is the file's,
 * adds to cart and reads what the cart charges, then picks Painting only again.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const U = 'https://theartframer.us/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
for (const [w, h] of [[1280, 900], [420, 860]]) {
  const p = await b.newPage(); await p.setViewport({ width: w, height: h });
  await p.goto(U, { waitUntil: 'domcontentloaded', timeout: 90000 }); await sleep(8000);
  const st = () => p.evaluate(() => {
    const vis = (e) => !!e && e.getBoundingClientRect().height > 0 && getComputedStyle(e).display !== 'none';
    const g = [...document.querySelectorAll('.af-opts .af-opt-group')];
    return { groupsVisible: g.filter(vis).length + '/' + g.length,
      live: (document.querySelector('.af-price-live') || {}).innerText,
      head: ((document.querySelector('.summary .price, .entry-summary .price, p.price') || {}).innerText || '').replace(/\s+/g, ' ') };
  });
  console.log('\n== ' + w);
  console.log('start   ' + JSON.stringify(await st()));
  const r = await p.$('input[name="af_kit"][value="digital"]');
  await p.evaluate(e => e.scrollIntoView({ block: 'center' }), r); await sleep(400);
  await p.evaluate(e => e.closest('label').click(), r); await sleep(1200);
  const d = await st(); console.log('digital ' + JSON.stringify(d));
  await p.evaluate(() => document.querySelector('input[name="af_kit"][value="painting"]').closest('label').click()); await sleep(1500);
  const back = await st(); console.log('painting only ' + JSON.stringify(back));
  await p.evaluate(() => document.querySelector('input[name="af_kit"][value="painting_bar_frame"]').closest('label').click()); await sleep(1500);
  const framed = await st(); console.log('framed kit    ' + JSON.stringify(framed));
  await p.evaluate(() => { const c = [...document.querySelectorAll('.af-frame-chips .af-chip-opt:not([disabled])')].find(b => b.dataset.val !== 'Without Frame'); if (c) c.click(); }); await sleep(1200);
  const withFrame = await st(); console.log('framed + frame ' + JSON.stringify(withFrame));
  await p.evaluate(() => document.querySelector('input[name="af_kit"][value="painting"]').closest('label').click()); await sleep(1500);
  const back2 = await st(); console.log('painting again ' + JSON.stringify(back2));
  if (w === 1280) {
    await p.evaluate(() => document.querySelector('input[name="af_kit"][value="digital"]').closest('label').click()); await sleep(800);
    await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}),
      p.evaluate(() => { const f = document.querySelector('form.cart'); const btn = f.querySelector('button[name="add-to-cart"], .single_add_to_cart_button'); btn.click(); })]);
    await sleep(3000);
    await p.goto('https://theartframer.us/cart/', { waitUntil: 'domcontentloaded', timeout: 90000 }); await sleep(5000);
    console.log('CART ' + JSON.stringify(await p.evaluate(() => [...document.querySelectorAll('.cart_item, tr.woocommerce-cart-form__cart-item, .wc-block-cart-items__row')].map(r => r.innerText.replace(/\s+/g, ' ').slice(0, 260)))));
  }
  const ok = /^0\//.test(d.groupsVisible) && /\$9\.\d\d/.test(d.live || '') && /\$9\.\d\d/.test(d.head) && /^1\//.test(back.groupsVisible) && /^3\//.test(framed.groupsVisible)
    && /^1\//.test(back2.groupsVisible) && back2.live === back.live && withFrame.live !== back.live;
  console.log('VERDICT ' + w + ' ' + (ok ? 'PASS' : 'FAIL'));
  await p.close();
}
await b.close();
