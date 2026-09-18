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
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 6000));

const snap = () => p.evaluate(() => {
  const grid = document.querySelector('#productGrid, .product-slider, .custom-product-track, ul.products');
  const cards = grid ? [...grid.querySelectorAll('.product-card, li.product')] : [];
  const strip = document.querySelector('#subcategorySlider, .subcategory-slider');
  const titles = cards.slice(0, 4).map(c => (c.innerText || '').trim().split('\n').find(x => x.length > 8) || '');
  return {
    cards: cards.length,
    titles,
    circles: strip ? [...strip.children].map(x => (x.innerText || '').trim().split('\n')[0]).slice(0, 8) : 'NO ROW',
    ourCircles: strip ? strip.querySelectorAll('.af-cp-circle').length : 0,
    moduleLoaded: !!document.getElementById('af-cp-collection-js'),
  };
});

console.log('module on page:', (await snap()).moduleLoaded);
console.log('\nBEFORE  ', JSON.stringify(await snap(), null, 0));

// Click via the real tab. Our handler prevents the default itself.
const clicked = await p.evaluate(() => {
  const a = [...document.querySelectorAll('.top-cat-btn')].find(x => (x.getAttribute('data-cat') || '') === 'corporate-printing');
  if (a) a.click();
  return !!a;
});
console.log('clicked the Corporate Printing tab:', clicked);
await new Promise(r => setTimeout(r, 8000));

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
