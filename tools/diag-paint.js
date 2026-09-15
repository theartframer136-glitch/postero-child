/**
 * Are the buttons actually PAINTED, or only correctly styled?
 *
 * Computed styles have been telling me the chips are applied — white glyph on
 * rgba(26,26,26,.68), tooltip attached, the button itself on top at its own
 * centre. The owner says the buttons are still not there. Both can be true: a
 * child reports its own styles perfectly while an ANCESTOR at opacity 0 stops
 * any of it reaching the screen. getComputedStyle never mentions that.
 *
 * So this stops asking the DOM and looks at the pixels. It screenshots the
 * hovered card, loads that picture back into the page, and reads the colour at
 * the centre of each button. A dark chip is unmistakable against artwork; if
 * the pixel is artwork-coloured, nothing was drawn there at all.
 *
 * It also walks each button's ancestors and prints their opacity, which is the
 * thing that hides a perfectly styled control.
 *
 * Read-only. Usage: node tools/diag-paint.js [url] [width]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/product-category/digital-canvas-prints/';
const WIDTH = parseInt(process.argv[3] || '1280', 10);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const BUTTONS = [
  ['cart',      '.af-icon-corner a.add_to_cart_button'],
  ['compare',   '.af-icon-corner .af-cmp-btn'],
  ['wishlist',  '.shop-action .woosw-btn'],
  ['quickview', '.shop-action .woosq-btn'],
];

/** Each button's box, and every ancestor that is dimming it. */
function chain(buttons) {
  const card = document.querySelector('ul.products li.product');
  if (!card) return { error: 'no product card' };
  const cardRect = card.getBoundingClientRect();
  const out = { card: [cardRect.left, cardRect.top, cardRect.width, cardRect.height].map(Math.round), rows: [] };
  for (const [label, sel] of buttons) {
    const el = card.querySelector(sel);
    if (!el) { out.rows.push({ label, missing: true }); continue; }
    const r = el.getBoundingClientRect();
    const dim = [];
    let n = el;
    while (n && n !== document.body) {
      const cs = getComputedStyle(n);
      const o = parseFloat(cs.opacity);
      if (o < 1 || cs.visibility !== 'visible' || cs.display === 'none') {
        const cls = (typeof n.className === 'string' && n.className)
          ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
        dim.push(n.tagName.toLowerCase() + cls + ' opacity:' + cs.opacity
          + (cs.visibility !== 'visible' ? ' vis:' + cs.visibility : '')
          + (cs.display === 'none' ? ' display:none' : ''));
      }
      n = n.parentElement;
    }
    out.rows.push({
      label, sel,
      box: [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)],
      centre: [Math.round(r.left + r.width / 2), Math.round(r.top + r.height / 2)],
      dimmedBy: dim,
    });
  }
  return out;
}

/** Read the screenshot back through a canvas — the only honest answer. */
function samplePixels(dataUrl, clip, points) {
  return new Promise((resolve) => {
    const im = new Image();
    im.onload = function () {
      const c = document.createElement('canvas');
      c.width = im.naturalWidth; c.height = im.naturalHeight;
      const ctx = c.getContext('2d');
      ctx.drawImage(im, 0, 0);
      // The screenshot may be at a device pixel ratio above 1.
      const sx = im.naturalWidth / clip.width;
      const sy = im.naturalHeight / clip.height;
      resolve(points.map((p) => {
        const x = Math.round((p.x - clip.x) * sx);
        const y = Math.round((p.y - clip.y) * sy);
        if (x < 0 || y < 0 || x >= c.width || y >= c.height) return { label: p.label, off: true };
        const d = ctx.getImageData(x, y, 1, 1).data;
        return { label: p.label, rgb: d[0] + ',' + d[1] + ',' + d[2] };
      }));
    };
    im.onerror = () => resolve([]);
    im.src = dataUrl;
  });
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: 1000 });
    await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(9000);

    await page.evaluate(() => {
      const c = document.querySelector('ul.products li.product');
      if (c) c.scrollIntoView({ block: 'center' });
    });
    await sleep(1500);
    await page.hover('ul.products li.product .product-img-wrap, ul.products li.product .product-image');
    await sleep(1800);

    const info = await page.evaluate(chain, BUTTONS);
    if (info.error) { console.log(info.error); return; }

    console.log('=== WHILE HOVERING, WHAT IS DIMMING EACH BUTTON ===');
    info.rows.forEach((r) => {
      if (r.missing) { console.log(`  ${r.label}: not in the card`); return; }
      console.log(`  ${r.label}  box [${r.box.join(', ')}]`);
      console.log(`     dimmed by: ${r.dimmedBy.length ? r.dimmedBy.join('  |  ') : 'nothing — fully opaque'}`);
    });

    const clip = { x: info.card[0], y: info.card[1], width: info.card[2], height: info.card[3] };
    const shot = await page.screenshot({ clip, encoding: 'base64' });
    const points = info.rows.filter((r) => !r.missing)
      .map((r) => ({ label: r.label, x: r.centre[0], y: r.centre[1] }));
    const px = await page.evaluate(samplePixels, 'data:image/png;base64,' + shot, clip, points);

    console.log('\n=== THE PIXEL ACTUALLY PAINTED AT EACH BUTTON CENTRE ===');
    console.log('   (the chip is rgba(26,26,26,.68) over artwork — expect something dark)');
    px.forEach((p) => {
      if (p.off) { console.log(`  ${p.label}: outside the card`); return; }
      const [r, g, b] = p.rgb.split(',').map(Number);
      const lum = Math.round(0.2126 * r + 0.7152 * g + 0.0722 * b);
      console.log(`  ${p.label}: rgb(${p.rgb})  brightness ${lum}`
        + (lum < 110 ? '   ← dark: the chip is painted' : '   ← light: NOTHING is painted here'));
    });
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
