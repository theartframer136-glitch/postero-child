// Every product's main picture, as a small JPEG in the log, read as a visitor.
//
// For comparing products picture to picture off-server (duplicate listings,
// matching against the Canva brochure). The deploy's diag-artcode-thumbs.php
// does the same from the server, but only inside a full deploy, which also
// runs every catalogue pass; this asks the public Store API instead, so it
// can run at any time without writing anything.
//
// Output, one line per product:
//   @@T|<pid>|<sku>|<base64 jpeg, longest edge 160 px, or empty>
// then "@@THUMBS DONE n=<products> pictures=<with a picture>".
//
// Only what the Store API lists (published, visible products). Read-only:
// nothing goes in a cart.
//
// Run: node tools/export-thumbs.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const MAX = 160;

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })).newPage();
await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
await page.waitForTimeout(1500);

// The whole list, 100 at a time, from inside the page (the host's bot
// protection refuses plain HTTP clients).
const products = [];
for (let n = 1; n < 30; n++) {
  const batch = await page.evaluate(async n => {
    const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&orderby=id&order=asc&page=' + n, { headers: { Accept: 'application/json' } });
    if (!r.ok) return null;
    return (await r.json()).map(p => {
      const im = (p.images || [])[0] || {};
      // The smallest size at least 320 wide from the srcset: WordPress lists
      // only sizes with the original's proportions, so nothing is cropped.
      const cands = String(im.srcset || '').split(',').map(s => s.trim().split(/\s+/)).filter(x => x[0])
        .map(([u, w]) => ({ u, w: parseInt(w, 10) || 0 })).filter(x => x.w >= 320).sort((a, b) => a.w - b.w);
      return { id: p.id, sku: p.sku || '', src: (cands[0] && cands[0].u) || im.src || '' };
    });
  }, n).catch(() => null);
  if (!batch || !batch.length) break;
  products.push(...batch);
  if (batch.length < 100) break;
}
console.log('products listed: ' + products.length);

let got = 0;
for (let i = 0; i < products.length; i += 8) {
  const out = await page.evaluate(async ({ list, MAX }) => Promise.all(list.map(async p => {
    if (!p.src) return { ...p, b64: '' };
    try {
      const blob = await (await fetch(p.src)).blob();
      const bmp = await createImageBitmap(blob);
      const s = Math.min(1, MAX / Math.max(bmp.width, bmp.height));
      const c = document.createElement('canvas');
      c.width = Math.max(1, Math.round(bmp.width * s)); c.height = Math.max(1, Math.round(bmp.height * s));
      const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height);
      x.imageSmoothingQuality = 'high'; x.drawImage(bmp, 0, 0, c.width, c.height);
      return { ...p, b64: c.toDataURL('image/jpeg', 0.8).split(',')[1] || '' };
    } catch (e) { return { ...p, b64: '' }; }
  })), { list: products.slice(i, i + 8), MAX }).catch(() => products.slice(i, i + 8).map(p => ({ ...p, b64: '' })));
  for (const p of out) { if (p.b64) got++; console.log('@@T|' + p.id + '|' + p.sku + '|' + p.b64); }
}
console.log('@@THUMBS DONE n=' + products.length + ' pictures=' + got);
await browser.close();
