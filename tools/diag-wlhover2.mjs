/**
 * Why the child theme's hover-image script (applyHoverFix, functions.php) does
 * not box the wishlist "You may also like" cards the way it boxes shop cards.
 * Same card on /shop/ and on the wishlist, at the owner's 1918x1078 screen:
 * is the script's marker there, how many <img> the card had and their src,
 * and what the theme's hover does (transform / opacity) to each image.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const look = async (url, sel) => {
  const p = await b.newPage(); await p.setViewport({ width: 1918, height: 1078 });
  await p.evaluateOnNewDocument(() => {
    // record what the first three cards' images looked like the moment DOMContentLoaded fired
    document.addEventListener('DOMContentLoaded', () => {
      window.__atDCL = [...document.querySelectorAll('li.product')].slice(0, 3).map(c => [...c.querySelectorAll('img')].map(i => (i.getAttribute('src') || '(no src)').slice(0, 60)));
    });
  });
  let ok = false; for (let i = 1; i <= 3 && !ok; i++) { try { await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; } catch { await sleep(4000); } }
  await sleep(8000);
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {} if (!bl) break; await sleep(3000); try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {} await sleep(6000); }
  await p.evaluate((sel) => { const s = document.querySelector(sel); if (s) s.scrollIntoView({ block: 'center' }); }, sel);
  await sleep(2500);
  console.log('\n##### ' + url + '  (' + sel + ')');
  console.log('imgs per card at DOMContentLoaded: ' + JSON.stringify(await p.evaluate(() => window.__atDCL)));
  const cards = await p.$$(sel + ' li.product');
  console.log('cards: ' + cards.length + ' | hover-script ran on: ' + await p.evaluate((sel) => [...document.querySelectorAll(sel + ' li.product')].filter(c => c.dataset.hoverFixed6).length, sel) +
    ' | with .af-img-ratio: ' + await p.evaluate((sel) => document.querySelectorAll(sel + ' li.product .af-img-ratio').length, sel) + ' | script present: ' + await p.evaluate(() => [...document.scripts].some(s => /hoverFixed6/.test(s.textContent))));
  const state = (idx) => p.evaluate((sel, idx) => {
    const card = document.querySelectorAll(sel + ' li.product')[idx];
    const box = card.querySelector('.af-img-ratio, .product-img-wrap, .product-transition'); const br = box.getBoundingClientRect();
    return [...card.querySelectorAll('img')].map(im => { const r = im.getBoundingClientRect(); const cs = getComputedStyle(im); const w = im.parentElement; const wcs = getComputedStyle(w);
      return String(im.className).split(/\s+/).filter(c => /af-|attachment/.test(c)).join('.').slice(0, 30) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' @' + Math.round(r.left - br.left) + ',' + Math.round(r.top - br.top) + ' fit=' + cs.objectFit + ' op=' + cs.opacity + ' tf=' + cs.transform + ' | parent.' + String(w.className).split(/\s+/).slice(0, 2).join('.') + ' op=' + wcs.opacity + ' tf=' + wcs.transform + ' ov=' + wcs.overflow; }).join('\n     ');
  }, sel, idx);
  console.log('  card 1 rest:  ' + await state(0));
  const r = await cards[0].boundingBox(); await p.mouse.move(r.x + r.width / 2, r.y + 120); await sleep(1500);
  console.log('  card 1 hover: ' + await state(0));
  console.log('  hover rules: ' + await p.evaluate((sel) => {
    const out = []; const im = document.querySelector(sel + ' li.product .second-image, ' + sel + ' li.product .af-hover-img'); if (!im) return 'none';
    for (const ss of document.styleSheets) { let rs; try { rs = ss.cssRules; } catch { continue; }
      const scan = (list) => { for (const r of list) { if (r.cssRules && !r.selectorText) { scan(r.cssRules); continue; } if (!r.selectorText || !r.style) continue;
        if (/hover/.test(r.selectorText) && (r.style.transform || r.style.opacity) && /second-image|product-img-wrap|product-transition|product-image/.test(r.selectorText)) out.push((ss.href || 'inline').split('/').pop().slice(0, 30) + ' ' + r.selectorText.slice(0, 120) + ' {tf=' + r.style.transform + ' op=' + r.style.opacity + '}'); } };
      try { scan(rs); } catch {} }
    return out.join(' || ');
  }, sel));
  await p.mouse.move(5, 5); await p.close();
};
await look('https://theartframer.us/shop/', 'ul.products');
await look('https://theartframer.us/wishlist/4TIY7Q/', '.af-wl-related');
await b.close();
