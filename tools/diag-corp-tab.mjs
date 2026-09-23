/**
 * Does clicking CORPORATE PRINTING now show Corporate Printing?
 *
 * Checked by what lands on the page, not by whether a request was made: the
 * cards that appear, and whether their links belong to that category rather
 * than to whatever the previous tab had shown.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900 });
// The [af-cp] console lines only print with the theme's debug switch on
// (inc/debug-flag.php, DEF-12). Set it before any page script runs.
await p.evaluateOnNewDocument(() => { try { localStorage.setItem('af_debug', '1'); } catch (e) {} });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 6000));

p.on('console', m => { const t = m.text(); if (t.indexOf('[af-cp]') === 0 || /error/i.test(t)) console.log('  console: ' + t); });
p.on('pageerror', e => console.log('  PAGE ERROR: ' + e.message));
p.on('request', r => { if (r.url().indexOf('admin-ajax') !== -1) console.log('  request: ' + r.method() + ' admin-ajax  ' + (r.postData() || '').slice(0, 90)); });

const snap = () => p.evaluate(() => {
  // This site's carousel lifts the cards OUT of #productGrid into its own
  // shell, so counting them inside that grid reports zero even when the load
  // worked. Look wherever they actually are.
  const grid = document.querySelector('#productGrid, .product-slider, .custom-product-track, ul.products');
  const shell = document.querySelector('.af-shell-track, .af-shell');
  const cards = [...document.querySelectorAll('.product-card, li.product')];
  const strip = document.querySelector('#subcategorySlider, .subcategory-slider');
  const titles = cards.slice(0, 4).map(c => (c.innerText || '').trim().split('\n').find(x => x.length > 8) || '');
  return {
    cards: cards.length,
    inGrid: grid ? grid.querySelectorAll('.product-card, li.product').length : 0,
    inShell: shell ? shell.querySelectorAll('.product-card, li.product').length : 0,
    titles,
    circles: strip ? [...strip.children].map(x => (x.innerText || '').trim().split('\n')[0]).slice(0, 8) : 'NO ROW',
    ourCircles: strip ? strip.querySelectorAll('.af-cp-circle').length : 0,
    moduleLoaded: !!document.getElementById('af-cp-collection-js'),
    handlerRan: document.documentElement.getAttribute('data-af-cp') || 'no',
    gridSeen: document.documentElement.getAttribute('data-af-cp-grid') || '-',
    answerBytes: document.documentElement.getAttribute('data-af-cp-bytes') || '-',
    wroteIntoGrid: document.documentElement.getAttribute('data-af-cp-wrote') || '-',
    builtCircles: document.documentElement.getAttribute('data-af-cp-circles') || '-',
    finished: document.documentElement.getAttribute('data-af-cp-done') || 'no',
    error: document.documentElement.getAttribute('data-af-cp-error') || '-',
  };
});

console.log('module on page:', (await snap()).moduleLoaded);
console.log('\nBEFORE  ', JSON.stringify(await snap(), null, 0));

// Wait until the module has attached AND the tab exists. The previous run
// clicked before either was true and then reported that the handler never
// ran, which said nothing about the site.
let ready = false;
for (let i = 0; i < 30; i++) {
  ready = await p.evaluate(() => !!document.getElementById('af-cp-collection-js') &&
    ![...document.querySelectorAll('.top-cat-btn')].every(x => (x.getAttribute('data-cat') || '') !== 'corporate-printing'));
  if (ready) break;
  await new Promise(r => setTimeout(r, 1000));
}
console.log('module attached and tab present:', ready);

// Click via the real tab. Our handler prevents the default itself.
const clicked = await p.evaluate(() => {
  const a = [...document.querySelectorAll('.top-cat-btn')].find(x => (x.getAttribute('data-cat') || '') === 'corporate-printing');
  if (a) a.click();
  return !!a;
});
console.log('clicked the Corporate Printing tab:', clicked);
// Wait for the handler to finish rather than for a fixed interval.
for (let i = 0; i < 25; i++) {
  const done = await p.evaluate(() => document.documentElement.getAttribute('data-af-cp-done')
    || document.documentElement.getAttribute('data-af-cp-error'));
  if (done) break;
  await new Promise(r => setTimeout(r, 800));
}
// and then for the carousel to rebuild from it
await new Promise(r => setTimeout(r, 4000));

const after = await snap();
console.log('\nAFTER   ', JSON.stringify(after, null, 0));
console.log('URL     ', await p.evaluate(() => location.href));

const corpish = /banner|tote|corporate|signage|badge|lanyard|brochure|flyer|card|mug|bag|print/i;
const looksCorp = after.titles.filter(t => corpish.test(t)).length;
const krishna = after.titles.filter(t => /radha|krishna|shiva|buddha|ganesh/i.test(t)).length;
console.log('\n  cards now: ' + after.cards);
console.log('  first titles: ' + JSON.stringify(after.titles));
console.log('  our circles: ' + after.ourCircles + '  -> ' + JSON.stringify(after.circles));
console.log(krishna > 0
  ? '  FAIL: the grid is still showing art canvases, not Corporate Printing'
  : (after.cards > 0 ? '  OK: the grid changed and shows no art canvases' : '  FAIL: no cards at all'));
await b.close();
