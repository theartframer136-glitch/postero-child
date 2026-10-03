// Which pictures on a page count as "broken" to qa-personas.mjs (loaded, but
// nothing to show), and what they are. Persona 6 flagged the Digital Downloads
// page after the 3 Oct plugin updates without naming a file, i.e. the images
// have no address at all: an empty tag, or a lazy-load stand-in that never got
// its picture. This prints each one's tag, where it sits, and whether it is
// still empty after scrolling the page through.
//
// Read-only.
//
// Run: node tools/diag-empty-images.mjs [site] (AF_QA_ONLY: paths, comma-separated)
import { chromium } from 'playwright';

const SITE = (process.argv.slice(2).find(a => a.includes('://')) || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PATHS = (process.env.AF_QA_ONLY || '/product-category/digital-downloads-2/,/shop/,/').split(',').map(s => s.trim()).filter(Boolean);
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const empty = () => page.evaluate(() => [...document.images]
  .filter(i => i.complete && i.naturalWidth === 0)
  .map(i => {
    let a = i.parentElement, where = [];
    while (a && where.length < 4) { if (a.className && typeof a.className === 'string') where.push(a.tagName.toLowerCase() + '.' + a.className.trim().split(/\s+/).slice(0, 3).join('.')); a = a.parentElement; }
    return { src: i.currentSrc || i.src || '', tag: i.outerHTML.slice(0, 260), where: where.join(' < '), shown: i.getBoundingClientRect().width + 'x' + i.getBoundingClientRect().height };
  }));
console.log('diag-empty-images: ' + SITE + '   ' + new Date().toISOString());
for (const p of PATHS) {
  const r = await page.goto(SITE + p, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  await page.waitForTimeout(2500);
  const first = await empty();
  for (let y = 0; y < 12; y++) { await page.mouse.wheel(0, 900); await page.waitForTimeout(250); }
  await page.waitForTimeout(1500);
  const after = await empty();
  const total = await page.evaluate(() => document.images.length);
  console.log('\n' + p + '  HTTP ' + (r ? r.status() : 0) + '  images ' + total + '  "broken" on load ' + first.length + ', after scrolling ' + after.length);
  for (const e of first) console.log('  ' + (after.some(x => x.tag === e.tag) ? 'still ' : 'filled') + '  src=' + JSON.stringify(e.src) + '  shown ' + e.shown + '\n          ' + e.tag.replace(/\s+/g, ' ') + '\n          in ' + e.where);
}
console.log('\ndone ' + new Date().toISOString());
await browser.close();
