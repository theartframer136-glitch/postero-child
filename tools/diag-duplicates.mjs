/**
 * The 17 lettered listings and the product holding each plain SKU, live.
 * Read-only. For each: does it answer, its title, main picture, reviews, and
 * a 16x16 average-hash of the main picture so a pair can be compared by pixels.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const PAIRS = [[17212,34243],[11541,11577],[11617,11577],[7824,33925],[19453,18600],[24592,34855],[24836,27325],[14678,34147],[7781,8805],[8424,34725],[8494,34732],[26875,8398],[7839,220],[17543,15913],[31829,14617],[8474,229],[31890,15730]];
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const seen = {};
async function look(id) {
  if (seen[id]) return seen[id];
  let status = 0;
  try { const r = await p.goto('https://theartframer.us/?p=' + id, { waitUntil: 'networkidle2', timeout: 90000 }); status = r ? r.status() : 0; } catch (e) { status = -1; }
  await sleep(2500);
  let info = null;
  for (let i = 0; i < 3 && !info; i++) {
    try {
      info = await p.evaluate(async () => {
        const img = document.querySelector('.woocommerce-product-gallery__image img, .woocommerce-product-gallery img, .summary ~ * img.wp-post-image, img.wp-post-image');
        const src = img ? (img.getAttribute('data-large_image') || img.currentSrc || img.src) : '';
        let hash = '';
        if (src) {
          const im = new Image(); im.crossOrigin = 'anonymous'; im.src = src;
          await new Promise(r => { im.onload = r; im.onerror = r; setTimeout(r, 8000); });
          try {
            const c = document.createElement('canvas'); c.width = 16; c.height = 16;
            const x = c.getContext('2d'); x.drawImage(im, 0, 0, 16, 16);
            const d = x.getImageData(0, 0, 16, 16).data; const g = [];
            for (let k = 0; k < d.length; k += 4) g.push(d[k] * .299 + d[k + 1] * .587 + d[k + 2] * .114);
            const avg = g.reduce((a, v) => a + v, 0) / g.length; hash = g.map(v => v > avg ? '1' : '0').join('');
          } catch (e) { hash = 'err'; }
        }
        const rc = document.querySelector('.woocommerce-review-link .count, .woocommerce-product-rating .count, #tab-title-reviews a');
        return { url: location.pathname.slice(0, 90), title: (document.querySelector('h1.product_title, h1') || {}).textContent?.trim().slice(0, 80) || document.title.slice(0, 80),
          notFound: /page not found|404/i.test(document.title), reviews: rc ? rc.textContent.trim() : '-',
          img: src.split('/').pop().slice(0, 70), hash };
      });
    } catch (e) { await sleep(2000); }
  }
  return seen[id] = { status, ...(info || {}) };
}
const ham = (a, b) => (a && b && a.length === b.length) ? [...a].filter((c, i) => c !== b[i]).length : -1;
for (const [dup, keep] of PAIRS) {
  const d = await look(dup), k = await look(keep);
  console.log(`\n#${dup} -> keep #${keep}  picture-distance=${ham(d.hash, k.hash)}/256`);
  console.log('  lettered ' + JSON.stringify({ s: d.status, nf: d.notFound, url: d.url, t: d.title, rev: d.reviews, img: d.img }));
  console.log('  plain    ' + JSON.stringify({ s: k.status, nf: k.notFound, url: k.url, t: k.title, rev: k.reviews, img: k.img }));
}
await b.close();
