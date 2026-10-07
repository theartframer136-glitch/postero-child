// Do the WALL HEIGHT buttons still measure correctly once the wall is frozen?
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-wallft.mjs [mobile|desktop] [resize]
//
// Locks a wall, then presses 8 / 9 / 10 ft and checks the artwork height
// against the only right answer: a 3 ft print on an 8 ft wall must be 10/8 as
// tall as the same print on a 10 ft one. Pass "resize" to rotate the viewport
// first - that is the case where reading the CURRENT stage height instead of
// the height the still was captured at applies the object-fit:cover factor
// twice and draws the piece far oversize while still claiming true scale.
// Does pressing a wall-height button AFTER the lock resize the artwork by the
// right amount? 10ft -> 8ft must make a 3ft print 10/8 = 1.25x taller, no more.
import http from 'node:http'; import fs from 'node:fs'; import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const HERE='/home/user/postero-child/tools/tow-harness', WWW=HERE+'/www';
const PORT=Number(process.env.TOW_PORT||9930), ORIGIN=`http://127.0.0.1:${PORT}`;
const MIME={'.html':'text/html; charset=utf-8','.png':'image/png','.jpg':'image/jpeg'};
const server=http.createServer((req,res)=>{const u=new URL(req.url,ORIGIN);
  if(req.method==='POST'&&u.pathname==='/admin-ajax.php'){let b='';req.on('data',c=>b+=c);req.on('end',()=>{res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify({success:true,data:{message:'ok',url:ORIGIN+'/saved.png',account:ORIGIN+'/a/'}}));});return;}
  let f=path.join(WWW,decodeURIComponent(u.pathname==='/'?'/index.html':u.pathname));
  if(!fs.existsSync(f)||fs.statSync(f).isDirectory()){ if(u.pathname.startsWith('/uploads/mockups/')) f=path.join(WWW,'uploads/mockups/room.jpg'); else {res.writeHead(404);res.end();return;} }
  res.writeHead(200,{'Content-Type':MIME[path.extname(f).toLowerCase()]||'application/octet-stream'});fs.createReadStream(f).pipe(res);});
await new Promise(r=>server.listen(PORT,'127.0.0.1',r));
const sleep=m=>new Promise(r=>setTimeout(r,m));
const b=await chromium.launch({headless:true,args:['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${HERE}/wall-lock.y4m`]});
const device=process.argv[2]||'mobile';
const ctx=await b.newContext({...(device==='mobile'?{viewport:{width:423,height:820},isMobile:true,hasTouch:true,deviceScaleFactor:2}:{viewport:{width:1280,height:900}}),permissions:['camera'],acceptDownloads:true});
const page=await ctx.newPage();
page.on('download',d=>d.cancel().catch(()=>{}));
await page.goto(ORIGIN+'/index.html',{waitUntil:'load'});
await page.evaluate(()=>{const s=document.getElementById('tow-prod');s.selectedIndex=1;s.dispatchEvent(new Event('change',{bubbles:true}));});
await sleep(1500);
const snap=()=>page.evaluate(()=>{const fb=document.getElementById('tow-framebox').getBoundingClientRect();const st=document.getElementById('tow-stage').getBoundingClientRect();const C=window.AFCal||{};
  return {h:Math.round(fb.height*10)/10,w:Math.round(fb.width*10)/10,stage:Math.round(st.width)+'x'+Math.round(st.height),ft:C.wallFt,px:C.pxPerFt,base:C.base,frozen:!!C.frozen,note:(document.getElementById('tow-scalenote')||{}).textContent||''};});
await page.click('#tow-cambtn');
for(let i=0;i<60;i++){await sleep(200);const s=await snap();if(s.frozen)break;}
await sleep(1800);
let at10=await snap();
console.log('device',device,'| at lock  ', JSON.stringify(at10));
if(process.argv[3]==='resize'){
  await page.setViewportSize(device==='mobile'?{width:820,height:423}:{width:900,height:800});   // rotate
  await new Promise(r=>setTimeout(r,900));
  const after=await snap();
  console.log('  after resize ->', JSON.stringify(after), ' artwork moved x'+(after.h/at10.h).toFixed(3));
  at10=after;   // the new baseline: 8ft must be 1.25x THIS
}
for(const ft of ['8','9','10']){
  await page.evaluate(f=>{document.querySelector(`#tow-wallh button[data-ft="${f}"]`).click();},ft);
  await sleep(700);
  const s=await snap();
  const expected = at10.h * (10/Number(ft));
  console.log(`  pressed ${ft} ft -> artwork ${s.h}px  (expected ${expected.toFixed(1)}px)  ratio ${(s.h/expected).toFixed(3)}  px/ft=${s.px&&s.px.toFixed(2)} stage=${s.stage}`);
}
await b.close(); server.close(); process.exit(0);
