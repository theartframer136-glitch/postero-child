/**
 * The wishlist page, with something actually on it.
 *
 * A wishlist belongs to the visitor: for a guest it lives in a cookie in their
 * own browser. So a fresh headless browser opening /wishlist/ is told, quite
 * correctly, that there is nothing saved — which is exactly what happened, and
 * it is why the card the owner is looking at cannot be measured by loading
 * that page directly.
 *
 * This saves one piece first, in its own throwaway session, and then measures
 * the page. Nothing is written anywhere but this browser's own cookie jar: the
 * heart on a product page is what any visitor clicks, and a guest's wishlist
 * never reaches the database.
 *
 * Usage: node tools/diag-wishlist.js [width] [product-url]
 */
const puppeteer = require('puppeteer-core');

const argv = process.argv.slice(2);
const WIDTH = /^\d+$/.test(argv[0] || '') ? parseInt(argv.shift(), 10) : 420;
const PRODUCT = argv[0] || 'https://theartframer.us/product/radha-krishna-mosaic-art-canvas-wall-art/';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function dump(vw) {
  const root = document.querySelector('.woosw-list') || document.querySelector('main');
  if (!root) return { error: 'no wishlist container' };
  const name = (el) => {
    const id = el.id ? '#' + el.id : '';
    const cls = (typeof el.className === 'string' && el.className)
      ? '.' + el.className.trim().split(/\s+/).slice(0, 4).join('.') : '';
    return el.tagName.toLowerCase() + id + cls;
  };
  const out = { vw, root: name(root), nodes: [], wide: [] };
  const walk = (el, d) => {
    if (d > 7) return;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    out.nodes.push({
      d, el: name(el),
      box: [Math.round(r.left), Math.round(r.width), Math.round(r.height)],
      disp: cs.display, fs: cs.fontSize,
      txt: el.children.length === 0 ? (el.textContent || '').trim().slice(0, 34) : '',
    });
    if (r.right > vw + 1) out.wide.push({ el: name(el), right: Math.round(r.right) });
    for (const c of el.children) walk(c, d + 1);
  };
  walk(root, 0);
  out.nodes = out.nodes.slice(0, 110);
  return out;
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
      + '(KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36');
    await page.setViewport({ width: WIDTH, height: 900, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });

    console.log('saving one piece first: ' + PRODUCT);
    await page.goto(PRODUCT, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(7000);                       // the page reloads itself once
    const clicked = await page.evaluate(() => {
      const b = document.querySelector('.woosw-btn');
      if (!b) return 'no wishlist button on the product page';
      b.click();
      return 'clicked ' + (b.className || '');
    });
    console.log('  ' + clicked);
    await sleep(6000);                       // let the save round-trip finish

    await page.goto('https://theartframer.us/wishlist/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(7000);
    const r = await page.evaluate(dump, WIDTH);
    if (r.error) { console.log(r.error); return; }

    console.log(`\n=== ${r.root} at ${r.vw}px ===`);
    for (const n of r.nodes) {
      const pad = '  '.repeat(n.d);
      console.log(`${pad}${n.el}  left ${n.box[0]}  ${n.box[1]}x${n.box[2]}  ${n.disp}  ${n.fs}`);
      if (n.txt) console.log(`${pad}   "${n.txt}"`);
    }
    if (r.wide.length) {
      console.log('\npast the right edge:');
      r.wide.forEach((w) => console.log(`  ${w.el}  right ${w.right}`));
    }
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
