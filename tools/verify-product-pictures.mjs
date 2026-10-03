// Does each product show the main picture inc/product-pictures.php gave it?
//
// Owner, 3 Oct, with the Personal Pic file CO-240006-0000.jpg (the stage
// dancer before the Nataraja and two lamp towers): "update the code and
// picture too", for #33294 only. #33294 "Dance Duet On Stage" takes the file
// as its main picture; #28778 "Dancer on Stage", which carries the same code,
// keeps its own red-stage photo.
//
// This checks, as a first-time visitor, for each product listed:
//   - the Store API's first picture (the main one) is, or is not, the file
//   - its page opens (HTTP 200), and the picture the page leads with (the
//     first gallery picture) and its og:image are, or are not, the file
//   - how many pictures the page shows
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-product-pictures.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHECK = [
  { id: 33294, file: 'co-240006-0000', shows: true,  what: 'takes the file as its main picture' },
  { id: 28778, file: 'co-240006-0000', shows: false, what: 'keeps its own red-stage photo' },
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-product-pictures: ' + SITE + '   ' + new Date().toISOString() + '\n');

// Pictures and address of each, from the Store API, from inside the page (the
// host's bot protection refuses plain HTTP clients).
await go('/');
await page.waitForTimeout(1500);
const api = await page.evaluate(async ids => {
  const out = {};
  const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&include=' + ids.join(','), { headers: { Accept: 'application/json' } })
    .then(r => r.ok ? r.json() : []).catch(() => []);
  for (const p of r) out[p.id] = { link: p.permalink || '', images: (p.images || []).map(i => i.src || '') };
  return out;
}, CHECK.map(p => p.id)).catch(() => ({}));

const base = s => String(s || '').split('?')[0].split('/').pop();
let good = 0;
for (const p of CHECK) {
  const a = api[p.id] || { link: '', images: [] };
  const main = a.images[0] || '';
  const r = a.link ? await go(a.link) : null;
  await page.waitForTimeout(1500);
  const d = r ? await page.evaluate(() => {
    const first = document.querySelector('.woocommerce-product-gallery__image');
    const img = first ? first.querySelector('img') : null;
    return {
      lead: first ? (first.getAttribute('data-thumb') || (img && (img.getAttribute('data-large_image') || img.currentSrc || img.src)) || '') : '',
      og: ((document.querySelector('meta[property="og:image"]') || {}).content || ''),
      count: document.querySelectorAll('.woocommerce-product-gallery__image').length,
    };
  }).catch(() => ({ lead: '', og: '', count: 0 })) : { lead: '', og: '', count: 0 };
  const status = r ? r.status() : 0;
  const has = s => base(s).toLowerCase().startsWith(p.file);
  const apiOk = has(main) === p.shows && main !== '';
  const leadOk = has(d.lead) === p.shows && d.lead !== '';
  const ogOk = d.og === '' ? true : has(d.og) === p.shows;
  const ok = status === 200 && apiOk && leadOk && ogOk;
  if (ok) good++;
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + p.what);
  console.log('         Store API main picture : ' + (base(main) || '(none)') + (apiOk ? '' : '   <- want ' + (p.shows ? '' : 'not ') + p.file));
  console.log('         page HTTP ' + status + ', leads with : ' + (base(d.lead) || '(none)') + (leadOk ? '' : '   <- want ' + (p.shows ? '' : 'not ') + p.file));
  console.log('         og:image                : ' + (base(d.og) || '(none)') + (ogOk ? '' : '   <- want ' + (p.shows ? '' : 'not ') + p.file));
  console.log('         pictures on the page    : ' + d.count + ' (Store API lists ' + a.images.length + ')');
}
console.log('\nright picture: ' + good + ' of ' + CHECK.length);
console.log('done ' + new Date().toISOString());
await browser.close();
