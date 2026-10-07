// GUARDS: the "tap to add it to your Photos" offer does not outlive its toast.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-toastshare.mjs
//
// One element shows every message. If the offer left it tappable, the toast
// would sit across the bottom of the wall swallowing drags, wired to whichever
// picture was current when it was armed. Expected: pointer-events back to
// none, and a later message cancels the offer.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.TOW_PORT || 9211);
const ORIGIN = `http://127.0.0.1:${PORT}`;
const Y4M = path.resolve(HERE, 'wall-lock.y4m');
const MIME = { '.html':'text/html; charset=utf-8','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.js':'text/javascript','.css':'text/css','.json':'application/json','.svg':'image/svg+xml' };
const ajaxHits = [];
const server = http.createServer((req,res)=>{
  const url = new URL(req.url, ORIGIN);
  if (req.method==='POST' && url.pathname==='/admin-ajax.php'){
    let b=''; req.on('data',c=>{b+=c;}); req.on('end',()=>{ const p=new URLSearchParams(b); ajaxHits.push({action:p.get('action'),source:p.get('source')});
      res.writeHead(200,{'Content-Type':'application/json'}); res.end(JSON.stringify({success:true,data:{message:'Saved to your account.',url:`${ORIGIN}/saved.png`,account:`${ORIGIN}/my-account/saved-previews/`}})); });
    return; }
  if (url.pathname==='/favicon.ico'){ res.writeHead(204); res.end(); return; }
  let file = path.join(WWW, decodeURIComponent(url.pathname==='/'?'/index.html':url.pathname));
  if(!file.startsWith(WWW)){ res.writeHead(403); res.end(); return; }
  if(!fs.existsSync(file)||fs.statSync(file).isDirectory()){
    if(url.pathname.startsWith('/uploads/mockups/')) file=path.join(WWW,'uploads/mockups/room.jpg');
    else { res.writeHead(404); res.end('nf'); return; } }
  res.writeHead(200,{'Content-Type':MIME[path.extname(file).toLowerCase()]||'application/octet-stream'});
  fs.createReadStream(file).pipe(res);
});
const sleep=(m)=>new Promise(r=>setTimeout(r,m));
const T0=Date.now(); const log=(m)=>console.log(`[${String(Date.now()-T0).padStart(5,' ')}ms] ${m}`);
await new Promise(r=>server.listen(PORT,'127.0.0.1',r));
const browser = await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${Y4M}`]});
const context = await browser.newContext({viewport:{width:423,height:900},isMobile:true,hasTouch:true,deviceScaleFactor:2,permissions:['camera'],acceptDownloads:true});
const page = await context.newPage();
// stub the share API so offerPhotos takes its tappable branch
await context.addInitScript(()=>{
  window.__shares=[];
  navigator.canShare = function(d){ return !!(d && d.files && d.files.length); };
  navigator.share = function(d){ window.__shares.push({n:(d.files&&d.files[0]&&d.files[0].name)||'?', size:(d.files&&d.files[0]&&d.files[0].size)||0, t:Date.now()}); return Promise.resolve(); };
});
const errs=[];
page.on('console',m=>{ if(m.type()==='error'){errs.push(m.text()); log('console.error: '+m.text());} });
page.on('pageerror',e=>{errs.push('pageerror: '+e.message); log('pageerror: '+e.message);});
page.on('download',d=>{ log('download: '+d.suggestedFilename()); d.cancel().catch(()=>{}); });
await page.goto(`${ORIGIN}/index.html`,{waitUntil:'load',timeout:15000});
await page.waitForFunction(()=>!!window.AFCal && document.querySelectorAll('#tow-prod option').length>1,null,{timeout:5000});
await page.evaluate(()=>{const s=document.getElementById('tow-prod'); s.selectedIndex=1; s.dispatchEvent(new Event('change',{bubbles:true}));});
await sleep(1500);
await page.click('#tow-cambtn');
log('camera started');
const probe = ()=>page.evaluate(()=>{
  const t=document.getElementById('tow-toast'), st=document.getElementById('tow-stage'), fb=document.getElementById('tow-framebox');
  const cs=getComputedStyle(t), tr=t.getBoundingClientRect(), sr=st.getBoundingClientRect(), fr=fb.getBoundingClientRect();
  return { frozen:window.AFCal.frozen, autoSaved:window.AFCal.autoSaved, text:t.textContent,
    pe:cs.pointerEvents, op:cs.opacity, shown:t.classList.contains('show'),
    toastRect:[Math.round(tr.x),Math.round(tr.y),Math.round(tr.width),Math.round(tr.height)],
    stageRect:[Math.round(sr.x),Math.round(sr.y),Math.round(sr.width),Math.round(sr.height)],
    fbRect:[Math.round(fr.x),Math.round(fr.y),Math.round(fr.width),Math.round(fr.height)],
    atToastCentre:(document.elementFromPoint(tr.x+tr.width/2, tr.y+tr.height/2)||{}).id||'?',
    shares: window.__shares.length };
});
// wait for lock + autoSave
let lockT=null;
for(let i=0;i<120;i++){ const p=await probe(); if(p.autoSaved){ lockT=Date.now(); log(`autoSaved at +${lockT-T0}ms toast="${p.text}" pe=${p.pe}`); break;} await sleep(150); }
if(!lockT){ log('NEVER auto-saved'); }
// sample pointer-events over time
const samples=[];
let clickedSave=false, saveAt=null, afterSaveProbe=null, shareAfterSave=null;
for(let i=0;i<70;i++){
  const p=await probe();
  samples.push({dt:Date.now()-lockT, pe:p.pe, shown:p.shown, text:p.text.slice(0,42), at:p.atToastCentre});
  if(!clickedSave && !process.env.NO_SAVE && Date.now()-lockT>2000){
    clickedSave=true; saveAt=Date.now()-lockT;
    await page.evaluate(()=>document.getElementById('tow-save').click());
    await sleep(120);
    afterSaveProbe=await probe();
    log(`clicked #tow-save at +${saveAt}ms after lock -> toast="${afterSaveProbe.text}" pe=${afterSaveProbe.pe} shares=${afterSaveProbe.shares}`);
    // now click the toast itself and see whether a stale share fires
    await page.evaluate(()=>{ const t=document.getElementById('tow-toast'); t.dispatchEvent(new MouseEvent('click',{bubbles:true})); });
    await sleep(200);
    shareAfterSave=await page.evaluate(()=>window.__shares.length);
    log(`clicked the toast after the save-toast -> window.__shares=${shareAfterSave}`);
  }
  await sleep(200);
}
// how long was pe=auto?
const autoSamples=samples.filter(s=>s.pe==='auto');
log(`pointer-events:auto seen from dt=${autoSamples.length?autoSamples[0].dt:'n/a'}ms to dt=${autoSamples.length?autoSamples[autoSamples.length-1].dt:'n/a'}ms (${autoSamples.length} of ${samples.length} samples)`);
console.log('--- samples ---');
samples.forEach(s=>console.log(`dt=${String(s.dt).padStart(5)} pe=${s.pe.padEnd(5)} shown=${s.shown} at=${s.at} "${s.text}"`));
console.log(`ajax: ${JSON.stringify(ajaxHits)}`);
console.log(`console errors: ${errs.length}`);
await context.close().catch(()=>{}); await browser.close().catch(()=>{}); server.close();
process.exit(0);
