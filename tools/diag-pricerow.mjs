/**
 * Price row on every kind of product card, measured against the Corporate
 * Signages card the owner pointed at (24 Sep): now price, old price, % off.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const PAGES = [S + '/', S + '/shop/', S + '/product-category/digital-canvas-prints/', S + '/wishlist/4TIY7Q/', S + '/deals/'];
for (const [w, h] of [[1280, 900], [420, 860]]) for (const u of PAGES) {
  const p = await b.newPage(); await p.setViewport({ width: w, height: h });
  try { await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 90000 }); } catch { console.log('noload ' + u); await p.close(); continue; }
  await sleep(6000);
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 120)); } });
  await sleep(2500);
  const out = await p.evaluate(() => {
    const seen = new Set(), res = [];
    const d = (el) => { if (!el) return '-'; const c = getComputedStyle(el), r = el.getBoundingClientRect();
      return c.fontSize + '/' + c.fontWeight + ' ' + c.color.replace(/rgba?\(|\)| /g, '') + ' ff=' + c.fontFamily.split(',')[0].replace(/"/g, '').slice(0, 14) + ' td=' + c.textDecorationLine + ' x=' + Math.round(r.left); };
    for (const row of document.querySelectorAll('.price-section, li.product .price, .product-card .price, .af-pricerow')) {
      const card = row.closest('li.product, .product-card, .product, [class*="card"]'); if (!card || seen.has(card)) continue;
      const r = row.getBoundingClientRect(); if (r.width < 5) continue; seen.add(card);
      const now = row.querySelector('ins .amount, ins') || [...row.querySelectorAll('.amount')].find(a => !a.closest('del,.old-price'));
      const was = row.querySelector('del, .old-price');
      const off = card.querySelector('.discount, .af-pct-off, .discount-percentage');
      const sec = card.closest('section, .elementor-widget, .e-con, .af-wl-related');
      const key = (card.className.toString().split(' ')[0] + '|' + (sec ? (sec.className.toString().split(' ').slice(0, 2).join('.')) : '')).slice(0, 70);
      res.push({ key, rowcls: row.className.toString().slice(0, 50), txt: row.innerText.replace(/\s+/g, ' ').slice(0, 50),
        row: d(row) + ' gap=' + getComputedStyle(row).gap + ' h=' + Math.round(r.height), now: d(now), was: d(was), off: d(off) + ' "' + (off ? off.innerText : '') + '"' });
    }
    // one per distinct style signature
    const sig = new Map(); for (const x of res) { const k = x.rowcls + x.now.split(' x=')[0] + x.was.split(' x=')[0] + x.off.split(' x=')[0]; if (!sig.has(k)) sig.set(k, { ...x, n: 0 }); sig.get(k).n++; }
    return [...sig.values()];
  });
  console.log('\n===== ' + w + ' ' + u);
  for (const x of out) console.log(JSON.stringify(x));
  await p.close();
}
await b.close();
