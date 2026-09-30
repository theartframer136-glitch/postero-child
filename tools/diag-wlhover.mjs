/**
 * Wishlist "You may also like": what happens to a card when the mouse is over
 * it (owner's recording, 30 Sep: the hover picture spills below the photo box
 * and sits off-centre). For the first four cards, before and during hover:
 * every image box inside the card, its size and position relative to the
 * photo box, natural size, object-fit, and the overflow of each ancestor.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const URL_ = 'https://theartframer.us/wishlist/4TIY7Q/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
let ok = false; for (let i = 1; i <= 3 && !ok; i++) { try { await p.goto(URL_, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; } catch { await sleep(4000); } }
await sleep(8000);
for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {} if (!bl) break; await sleep(3000); try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {} await sleep(6000); }
await p.evaluate(() => { const s = document.querySelector('.af-wl-related'); if (s) s.scrollIntoView({ block: 'center' }); });
await sleep(2500);
const measure = (idx) => p.evaluate((idx) => {
  const card = document.querySelectorAll('.af-wl-related li.product')[idx]; if (!card) return 'no card ' + idx;
  const box = card.querySelector('.product-img-wrap') || card.querySelector('.product-transition');
  const br = box.getBoundingClientRect();
  const out = ['  photo box ' + Math.round(br.width) + 'x' + Math.round(br.height)];
  const chain = []; let e = card.querySelector('.second-image img, .af-hover-img') ; let a = e ? e.parentElement : null;
  while (a && a !== card.parentElement) { const cs = getComputedStyle(a); chain.push(a.tagName.toLowerCase() + '.' + String(a.className).split(/\s+/).slice(0, 2).join('.') + '{ov=' + cs.overflow + ',pos=' + cs.position + ',h=' + cs.height + '}'); a = a.parentElement; }
  out.push('  ancestors of hover img: ' + chain.join(' > '));
  card.querySelectorAll('img').forEach((im) => {
    const r = im.getBoundingClientRect(); const cs = getComputedStyle(im); const wrap = im.closest('.product-image, .second-image, .image-main') ;
    const wcs = wrap ? getComputedStyle(wrap) : null; const wr = wrap ? wrap.getBoundingClientRect() : null;
    out.push('  img.' + String(im.className).split(/\s+/).slice(0, 3).join('.') + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' top=' + Math.round(r.top - br.top) + ' bottom=' + Math.round(r.bottom - br.bottom) + ' left=' + Math.round(r.left - br.left) +
      ' nat=' + im.naturalWidth + 'x' + im.naturalHeight + ' fit=' + cs.objectFit + ' h=' + cs.height + ' op=' + cs.opacity + ' vis=' + cs.visibility + ' src=' + (im.currentSrc || im.src).split('/').pop().slice(0, 40) +
      (wrap ? ' | wrap.' + String(wrap.className).split(/\s+/).slice(0, 2).join('.') + ' ' + Math.round(wr.width) + 'x' + Math.round(wr.height) + ' op=' + wcs.opacity + ' ov=' + wcs.overflow + ' pos=' + wcs.position + ' top=' + Math.round(wr.top - br.top) : ''));
  });
  return out.join('\n');
}, idx);
for (let i = 0; i < 4; i++) {
  console.log('\n=== card ' + (i + 1) + ' BEFORE hover');
  console.log(await measure(i));
  const card = (await p.$$('.af-wl-related li.product'))[i]; if (!card) break;
  const r = await card.boundingBox(); await p.mouse.move(r.x + r.width / 2, r.y + 120); await sleep(1200);
  console.log('=== card ' + (i + 1) + ' DURING hover');
  console.log(await measure(i));
  await p.mouse.move(5, 5); await sleep(900);
}
// the rules that give the hover picture its size, if the theme's
const rules = await p.evaluate(() => {
  const im = document.querySelector('.af-wl-related li.product .second-image img, .af-wl-related li.product .af-hover-img'); if (!im) return 'no hover img';
  const out = [];
  for (const ss of document.styleSheets) { let rs; try { rs = ss.cssRules; } catch { continue; }
    const scan = (list, media) => { for (const r of list) { if (r.cssRules && !r.selectorText) { scan(r.cssRules, r.conditionText || media); continue; }
      if (!r.selectorText || !r.style) continue; let m = false; try { m = im.matches(r.selectorText.replace(/::?(before|after|hover)/g, '')); } catch {}
      if (m && (r.style.height || r.style.width || r.style.objectFit || r.style.position || r.style.top || r.style.transform)) out.push((ss.href || 'inline#' + (ss.ownerNode && ss.ownerNode.id)).split('/').pop().slice(0, 40) + ' [' + (media || '') + '] ' + r.selectorText.slice(0, 110) + ' { h=' + r.style.height + ' w=' + r.style.width + ' fit=' + r.style.objectFit + ' pos=' + r.style.position + ' top=' + r.style.top + ' tf=' + r.style.transform + ' }'); } };
    try { scan(rs, ''); } catch {} }
  return out.join('\n');
});
console.log('\n=== rules sizing the hover img\n' + rules);
await b.close();
