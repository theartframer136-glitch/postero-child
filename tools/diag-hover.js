/**
 * What actually happens when you hover a product card?
 *
 * All four controls the owner is asking for already exist in the card —
 * measured on the live category page:
 *
 *   div.af-icon-corner  80x36     add to cart, compare
 *   div.group-action    280x40    wishlist, quick view
 *
 * They are laid out at real sizes and none of them appear. Sizes alone cannot
 * explain that: a control can be perfectly sized and still be invisible
 * (opacity 0), unreachable (something painted over it), or revealed by a rule
 * whose selector no longer matches anything.
 *
 * So this reports the three things sizes do not: opacity, stacking, and who is
 * actually on top at the point a visitor's cursor lands — before hover, and
 * again while hovering.
 *
 * Read-only. Usage: node tools/diag-hover.js [url] [width]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/product-category/digital-canvas-prints/';
const WIDTH = parseInt(process.argv[3] || '1280', 10);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const WANTED = [
  '.af-icon-corner',
  '.af-icon-corner .add_to_cart_button',
  '.af-icon-corner .af-cmp-btn',
  '.group-action',
  '.shop-action',
  '.woosw-btn',
  '.woosq-btn',
  '.woocommerce-LoopProduct-link',
];

function look(sels) {
  const card = document.querySelector('ul.products li.product');
  if (!card) return { error: 'no product card' };
  const name = (el) => {
    if (!el) return '(nothing)';
    const cls = (typeof el.className === 'string' && el.className)
      ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '';
    return el.tagName.toLowerCase() + cls;
  };
  const out = { rows: [] };
  for (const sel of sels) {
    const el = card.querySelector(sel);
    if (!el) { out.rows.push({ sel, missing: true }); continue; }
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    out.rows.push({
      sel,
      box: Math.round(r.width) + 'x' + Math.round(r.height),
      opacity: cs.opacity, visibility: cs.visibility, display: cs.display,
      z: cs.zIndex, position: cs.position,
      transform: cs.transform === 'none' ? 'none' : 'set',
      pointer: cs.pointerEvents,
    });
  }
  // A button drawn at font-size 0 has nothing to draw with. These plugins put
  // their icon in a ::before with its own size, so the pseudo-element is where
  // the glyph actually lives — or fails to. And a glyph the right size is
  // still invisible if it is white on white, so take the colours too.
  out.glyphs = [];
  for (const sel of ['.woosw-btn', '.woosq-btn', '.af-icon-corner .add_to_cart_button',
                     '.af-icon-corner .af-cmp-btn']) {
    const el = card.querySelector(sel);
    if (!el) { out.glyphs.push({ sel, missing: true }); continue; }
    const cs = getComputedStyle(el);
    const bf = getComputedStyle(el, '::before');
    const r = el.getBoundingClientRect();
    out.glyphs.push({
      sel,
      at: Math.round(r.left) + ',' + Math.round(r.top),
      color: cs.color, background: cs.backgroundColor,
      fontSize: cs.fontSize, fontFamily: (cs.fontFamily || '').slice(0, 34),
      text: (el.textContent || '').trim().slice(0, 22),
      beforeContent: bf.content, beforeFont: (bf.fontFamily || '').slice(0, 34),
      beforeSize: bf.fontSize, beforeColor: bf.color, beforeDisplay: bf.display,
    });
  }

  // Who receives the click in the middle of the picture, and in the corner
  // where the cart and compare buttons live?
  const img = card.querySelector('.product-image, .product-img-wrap');
  if (img) {
    const r = img.getBoundingClientRect();
    const at = (x, y) => name(document.elementFromPoint(Math.round(x), Math.round(y)));
    out.onTop = {
      middle: at(r.left + r.width / 2, r.top + r.height / 2),
      topRight: at(r.right - 24, r.top + 20),
      bottom: at(r.left + r.width / 2, r.bottom - 22),
    };
  }
  return out;
}

const show = (label, r) => {
  console.log(`\n=== ${label} ===`);
  if (r.error) { console.log('  ' + r.error); return; }
  r.rows.forEach((x) => {
    if (x.missing) { console.log(`  ${x.sel}  — NOT IN THE CARD`); return; }
    console.log(`  ${x.sel}  ${x.box}  opacity:${x.opacity} vis:${x.visibility} `
      + `display:${x.display} z:${x.z} pos:${x.position} transform:${x.transform} pointer:${x.pointer}`);
  });
  (r.glyphs || []).forEach((g) => {
    if (g.missing) { console.log(`  ${g.sel}  — not in the card`); return; }
    console.log(`  ${g.sel}  at ${g.at}  text:"${g.text}"`);
    console.log(`     font ${g.fontSize} ${g.fontFamily}  colour ${g.color} on ${g.background}`);
    console.log(`     ::before content:${g.beforeContent} font:${g.beforeFont} `
      + `size:${g.beforeSize} colour:${g.beforeColor} display:${g.beforeDisplay}`);
  });
  if (r.onTop) {
    console.log('  what is on top:');
    console.log(`     middle of the picture: ${r.onTop.middle}`);
    console.log(`     top-right corner:      ${r.onTop.topRight}`);
    console.log(`     bottom of the picture: ${r.onTop.bottom}`);
  }
};

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: 1000 });
    await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(9000);                        // the page reloads itself once

    show('BEFORE HOVER', await page.evaluate(look, WANTED));

    const hovered = await page.evaluate(() => {
      const card = document.querySelector('ul.products li.product');
      if (!card) return 'no card';
      card.scrollIntoView({ block: 'center' });
      return 'scrolled to the first card';
    });
    console.log('\n' + hovered);
    await sleep(1200);
    await page.hover('ul.products li.product .product-img-wrap, ul.products li.product .product-image');
    await sleep(1600);

    show('WHILE HOVERING', await page.evaluate(look, WANTED));
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
