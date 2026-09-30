// Category tiles in the product grid: what they hold and why they look empty.
//
// Owner, 30 Sep, screenshot of /product-category/banners-signage/: the tiles
// for the subcategories (Backdrops, Banner Stands, ...) sit in the same grid as
// the product cards and show a large blank area. This measures, as a
// first-time visitor on a desktop and a phone, every item of the grid on the
// categories that have subcategories: whether it is a category tile or a
// product, its height, how much of it is filled, and what it contains.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/diag-subcat-tiles.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PAGES = ['/product-category/banners-signage/', '/product-category/art-accessories/', '/shop/'];

const browser = await chromium.launch({ headless: true });
for (const [label, viewport] of [['desktop', { width: 1440, height: 1000 }], ['phone', { width: 390, height: 844 }]]) {
  const page = await (await browser.newContext({ viewport, ignoreHTTPSErrors: true })).newPage();
  for (const path of PAGES) {
    const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
    await page.waitForTimeout(2500);
    const d = await page.evaluate(() => {
      const ul = document.querySelector('ul.products');
      if (!ul) return null;
      const cs = getComputedStyle(ul);
      const items = [...ul.children].filter(li => li.tagName === 'LI').map(li => {
        const b = li.getBoundingClientRect();
        // how far down the tile its last visible child reaches
        let used = 0;
        for (const el of li.querySelectorAll('*')) {
          const e = el.getBoundingClientRect();
          if (e.height > 0 && e.width > 0 && getComputedStyle(el).visibility !== 'hidden') used = Math.max(used, e.bottom - b.top);
        }
        return {
          kind: li.classList.contains('product-category') ? 'CATEGORY' : 'product',
          name: ((li.querySelector('h2, h3, .woocommerce-loop-category__title, .woocommerce-loop-product__title') || {}).textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40),
          top: Math.round(b.top + window.scrollY), h: Math.round(b.height), used: Math.round(used),
          display: getComputedStyle(li).display, dir: getComputedStyle(li).flexDirection,
          price: !!li.querySelector('.price'), brochure: !!li.querySelector('.taf-broch'),
          cls: li.className.slice(0, 80),
        };
      });
      return { grid: { display: cs.display, cols: cs.gridTemplateColumns.split(' ').length, alignItems: cs.alignItems }, items };
    }).catch(() => null);
    console.log(`\n=== ${label}  ${path}  HTTP ${r ? r.status() : 0}`);
    if (!d) { console.log('  no product grid on the page'); continue; }
    console.log(`  grid: display ${d.grid.display}, ${d.grid.cols} columns, align-items ${d.grid.alignItems}`);
    const rows = {};
    for (const it of d.items) (rows[it.top] = rows[it.top] || []).push(it);
    let n = 0;
    for (const top of Object.keys(rows).map(Number).sort((a, b) => a - b)) {
      for (const it of rows[top]) {
        n++;
        const blank = it.h - it.used;
        console.log(`  row@${String(top).padStart(5)}  ${it.kind.padEnd(8)} h ${String(it.h).padStart(4)} used ${String(it.used).padStart(4)} blank ${String(blank).padStart(4)}  ${it.display}/${it.dir}  price:${it.price ? 'y' : 'n'} brochure:${it.brochure ? 'y' : 'n'}  ${it.name}`);
      }
      if (n > 24) { console.log('  ...'); break; }
    }
    const cats = d.items.filter(i => i.kind === 'CATEGORY').length;
    console.log(`  category tiles: ${cats}   products: ${d.items.length - cats}`);
  }
}
await browser.close();
