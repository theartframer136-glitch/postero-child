// GUARDS: the auto-save waits for the frozen wall to decode before composing.
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/tow-harness/run-wallready.mjs
//
// The still is handed to <img> as a data: URL and composePreview draws that
// img; composing too early would bake a transparent or half-drawn wall into
// the saved picture. Expected: the composed PNG is stage-sized with
// opaqueFrac 1, and exactly one save.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.TOW_PORT || 0) || (9100 + (process.pid % 200));
const ORIGIN = `http://127.0.0.1:${PORT}`;
const CPU = Number(process.argv[2] || 1);
const BLOCK = Number(process.argv[3] || 0);
const Y4M = path.resolve(HERE, 'wall-lock.y4m');

const ajaxHits = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (req.method === 'POST' && url.pathname === '/admin-ajax.php') {
    let body = ''; req.on('data', c => body += c);
    req.on('end', () => {
      const p = new URLSearchParams(body);
      ajaxHits.push({ source: p.get('source'), len: (p.get('image') || '').length });
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: true, data: { message: 'ok', url: `${ORIGIN}/saved.png`, account: `${ORIGIN}/acct/` } }));
    });
    return;
  }
  if (url.pathname === '/favicon.ico') { res.writeHead(204); res.end(); return; }
  let file = path.join(WWW, decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname));
  if (!fs.existsSync(file) || fs.statSync(file).isDirectory()) {
    if (url.pathname.startsWith('/uploads/mockups/')) file = path.join(WWW, 'uploads/mockups/room.jpg');
    else { res.writeHead(404); res.end('nf'); return; }
  }
  const ext = path.extname(file).toLowerCase();
  res.writeHead(200, { 'Content-Type': ext === '.html' ? 'text/html; charset=utf-8' : ext === '.png' ? 'image/png' : 'image/jpeg' });
  fs.createReadStream(file).pipe(res);
});
const sleep = ms => new Promise(r => setTimeout(r, ms));
await new Promise(r => server.listen(PORT, '127.0.0.1', r));

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu','--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream',`--use-file-for-fake-video-capture=${Y4M}`] });
const context = await browser.newContext({ viewport: { width: 423, height: 820 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, permissions: ['camera'], acceptDownloads: true });
const page = await context.newPage();
const errs = [];
page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
page.on('pageerror', e => errs.push('pageerror: ' + e.message));
page.on('download', d => d.cancel().catch(() => {}));

await page.addInitScript(({ BLOCK }) => {
  window.__W = { draws: [], srcSets: [], loads: [], saves: [], lastURL: '' };
  const od = CanvasRenderingContext2D.prototype.drawImage;
  CanvasRenderingContext2D.prototype.drawImage = function (img) {
    try {
      if (img && img.tagName === 'IMG' && img.id === 'tow-wallimg') {
        window.__W.draws.push({ t: Math.round(performance.now()), complete: img.complete, nw: img.naturalWidth, nh: img.naturalHeight, kind: (img.getAttribute('src') || '').slice(0, 11) });
      }
    } catch (e) {}
    return od.apply(this, arguments);
  };
  // hook the instant the still is attached; optionally jam the main thread there
  const arm = () => {
    const im = document.getElementById('tow-wallimg');
    if (!im) { requestAnimationFrame(arm); return; }
    const proto = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'src');
    Object.defineProperty(im, 'src', {
      configurable: true,
      get() { return proto.get.call(this); },
      set(v) {
        proto.set.call(this, v);
        const isData = String(v).slice(0, 11) === 'data:image/';
        window.__W.srcSets.push({ t: Math.round(performance.now()), kind: String(v).slice(0, 11), len: String(v).length, completeNow: this.complete, nw: this.naturalWidth });
        if (isData && BLOCK > 0) {
          // chunked jam: busy-loop 60ms at a time, yielding to the event loop
          // between chunks, so the load queue and the timer queue both get
          // fair chances to run. This is the reviewer's "main thread busy".
          let left = BLOCK;
          const chunk = () => {
            const e = performance.now() + 60; while (performance.now() < e) { Math.sqrt(Math.random()); }
            left -= 60; if (left > 0) setTimeout(chunk, 0);
          };
          setTimeout(chunk, 0);
          const e0 = performance.now() + 60; while (performance.now() < e0) { Math.sqrt(Math.random()); }
        }
      }
    });
    im.addEventListener('load', () => window.__W.loads.push({ t: Math.round(performance.now()), nw: im.naturalWidth }));
  };
  arm();
}, { BLOCK });

const cdp = await context.newCDPSession(page);
if (CPU > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: CPU });

await page.goto(`${ORIGIN}/index.html`, { waitUntil: 'load', timeout: 30000 });
await page.waitForFunction(() => !!window.AFCal && document.querySelectorAll('#tow-prod option').length > 1, null, { timeout: 20000 });
await page.evaluate(() => {
  const s = document.getElementById('tow-prod'); s.selectedIndex = 1; s.dispatchEvent(new Event('change', { bubbles: true }));
  const o = window.AFPreview.save.bind(window.AFPreview);
  window.AFPreview.save = function (url, meta, cb) { window.__W.saves.push({ t: Math.round(performance.now()), len: url.length, source: meta && meta.source }); window.__W.lastURL = url; return o(url, meta, cb); };
});
await sleep(2500 * Math.max(1, CPU / 4));
await page.click('#tow-cambtn');
const deadline = Date.now() + 40000 * Math.max(1, CPU / 4);
let locked = false;
while (Date.now() < deadline) {
  const st = await page.evaluate(() => ({ l: window.AFCal.locked, f: window.AFCal.frozen, a: window.AFCal.autoSaved }));
  if (st.a) { locked = true; break; }
  if (st.f) locked = true;
  await sleep(200);
}
await sleep(3000);
if (CPU > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: 1 });

const W = await page.evaluate(() => ({ draws: window.__W.draws, srcSets: window.__W.srcSets, loads: window.__W.loads, saves: window.__W.saves, cal: { locked: window.AFCal.locked, frozen: window.AFCal.frozen, autoSaved: window.AFCal.autoSaved } }));
// does the saved PNG actually contain a wall?
const opaque = await page.evaluate(async () => {
  const u = window.__W.lastURL; if (!u) return null;
  const im = new Image(); im.src = u; await im.decode();
  const c = document.createElement('canvas'); c.width = im.naturalWidth; c.height = im.naturalHeight;
  const x = c.getContext('2d'); x.drawImage(im, 0, 0);
  const d = x.getImageData(0, 0, c.width, c.height).data;
  let op = 0, tot = 0;
  for (let i = 3; i < d.length; i += 4 * 97) { tot++; if (d[i] > 0) op++; }
  return { w: c.width, h: c.height, opaqueFrac: Math.round((op / tot) * 1000) / 1000 };
});
await context.close().catch(() => {}); await browser.close().catch(() => {}); server.close();
console.log(`cpuRate=${CPU} blockMs=${BLOCK}`);
console.log('cal:', JSON.stringify(W.cal));
console.log('src sets on #tow-wallimg:', JSON.stringify(W.srcSets));
console.log('load events:', JSON.stringify(W.loads));
console.log('wall drawImage calls (composePreview):', JSON.stringify(W.draws));
console.log('AFPreview.save calls:', JSON.stringify(W.saves));
console.log('saved PNG:', JSON.stringify(opaque));
console.log('ajax:', JSON.stringify(ajaxHits));
console.log('console errors:', errs.length, errs.slice(0, 3).join(' | '));
process.exit(0);
