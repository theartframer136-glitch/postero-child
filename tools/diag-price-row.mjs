// Dump how a product card's price area is really built and laid out on the
// LIVE site, so the three-line price ($80.00 / struck / (34% off)) can be
// fixed from evidence instead of guesswork. Read-only.
import { chromium } from 'playwright';

const SITE = 'https://theartframer.us/?afv=' + Math.floor(Date.now() / 1000);
const browser = await chromium.launch();
const page = await (await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-Verify',
})).newPage();

await page.goto(SITE, { waitUntil: 'load', timeout: 120000 });
await page.waitForTimeout(9000);

const dump = await page.evaluate(() => {
  const out = [];
  const dels = Array.from(document.querySelectorAll('del, .old-price')).slice(0, 3);
  for (const del of dels) {
    const row = del.parentElement;
    const card = del.closest('.product-card, li.product, .trending-card') || row;
    const cs = el => { const s = getComputedStyle(el); return {
      display: s.display, width: s.width, flexBasis: s.flexBasis, flexWrap: s.flexWrap,
      float: s.float, position: s.position, margin: s.margin, lineHeight: s.lineHeight,
      fontSize: s.fontSize, whiteSpace: s.whiteSpace }; };
    const rect = el => { const r = el.getBoundingClientRect(); return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }; };

    out.push({
      cardClass: (card.className || '').toString().slice(0, 80),
      // everything between the title and the buttons, so we see what shares
      // the price's container (the Digital Download badge is a suspect)
      priceAreaHTML: (row.outerHTML || '').replace(/\s+/g, ' ').slice(0, 1400),
      rowTag: row.tagName + '.' + (row.className || '').toString().slice(0, 70),
      rowHasAfPricerow: row.classList.contains('af-pricerow'),
      rowStyle: cs(row),
      rowRect: rect(row),
      children: Array.from(row.children).slice(0, 8).map(c => ({
        tag: c.tagName + '.' + (c.className || '').toString().slice(0, 45),
        text: (c.innerText || '').replace(/\s+/g, ' ').slice(0, 30),
        style: cs(c),
        rect: rect(c),
      })),
      // what the parent does to the row (a grid/column parent puts every
      // child on its own line no matter what the row itself says)
      parentTag: row.parentElement ? row.parentElement.tagName + '.' + (row.parentElement.className || '').toString().slice(0, 60) : 'none',
      parentStyle: row.parentElement ? cs(row.parentElement) : null,
      parentChildren: row.parentElement ? Array.from(row.parentElement.children).slice(0, 10).map(c =>
        c.tagName + '.' + (c.className || '').toString().slice(0, 35) + ' [' + (c.innerText || '').replace(/\s+/g, ' ').slice(0, 25) + ']') : [],
    });
  }
  return out;
});

console.log(JSON.stringify(dump, null, 1));

// visual proof of one card
const card = await page.$('.product-card, li.product');
if (card) { await card.scrollIntoViewIfNeeded(); await page.waitForTimeout(500); await card.screenshot({ path: 'card.png' }).catch(() => {}); }
await browser.close();
