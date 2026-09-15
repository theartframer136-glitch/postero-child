/**
 * Does the Grid/Masonry toggle actually change anything?
 *
 * The owner reports that picking either one leaves the page looking the same.
 * A screenshot cannot settle that — the two can look alike at a glance — so
 * this clicks each button and measures where the cards actually sit.
 *
 * Masonry means cards no longer share a baseline: their tops stagger. Grid
 * means every card in a row starts at the same y. Counting distinct top
 * offsets tells the two apart without anyone squinting.
 *
 * Read-only. Usage: node tools/diag-layout.js [url] [width]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/product-category/digital-canvas-prints/';
const WIDTH = parseInt(process.argv[3] || '1280', 10);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function shape() {
  const ul = document.querySelector('ul.products');
  if (!ul) return { error: 'no ul.products on the page' };
  const cs = getComputedStyle(ul);
  const tops = [], lefts = [];
  ul.querySelectorAll('li.product').forEach((li) => {
    const r = li.getBoundingClientRect();
    tops.push(Math.round(r.top));
    lefts.push(Math.round(r.left));
  });
  return {
    classes: ul.className,
    display: cs.display,
    cols: cs.gridTemplateColumns,
    autoRows: cs.gridAutoRows,
    cards: tops.length,
    distinctTops: new Set(tops).size,
    distinctLefts: new Set(lefts).size,
    firstTops: tops.slice(0, 8),
    // Does any card carry the row-span the masonry packing writes?
    spans: Array.from(ul.querySelectorAll('li.product'))
      .slice(0, 6).map((li) => li.style.gridRowEnd || '(none)'),
  };
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: 1000 });
    await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(9000);                       // the page reloads itself once

    const toggle = await page.evaluate(() => {
      const t = document.querySelector('.af-layout-toggle');
      if (!t) return 'THE TOGGLE IS NOT ON THE PAGE';
      return 'toggle present: ' + Array.from(t.querySelectorAll('button'))
        .map((b) => b.dataset.mode + (b.classList.contains('on') ? '(on)' : '')).join(' ');
    });
    console.log(toggle);

    for (const mode of ['grid', 'masonry', 'grid']) {
      const clicked = await page.evaluate((m) => {
        const b = document.querySelector('.af-layout-toggle button[data-mode="' + m + '"]');
        if (!b) return 'no ' + m + ' button';
        b.click();
        return 'clicked ' + m;
      }, mode);
      await sleep(2500);
      const s = await page.evaluate(shape);
      console.log(`\n--- ${clicked} ---`);
      if (s.error) { console.log('  ' + s.error); continue; }
      console.log(`  ul.products class: ${s.classes}`);
      console.log(`  display: ${s.display}   columns: ${s.cols}   grid-auto-rows: ${s.autoRows}`);
      console.log(`  ${s.cards} cards, ${s.distinctLefts} distinct left edges, ${s.distinctTops} distinct tops`);
      console.log(`  first tops: ${s.firstTops.join(', ')}`);
      console.log(`  row spans written: ${s.spans.join(', ')}`);
    }
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
