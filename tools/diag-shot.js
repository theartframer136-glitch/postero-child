/**
 * A picture of the card, printed into the log so I can look at it.
 *
 * Every measurement I have run says the buttons are painted. The owner keeps
 * saying they are not there. One of us is looking at something the other is
 * not, and numbers have not settled it in three attempts — so this stops
 * describing the card and shows it.
 *
 * The screenshot is cropped to the card, scaled down and encoded as a JPEG,
 * then printed as base64 in fixed-width lines. Small enough for a log, and it
 * can be reassembled and viewed at the other end. No more inference.
 *
 * Read-only. Usage: node tools/diag-shot.js [url] [width]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/product-category/digital-canvas-prints/';
const WIDTH = parseInt(process.argv[3] || '1280', 10);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Shrink and re-encode inside the page — no image library needed out here. */
function shrink(dataUrl, targetW, quality) {
  return new Promise((resolve) => {
    const im = new Image();
    im.onload = function () {
      const scale = targetW / im.naturalWidth;
      const c = document.createElement('canvas');
      c.width = Math.round(im.naturalWidth * scale);
      c.height = Math.round(im.naturalHeight * scale);
      c.getContext('2d').drawImage(im, 0, 0, c.width, c.height);
      resolve(c.toDataURL('image/jpeg', quality));
    };
    im.onerror = () => resolve('');
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

    const clip = await page.evaluate(() => {
      const card = document.querySelector('ul.products li.product');
      if (!card) return null;
      card.scrollIntoView({ block: 'center' });
      return null;
    });
    await sleep(1500);
    const box = await page.evaluate(() => {
      const card = document.querySelector('ul.products li.product');
      if (!card) return null;
      const r = card.getBoundingClientRect();
      // The picture only — the words below it are not in question.
      return { x: Math.round(r.left), y: Math.round(r.top),
               width: Math.round(r.width), height: Math.round(Math.min(r.height, 330)) };
    });
    if (!box) { console.log('no product card'); return; }

    // Hover, because the owner's complaint is about hovering — even though
    // nothing should now depend on it.
    await page.hover('ul.products li.product .product-img-wrap, ul.products li.product .product-image');
    await sleep(1500);

    const raw = await page.screenshot({ clip: box, encoding: 'base64', fromSurface: false });
    const small = await page.evaluate(shrink, 'data:image/png;base64,' + raw, 330, 0.62);
    const b64 = small.split(',')[1] || '';
    console.log(`=== CARD PICTURE (${box.width}x${box.height}, jpeg, ${b64.length} base64 chars) ===`);
    console.log('--8<-- BEGIN');
    for (let i = 0; i < b64.length; i += 180) console.log(b64.slice(i, i + 180));
    console.log('--8<-- END');
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
