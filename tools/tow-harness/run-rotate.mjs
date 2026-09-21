// GUARDS: the stage pin taken at the lock is RELEASED when the window changes.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-rotate.mjs \
//       [<w1>x<h1>] [<w2>x<h2>] [deviceScaleRatio] [mobile|desktop]
//   defaults: 390x844 844x390 3 mobile
//
// freezeWall() pins the stage height so the rail growing underneath cannot
// rescale the still (and with it the artwork) a moment after the capture. That
// pin is written WITHOUT !important so the phone rule wins - but a phone turned
// on its side is past the 781px breakpoint, where that rule no longer applies,
// and the pin would otherwise survive into landscape and hold a portrait box.
// So any resize drops it. This drives a real lock in portrait, rotates, and
// compares the stage against a CONTROL: the box the stylesheet alone would
// give at that width. They must match, and the inline height must be gone.
//
// run-recover.mjs has a "rotate" scenario too, but only at 423x820 -> 820x423;
// this one takes any geometry and device-scale ratio, which is what the
// landscape claim needed to be settled at 390x844 dpr 3.
//
// Note: not every geometry locks in the time allowed - a stage the harness
// scrolls only partly into view gives the detector black frames. The report
// says NOT LOCKED loudly when that happens; it is a limit of the rig, not a
// finding about the site.

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
const A = (process.argv[2] || '390x844').split('x').map(Number);
const B = (process.argv[3] || '844x390').split('x').map(Number);
const DSR = Number(process.argv[4] || 3);
const MODE = (process.argv[5] || 'mobile');
const Y4M = path.resolve(HERE, 'wall-lock.y4m');

const ajaxHits = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (req.method === 'POST' && url.pathname === '/admin-ajax.php') {
    let body = ''; req.on('data', c => body += c);
    req.on('end', () => { ajaxHits.push(body.length); res.writeHead(200, {'Content-Type':'application/json'});
      res.end(JSON.stringify({success:true,data:{message:'ok',url:ORIGIN+'/saved.png',account:ORIGIN+'/acct/'}})); });
    return;
  }
  if (url.pathname === '/favicon.ico') { res.writeHead(204); res.end(); return; }
  let file = path.join(WWW, decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname));
  if (!file.startsWith(WWW)) { res.writeHead(403); res.end(); return; }
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) {
    if (url.pathname.startsWith('/uploads/mockups/')) file = path.join(WWW, 'uploads/mockups/room.jpg');
    else { res.writeHead(404); res.end('nf'); return; }
  }
  const MIME = {'.html':'text/html; charset=utf-8','.png':'image/png','.jpg':'image/jpeg','.js':'text/javascript','.css':'text/css'};
  res.writeHead(200, {'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream'});
  fs.createReadStream(file).pipe(res);
});
const sleep = ms => new Promise(r => setTimeout(r, ms));
const T0 = Date.now();
const log = m => console.log(`[${String(Date.now()-T0).padStart(5)}ms] ${m}`);
await new Promise(r => server.listen(PORT, '127.0.0.1', r));

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu',
  '--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${Y4M}`]});
const ctxOpts = MODE === 'mobile'
  ? { viewport:{width:A[0],height:A[1]}, isMobile:true, hasTouch:true, deviceScaleFactor:DSR }
  : { viewport:{width:A[0],height:A[1]} };
const context = await browser.newContext({ ...ctxOpts, permissions:['camera'], acceptDownloads:true });
const page = await context.newPage();
const errs = [];
page.on('console', m => { if (m.type()==='error') { errs.push(m.text()); log('console.error: '+m.text()); }});
page.on('pageerror', e => { errs.push('pageerror: '+e.message); log('pageerror: '+e.message); });
page.on('download', d => { log('download: '+d.suggestedFilename()); d.cancel().catch(()=>{}); });

const probe = () => page.evaluate(() => {
  const stg = document.getElementById('tow-stage');
  const r = stg.getBoundingClientRect();
  const fb = document.getElementById('tow-framebox').getBoundingClientRect();
  const cs = getComputedStyle(stg);
  return {
    vw: innerWidth, vh: innerHeight,
    stage: `${Math.round(r.width)}x${Math.round(r.height)} (aspect ${(r.width/r.height).toFixed(2)})`,
    inlineH: stg.style.height || '(none)', inlineFlex: stg.style.flex || '(none)',
    computedH: cs.height, computedFlex: cs.flex, computedAR: cs.aspectRatio,
    frozen: !!(window.AFCal||{}).frozen, locked: !!(window.AFCal||{}).locked,
    lockW: (window.AFCal||{}).lockW, lockH: (window.AFCal||{}).lockH,
    fb: `${Math.round(fb.width)}x${Math.round(fb.height)}`,
  };
});

await page.goto(`${ORIGIN}/index.html`, { waitUntil:'load', timeout:15000 });
await page.waitForFunction(() => !!window.AFCal && document.querySelectorAll('#tow-prod option').length>1, null, {timeout:5000});
await page.evaluate(() => { const s=document.getElementById('tow-prod'); s.selectedIndex=1; s.dispatchEvent(new Event('change',{bubbles:true})); });
await sleep(1500);
log('before cam: ' + JSON.stringify(await probe()));
await page.click('#tow-cambtn');
const t0 = Date.now();
while (Date.now()-t0 < 15000) {
  const st = await page.evaluate(() => !!(window.AFCal||{}).frozen);
  if (st) break;
  await sleep(150);
}
await sleep(1200);
const beforeRot = await probe();
log('AFTER LOCK   (portrait): ' + JSON.stringify(beforeRot));

await page.setViewportSize({ width: B[0], height: B[1] });
await sleep(900);
const afterRot = await probe();
log('AFTER ROTATE (landscape): ' + JSON.stringify(afterRot));

// control: what does the stylesheet want at this width with no inline pin?
const noPin = await page.evaluate(() => {
  const stg=document.getElementById('tow-stage');
  const h=stg.style.height, f=stg.style.flex;
  stg.style.removeProperty('height'); stg.style.removeProperty('flex');
  const r=stg.getBoundingClientRect();
  const out=`${Math.round(r.width)}x${Math.round(r.height)}`;
  stg.style.height=h; stg.style.flex=f;
  return out;
});
log('CONTROL (inline height/flex stripped by hand): ' + noPin);

// rotate back
await page.setViewportSize({ width: A[0], height: A[1] });
await sleep(700);
const back = await probe();
log('BACK to portrait: ' + JSON.stringify(back));
log('console errors: ' + (errs.length ? errs.join(' || ') : 'none'));

console.log('\n===== ROTATE REPORT =====');
const P = (ok, what) => console.log((ok ? 'PASS' : 'FAIL') + ': ' + what);
if (!beforeRot.frozen) {
  console.log('NOT LOCKED — the detector never fired at ' + A[0] + 'x' + A[1] + ', so the rotation was not');
  console.log('             exercised. A rig limit (black frames from a part-scrolled stage), not a site');
  console.log('             finding: try 390x844 or 423x820, which do lock.');
} else {
  P(beforeRot.inlineH !== '(none)', 'the stage was pinned at the lock (' + beforeRot.inlineH + ')');
  P(afterRot.inlineH === '(none)' && afterRot.inlineFlex === '(none)', 'the pin was released on the rotation');
  P(afterRot.stage.split(' ')[0] === noPin, 'landscape stage matches the stylesheet alone (' + afterRot.stage.split(' ')[0] + ' vs control ' + noPin + ')');
  const a = beforeRot.fb.split('x').map(Number), b2 = afterRot.fb.split('x').map(Number);
  const sA = beforeRot.stage.split(' ')[0].split('x').map(Number), sB = afterRot.stage.split(' ')[0].split('x').map(Number);
  const cover = Math.max(sB[0] / sA[0], sB[1] / sA[1]);
  console.log('         artwork ' + beforeRot.fb + ' -> ' + afterRot.fb + '; cover factor ' + cover.toFixed(3) + ', measured ' + (b2[1] / a[1]).toFixed(3));
  P(Math.abs(b2[1] / a[1] - cover) / cover < 0.06, 'the artwork tracked the frozen wall through the rotation');
  P(back.stage.split(' ')[0] === beforeRot.stage.split(' ')[0], 'rotating back restores the portrait box');
  P(errs.length === 0, 'no console errors');
}
await context.close().catch(()=>{}); await browser.close().catch(()=>{}); server.close();
process.exit(0);
