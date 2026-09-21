/**
 * The Digital Download quick-view on /product-category/digital-downloads-2/.
 * The recording shows it opening with an empty preview pane and then refusing
 * to close when the x is pressed. This finds out which element is actually on
 * screen, what its <img> is doing, and what each close route does.
 */
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');

const URL_ = 'https://theartframer.us/product-category/digital-downloads-2/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900 });
const errs = [];
p.on('console', (m) => { if (m.type() === 'error') errs.push(m.text().slice(0, 140)); });
p.on('pageerror', (e) => errs.push('pageerror: ' + e.message.slice(0, 140)));

let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto(URL_, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
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
await p.evaluate(() => {
  const a = document.getElementById('af-ck-accept'); if (a) a.click();
  ['#afOverlay', '#af-consent', '.af-overlay'].forEach((s) => document.querySelectorAll(s).forEach((e) => e.remove()));
});
await new Promise((r) => setTimeout(r, 800));

// what is on a card, and which control is the eye?
console.log('card controls: ' + await p.evaluate(() => {
  const card = document.querySelector('li.product, .product-card, .product');
  if (!card) return '(no card)';
  return [...card.querySelectorAll('a,button')].slice(0, 14).map((e) => {
    const r = e.getBoundingClientRect();
    return e.tagName.toLowerCase() + '.' + String(e.className).split(/\s+/).slice(0, 2).join('.') +
      ' "' + (e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 18) + '"' +
      (r.width ? ' ' + Math.round(r.width) + 'x' + Math.round(r.height) : ' hidden');
  }).join(' | ');
}));

const state = () => p.evaluate(() => {
  const vis = (e) => { const c = getComputedStyle(e); const r = e.getBoundingClientRect(); return c.display !== 'none' && c.visibility !== 'hidden' && c.opacity !== '0' && r.width > 100 && r.height > 100; };
  // every dialog-ish thing currently on screen
  const open = [...document.querySelectorAll('div,aside,section')].filter((e) => {
    const c = getComputedStyle(e); const r = e.getBoundingClientRect();
    return (c.position === 'fixed') && vis(e) && r.width > 300 && r.height > 200 && Number(c.zIndex || 0) > 10;
  }).slice(0, 4).map((e) => e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className).split(/\s+/).slice(0, 3).join('.'));
  const ov = document.getElementById('af-dd-overlay');
  const im = document.getElementById('af-dd-img');
  const msg = document.getElementById('af-dd-msg');
  const x = document.querySelector('#af-dd-overlay .af-dd-x');
  const xr = x ? x.getBoundingClientRect() : null;
  const topAtX = xr && xr.width ? document.elementFromPoint(xr.left + xr.width / 2, xr.top + xr.height / 2) : null;
  return {
    openDialogs: open,
    ddOpen: !!(ov && ov.classList.contains('open')),
    ddDisplay: ov ? getComputedStyle(ov).display : '(no overlay)',
    img: im ? { src: (im.getAttribute('src') || '(none)').slice(0, 70), display: getComputedStyle(im).display,
      nw: im.naturalWidth, nh: im.naturalHeight, complete: im.complete,
      box: (() => { const r = im.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); })() } : '(no img)',
    title: (document.getElementById('af-dd-title') || {}).textContent || '',
    msg: msg ? msg.textContent : '(no msg el)',
    xBox: xr ? Math.round(xr.width) + 'x' + Math.round(xr.height) + '@' + Math.round(xr.left) + ',' + Math.round(xr.top) : '(no x)',
    topAtX: topAtX ? topAtX.tagName.toLowerCase() + (topAtX.id ? '#' + topAtX.id : '') + '.' + String(topAtX.className).split(/\s+/).slice(0, 2).join('.') : '(none)',
  };
});

// the eye / quick view on the first card
const clicked = await p.evaluate(() => {
  const card = document.querySelector('li.product, .product-card, .product');
  if (!card) return '(no card)';
  const eye = card.querySelector('.quick-view, [class*="quick"], [class*="eye"], [data-quick], a[href*="quick"]');
  if (eye) { eye.scrollIntoView({ block: 'center' }); return 'eye: ' + eye.tagName.toLowerCase() + '.' + String(eye.className).split(/\s+/).slice(0, 2).join('.'); }
  return '(no eye found)';
});
console.log('quick-view control: ' + clicked);
await new Promise((r) => setTimeout(r, 500));
try {
  await p.click('li.product .quick-view, li.product [class*="quick"], .product-card [class*="quick"], .product [class*="quick"]');
  console.log('clicked the quick-view control');
} catch (e) { console.log('could not click quick view: ' + e.message.slice(0, 90)); }
await new Promise((r) => setTimeout(r, 3500));
const afterOpen = await state();
console.log('AFTER OPEN: ' + JSON.stringify(afterOpen, null, 1).replace(/\n\s*/g, ' '));

// WHY does the x not work? Count the overlays, and watch what a click on the
// button actually does - which element is the target, does close() run, does
// anything stop the event on the way.
const P = (ok, what) => console.log((ok ? 'PASS' : 'FAIL') + ': ' + what);
const reopen = async () => {
  await p.evaluate(() => { document.getElementById('af-dd-overlay').classList.remove('open'); });
  await new Promise((r) => setTimeout(r, 200));
  try { await p.click('li.product [class*="quick"], .product-card [class*="quick"], .product [class*="quick"]'); } catch {}
  await new Promise((r) => setTimeout(r, 2500));
};

console.log('\n===== LIVE REPORT =====');
P(afterOpen.ddOpen, 'the quick view opens');
P(afterOpen.img && afterOpen.img.nw > 0, 'the preview pane has a picture in it (' + (afterOpen.img && afterOpen.img.nw) + 'x' + (afterOpen.img && afterOpen.img.nh) + ')');

// Add to Cart and the title must NOT close it: the overlay carries
// data-dd-close and is an ancestor of every control inside.
await p.evaluate(() => document.getElementById('af-dd-add').click());
await new Promise((r) => setTimeout(r, 400));
P((await state()).ddOpen, 'Add to Cart does not close it');

// the x - the fault in the recording
try { await p.click('#af-dd-overlay .af-dd-x'); } catch (e) { console.log('x click threw: ' + e.message.slice(0, 60)); }
await new Promise((r) => setTimeout(r, 700));
P(!(await state()).ddOpen, 'the x closes it');

await reopen();
await p.evaluate(() => { const o = document.getElementById('af-dd-overlay'); o.dispatchEvent(new MouseEvent('click', { bubbles: true })); });
await new Promise((r) => setTimeout(r, 700));
P(!(await state()).ddOpen, 'the backdrop closes it');

await reopen();
await p.keyboard.press('Escape');
await new Promise((r) => setTimeout(r, 700));
P(!(await state()).ddOpen, 'Escape still closes it');

P(errs.filter((e) => !/status of (403|404)/.test(e)).length === 0,
  'no console errors beyond the page\'s pre-existing 403/404 asset noise');
await b.close();
