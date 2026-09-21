// Offline Playwright rig for the "Try It On Your Wall" live-camera calibration.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run.mjs <lock|miss> <mobile|desktop>
//
// Serves tools/tow-harness/www on 127.0.0.1:8765, feeds Chromium a fake camera
// from wall-lock.y4m / wall-miss.y4m, drives the page and prints a timeline +
// REPORT. Exit code is always 0; PASS/FAIL lines are the verdicts.

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.TOW_PORT || 0) || (9000 + (process.pid % 900));
const ORIGIN = `http://127.0.0.1:${PORT}`;

const clip = (process.argv[2] || 'lock').toLowerCase();       // lock | miss
const device = (process.argv[3] || 'mobile').toLowerCase();  // mobile | desktop
if (!['lock', 'miss'].includes(clip) || !['mobile', 'desktop'].includes(device)) {
  console.log('usage: run.mjs <lock|miss> <mobile|desktop>');
  process.exit(0);
}
const Y4M = path.resolve(HERE, clip === 'lock' ? 'wall-lock.y4m' : 'wall-miss.y4m');
if (!fs.existsSync(Y4M)) { console.log(`missing ${Y4M} — run: php tools/tow-harness/make-walls.php`); process.exit(0); }
if (!fs.existsSync(path.join(WWW, 'index.html'))) { console.log('missing www/index.html — run: php tools/tow-harness/render.php'); process.exit(0); }

const MIME = { '.html': 'text/html; charset=utf-8', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.svg': 'image/svg+xml' };

// ── static server + admin-ajax mock ─────────────────────────────────────────
const ajaxHits = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (req.method === 'POST' && url.pathname === '/admin-ajax.php') {
    let body = '';
    req.on('data', (c) => { body += c; });
    req.on('end', () => {
      const p = new URLSearchParams(body);
      const image = p.get('image') || '';
      ajaxHits.push({
        t: Date.now(), action: p.get('action'), source: p.get('source'), product: p.get('product'),
        size: p.get('size'), frame: p.get('frame'), color: p.get('color'), layout: p.get('layout'),
        imagePrefix: image.slice(0, 30), imageLength: image.length,
      });
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
    else { res.writeHead(404, { 'Content-Type': 'text/plain' }); res.end('not found: ' + url.pathname); return; }
  }
  res.writeHead(200, { 'Content-Type': MIME[path.extname(file).toLowerCase()] || 'application/octet-stream' });
  fs.createReadStream(file).pipe(res);
});

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const T0 = Date.now();
const ms = () => String(Date.now() - T0).padStart(5, ' ');
const log = (m) => console.log(`[${ms()}ms] ${m}`);

// ── browser ─────────────────────────────────────────────────────────────────
server.on('error', (e) => { console.log(`server error: ${e.message} (a previous run still holding the port? pkill -f tow-harness/run.mjs)`); process.exit(0); });
await new Promise((r) => server.listen(PORT, '127.0.0.1', r));
log(`server on ${ORIGIN} serving ${WWW}`);
log(`clip=${clip} device=${device} y4m=${Y4M}`);

const browser = await chromium.launch({
  headless: true,
  args: [
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--use-fake-ui-for-media-stream',
    '--use-fake-device-for-media-stream',
    `--use-file-for-fake-video-capture=${Y4M}`,
  ],
});
const ctxOpts = device === 'mobile'
  ? { viewport: { width: 423, height: 820 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }
  : { viewport: { width: 1280, height: 900 } };
const context = await browser.newContext({ ...ctxOpts, permissions: ['camera'], acceptDownloads: true });
const page = await context.newPage();

const consoleErrors = [];
const downloads = [];
const toasts = [];
page.on('console', (m) => { if (m.type() === 'error') { consoleErrors.push(m.text()); log(`console.error: ${m.text()}`); } });
page.on('pageerror', (e) => { consoleErrors.push('pageerror: ' + e.message); log(`pageerror: ${e.message}`); });
page.on('download', (d) => { downloads.push({ t: Date.now() - T0, filename: d.suggestedFilename() }); log(`download event: ${d.suggestedFilename()}`); d.cancel().catch(() => {}); });
page.on('requestfailed', (r) => { if (!r.url().includes('favicon')) log(`request failed: ${r.url()} ${r.failure()?.errorText}`); });

const outcome = { lockedAt: null, sizeAtLock: null, sizeLater: null, finalState: null };

try {
  await page.goto(`${ORIGIN}/index.html`, { waitUntil: 'load', timeout: 15000 });
  log('page loaded');
  await page.waitForFunction(() => !!window.AFCal && document.querySelectorAll('#tow-prod option').length > 1, null, { timeout: 5000 });

  const picked = await page.evaluate(() => {
    const s = document.getElementById('tow-prod');
    s.selectedIndex = 1; s.dispatchEvent(new Event('change', { bubbles: true }));
    return s.options[1].textContent;
  });
  log(`selected product: ${picked}`);
  await sleep(1500);

  const stage = await page.evaluate(() => { const r = document.getElementById('tow-stage').getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height) }; });
  log(`stage ${stage.w}x${stage.h} (aspect ${(stage.w / stage.h).toFixed(3)}; 4:3 = 1.333)`);

  await page.click('#tow-cambtn');
  log('clicked #tow-cambtn');

  const snapshot = () => page.evaluate(() => {
    const cs = (id) => { const el = document.getElementById(id); return el ? getComputedStyle(el).display : 'missing'; };
    const cam = document.getElementById('tow-cam');
    const wall = document.getElementById('tow-wallimg');
    const fb = document.getElementById('tow-framebox');
    const fbr = fb.getBoundingClientRect();
    const cal = {};
    for (const k of Object.keys(window.AFCal || {})) {
      const v = window.AFCal[k];
      cal[k] = (typeof v === 'number') ? Math.round(v * 1000) / 1000 : (typeof v === 'object' && v !== null ? '[obj]' : v);
    }
    return {
      cal,
      calboxClasses: (document.getElementById('tow-calbox') || {}).className || '',
      calDisplay: cs('tow-cal'), recalDisplay: cs('tow-recal'),
      camDisplay: getComputedStyle(cam).display, camSrcNull: cam.srcObject === null,
      camVideo: `${cam.videoWidth}x${cam.videoHeight} rs=${cam.readyState} paused=${cam.paused}`,
      wallDisplay: getComputedStyle(wall).display,
      wallSrc: (wall.getAttribute('src') || '').slice(0, 40),
      fbDisplay: getComputedStyle(fb).display, fbSize: `${Math.round(fbr.width)}x${Math.round(fbr.height)}`,
      toast: (document.getElementById('tow-toast') || {}).textContent || '',
      calmsg: ((document.getElementById('tow-calmsg') || {}).textContent || '').trim().slice(0, 60),
    };
  });

  const start = Date.now();
  const MAX = 15000;
  let last = '';
  let lastToast = '';
  let lockT = null;
  let state = null;
  while (Date.now() - start < MAX) {
    state = await snapshot();
    if (state.toast && state.toast !== lastToast) { toasts.push({ t: Date.now() - T0, text: state.toast }); lastToast = state.toast; }
    const key = JSON.stringify([state.cal.locked, state.cal.frozen, state.cal.autoSaved, state.calboxClasses, state.camDisplay, state.camSrcNull, state.wallDisplay, state.wallSrc.slice(0, 5), state.fbDisplay, state.cal.streak]);
    if (key !== last) {
      last = key;
      log(`locked=${state.cal.locked} streak=${state.cal.streak} calbox="${state.calboxClasses}" cam=${state.camDisplay}/srcNull=${state.camSrcNull} video=${state.camVideo} wallimg=${state.wallDisplay}/${state.wallSrc.slice(0, 12)} framebox=${state.fbDisplay} ${state.fbSize}` + (state.cal.frozen !== undefined ? ` frozen=${state.cal.frozen}` : '') + (state.cal.autoSaved !== undefined ? ` autoSaved=${state.cal.autoSaved}` : ''));
    }
    if (state.cal.locked && lockT === null) {
      lockT = Date.now();
      outcome.lockedAt = lockT - T0;
      outcome.sizeAtLock = state.fbSize;
      log(`LOCK detected; framebox ${state.fbSize}; calmsg="${state.calmsg}"`);
    }
    if (lockT !== null && Date.now() - lockT >= 3000) {
      outcome.sizeLater = state.fbSize;
      // give a late auto-save / download a moment more, then stop
      await sleep(600);
      state = await snapshot();
      if (state.toast && state.toast !== lastToast) { toasts.push({ t: Date.now() - T0, text: state.toast }); lastToast = state.toast; }
      break;
    }
    await sleep(200);
  }
  outcome.finalState = state;
} catch (e) {
  log(`harness error: ${e.message}`);
  consoleErrors.push('harness: ' + e.message);
}

await sleep(300);
await context.close().catch(() => {});
await browser.close().catch(() => {});
server.close();

// ── REPORT ──────────────────────────────────────────────────────────────────
const s = outcome.finalState || {};
const P = (ok, label) => console.log(`${ok ? 'PASS' : 'FAIL'}: ${label}`);
console.log('');
console.log('===== REPORT =====');
console.log(`run: clip=${clip} device=${device}`);
console.log(`lockedAt(ms): ${outcome.lockedAt === null ? 'never' : outcome.lockedAt + ' (' + (outcome.lockedAt - 0) + 'ms since start)'}`);
console.log(`calbox classes: "${s.calboxClasses ?? '?'}"  (#tow-cal display=${s.calDisplay}, #tow-recal display=${s.recalDisplay})`);
console.log(`#tow-cam: display=${s.camDisplay} srcObject null=${s.camSrcNull} (${s.camVideo})`);
console.log(`#tow-wallimg: display=${s.wallDisplay} src=${s.wallSrc ? (s.wallSrc.startsWith('data:') ? 'data:' : s.wallSrc.startsWith('http') ? 'http' : s.wallSrc) : '(none)'}${s.wallSrc ? ' [' + s.wallSrc.slice(0, 40) + ']' : ''}`);
console.log(`AFCal: ${JSON.stringify(s.cal || {})}`);
console.log(`#tow-framebox: display=${s.fbDisplay} size at lock=${outcome.sizeAtLock ?? 'n/a'} size 3s later=${outcome.sizeLater ?? 'n/a'} size final=${s.fbSize}`);
console.log(`download event: ${downloads.length ? downloads.map((d) => `${d.filename} @${d.t}ms`).join(', ') : 'none'}`);
console.log(`POST /admin-ajax.php: ${ajaxHits.length ? ajaxHits.map((h) => `action=${h.action} source=${h.source} product=${h.product} size=${h.size} frame=${h.frame} layout=${h.layout} image=${h.imagePrefix}… (${h.imageLength} chars) @${h.t - T0}ms`).join(' | ') : 'none'}`);
console.log(`toast history: ${toasts.length ? toasts.map((t) => `[${t.t}ms] ${t.text}`).join(' || ') : '(none)'}`);
console.log(`console errors (${consoleErrors.length}): ${consoleErrors.length ? consoleErrors.join(' || ') : 'none'}`);
if (clip === 'lock') P(outcome.lockedAt !== null, '(a) lock happens with wall-lock');
if (clip === 'miss') P(outcome.lockedAt === null, '(b) never locks with wall-miss');
P(consoleErrors.length === 0, '(c) no console errors');
console.log(`INFO (not asserted): freeze=${s.camDisplay === 'none' || s.camSrcNull ? 'video hidden/detached' : 'video live'} download=${downloads.length ? 'yes' : 'no'} ajax=${ajaxHits.length ? 'yes' : 'no'}`);
console.log(`total ${Date.now() - T0}ms`);
process.exit(0);
