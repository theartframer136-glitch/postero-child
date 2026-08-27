// Does Gold Foiled & UV actually appear in "Shop by Collection", and does
// clicking it show the section's products?
//
// This is the check the owner made by hand in their 2026-08-26 recording:
// walk the tab strip with its arrows, look for the premium section, look at
// the circle row underneath. A real Chromium does it here, because the tab may
// be put on the page by the browser (inc/goldfoil-collection.php clones one of
// the theme's own tabs when the theme's list leaves the section out) and no
// amount of fetching HTML would ever see that.
//
// Exits 0 only when the tab is there AND clicking it changes the products.
// Run from the Verify Gold Foil Collection workflow.
import { chromium } from 'playwright';

const SITE = 'https://theartframer.us/?afv=' + Math.floor(Date.now() / 1000);
const WANT = 'goldfoileduv';
const norm = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');

const browser = await chromium.launch();
const page = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-Verify',
}).then((c) => c.newPage());

page.on('pageerror', (e) => console.log('PAGE ERROR:', e.message.slice(0, 200)));

console.log('loading', SITE);
await page.goto(SITE, { waitUntil: 'load', timeout: 120000 });
await page.waitForTimeout(9000);

// ── 1. is the tab on the page at all? ──────────────────────────────────────
const strip = await page.evaluate((want) => {
  const n = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
  const el = document.querySelector('#topCatSlider, .top-category-slider');
  if (!el) return { found: false, reason: 'no tab strip on the page' };
  const tabs = Array.from(el.querySelectorAll('.top-cat-btn'));
  const mine = tabs.find((t) => n(t.textContent) === want);
  return {
    found: !!mine,
    tabs: tabs.map((t) => t.textContent.trim()),
    byTheme: !!(mine && !mine.hasAttribute('data-af-gf')),
    href: mine ? mine.getAttribute('href') : '',
    position: mine ? tabs.indexOf(mine) + 1 : 0,
  };
}, WANT);
console.log('tabs in the strip:', JSON.stringify(strip.tabs));
if (!strip.found) {
  console.log('RESULT: THE SECTION IS NOT IN THE STRIP —', strip.reason || 'no matching tab');
  await page.screenshot({ path: 'gf-before.png', fullPage: false });
  await browser.close();
  process.exit(2);
}
console.log(`tab found at position ${strip.position}, rendered by ${strip.byTheme ? 'the theme itself' : 'the child theme clone'} -> ${strip.href}`);

// ── 2. click it and watch the products ─────────────────────────────────────
const snap = () => page.evaluate(() => {
  const shell = document.querySelector('.af-shell');
  const g = document.querySelector('#productGrid, .product-slider, ul.products');
  const area = shell || g;
  return {
    text: area ? area.innerText.replace(/\s+/g, ' ').slice(0, 160) : '',
    cards: area ? area.querySelectorAll('li.product, .product-card, .woocommerce-loop-product__title').length : 0,
    circles: Array.from(document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content'))
      .filter((s) => s.offsetParent)
      .map((s) => Array.from(s.querySelectorAll('li.cat-item, .sub-cat')).map((c) => c.textContent.trim().slice(0, 24)))[0] || [],
  };
});

const before = await snap();
console.log('before:', JSON.stringify(before));
await page.screenshot({ path: 'gf-before.png' });

await page.evaluate((want) => {
  const n = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
  const t = Array.from(document.querySelectorAll('.top-cat-btn')).find((x) => n(x.textContent) === want);
  if (t) t.scrollIntoView({ block: 'center', inline: 'center' });
}, WANT);
await page.waitForTimeout(900);

const box = await page.evaluate((want) => {
  const n = (s) => (s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');
  const t = Array.from(document.querySelectorAll('.top-cat-btn')).find((x) => n(x.textContent) === want);
  if (!t) return null;
  const r = t.getBoundingClientRect();
  return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
}, WANT);
if (!box) { console.log('RESULT: the tab vanished before it could be clicked'); await browser.close(); process.exit(2); }

await page.mouse.click(box.x, box.y);
await page.waitForTimeout(8000);

const after = await snap();
console.log('after: ', JSON.stringify(after));
await page.screenshot({ path: 'gf-after.png' });

// ── 3. the verdict ─────────────────────────────────────────────────────────
const url = page.url();
const navigated = /gold-foiled-uv/.test(url);
const swapped = after.cards > 0 && after.text !== before.text;
const circlesChanged = JSON.stringify(after.circles) !== JSON.stringify(before.circles);

console.log('circle row now:', JSON.stringify(after.circles));
if (navigated) {
  console.log('RESULT: PASS — the click landed on the section archive', url);
} else if (swapped) {
  console.log(`RESULT: PASS — ${after.cards} product(s) swapped in`
    + (circlesChanged ? ', and the circle row changed with them' : ', but the circle row did not change'));
} else {
  console.log('RESULT: FAIL — the tab is there, but clicking it changed nothing');
  await browser.close();
  process.exit(3);
}
await browser.close();
