// Can the Digital Download modal be closed when a plugin traps clicks?
//
//   NODE_PATH=/opt/node22/lib/node_modules node tools/dd-harness/run.mjs [trap|notrap]
//
// The live quick view leaves a document-level CAPTURE listener that calls
// stopPropagation() on every click, so the event never descends past document
// and nothing bound inside the modal can ever hear it. Measured on the live
// page: the capture chain recorded exactly one entry, "document". That is why
// the × did nothing in the owner's recording and only Escape worked.
//
// render.php reproduces that trap, so "closes with the trap in place" is a
// thing this can actually assert. It also checks the opposite mistake: the
// overlay itself carries data-dd-close, so a careless closest() test would
// shut the modal on Add to Cart, the one action it exists to offer.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const HERE = path.dirname(fileURLToPath(import.meta.url));
const WWW = path.join(HERE, 'www');
const PORT = Number(process.env.DD_PORT || 0) || (9000 + (process.pid % 900));
const ORIGIN = `http://127.0.0.1:${PORT}`;
const mode = (process.argv[2] || 'trap').toLowerCase();
if (!fs.existsSync(path.join(WWW, 'index.html'))) { console.log('run: php tools/dd-harness/render.php'); process.exit(0); }

// The modal asks admin-ajax for a watermarked preview. Answer slowly the first
// time, the way the server does while GD renders a big master — that wait is
// the blank pane in the recording, and the page must say something during it.
let previewCalls = 0;
const server = http.createServer((req, res) => {
  const url = new URL(req.url, ORIGIN);
  if (url.pathname === '/admin-ajax.php') {
    previewCalls++;
    const slow = Number(process.env.DD_SLOW_MS || 1500);
    setTimeout(() => {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ success: true, data: {
        url: ORIGIN + '/preview.png', wm: 1, price_html: '<span class="price">$9.99</span>' } }));
    }, previewCalls === 1 ? slow : 0);
    return;
  }
  if (url.pathname === '/preview.png') {
    // a 2x2 png, enough for naturalWidth > 0
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGP8//8/AxJgYkAFOPkAZtcDDfUiAFQAAAAASUVORK5CYII=', 'base64');
    res.writeHead(200, { 'Content-Type': 'image/png' }); res.end(png); return;
  }
  const file = path.join(WWW, url.pathname === '/' ? '/index.html' : url.pathname);
  if (!file.startsWith(WWW) || !fs.existsSync(file)) { res.writeHead(404); res.end('nf'); return; }
  res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
  fs.createReadStream(file).pipe(res);
});
server.on('error', (e) => { console.log('server error: ' + e.message); process.exit(0); });
await new Promise((r) => server.listen(PORT, '127.0.0.1', r));

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const context = await browser.newContext({ viewport: { width: 1100, height: 850 } });
const page = await context.newPage();
const errs = [];
page.on('console', (m) => { if (m.type() === 'error') errs.push(m.text().slice(0, 140)); });
page.on('pageerror', (e) => errs.push('pageerror: ' + e.message.slice(0, 140)));
if (mode === 'notrap') await page.addInitScript(() => { window.__noTrap = true; });
await page.goto(ORIGIN + '/index.html', { waitUntil: 'load' });

const st = () => page.evaluate(() => {
  const o = document.getElementById('af-dd-overlay');
  const w = document.querySelector('#af-dd-overlay .af-dd-imgwrap');
  const m = document.getElementById('af-dd-msg');
  const i = document.getElementById('af-dd-img');
  return { open: !!(o && o.classList.contains('open')),
    loading: !!(w && w.classList.contains('loading')),
    msg: m ? m.textContent : '', imgSrc: (i && i.getAttribute('src')) || '(none)',
    bodyOverflow: document.body.style.overflow || '(none)' };
});

// open it the way a card does
const openIt = async () => {
  await page.evaluate(() => {
    const o = document.getElementById('af-dd-overlay');
    o.classList.remove('open');
    document.querySelector('.digital-download').click();
  });
  await sleep(250);
};

console.log(`mode=${mode} (trap ${mode === 'trap' ? 'IS' : 'is NOT'} installed)`);
const P = (ok, what) => { console.log((ok ? 'PASS' : 'FAIL') + ': ' + what); return ok; };

// 1. it opens, and says something while the preview is being made
await openIt();
const opened = await st();
console.log('  on open: ' + JSON.stringify(opened));
P(opened.open, 'the modal opens');
P(opened.loading && /preparing/i.test(opened.msg), 'it says the preview is being prepared, rather than showing a blank pane');
await sleep(2200);
const loaded = await st();
console.log('  after the preview lands: ' + JSON.stringify(loaded));
P(!loaded.loading && loaded.imgSrc.indexOf('preview.png') > -1, 'the preview replaces the waiting state');
P(loaded.msg === '', 'and the waiting message is cleared');

// 2. Add to Cart must NOT close it — the overlay carries data-dd-close and is
//    an ancestor of every control in here
await page.evaluate(() => document.getElementById('af-dd-add').click());
await sleep(200);
P((await st()).open, 'clicking Add to Cart does not close the modal');
await page.evaluate(() => document.getElementById('af-dd-title').click());
await sleep(200);
P((await st()).open, 'clicking the title does not close it either');

// 3. the ×. Watched, not just sampled: closing on pointerdown would take the
// modal out from under the cursor and let the trailing click land on the card
// behind it and reopen the quick view - measured on the live page as the class
// sequence ["closed","OPEN"]. The assertion is the END state, and the trace is
// printed so a reopen is never mistaken for a failure to close.
await page.evaluate(() => {
  window.__trace = [];
  const o = document.getElementById('af-dd-overlay');
  new MutationObserver(() => window.__trace.push(o.classList.contains('open') ? 'OPEN' : 'closed'))
    .observe(o, { attributes: true, attributeFilter: ['class'] });
});
await page.click('#af-dd-overlay .af-dd-x');
await sleep(400);
console.log('  class changes after the ×: ' + JSON.stringify(await page.evaluate(() => window.__trace)));
P(!(await st()).open, 'the × closes it' + (mode === 'trap' ? ' even with the click trap installed' : ''));
P(!(await page.evaluate(() => window.__trace.join(','))).includes('closed,OPEN'), 'and nothing reopens it');
P((await st()).bodyOverflow === '(none)', 'and the page can scroll again');

// 3b. A late answer must not resurrect a modal the visitor has dismissed.
// unavailable() calls open(), and its guard only compares seq !== reqSeq -
// which close() never changed - so a fetch still in flight when the × was
// pressed could reopen the modal a moment later. Measured on the live page as
// the class sequence ["closed","OPEN"].
{
  await page.evaluate(() => { window.__late = []; const o = document.getElementById('af-dd-overlay');
    new MutationObserver(() => window.__late.push(o.classList.contains('open') ? 'OPEN' : 'closed'))
      .observe(o, { attributes: true, attributeFilter: ['class'] }); });
  await page.route('**/admin-ajax.php', async (route) => {     // make THIS open fail, slowly
    await new Promise((r) => setTimeout(r, 1200));
    await route.fulfill({ status: 500, contentType: 'application/json', body: '{"success":false}' });
  });
  await openIt();
  await sleep(300);
  await page.click('#af-dd-overlay .af-dd-x');                 // dismiss while it is still out
  await sleep(2000);                                           // let the failure land
  const trace = await page.evaluate(() => window.__late.join(','));
  console.log('  class changes, dismissed mid-flight: [' + trace + ']');
  P(!(await st()).open, 'a failed preview does not reopen a dismissed modal');
  P(!trace.includes('closed,OPEN'), 'and the class never goes back to open');
  await page.unroute('**/admin-ajax.php');
}

// 3c. The page chrome is not a product card. On the live archive the body
// carries term-digital-downloads-2, which [class*="digital-download"] matches,
// so every click resolved to trg = <body> - body satisfies CARD_SEL too - and
// the handler called preventDefault() and opened the modal. Clicking the logo
// stopped going home. Measured: selectorMatch and cardFromLogo were both
// "body.archive.tax-product_cat.term-digital-downloads-2".
{
  await page.evaluate(() => { const o = document.getElementById('af-dd-overlay'); o.classList.remove('open'); });
  await sleep(150);
  const res = await page.evaluate(() => {
    const logo = document.querySelector('.custom-logo-link');
    const ev = new MouseEvent('click', { bubbles: true, cancelable: true });
    logo.dispatchEvent(ev);
    return { prevented: ev.defaultPrevented,
      ddOpen: document.getElementById('af-dd-overlay').classList.contains('open') };
  });
  console.log('  logo click: ' + JSON.stringify(res));
  P(!res.ddOpen, 'clicking the logo does not open the modal, even on an archive whose body says digital-downloads');
  P(!res.prevented, 'and its navigation is not cancelled');
  // A working logo really does navigate, which tears down the page - wait for
  // it and carry on, rather than letting the rest of the run die on a
  // destroyed execution context.
  await page.waitForLoadState ? await page.waitForLoadState('load') : await sleep(800);
  await sleep(400);
}

// 4. the backdrop
await openIt(); await sleep(150);
await page.evaluate(() => {
  const o = document.getElementById('af-dd-overlay');
  const r = o.getBoundingClientRect();
  o.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: r.left + 6, clientY: r.top + 6 }));
});
await sleep(250);
P(!(await st()).open, 'the backdrop closes it');

// 5. Escape, which always worked and must go on working
await openIt(); await sleep(150);
await page.keyboard.press('Escape');
await sleep(250);
P(!(await st()).open, 'Escape closes it');

P(errs.length === 0, 'no console errors' + (errs.length ? ': ' + errs.join(' | ') : ''));
await browser.close();
server.close();
process.exit(0);
