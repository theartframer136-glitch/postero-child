/**
 * Wishlist "You may also like": a grey empty box sits under the photo in each
 * card. List every element inside the first card's image area - box, colour,
 * what image it holds - so the empty one can be named.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const URL_ = 'https://theartframer.us/wishlist/4TIY7Q/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
for (const [w,h] of [[546,642],[1280,900]]) {
const p = await b.newPage(); await p.setViewport({ width: w, height: h });
let ok=false; for (let i=1;i<=3&&!ok;i++){ try{ await p.goto(URL_,{waitUntil:'domcontentloaded',timeout:90000}); ok=true; }catch{ await new Promise(r=>setTimeout(r,4000)); } }
if(!ok){ console.log('could not load'); continue; }
await new Promise(r=>setTimeout(r,8000));
for (let i=0;i<6;i++){ let bl=false; try{ bl=await p.evaluate(()=>document.body.innerText.includes('Checking your browser')); }catch{} if(!bl)break; await new Promise(r=>setTimeout(r,3000)); try{ await p.reload({waitUntil:'domcontentloaded',timeout:60000}); }catch{} await new Promise(r=>setTimeout(r,6000)); }
await p.evaluate(()=>{ const s=document.querySelector('.af-wl-related'); if(s) s.scrollIntoView(); });
await new Promise(r=>setTimeout(r,3000));
console.log('\n===== viewport ' + w + ' =====');
console.log(await p.evaluate(() => {
  const sec = document.querySelector('.af-wl-related'); if (!sec) return 'no .af-wl-related';
  const card = sec.querySelector('li.product'); if (!card) return 'no card';
  const cr = card.getBoundingClientRect();
  const out = ['card ' + Math.round(cr.width) + 'x' + Math.round(cr.height) + ' cls=' + card.className.slice(0,80)];
  const walk = (el, d) => {
    for (const c of el.children) {
      const r = c.getBoundingClientRect(), cs = getComputedStyle(c);
      if (r.top - cr.top > 520) continue;
      out.push('  '.repeat(d) + c.tagName.toLowerCase() + '.' + String(c.className).split(/\s+/).slice(0,4).join('.') +
        ' @' + Math.round(r.top - cr.top) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) +
        ' d=' + cs.display + ' pos=' + cs.position + ' bg=' + cs.backgroundColor + ' op=' + cs.opacity +
        (c.tagName === 'IMG' ? ' src=' + (c.currentSrc||c.getAttribute('src')||'').split('/').pop().slice(0,45) + ' nat=' + c.naturalWidth + 'x' + c.naturalHeight + ' h=' + cs.height : ''));
      if (d < 5) walk(c, d + 1);
    }
  };
  walk(card, 1);
  return out.join('\n');
}));
await p.close();
}
await b.close();
