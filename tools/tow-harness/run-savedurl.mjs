// GUARDS: an automatic save must never arm the WhatsApp / Email / Copy buttons.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-savedurl.mjs
//
// savedURL is what those buttons hand out. The visitor asked for a wall
// preview, not to publish a photograph of their room, so sharing stays on the
// product link until they press "Save to my account" themselves. Expected:
// "NO LEAK: share still points at the product/page link".
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
const AJAX_DELAY = Number(process.env.TOW_AJAX_DELAY || 4000);
const ajaxHits = [];
const server = http.createServer((req,res)=>{
  const url = new URL(req.url, ORIGIN);
  if (req.method==='POST' && url.pathname==='/admin-ajax.php'){
    let body=''; req.on('data',c=>{body+=c;});
    req.on('end',()=>{
      const p=new URLSearchParams(body);
      ajaxHits.push({t:Date.now()-T0, source:p.get('source'), len:(p.get('image')||'').length});
      console.log(`[${Date.now()-T0}ms] admin-ajax POST received (source=${p.get('source')}), holding response ${AJAX_DELAY}ms`);
      setTimeout(()=>{
        res.writeHead(200,{'Content-Type':'application/json'});
        res.end(JSON.stringify({success:true,data:{message:'Saved to your account.',url:`${ORIGIN}/saved.png`,account:`${ORIGIN}/my-account/saved-previews/`}}));
        console.log(`[${Date.now()-T0}ms] admin-ajax response released`);
      }, AJAX_DELAY);
    });
    return;
  }
  if (url.pathname==='/favicon.ico'){res.writeHead(204);res.end();return;}
  let file=path.join(WWW, decodeURIComponent(url.pathname==='/'?'/index.html':url.pathname));
  if(!file.startsWith(WWW)){res.writeHead(403);res.end();return;}
  if(!fs.existsSync(file)||fs.statSync(file).isDirectory()){
    if(url.pathname.startsWith('/uploads/mockups/')) file=path.join(WWW,'uploads/mockups/room.jpg');
    else {res.writeHead(404);res.end('nf');return;}
  }
  res.writeHead(200,{'Content-Type':MIME[path.extname(file).toLowerCase()]||'application/octet-stream'});
  fs.createReadStream(file).pipe(res);
});
const sleep=(m)=>new Promise(r=>setTimeout(r,m));
const T0=Date.now(); const log=(m)=>console.log(`[${Date.now()-T0}ms] ${m}`);
await new Promise(r=>server.listen(PORT,'127.0.0.1',r));
const browser=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${Y4M}`]});
const context=await browser.newContext({viewport:{width:423,height:820},isMobile:true,hasTouch:true,deviceScaleFactor:2,permissions:['camera'],acceptDownloads:true});
const page=await context.newPage();
page.on('console',m=>{if(m.type()==='error')log('console.error: '+m.text());});
page.on('pageerror',e=>log('pageerror: '+e.message));
page.on('download',d=>{log('download: '+d.suggestedFilename());d.cancel().catch(()=>{});});
await page.goto(`${ORIGIN}/index.html`,{waitUntil:'load',timeout:15000});
await page.waitForFunction(()=>!!window.AFCal&&document.querySelectorAll('#tow-prod option').length>1,null,{timeout:5000});
await page.evaluate(()=>{const s=document.getElementById('tow-prod');s.selectedIndex=1;s.dispatchEvent(new Event('change',{bubbles:true}));});
await sleep(1500);
// capture what the share buttons hand out
await page.evaluate(()=>{ window.__shared=[]; const real=AFPreview.copy;
  AFPreview.copy=function(t,cb){ window.__shared.push(t); if(cb)cb(true); };
  AFPreview.native=function(){ return false; };
  window.open=function(u){ window.__shared.push('open:'+u); return null; };
});
await page.click('#tow-cambtn'); log('camera started');
await page.waitForFunction(()=>window.AFCal&&window.AFCal.frozen===true,null,{timeout:20000});
log('LOCK/FREEZE detected; wall='+(await page.evaluate(()=>document.getElementById('tow-wallimg').getAttribute('src').slice(0,20))));
// wait until the auto-save POST has actually gone out (it is being held)
const t1=Date.now(); while(ajaxHits.length===0 && Date.now()-t1<10000) await sleep(100);
log('ajax in flight: '+(ajaxHits.length?'yes':'NO — auto save never fired'));
await sleep(200);
// THE SCENARIO: visitor taps a room scene while the POST is in flight
await page.evaluate(()=>{ document.querySelectorAll('.af-tow-scene')[1].click(); });
log('tapped room scene 2; wall now='+(await page.evaluate(()=>document.getElementById('tow-wallimg').getAttribute('src').slice(0,60))));
await page.evaluate(()=>{ document.getElementById('tow-share-copy').click(); });
log('share-copy BEFORE callback -> '+JSON.stringify(await page.evaluate(()=>window.__shared.slice())));
// let the held response land
await sleep(AJAX_DELAY+1500);
await page.evaluate(()=>{ window.__shared=[]; document.getElementById('tow-share-copy').click(); });
const after=await page.evaluate(()=>({shared:window.__shared.slice(), toast:(document.getElementById('tow-toast')||{}).textContent||'', wall:document.getElementById('tow-wallimg').getAttribute('src').slice(0,60), acct:(function(){const l=document.getElementById('tow-acctlink');return l?getComputedStyle(l).display+' '+l.getAttribute('href'):'missing';})()}));
log('share-copy AFTER callback -> '+JSON.stringify(after,null,1));
console.log('\n===== VERDICT =====');
const leaked = after.shared.some(u=>String(u).includes('/saved.png'));
console.log(`shared target after late callback: ${JSON.stringify(after.shared)}`);
console.log(leaked ? 'LEAK: savedURL was re-armed by the late auto-save callback' : 'NO LEAK: share still points at the product/page link');
await context.close().catch(()=>{}); await browser.close().catch(()=>{}); server.close();
process.exit(0);
