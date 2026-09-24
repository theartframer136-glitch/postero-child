// M-03: does search find the spellings customers actually type?
//
// Test Run 03, M-03 (Partial), measured 23 Sep:
//   "shiv"            28 results    ✓ partial words work
//   "radah krishna"  112 results    ✓ one wrong word tolerated
//   "krishan"          0            ✗
//   "krisna"           0            ✗
//   "ganpati"          0            ✗
//   "buddah"           0            ✗
//
// For each search, as a first-time visitor, on the product search the report
// used (/?s=…&post_type=product) and on the site search (/?s=…): the result
// count the page states, the product cards on the first page, and the
// "Including results for …" line under the heading.
//
// Checks:
//   1-4  the four ✗ above find pieces, and the line names the word they found
//        ("krishan" → krishna, "krisna" → krishna, "ganpati" → ganesha,
//        "buddah" → buddha)
//   5    "shiv" and "radah krishna" find at least what they did (28, 112)
//   6    correctly spelled words say nothing about other spellings
//   7    a word the catalogue has nothing near ("dinosaur") still finds nothing
//   8    the site search (/?s=krishan) finds the same pieces and says so too
//   9    correct spellings find at least what they did before any of this
//        (run 102: krishna 118, ganesha 16, buddha 21). The first deploy
//        broke this: "ganesha" fell to 15, because the added matches left
//        out a product set "Shop only" that the title search still returns.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-m03.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const results = [];
const say = (ok, what, seen) => { results.push(ok); console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + what.padEnd(60) + seen); };

const look = async (path) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) { await ctx.close(); return null; }
  await page.waitForTimeout(2500);
  const d = await page.evaluate(() => {
    const t = s => String(s || '').replace(/\s+/g, ' ').trim();
    const countText = t((document.querySelector('.woocommerce-result-count, .af-sr-count') || {}).textContent);
    // "Showing 1–12 of 28 results", "Showing all 5 results", "Showing the
    // single result", "28 results — page 1 of 3", "5 results"
    let n = null;
    let m = countText.match(/of\s+([\d,]+)\s+result/i) || countText.match(/all\s+([\d,]+)\s+result/i) || countText.match(/^([\d,]+)\s+result/i);
    if (m) n = parseInt(m[1].replace(/,/g, ''), 10);
    else if (/single result/i.test(countText)) n = 1;
    const cards = document.querySelectorAll('ul.products li.product, .af-sr li.product, .af-sr .product-card').length;
    if (n === null) n = cards;
    return {
      n, cards, countText,
      h1: t((document.querySelector('h1') || {}).textContent).slice(0, 60),
      also: t((document.querySelector('.af-sr-also') || {}).textContent),
      titles: [...document.querySelectorAll('ul.products li.product .woocommerce-loop-product__title, ul.products li.product h2, ul.products li.product h3, .af-sr li.product h2')]
        .slice(0, 3).map(e => t(e.textContent).slice(0, 34)),
    };
  }).catch(() => null);
  await ctx.close();
  return d ? { status: r.status(), ...d } : null;
};
const q = s => '/?s=' + encodeURIComponent(s).replace(/%20/g, '+');
const line = d => d ? (String(d.n).padStart(4) + ' results · ' + (d.also ? '"' + d.also + '"' : 'no note') + (d.titles.length ? ' · ' + d.titles.join(' | ') : '')) : 'no answer';

console.log('verify-m03: ' + SITE + '   ' + new Date().toISOString() + '\n');

const seen = {};
for (const term of ['krishan', 'krisna', 'ganpati', 'buddah', 'shiv', 'radah krishna', 'krishna', 'ganesha', 'buddha', 'dinosaur', 'laxmi', 'elefant', 'radhakrishna']) {
  seen[term] = await look(q(term) + '&post_type=product');
  console.log('  ' + ('"' + term + '"').padEnd(18) + line(seen[term]));
}
console.log('');

for (const [typed, want] of [['krishan', 'krishna'], ['krisna', 'krishna'], ['ganpati', 'ganesha'], ['buddah', 'buddha']]) {
  const d = seen[typed];
  say(!!d && d.n > 0 && new RegExp('“' + want + '”|"' + want + '"').test(d.also),
    (['krishan', 'krisna', 'ganpati', 'buddah'].indexOf(typed) + 1) + '  "' + typed + '" finds pieces and names "' + want + '"', d ? d.n + ' · ' + (d.also || 'no note') : 'no answer');
}
say(!!seen.shiv && !!seen['radah krishna'] && seen.shiv.n >= 28 && seen['radah krishna'].n >= 112,
  '5  "shiv" and "radah krishna" at least 28 and 112, as before', (seen.shiv ? seen.shiv.n : '?') + ' and ' + (seen['radah krishna'] ? seen['radah krishna'].n : '?'));
const quiet = ['krishna', 'ganesha', 'buddha'].filter(k => seen[k] && seen[k].n > 0 && !seen[k].also);
say(quiet.length === 3, '6  correct spellings find pieces with no note about others', ['krishna', 'ganesha', 'buddha'].map(k => k + ' ' + (seen[k] ? seen[k].n + (seen[k].also ? ' "' + seen[k].also + '"' : '') : '?')).join(' · '));
say(!!seen.dinosaur && seen.dinosaur.n === 0 && !seen.dinosaur.also, '7  "dinosaur" still finds nothing', seen.dinosaur ? seen.dinosaur.n + ' · ' + (seen.dinosaur.also || 'no note') : 'no answer');
const site = await look(q('krishan'));
say(!!site && site.n > 0 && /krishna/.test(site.also), '8  the site search /?s=krishan finds pieces and says so too', line(site) + (site ? ' · h1 "' + site.h1 + '"' : ''));

const was = { krishna: 118, ganesha: 16, buddha: 21 };
say(Object.keys(was).every(k => seen[k] && seen[k].n >= was[k]), '9  correct spellings find at least what they did before (118, 16, 21)',
  Object.keys(was).map(k => k + ' ' + (seen[k] ? seen[k].n : '?')).join(' · '));

const right = results.filter(Boolean).length;
console.log('\n' + (right === results.length ? 'M-03 FIXED' : 'M-03 NOT FIXED') + ': ' + right + ' of ' + results.length + ' checks right');
console.log('done ' + new Date().toISOString());
await browser.close();
