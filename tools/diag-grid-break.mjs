/**
 * The grid under Shop by Collection is stuck on a single full-width card and
 * no longer changes when a tab is clicked, and the circle row has gone.
 *
 * The newest thing touching that grid is emptyNotice() in
 * inc/goldfoil-collection.php, which does grid.innerHTML = '' when the
 * embossed section is opened while it holds no products. If that wipes a
 * wrapper the theme's layout depends on, every later tab would drop its cards
 * into an unstyled container - exactly one enormous column.
 *
 * So run the two orders separately and compare:
 *   A. fresh page -> click Corporate Printing
 *   B. fresh page -> click Embossed Prints -> click Corporate Printing
 * If A is fine and B is broken, the fault is mine and it is emptyNotice.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });

async function load(p) {
  let ok = false;
  for (let i = 1; i <= 3 && !ok; i++) {
    try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
    catch (e) { console.log('  load attempt ' + i + ' failed'); await new Promise(r => setTimeout(r, 4000)); }
  }
  if (!ok) return false;
  await new Promise(r => setTimeout(r, 6000));
  for (let i = 0; i < 6; i++) {
    let blocked = false;
    try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch { }
    if (!blocked) break;
    await new Promise(r => setTimeout(r, 2500));
    try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {}
    await new Promise(r => setTimeout(r, 4000));
  }
  return true;
}

const clickTab = async (p, re) => p.evaluate((src) => {
  const rx = new RegExp(src, 'i');
  const a = [...document.querySelectorAll('#topCatSlider .top-cat-btn, .top-category-slider .top-cat-btn')]
    .find(x => rx.test(x.innerText || ''));
  if (a) a.click();
  return !!a;
}, re.source);

const look = (p, label) => p.evaluate((label) => {
  // The grid: the container that holds the product cards under the strip.
  const card = document.querySelector('.top-category-container ~ * li.product, li.product, .product-card');
  let grid = null;
  for (let n = card; n && n !== document.body; n = n.parentElement) {
    if (n.children.length >= 1 && n.querySelectorAll('li.product, .product-card').length >= 1) { grid = n; break; }
  }
  const cards = [...document.querySelectorAll('li.product, .product-card')];
  const strip = document.querySelector('#subcategorySlider, .subcategory-slider');
  const notice = document.querySelector('.af-gf-empty');
  const g = grid ? getComputedStyle(grid) : null;
  const first = cards[0] ? cards[0].getBoundingClientRect() : null;
  return {
    label,
    cards: cards.length,
    firstCardWidth: first ? Math.round(first.width) : null,
    firstCardHeight: first ? Math.round(first.height) : null,
    gridTag: grid ? grid.tagName.toLowerCase() + '.' + String(grid.className).split(/\s+/).slice(0,3).join('.') : null,
    gridDisplay: g ? g.display : null,
    gridCols: g ? g.gridTemplateColumns : null,
    gridWidth: grid ? Math.round(grid.getBoundingClientRect().width) : null,
    circleRow: strip ? [...strip.children].length : 'ROW MISSING',
    emptyNotice: !!notice,
  };
}, label);

for (const order of [['A: Corporate only'], ['B: Embossed then Corporate']]) {
  const p = await b.newPage();
  await p.setViewport({ width: 1280, height: 900 });
  if (!(await load(p))) { console.log(order[0] + ': could not load'); await p.close(); continue; }
  await p.evaluate(() => { document.addEventListener('click', e => e.preventDefault(), true); });

  console.log('\n=== ' + order[0] + ' ===');
  console.log('  before      ', JSON.stringify(await look(p, 'before')));
  if (order[0].startsWith('B')) {
    await clickTab(p, /emboss/);
    await new Promise(r => setTimeout(r, 5000));
    console.log('  after Emboss', JSON.stringify(await look(p, 'emboss')));
  }
  await clickTab(p, /corporate/);
  await new Promise(r => setTimeout(r, 6000));
  console.log('  after Corp  ', JSON.stringify(await look(p, 'corp')));
  await clickTab(p, /framed/);
  await new Promise(r => setTimeout(r, 6000));
  console.log('  after Framed', JSON.stringify(await look(p, 'framed')));
  await p.close();
}
await b.close();
