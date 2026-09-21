/**
 * The live /try-on-wall/ page, on a phone, with a fake camera pointed at a
 * generated wall whose ceiling and floor lines sit exactly on the calibration
 * rectangle. Proves on the SHIPPED site what the offline rig proves locally:
 * the rectangle goes green, the wall is captured as a still, the camera is
 * released, the artwork stops moving, and the shot is downloaded and posted
 * to the account.
 *
 * The y4m is generated here rather than committed (14MB), so the workflow is
 * self-contained: a flat wall with two strong horizontal lines at 16% and 84%
 * of the frame - the rectangle's own edges.
 */
import { createRequire } from 'module';
import fs from 'node:fs';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');

// ── build the wall clip, by hand ────────────────────────────────────────────
// A y4m is a header, then "FRAME\n" and three raw planes per frame. Writing it
// directly means the probe needs nothing installed on the runner - the first
// version shelled out to ffmpeg, which is not on the image.
const W = 640, H = 480, TOP = Math.round(H * 0.16), BOT = Math.round(H * 0.84);
const Y = Buffer.alloc(W * H);
for (let y = 0; y < H; y++) {
  const near = (Math.abs(y - TOP) <= 3 || Math.abs(y - BOT) <= 3);
  for (let x = 0; x < W; x++) {
    Y[y * W + x] = near ? 16 : 200 + ((x * 7 + y * 3) % 9);   // flat wall, two hard lines
  }
}
const U = Buffer.alloc((W / 2) * (H / 2), 128);
const V = Buffer.alloc((W / 2) * (H / 2), 128);
const head = Buffer.from(`YUV4MPEG2 W${W} H${H} F15:1 Ip A1:1 C420jpeg\n`, 'ascii');
const frame = Buffer.concat([Buffer.from('FRAME\n', 'ascii'), Y, U, V]);
const frames = [];
for (let i = 0; i < 45; i++) frames.push(frame);              // 3 seconds at 15fps
fs.writeFileSync('/tmp/wall.y4m', Buffer.concat([head, ...frames]));
console.log('wall clip: /tmp/wall.y4m  ' + W + 'x' + H + ', lines at rows ' + TOP + ' and ' + BOT);

const b = await puppeteer.launch({
  channel: 'chrome', headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu',
    '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream',
    '--use-file-for-fake-video-capture=/tmp/wall.y4m'],
});
const p = await b.newPage();
await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
try { await b.defaultBrowserContext().overridePermissions('https://theartframer.us', ['camera']); } catch {}

const downloads = [];
const posts = [];
p.on('console', (m) => { if (m.type() === 'error') console.log('console.error: ' + m.text().slice(0, 160)); });
await p.setRequestInterception(true);
p.on('request', (r) => {
  if (r.method() === 'POST' && /admin-ajax\.php/.test(r.url())) {
    const body = r.postData() || '';
    const g = (k) => (body.match(new RegExp('(?:^|&)' + k + '=([^&]*)')) || [])[1] || '';
    posts.push({ action: decodeURIComponent(g('action')), source: decodeURIComponent(g('source')), bytes: body.length });
    // Do NOT actually save into the live account - answer it here.
    r.respond({ status: 200, contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { message: 'Saved to your account.', url: 'https://theartframer.us/x.png', account: 'https://theartframer.us/my-account/' } }) });
    return;
  }
  r.continue();
});

let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/try-on-wall/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise((r) => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise((r) => setTimeout(r, 9000));
for (let i = 0; i < 6; i++) {
  let blocked = false;
  try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {}
  if (!blocked) break;
  await new Promise((r) => setTimeout(r, 3000));
  try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {}
  await new Promise((r) => setTimeout(r, 6000));
}
try {
  const cdp = await p.target().createCDPSession();
  await cdp.send('Browser.setDownloadBehavior', { behavior: 'allow', downloadPath: '/tmp/dl' });
} catch {}

// The consent card and the offer popup sit over the page on a first visit and
// swallow a synthetic click aimed at the camera button - which reads exactly
// like a dead button. Accept the one, dismiss the other, then work.
const cleared = await p.evaluate(() => {
  const out = [];
  const acc = document.getElementById('af-ck-accept');
  if (acc) { acc.click(); out.push('accepted cookies'); }
  ['#afOverlay', '#af-consent', '.af-overlay'].forEach((sel) => {
    document.querySelectorAll(sel).forEach((e) => { e.remove(); out.push('removed ' + sel); });
  });
  return out.join(', ') || 'nothing in the way';
});
console.log('overlays: ' + cleared);
await new Promise((r) => setTimeout(r, 800));

const picked = await p.evaluate(() => {
  const s = document.getElementById('tow-prod');
  if (!s || s.options.length < 2) return '(no products)';
  s.selectedIndex = 1; s.dispatchEvent(new Event('change', { bubbles: true }));
  return s.options[1].textContent;
});
console.log('product: ' + picked);
await new Promise((r) => setTimeout(r, 2500));

const state = () => p.evaluate(() => {
  const $ = (id) => document.getElementById(id);
  const box = (el) => { const r = el.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); };
  const C = window.AFCal || {};
  const v = $('tow-cam'), im = $('tow-wallimg');
  return { frozen: !!C.frozen, locked: !!C.locked, autoSaved: !!C.autoSaved, streak: C.streak,
    px: C.pxPerFt, lockW: C.lockW, lockH: C.lockH,
    calbox: $('tow-calbox').className, cam: getComputedStyle(v).display, camLive: !!v.srcObject,
    wall: (im.getAttribute('src') || '').slice(0, 22), wallShown: getComputedStyle(im).display,
    framebox: box($('tow-framebox')), stage: box($('tow-stage')),
    note: ($('tow-scalenote') || {}).textContent || '', toast: ($('tow-toast') || {}).textContent || '' };
});

// a real click, so the stage is scrolled into view and the video renders
// Is the camera reachable at all in this browser, on this page? A silent
// failure here looks exactly like a broken lock, so ask directly first.
const camProbe = await p.evaluate(async () => {
  if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) return 'no mediaDevices';
  try {
    const st = await navigator.mediaDevices.getUserMedia({ video: true });
    const t = st.getVideoTracks()[0];
    const s = t ? t.getSettings() : {};
    st.getTracks().forEach((x) => x.stop());
    return 'ok ' + (s.width || '?') + 'x' + (s.height || '?');
  } catch (e) { return 'FAILED ' + e.name + ': ' + e.message; }
});
console.log('getUserMedia: ' + camProbe);

await p.evaluate(() => document.getElementById('tow-cambtn').scrollIntoView({ block: 'center' }));
await new Promise((r) => setTimeout(r, 600));
console.log('on top of the camera button: ' + await p.evaluate(() => {
  const b = document.getElementById('tow-cambtn').getBoundingClientRect();
  const top = document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2);
  return top ? (top.tagName.toLowerCase() + (top.id ? '#' + top.id : '') + '.' + String(top.className).split(/\s+/).slice(0, 3).join('.')) : '(nothing)';
}));
await p.click('#tow-cambtn');
// And put the WALL on screen, not the button that started it. The camera
// button lives in the room panel below the stage, so scrolling to it pushes
// the stage off the top - and headless Chromium does not render video frames
// for an element that is off screen, so drawImage() reads back solid black
// and the edge detector can never fire. The same trap as the offline rig.
await new Promise((r) => setTimeout(r, 700));
await p.evaluate(() => document.getElementById('tow-stage').scrollIntoView({ block: 'center' }));
await new Promise((r) => setTimeout(r, 1200));
console.log('after click: ' + JSON.stringify(await p.evaluate(() => {
  const v = document.getElementById('tow-cam');
  return { camBtn: (document.getElementById('tow-cambtn').textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40),
    camDisplay: getComputedStyle(v).display, srcObject: !!v.srcObject, vw: v.videoWidth, rs: v.readyState,
    calDisplay: document.getElementById('tow-cal').style.display,
    toast: (document.getElementById('tow-toast') || {}).textContent || '' };
})));
let atLock = null;
for (let i = 0; i < 70 && !atLock; i++) {
  await new Promise((r) => setTimeout(r, 250));
  const s = await state();
  if (i % 8 === 0) {
    const look = await p.evaluate(() => {
      const v = document.getElementById('tow-cam');
      const st = document.getElementById('tow-stage').getBoundingClientRect();
      if (!(v.videoWidth > 0)) return 'no video';
      const W = 120, H = 90;
      const cv = document.createElement('canvas'); cv.width = W; cv.height = H;
      const cx = cv.getContext('2d', { willReadFrequently: true });
      cx.drawImage(v, 0, 0, W, H);
      let d; try { d = cx.getImageData(0, 0, W, H).data; } catch (e) { return 'tainted'; }
      let black = true;
      for (let i2 = 0; i2 < d.length; i2 += 4) if (d[i2] > 12) { black = false; break; }
      const lum = (x, y) => { const i3 = (y * W + x) * 4; return d[i3] * 0.299 + d[i3 + 1] * 0.587 + d[i3 + 2] * 0.114; };
      const rows = []; for (let y = 1; y < H - 1; y++) { let a = 0; for (let x = 18; x < 102; x++) a += Math.abs(lum(x, y + 1) - lum(x, y - 1)); rows[y] = a; }
      const sorted = rows.filter((z) => z != null).sort((a, b2) => a - b2), med = sorted[Math.floor(sorted.length / 2)] || 1;
      const pick = (a, b2) => { let best = -1, by = 0; for (let y = Math.max(1, Math.round(a)); y < Math.min(H - 1, Math.round(b2)); y++) if (rows[y] > best) { best = rows[y]; by = y; } return by + '(x' + (best / med).toFixed(1) + ')'; };
      const sc = Math.max(st.width / v.videoWidth, st.height / v.videoHeight);
      const cropY = (v.videoHeight - st.height / sc) / 2;
      const toRow = (f) => ((cropY + (f * st.height) / sc) / v.videoHeight * H).toFixed(1);
      return (black ? 'ALL BLACK; ' : '') + 'want ' + toRow(0.16) + '/' + toRow(0.84) + ' got ' + pick(H * 0.03, H * 0.48) + '/' + pick(H * 0.52, H * 0.97);
    });
    console.log(`  t+${(i * 0.25).toFixed(1)}s streak=${s.streak} calbox="${s.calbox}" cam=${s.cam} frozen=${s.frozen} | ${look}`);
  }
  if (s.frozen) atLock = s;
}
if (!atLock) { console.log('FAIL — never locked'); console.log(JSON.stringify(await state())); await b.close(); process.exit(0); }
console.log('LOCKED+FROZEN: ' + JSON.stringify(atLock));
await new Promise((r) => setTimeout(r, 4000));
const later = await state();
console.log('4s LATER     : ' + JSON.stringify(later));
try { downloads.push(...fs.readdirSync('/tmp/dl')); } catch {}

const P = (ok2, what) => console.log((ok2 ? 'PASS' : 'FAIL') + ': ' + what);
console.log('\n===== LIVE REPORT =====');
P(atLock.locked && /\blocked\b/.test(atLock.calbox) && !/\bnear\b/.test(atLock.calbox), 'the rectangle turned green at the match');
P(atLock.frozen && !atLock.camLive && atLock.cam === 'none', 'the camera was released at the match');
P(atLock.wall.startsWith('data:'), 'the matched wall was captured as a still (' + atLock.wall + ')');
P(atLock.framebox === later.framebox, 'the artwork did not move afterwards (' + atLock.framebox + ' -> ' + later.framebox + ')');
P(/measured against your own wall/.test(later.note), 'the scale is the measured one: "' + later.note.trim() + '"');
P(downloads.length > 0, 'the shot was downloaded (' + (downloads.join(', ') || 'none') + ')');
P(posts.some((x) => x.action === 'af_save_preview' && x.source === 'try-on-wall'), 'the shot was posted to the account (' + JSON.stringify(posts) + ')');
console.log('toast: "' + later.toast.trim() + '"');
await b.close();
