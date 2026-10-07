// GUARDS: the automatic account save is capped per visit (AUTO_ACCT_MAX = 3),
// and the manual "Save to my account" button is NOT capped with it.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-repeat.mjs
//   TOW_CYCLES=6 ...            (how many lock / scan-again cycles to drive)
//
// The server takes twelve saves per ten minutes and keeps sixty per person, so
// a visitor sweeping a room must not be able to spend the lot automatically.
// Expected on the current code: 6 cycles -> 3 automatic POSTs + 6 downloads,
// then one more POST from the deliberate manual press at the end.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.TOW_PORT || 9231);
const ORIGIN = `http://127.0.0.1:${PORT}`;
const Y4M = path.resolve(HERE, 'wall-lock.y4m');
const MIME = { '.html':'text/html; charset=utf-8','.png':'image/png','.jpg':'image/jpeg','.js':'text/javascript','.css':'text/css','.json':'application/json','.svg':'image/svg+xml' };
const ajaxHits = [];
const server = http.createServer((req,res)=>{
  const url=new URL(req.url,ORIGIN);
  if(req.method==='POST'&&url.pathname==='/admin-ajax.php'){
    let body=''; req.on('data',c=>{body+=c;});
    req.on('end',()=>{ const p=new URLSearchParams(body);
      ajaxHits.push({t:Date.now()-T0, action:p.get('action'), source:p.get('source'), size:p.get('size'), len:(p.get('image')||'').length});
      res.writeHead(200,{'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true,data:{message:'Saved to your account.',url:`${ORIGIN}/saved.png`,account:`${ORIGIN}/my-account/`}}));
    });
    return;
  }
  if(url.pathname==='/favicon.ico'){res.writeHead(204);res.end();return;}
  let file=path.join(WWW, decodeURIComponent(url.pathname==='/'?'/index.html':url.pathname));
  if(!file.startsWith(WWW)){res.writeHead(403);res.end();return;}
  if(!fs.existsSync(file)||fs.statSync(file).isDirectory()){
    if(url.pathname.startsWith('/uploads/mockups/')) file=path.join(WWW,'uploads/mockups/room.jpg');
    else {res.writeHead(404);res.end('nf');return;}
  }
  res.writeHead(200,{'Content-Type':MIME[path.extname(file).toLowerCase()]||'application/octet-stream'});
  fs.createReadStream(file).pipe(res);
});
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const T0=Date.now();
const log=m=>console.log(`[${String(Date.now()-T0).padStart(5)}ms] ${m}`);
await new Promise(r=>server.listen(PORT,'127.0.0.1',r));
const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${Y4M}`]});
const context=await browser.newContext({viewport:{width:423,height:820},isMobile:true,hasTouch:true,deviceScaleFactor:2,permissions:['camera'],acceptDownloads:true});
const page=await context.newPage();
const downloads=[]; const errs=[];
page.on('download',d=>{downloads.push(Date.now()-T0); log(`download: ${d.suggestedFilename()}`); d.cancel().catch(()=>{});});
page.on('console',m=>{if(m.type()==='error'){errs.push(m.text()); log('console.error '+m.text());}});
page.on('pageerror',e=>{errs.push(e.message); log('pageerror '+e.message);});
await page.goto(`${ORIGIN}/index.html`,{waitUntil:'load',timeout:15000});
await page.waitForFunction(()=>!!window.AFCal&&document.querySelectorAll('#tow-prod option').length>1,null,{timeout:5000});
await page.evaluate(()=>{const s=document.getElementById('tow-prod');s.selectedIndex=1;s.dispatchEvent(new Event('change',{bubbles:true}));});
await sleep(1500);
const sizes = await page.evaluate(()=>Array.from(document.querySelectorAll('#tow-size option')).map(o=>o.value));
log('size options: '+JSON.stringify(sizes));
const waitLock=async(limit=15000)=>{const s=Date.now();while(Date.now()-s<limit){const l=await page.evaluate(()=>!!(window.AFCal&&window.AFCal.locked));if(l)return true;await sleep(200);}return false;};
const CYCLES=Number(process.env.TOW_CYCLES||6);
for(let i=0;i<CYCLES;i++){
  if(i===0){ await page.click('#tow-cambtn'); }
  else {
    // vary the composition so the lastAutoSig floor does not mask the cap
    if(sizes.length>1){ const v=sizes[(i)%sizes.length];
      await page.evaluate(v=>{const s=document.getElementById('tow-size');s.value=v;s.dispatchEvent(new Event('change',{bubbles:true}));},v); }
    await page.evaluate(()=>document.getElementById('tow-recal').click());    // frozen -> startCam -> unfreeze -> calStart
  }
  const ok=await waitLock();
  const st=await page.evaluate(()=>({frozen:window.AFCal.frozen,locked:window.AFCal.locked,autoSaved:window.AFCal.autoSaved,toast:(document.getElementById('tow-toast')||{}).textContent||''}));
  log(`cycle ${i+1}: lock=${ok} ${JSON.stringify(st)} posts=${ajaxHits.length} downloads=${downloads.length}`);
  await sleep(2500);
  log(`cycle ${i+1} after settle: posts=${ajaxHits.length} downloads=${downloads.length} toast="${(await page.evaluate(()=>(document.getElementById('tow-toast')||{}).textContent||''))}"`);
}
// now a deliberate manual save
await page.evaluate(()=>document.getElementById('tow-saveacct').click());
await sleep(1500);
log(`after manual Save to my account: posts=${ajaxHits.length}`);
await context.close().catch(()=>{}); await browser.close().catch(()=>{}); server.close();
console.log('===== REPORT =====');
console.log(`cycles=${CYCLES} POSTs=${ajaxHits.length} downloads=${downloads.length} consoleErrors=${errs.length}`);
console.log(ajaxHits.map(h=>`@${h.t}ms action=${h.action} source=${h.source} size=${h.size} len=${h.len}`).join('\n'));
process.exit(0);
