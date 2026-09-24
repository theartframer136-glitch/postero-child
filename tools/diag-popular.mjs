/**
 * Product page: the product rows near the bottom. Some cards show a grey
 * "Postero" box instead of the artwork, and the rest look soft. For every
 * card: which section, the product, the img src it asked for, what size that
 * file really is, how big it is drawn, and why a placeholder is showing.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const URL_ = 'https://theartframer.us/product/radha-krishna-lotus-embrace-canvas-wall-art-2-5x3-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1.5 });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) { try { await p.goto(URL_, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; } catch { await new Promise(r => setTimeout(r, 4000)); } }
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 8000));
for (let i = 0; i < 6; i++) { let bl=false; try { bl = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {} if (!bl) break; await new Promise(r=>setTimeout(r,3000)); try { await p.reload({waitUntil:'domcontentloaded',timeout:60000}); } catch {} await new Promise(r=>setTimeout(r,6000)); }
// scroll the whole page so lazy images get their chance
for (let y = 0; y < 30; y++) { await p.evaluate(() => window.scrollBy(0, 700)); await new Promise(r => setTimeout(r, 350)); }
await new Promise(r => setTimeout(r, 2500));
const out = await p.evaluate(() => {
  const res = [];
  const heads = [...document.querySelectorAll('h2,h3')].filter(h => /popular|related|you may|also like|recently/i.test(h.textContent));
  heads.forEach(h => {
    const sec = h.closest('section, .related, .upsells, .elementor-widget, .af-pp-sec, div') || h.parentElement;
    const cards = [...sec.querySelectorAll('li.product, .product-card, .af-mini-card, .product')].slice(0, 12);
    res.push('### "' + h.textContent.trim() + '"  section=' + sec.tagName.toLowerCase() + '.' + String(sec.className).split(/\s+/).slice(0,3).join('.') + '  cards=' + cards.length);
    cards.forEach(c => {
      const img = c.querySelector('img');
      const name = (c.querySelector('h2,h3,.woocommerce-loop-product__title,.af-mini-title,.product-title') || {}).textContent || '';
      if (!img) { res.push('   [no img] ' + name.trim().slice(0, 40) + ' bg=' + getComputedStyle(c.querySelector('a,div')||c).backgroundImage.slice(0,60)); return; }
      const r = img.getBoundingClientRect();
      res.push('   ' + name.trim().slice(0, 40).padEnd(40) +
        ' | drawn ' + Math.round(r.width) + 'x' + Math.round(r.height) +
        ' | file ' + img.naturalWidth + 'x' + img.naturalHeight + (img.complete ? '' : ' (incomplete)') +
        ' | cur=' + (img.currentSrc || '').split('/').pop().slice(0, 60) +
        ' | src=' + (img.getAttribute('src') || '').split('/').pop().slice(0, 50) +
        ' | data-src=' + ((img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || '').split('/').pop().slice(0, 50) || '-') +
        ' | srcset=' + (img.getAttribute('srcset') ? 'yes' : 'no') + ' sizes=' + (img.getAttribute('sizes') || '-') +
        ' | cls=' + img.className.slice(0, 40));
    });
  });
  return res.join('\n');
});
console.log(out);
await b.close();
