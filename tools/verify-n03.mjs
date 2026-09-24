// N-03: does an empty product search say so, instead of "This collection is
// empty right now."?
//
// Each case is a first-time visitor. The search cases must name the search;
// the shop cases are controls, DEF-17's messages, which must not change:
//   1  /?s=dinosaur&post_type=product              No artwork matches “dinosaur”.
//   2  /?s=zzqxv&post_type=product                 No artwork matches “zzqxv”.
//   3  the same search with a price range          Nothing matches “dinosaur” with
//                                                  those filters; Clear keeps ?s=
//   4  /shop/ with a valid range nothing is in     Nothing here matches those filters.
// On every page: no "collection is empty" on a search, and no art code
// outside a product card.
//
// The empty search used to be "krishan", the misspelling Test Run 03 found
// returning nothing. Since M-03 it finds the Krishna pieces, so "dinosaur",
// which the catalogue has no word near, stands in for it.
//
// Read-only.
//
// Run: node tools/verify-n03.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const cases = [
  { path: '/?s=dinosaur&post_type=product', title: /^No artwork matches “dinosaur”\.$/, button: ['Browse the shop', /\/shop\/?$/] },
  { path: '/?s=zzqxv&post_type=product', title: /^No artwork matches “zzqxv”\.$/, button: ['Browse the shop', /\/shop\/?$/] },
  { path: '/?s=dinosaur&post_type=product&min_price=10&max_price=20', title: /^Nothing matches “dinosaur” with those filters\.$/,
    button: ['Clear the filters', /[?&]s=dinosaur(&|$)/] },
  { path: '/shop/?min_price=90000&max_price=99000', title: /^Nothing here matches those filters\.$/, button: ['Clear the filters', /\/shop\/?$/] },
];

const browser = await chromium.launch({ headless: true });
const results = [];
console.log('verify-n03: ' + SITE + '   ' + new Date().toISOString() + '\n');
for (const c of cases) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const r = await page.goto(SITE + c.path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) { console.log('  NO DATA ' + c.path + '  no answer'); await ctx.close(); continue; }
  await page.waitForTimeout(3500);
  const d = await page.evaluate(() => {
    const t = s => String(s || '').replace(/\s+/g, ' ').trim();
    const box = document.querySelector('.af-empty-filter');
    return {
      title: box ? t((box.querySelector('.af-ef-title') || {}).textContent) : '',
      detail: box ? t((box.querySelector('.af-ef-detail') || {}).textContent) : '',
      buttons: box ? [...box.querySelectorAll('a.af-ef-btn')].map(a => [t(a.textContent), a.href]) : [],
      collection: /collection is empty/i.test(document.body.innerText || ''),
      stray: [...document.querySelectorAll('.af-art-code')].filter(e => e.offsetParent !== null && /\S/.test(t(e.textContent))
        && !e.closest('li.product, .product-card, .trending-card, [class*="type-product"]')).map(e => t(e.textContent)),
      landed: location.pathname + location.search,
    };
  }).catch(() => null);
  if (!d) { console.log('  NO DATA ' + c.path + '  page unreadable'); await ctx.close(); continue; }
  const btn = d.buttons.find(b => b[0] === c.button[0]);
  const search = /[?&]s=/.test(c.path);
  const ok = c.title.test(d.title) && !!btn && c.button[1].test(btn[1]) && !(search && d.collection) && !d.stray.length;
  results.push(ok);
  console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + ' ' + c.path + '   HTTP ' + r.status() + (d.landed !== c.path ? ' → ' + d.landed : ''));
  console.log('          title   "' + d.title + '"');
  console.log('          detail  "' + d.detail + '"');
  console.log('          buttons ' + (d.buttons.map(b => '[' + b[0] + '] → ' + b[1].replace(/^https?:\/\/[^/]+/, '')).join('  ') || 'none'));
  if (search && d.collection) console.log('          "collection is empty" is on the page');
  if (d.stray.length) console.log('          art code outside a card: ' + d.stray.join(', '));
  await ctx.close();
}
const right = results.filter(Boolean).length;
console.log('\n' + (right === cases.length ? 'N-03 FIXED' : 'N-03 NOT FIXED') + ': ' + right + ' of ' + cases.length + ' pages right');
console.log('done ' + new Date().toISOString());
await browser.close();
