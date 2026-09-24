/**
 * Live product page: with "Without Frame" chosen, can a frame colour still be
 * picked? Reports what the page really serves and does - not what the source
 * says it should.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const URL_ = 'https://theartframer.us/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
const errs=[]; p.on('pageerror', e => errs.push(e.message.slice(0,160)));
let ok=false; for (let i=1;i<=3&&!ok;i++){ try{ await p.goto(URL_,{waitUntil:'domcontentloaded',timeout:90000}); ok=true; }catch{ await new Promise(r=>setTimeout(r,4000)); } }
if(!ok){ console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r=>setTimeout(r,8000));
for (let i=0;i<6;i++){ let bl=false; try{ bl=await p.evaluate(()=>document.body.innerText.includes('Checking your browser')); }catch{} if(!bl)break; await new Promise(r=>setTimeout(r,3000)); try{ await p.reload({waitUntil:'domcontentloaded',timeout:60000}); }catch{} await new Promise(r=>setTimeout(r,6000)); }
await p.evaluate(()=>{ const a=document.getElementById('af-ck-accept'); if(a)a.click(); ['#afOverlay','#af-consent'].forEach(s=>document.querySelectorAll(s).forEach(e=>e.remove())); });
const st = () => p.evaluate(() => {
  const opts = [...document.querySelectorAll('.af-opts')];
  const w = opts.find(o => o.getBoundingClientRect().width > 0) || opts[0];
  if (!w) return { err: 'no .af-opts on page' };
  const sws = [...w.querySelectorAll('.af-swatch, [data-type="color"]')];
  const fr = w.querySelector('[data-type="frame"].active');
  return {
    optsCount: opts.length,
    served: { needsNote: !!w.querySelector('.af-color-needs'), fnNew: document.documentElement.outerHTML.includes('choose a frame first') },
    wrapCls: w.className,
    frame: fr ? fr.dataset.val : '(none active)',
    frameInput: (document.querySelector('input[name="af_frame"]')||{}).value,
    colorInput: (document.querySelector('input[name="af_color"]')||{}).value,
    swatches: sws.map(x => x.tagName.toLowerCase() + '.' + x.className.replace(/\s+/g,'.') + (x.disabled?'[disabled]':'') + ' op=' + getComputedStyle(x).opacity + ' bg=' + getComputedStyle(x).backgroundColor),
    other: [...document.querySelectorAll('.af-swatch2, .variations select, [name*="color" i]')].slice(0,6).map(x => x.tagName.toLowerCase()+'.'+String(x.className).slice(0,40)+' name='+(x.name||'')),
  };
});
const s1 = await st(); console.log('LOADED: ' + JSON.stringify(s1, null, 1));
// click Without Frame explicitly, then try Silver
try {
  await p.evaluate(() => { const w=[...document.querySelectorAll('.af-opts')].find(o=>o.getBoundingClientRect().width>0); w.querySelector('[data-type="frame"][data-val="Without Frame"]').scrollIntoView({block:'center'}); });
  await p.click('.af-opts [data-type="frame"][data-val="Without Frame"]');
  await new Promise(r=>setTimeout(r,600));
  await p.click('.af-opts [data-type="color"][data-val="Silver"]').catch(e=>console.log('silver click: '+e.message.slice(0,80)));
  await new Promise(r=>setTimeout(r,600));
} catch(e) { console.log('click err: ' + e.message.slice(0,120)); }
const s2 = await st(); console.log('AFTER Without Frame + Silver: ' + JSON.stringify(s2, null, 1));
console.log('page errors: ' + (errs.join(' | ') || 'none'));
await b.close();
