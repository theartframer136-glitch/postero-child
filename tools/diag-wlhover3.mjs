/** After the fix: hovering a wishlist "You may also like" card keeps its photo (no fade, no zoom). */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const p = await b.newPage(); await p.setViewport({ width: 1918, height: 1078 });
const U = 'https://theartframer.us/wishlist/4TIY7Q/';
let ok = false; for (let i = 1; i <= 3 && !ok; i++) { try { await p.goto(U, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; } catch { await sleep(4000); } }
await sleep(8000);
for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {} if (!bl) break; await sleep(3000); try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {} await sleep(6000); }
await p.evaluate(() => { const s = document.querySelector('.af-wl-related'); if (s) s.scrollIntoView({ block: 'center' }); }); await sleep(2500);
console.log('rule on page: ' + await p.evaluate(() => /No hover swap in the related rows/.test(document.documentElement.innerHTML)));
const st = (i) => p.evaluate((i) => { const c = document.querySelectorAll('.af-wl-related li.product')[i]; const m = c.querySelector('.image-main'), s = c.querySelector('.second-image');
  const box = c.querySelector('.product-img-wrap').getBoundingClientRect(); const mr = m.querySelector('img').getBoundingClientRect();
  return 'main op=' + getComputedStyle(m).opacity + ' ' + Math.round(mr.width) + 'x' + Math.round(mr.height) + ' @' + Math.round(mr.left - box.left) + ',' + Math.round(mr.top - box.top) + ' | second op=' + getComputedStyle(s).opacity + ' tf=' + getComputedStyle(s).transform; }, i);
let pass = true; const cards = await p.$$('.af-wl-related li.product');
for (let i = 0; i < Math.min(4, cards.length); i++) {
  const before = await st(i); const r = await cards[i].boundingBox(); await p.mouse.move(r.x + r.width / 2, r.y + 120); await sleep(1500);
  const during = await st(i); console.log('card ' + (i + 1) + '\n   rest : ' + before + '\n   hover: ' + during);
  if (!/main op=1 /.test(during) || !/second op=0 tf=none/.test(during) || !/@0,0/.test(during)) pass = false;
  await p.mouse.move(5, 5); await sleep(800);
}
console.log('VERDICT ' + (pass ? 'PASS: photo stays on hover' : 'FAIL'));
await b.close();
