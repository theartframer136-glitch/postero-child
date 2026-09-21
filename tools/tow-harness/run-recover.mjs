// What happens AFTER the wall is locked and the picture is frozen?
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-recover.mjs <scenario> [mobile|desktop]
//
// scenarios:
//   scan-again  tap the recalibrate pill        -> camera back, still dropped, no stale lock
//   scene       pick a room scene               -> room photo back, frozen cleared
//   camtoggle   press the camera button         -> camera back on
//   upload      upload a wall photo             -> uploaded wall, frozen cleared
//   resize      shrink the window while frozen  -> the artwork must track the still, not the phone
//   rotate      turn the phone to landscape     -> the stage pin is released, the artwork still tracks
//   twice       lock, scan again, lock again    -> a second save, not a stuck autoSaved flag
//
// Everything the lock run proves is already covered by run.mjs; this covers the
// ways OUT of the frozen state, which is where a freeze feature usually rots.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.TOW_PORT || 0) || (9400 + (process.pid % 400));
const ORIGIN = `http://127.0.0.1:${PORT}`;

const SCENARIOS = ['scan-again', 'scene', 'camtoggle', 'upload', 'resize', 'rotate', 'twice'];
const scenario = (process.argv[2] || 'scan-again').toLowerCase();
const device = (process.argv[3] || 'mobile').toLowerCase();
if (!SCENARIOS.includes(scenario)) { console.log(`usage: run-recover.mjs <${SCENARIOS.join('|')}> [mobile|desktop]`); process.exit(0); }
const Y4M = path.resolve(HERE, 'wall-lock.y4m');
if (!fs.existsSync(Y4M) || !fs.existsSync(path.join(WWW, 'index.html'))) {
  console.log('run: php tools/tow-harness/make-walls.php && php tools/tow-harness/render.php'); process.exit(0);
}

const MIME = { '.html': 'text/html; charset=utf-8', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.svg': 'image/svg+xml' };
const ajaxHits = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (req.method === 'POST' && url.pathname === '/admin-ajax.php') {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      const p = new URLSearchParams(body);
      ajaxHits.push({ t: Date.now(), action: p.get('action'), product: p.get('product'), imageLength: (p.get('image') || '').length });
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: true, data: { message: 'Saved to your account.', url: `${ORIGIN}/saved.png`, account: `${ORIGIN}/my-account/saved-previews/` } }));
    });
    return;
  }
  if (url.pathname === '/favicon.ico') { res.writeHead(204); res.end(); return; }
  let file = path.join(WWW, decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname));
  if (!file.startsWith(WWW)) { res.writeHead(403); res.end(); return; }
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) {
    if (url.pathname.startsWith('/uploads/mockups/')) file = path.join(WWW, 'uploads/mockups/room.jpg');
    else { res.writeHead(404); res.end('nf'); return; }
  }
  res.writeHead(200, { 'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const T0 = Date.now();
const log = (m) => console.log(`[${String(Date.now() - T0).padStart(5, ' ')}ms] ${m}`);
server.on('error', (e) => { console.log(`server error: ${e.message}`); process.exit(0); });
await new Promise((r) => server.listen(PORT, '127.0.0.1', r));

const browser = await chromium.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu',
    '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream',
    `--use-file-for-fake-video-capture=${Y4M}`],
});
const context = await browser.newContext({
  ...(device === 'mobile'
    ? { viewport: { width: 423, height: 820 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }
    : { viewport: { width: 1280, height: 900 } }),
  permissions: ['camera'], acceptDownloads: true,
});
const page = await context.newPage();
const consoleErrors = [];
const downloads = [];
page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)); });
page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message.slice(0, 200)));
page.on('download', (d) => downloads.push(d.suggestedFilename()));

const state = () => page.evaluate(() => {
  const $ = (id) => document.getElementById(id);
  const box = (el) => { if (!el) return null; const r = el.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); };
  const im = $('tow-wallimg'), v = $('tow-cam');
  const C = window.AFCal || {};
  const scenes = [...document.querySelectorAll('.af-tow-scene')].map((b) => b.classList.contains('on') ? 1 : 0);
  return {
    streak: C.streak, videoW: ($('tow-cam')||{}).videoWidth, videoH: ($('tow-cam')||{}).videoHeight, ready: ($('tow-cam')||{}).readyState,
    frozen: !!C.frozen, locked: !!C.locked, autoSaved: !!C.autoSaved, timer: C.timer !== null && C.timer !== undefined,
    pxPerFt: C.pxPerFt, lockW: C.lockW, lockH: C.lockH, wallFt: C.wallFt,
    wallSrc: (im.getAttribute('src') || '').slice(0, 24),
    wallDisplay: getComputedStyle(im).display,
    camDisplay: getComputedStyle(v).display,
    camLive: !!v.srcObject,
    calDisplay: $('tow-cal').style.display, recalDisplay: $('tow-recal').style.display,
    calbox: $('tow-calbox').className,
    framebox: box($('tow-framebox')),
    stage: box($('tow-stage')),
    note: ($('tow-scalenote') || {}).textContent || '',
    cambtn: ($('tow-cambtn') || {}).textContent || '',
    scenesOn: scenes.reduce((a, b) => a + b, 0),
    toast: ($('tow-toast') || {}).textContent || '',
  };
});


// Same maths as the page's calSample()/calRows(), so a run that will not lock
// can say WHY: where the two strong horizontal edges actually are, and where
// the rectangle wants them.
const detect = () => page.evaluate(() => {
  const v = document.getElementById('tow-cam');
  const st = document.getElementById('tow-stage').getBoundingClientRect();
  if (!(v.videoWidth > 0)) return 'no video';
  const W = 120, H = 90;
  const cv = document.createElement('canvas'); cv.width = W; cv.height = H;
  const cx = cv.getContext('2d', { willReadFrequently: true });
  cx.drawImage(v, 0, 0, W, H);
  let d;
  try { d = cx.getImageData(0, 0, W, H).data; } catch (e) { return 'tainted: ' + e.message; }
  let allBlack = true;
  for (let i = 0; i < d.length; i += 4) { if (d[i] > 12 || d[i+1] > 12 || d[i+2] > 12) { allBlack = false; break; } }
  const lum = (x, y) => { const i = (y * W + x) * 4; return d[i]*0.299 + d[i+1]*0.587 + d[i+2]*0.114; };
  const x0 = Math.round(W*0.15), x1 = Math.round(W*0.85);
  const rows = [];
  for (let y = 1; y < H-1; y++) { let a = 0; for (let x = x0; x < x1; x++) a += Math.abs(lum(x, y+1) - lum(x, y-1)); rows[y] = a; }
  const sorted = rows.filter((v2) => v2 != null).sort((a, b) => a - b);
  const med = sorted[Math.floor(sorted.length/2)] || 1;
  const pick = (a, b) => { let best = -1, by = 0; for (let y = Math.max(1, Math.round(a)); y < Math.min(H-1, Math.round(b)); y++) if (rows[y] > best) { best = rows[y]; by = y; } return { y: by, str: (best/med).toFixed(1), ok: best > med*2.2 }; };
  const s = Math.max(st.width/v.videoWidth, st.height/v.videoHeight);
  const cropY = (v.videoHeight - st.height/s)/2;
  const toRow = (f) => (cropY + (f*st.height)/s) / v.videoHeight * H;
  const top = pick(H*0.03, H*0.48), bot = pick(H*0.52, H*0.97);
  return `frames ${allBlack ? 'ALL BLACK' : 'ok'}; want top=${toRow(0.16).toFixed(1)} bot=${toRow(0.84).toFixed(1)} tol=${(H*0.055).toFixed(1)}; got top=${top.y}(x${top.str},${top.ok}) bot=${bot.y}(x${bot.str},${bot.ok})`;
});

await page.goto(`${ORIGIN}/index.html`, { waitUntil: 'domcontentloaded' });
await page.evaluate(() => {
  const s = document.getElementById('tow-prod');
  if (s && s.options.length > 1) { s.selectedIndex = 1; s.dispatchEvent(new Event('change', { bubbles: true })); }
});
await sleep(1500);
const before = await state();
log(`before camera: scenesOn=${before.scenesOn} wall=${before.wallSrc} framebox=${before.framebox}`);

// A REAL click, not el.click() in the page: Playwright scrolls the target into
// view first, and headless Chromium only renders video frames for an element
// that is actually on screen - off screen, drawImage() reads back solid black
// and the edge detector can never fire. That cost an hour; leave it as click().
await page.click('#tow-cambtn');
let locked = null;
for (let i = 0; i < 60 && !locked; i++) {
  await sleep(200);
  const s = await state();
  if (i === 6) log('  detect: ' + await detect());
  if (i % 10 === 0) log(`  poll${i} frozen=${s.frozen} streak=${s.streak} calbox="${s.calbox}" video=${s.videoW}x${s.videoH} rs=${s.ready} stage=${s.stage} tick=${s.timer}`);
  if (s.frozen) locked = s;
}
if (!locked) { console.log('NEVER LOCKED — cannot test recovery'); await browser.close(); server.close(); process.exit(0); }
log(`locked+frozen: framebox=${locked.framebox} stage=${locked.stage} pxPerFt=${locked.pxPerFt} wall=${locked.wallSrc} camLive=${locked.camLive}`);
await sleep(1600);                                   // let the auto-save land
const atLock = await state();
const savesAtLock = ajaxHits.length, dlAtLock = downloads.length;

let acted = '', midway = null;
if (scenario === 'scan-again') {
  // On a phone the in-stage pill is hidden by the <=600px rule that keeps
  // everything off the video; the reachable control is the mirrored chip in
  // the strip above it. Click whichever one the visitor can actually see.
  const sel = device === 'mobile' ? '.af-tow-camhead-recal' : '#tow-recal';
  acted = 'clicked ' + sel;
  await page.click(sel);
  await sleep(700);
  midway = await state();
} else if (scenario === 'scene') {
  acted = 'clicked room scene 2';
  await page.click('.af-tow-scene:nth-of-type(2)');
} else if (scenario === 'camtoggle') {
  acted = 'clicked #tow-cambtn';
  await page.click('#tow-cambtn');
  await sleep(700);
  midway = await state();      // before it can lock again, which it will
} else if (scenario === 'upload') {
  acted = 'uploaded a wall photo';
  await page.setInputFiles('#tow-wall', path.join(WWW, 'art-101.png'));
} else if (scenario === 'resize') {
  acted = 'viewport 423x820 -> 360x640';
  await page.setViewportSize({ width: 360, height: 640 });
} else if (scenario === 'rotate') {
  // Past 781px the phone stylesheet stops applying, so the stage that was
  // pinned at the lock must be released or it keeps its portrait height in
  // landscape - and the artwork must still track the still by the cover factor.
  acted = 'rotated 423x820 -> 820x423';
  await page.setViewportSize({ width: 820, height: 423 });
} else if (scenario === 'twice') {
  acted = 'scan again, then lock again';
  await page.click(device === 'mobile' ? '.af-tow-camhead-recal' : '#tow-recal');
  await sleep(600);
  let re = null;
  for (let i = 0; i < 60 && !re; i++) { await sleep(200); const s = await state(); if (s.frozen) re = s; }
  log(re ? `re-locked: framebox=${re.framebox}` : 're-lock NEVER happened');
}
await sleep(scenario === 'twice' ? 2200 : 1800);
const after = await state();

console.log('\n===== RECOVER REPORT =====');
console.log(`scenario: ${scenario} device: ${device}`);
console.log(`at lock : frozen=${atLock.frozen} locked=${atLock.locked} autoSaved=${atLock.autoSaved} tick=${atLock.timer} cam=${atLock.camDisplay}/live=${atLock.camLive} wall=${atLock.wallSrc} scenesOn=${atLock.scenesOn}`);
console.log(`          framebox=${atLock.framebox} stage=${atLock.stage} pxPerFt=${atLock.pxPerFt} lock=${atLock.lockW}x${atLock.lockH} calbox="${atLock.calbox}" recal=${atLock.recalDisplay}`);
console.log(`          note="${atLock.note.trim()}"`);
console.log(`action  : ${acted}`);
console.log(`after   : frozen=${after.frozen} locked=${after.locked} autoSaved=${after.autoSaved} tick=${after.timer} cam=${after.camDisplay}/live=${after.camLive} wall=${after.wallSrc} wallDisplay=${after.wallDisplay} scenesOn=${after.scenesOn}`);
console.log(`          framebox=${after.framebox} stage=${after.stage} pxPerFt=${after.pxPerFt} calbox="${after.calbox}" cal=${after.calDisplay} recal=${after.recalDisplay}`);
console.log(`          note="${after.note.trim()}"  cambtn="${after.cambtn.replace(/\s+/g, ' ').trim().slice(0, 48)}"`);
console.log(`saves   : ajax ${savesAtLock} at lock -> ${ajaxHits.length} now;  downloads ${dlAtLock} -> ${downloads.length} [${downloads.join(', ')}]`);
console.log(`console errors (${consoleErrors.length}): ${consoleErrors.join(' | ') || 'none'}`);

const P = (ok, what) => console.log(`${ok ? 'PASS' : 'FAIL'}: ${what}`);
P(atLock.frozen && !atLock.camLive && atLock.wallSrc.startsWith('data:'), 'lock froze the wall and released the camera');
P(atLock.autoSaved && savesAtLock === 1 && dlAtLock === 1, 'exactly one auto-save (1 download + 1 ajax) at the lock');
P(!atLock.timer, 'the calibration tick was stopped at the lock');
if (scenario === 'scan-again' || scenario === 'camtoggle') {
  // Read the state right after the tap: the detector is free to lock again a
  // second later, and a re-lock is the wanted behaviour, not a failure.
  const m = midway || after;
  console.log(`midway  : frozen=${m.frozen} locked=${m.locked} cam=${m.camDisplay}/live=${m.camLive} wall=${m.wallSrc} calbox="${m.calbox}"`);
  P(!m.frozen && !m.locked && m.camLive, 'the camera is looking again and nothing stale is left locked');
  P(!m.wallSrc.startsWith('data:'), 'the frozen still was dropped when the camera came back');
} else if (scenario === 'scene') {
  P(!after.frozen && !after.locked && !after.camLive, 'picking a room leaves the frozen state');
  P(after.scenesOn === 1 && !after.wallSrc.startsWith('data:'), 'the room photo is back and its thumbnail is lit');
} else if (scenario === 'upload') {
  P(!after.frozen && !after.locked && !after.camLive, 'uploading a wall leaves the frozen state');
  P(after.wallDisplay === 'block' && after.scenesOn === 0, 'the uploaded wall is shown and no room thumbnail is lit');
} else if (scenario === 'resize' || scenario === 'rotate') {
  P(after.frozen && after.locked && !after.camLive, 'still frozen after the ' + scenario);
  if (scenario === 'rotate') {
    const inline = await page.evaluate(() => document.getElementById('tow-stage').style.height || '(none)');
    P(inline === '(none)', 'the stage pin was released on the rotation (inline height ' + inline + ')');
  }
  const a = atLock.framebox.split('x').map(Number), b = after.framebox.split('x').map(Number);
  const sA = atLock.stage.split('x').map(Number), sB = after.stage.split('x').map(Number);
  const cover = Math.max(sB[0] / sA[0], sB[1] / sA[1]);
  const got = b[1] / a[1];
  console.log(`          expected artwork to track the still by cover factor ${cover.toFixed(3)}; measured ${got.toFixed(3)}`);
  P(Math.abs(got - cover) / cover < 0.06, 'the artwork tracked the frozen wall, not the window');
  P(ajaxHits.length === savesAtLock && downloads.length === dlAtLock, 'a resize did not save again');
} else if (scenario === 'twice') {
  P(after.frozen && after.locked, 'the second lock froze again');
  // The fake camera replays one clip. On a phone the stage never moves, so the
  // second composite is byte-identical and the duplicate guard keeps the save
  // already made. On desktop the rail grows when the first save reveals the
  // "View saved previews" link, the stage grows with it and the second shot is
  // genuinely a different picture - so it is saved, which is also right. What
  // must hold either way: at most one save per lock, and never more than two.
  const saved = ajaxHits.length;
  P(saved >= 1 && saved <= 2 && downloads.length === saved, `one save per lock, no more (${saved})`);
  if (saved === 1) P(/already saved/i.test(after.toast), 'the identical shot was recognised: "' + after.toast.trim() + '"');
  else P(/saved to your/i.test(after.toast), 'the different shot was kept: "' + after.toast.trim() + '"');
}
P(consoleErrors.length === 0, 'no console errors');

await browser.close();
server.close();
process.exit(0);
