/**
 * Is something clipping these buttons out of existence?
 *
 * The buttons report a correct 38x38 box, full opacity, and a chip background,
 * and they paint nothing. That combination has one common cause: an ancestor
 * with overflow:hidden whose box does not contain them. The clip is invisible
 * to getComputedStyle on the button and to getBoundingClientRect, which is why
 * every check so far has said everything is fine.
 *
 * (I also have to correct a reading of my own: I called rgb(178,8,121) "dark,
 * the chip is painted". It is saturated pink — the painting. A luminance
 * formula rates strong colour as dark, and I took the number for the answer.
 * Saturation is the honest test for a grey chip, and it is used below.)
 *
 * Read-only. Usage: node tools/diag-clip.js [url] [width]
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

function clips(buttons) {
  const card = document.querySelector('ul.products li.product');
  if (!card) return { error: 'no product card' };
  const name = (el) => {
    const cls = (typeof el.className === 'string' && el.className)
      ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
    return el.tagName.toLowerCase() + cls;
  };
  const out = { rows: [] };
  for (const [label, sel] of buttons) {
    const el = card.querySelector(sel);
    if (!el) { out.rows.push({ label, missing: true }); continue; }
    const r = el.getBoundingClientRect();
    const notes = [];
    let n = el.parentElement;
    while (n && n !== document.body) {
      const cs = getComputedStyle(n);
      const clipped = cs.overflow !== 'visible' || cs.overflowX !== 'visible' || cs.overflowY !== 'visible';
      if (clipped) {
        const nr = n.getBoundingClientRect();
        const outside =
          r.right  <= nr.left  + 0.5 ? 'entirely LEFT of it'  :
          r.left   >= nr.right - 0.5 ? 'entirely RIGHT of it' :
          r.bottom <= nr.top   + 0.5 ? 'entirely ABOVE it'    :
          r.top    >= nr.bottom- 0.5 ? 'entirely BELOW it'    :
          (r.left < nr.left - 0.5 || r.right > nr.right + 0.5 ||
           r.top < nr.top - 0.5 || r.bottom > nr.bottom + 0.5) ? 'partly outside' : '';
        notes.push(`${name(n)} overflow:${cs.overflow} box[${Math.round(nr.left)},${Math.round(nr.top)},`
          + `${Math.round(nr.width)},${Math.round(nr.height)}]`
          + (outside ? '   ← THE BUTTON IS ' + outside.toUpperCase() : '   (button inside)'));
      }
      n = n.parentElement;
    }
    out.rows.push({
      label,
      box: [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)],
      clippers: notes,
    });
  }
  return out;
}

/** A grey chip desaturates whatever is behind it. Strong colour means no chip. */
function sample(dataUrl, clip, points) {
  return new Promise((resolve) => {
    const im = new Image();
    im.onload = function () {
      const c = document.createElement('canvas');
      c.width = im.naturalWidth; c.height = im.naturalHeight;
      const ctx = c.getContext('2d');
      ctx.drawImage(im, 0, 0);
      const sx = im.naturalWidth / clip.width, sy = im.naturalHeight / clip.height;
      resolve(points.map((p) => {
        const x = Math.round((p.x - clip.x) * sx), y = Math.round((p.y - clip.y) * sy);
        if (x < 0 || y < 0 || x >= c.width || y >= c.height) return { label: p.label, off: true };
        const d = ctx.getImageData(x, y, 1, 1).data;
        const mx = Math.max(d[0], d[1], d[2]), mn = Math.min(d[0], d[1], d[2]);
        return { label: p.label, rgb: `${d[0]},${d[1]},${d[2]}`, spread: mx - mn, max: mx };
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

    const info = await page.evaluate(clips, BUTTONS);
    if (info.error) { console.log(info.error); return; }
    console.log('=== IS ANYTHING CLIPPING THEM? ===');
    info.rows.forEach((r) => {
      if (r.missing) { console.log(`  ${r.label}: not in the card`); return; }
      console.log(`\n  ${r.label}  box [${r.box.join(', ')}]`);
      if (!r.clippers.length) { console.log('     no clipping ancestor'); return; }
      r.clippers.forEach((c) => console.log('     ' + c));
    });

    const cardBox = await page.evaluate(() => {
      const c = document.querySelector('ul.products li.product').getBoundingClientRect();
      return { x: Math.round(c.left), y: Math.round(c.top),
               width: Math.round(c.width), height: Math.round(Math.min(c.height, 340)) };
    });
    const shot = await page.screenshot({ clip: cardBox, encoding: 'base64', fromSurface: false });
    const pts = info.rows.filter((r) => !r.missing).map((r) => ({
      label: r.label, x: r.box[0] + r.box[2] / 2, y: r.box[1] + r.box[3] / 2 }));
    const px = await page.evaluate(sample, 'data:image/png;base64,' + shot, cardBox, pts);

    console.log('\n=== PAINTED COLOUR (a grey chip DESATURATES what is behind it) ===');
    px.forEach((p) => {
      if (p.off) { console.log(`  ${p.label}: outside the shot`); return; }
      const chip = p.spread < 26 && p.max < 130;
      console.log(`  ${p.label}: rgb(${p.rgb})  colour spread ${p.spread}, brightest channel ${p.max}`
        + (chip ? '   ← grey and dark: THE CHIP IS THERE'
                : '   ← strong colour: that is the artwork, NO CHIP'));
    });
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
