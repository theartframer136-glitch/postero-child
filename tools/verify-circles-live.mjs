// Click a collection circle on the LIVE homepage with a real Chromium mouse
// and report exactly what happens — served assets, page markers, the click's
// AJAX, and whether the visible products changed. Run from GitHub Actions
// (Verify Circles Live workflow); exits 0 when the click visibly works.
import { chromium } from 'playwright';

const SITE = 'https://theartframer.us/?af_debug_circles=1&afv=' + Math.floor(Date.now() / 1000);

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-Verify',
});
const page = await ctx.newPage();

const ajax = [];
page.on('response', r => {
  if (r.url().includes('admin-ajax.php')) ajax.push(r.status() + ' <- ' + (r.request().postData() || '').slice(0, 120));
});
page.on('pageerror', e => console.log('PAGE ERROR:', e.message.slice(0, 300)));

console.log('loading', SITE);
await page.goto(SITE, { waitUntil: 'load', timeout: 120000 });
await page.waitForTimeout(8000); // let late scripts settle

// ── which custom.js does a fresh visitor get? ──
const jsUrl = await page.evaluate(() => {
  const s = document.querySelector('script[src*="assets/js/custom.js"]');
  return s ? s.src : 'NOT FOUND';
});
console.log('custom.js URL as served:', jsUrl);
if (jsUrl !== 'NOT FOUND') {
  const info = await page.evaluate(async (u) => {
    const t = await (await fetch(u, { cache: 'reload' })).text();
    const i = t.indexOf('pointerdown');
    const downHandler = i >= 0 ? t.slice(i, i + 1100) : '';
    return {
      bytes: t.length,
      // old code: the pointerdown handler itself arms af-dragging (the click killer)
      pointerdownArmsDragging: /classList\.add\('af-dragging'\)/.test(downHandler),
      hasStaleMovedFix: t.includes("e.type !== 'pointerup'"),
    };
  }, jsUrl);
  console.log('custom.js served content:', JSON.stringify(info));
}

const markers = await page.evaluate(() => ({
  circleActiveCss: !!document.getElementById('af-circle-active'),
  strips: document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content').length,
  pfAnchors: document.querySelectorAll('a.pf-value').length,
  subCats: document.querySelectorAll('.sub-cat').length,
  catItems: document.querySelectorAll('li.cat-item').length,
  grids: ['#productGrid', '.product-slider', 'ul.products', '.af-shell'].map(s => s + ':' + document.querySelectorAll(s).length).join('  '),
}));
console.log('page markers:', JSON.stringify(markers));

// ── pick the third circle in the first populated strip ──
const circle = await page.evaluateHandle(() => {
  const strips = document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content');
  for (const strip of strips) {
    const items = strip.querySelectorAll('li.cat-item, .sub-cat');
    if (items.length >= 3) { items[2].scrollIntoView({ block: 'center' }); return items[2]; }
  }
  return null;
});
if (!(await circle.evaluate(el => !!el))) {
  console.log('RESULT: NO CIRCLE FOUND on the page');
  await page.screenshot({ path: 'before.png' });
  await browser.close();
  process.exit(2);
}
await page.waitForTimeout(600); // scrollIntoView settle
const label = await circle.evaluate(el => (el.textContent || '').trim().slice(0, 40));
console.log('clicking circle:', JSON.stringify(label));

const snap = () => page.evaluate(() => {
  const g = document.querySelector('#productGrid, .product-slider, ul.products');
  const shell = document.querySelector('.af-shell');
  return {
    url: location.href.split('&afv=')[0],
    gridSig: g ? g.innerHTML.length + '|' + (g.innerText || '').replace(/\s+/g, ' ').slice(0, 150) : 'none',
    shellSig: shell ? (shell.innerText || '').replace(/\s+/g, ' ').slice(0, 150) : 'no shell',
    active: Array.from(document.querySelectorAll('.subcategory-slider .active, #subcategorySlider .active, li.cat-item.active'))
      .map(e => (e.textContent || '').trim().slice(0, 30)),
    dbg: (document.getElementById('af-circdbg') || { innerText: 'absent' }).innerText,
  };
});

const before = await snap();
await page.screenshot({ path: 'before.png' });

// human-speed press: move, down, hold, up
const box = await circle.evaluate(el => {
  const r = el.getBoundingClientRect();
  return { x: r.x + r.width / 2, y: r.y + Math.min(r.height / 2, 45) };
});
await page.mouse.move(box.x, box.y);
await page.mouse.down();
await page.waitForTimeout(140);
await page.mouse.up();

await page.waitForTimeout(7000); // ajax + shell rebuild time
const after = await snap().catch(() => ({ url: page.url(), note: 'page navigated' }));
await page.screenshot({ path: 'after.png' }).catch(() => {});

console.log('ajax during/after click:', JSON.stringify(ajax));
console.log('BEFORE:', JSON.stringify(before));
console.log('AFTER :', JSON.stringify(after));

const changed = after.note === 'page navigated'
  || after.url !== before.url
  || after.gridSig !== before.gridSig
  || after.shellSig !== before.shellSig;
console.log(changed
  ? 'RESULT: CLICK WORKED — products changed or the page navigated'
  : 'RESULT: CLICK DEAD — nothing changed after a normal mouse click');
await browser.close();
process.exit(changed ? 0 : 1);
