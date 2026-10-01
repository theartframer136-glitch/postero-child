// Do the Personal Pic products carry their CO codes?
//
// Owner, 30 Sep: the Personal Pic folder (Final Edited Photos > Based on Excel
// Sheet > Personal Pic) files six pictures as CO-240001 to CO-240006. On 1 Oct
// it holds 25: CO-240007 to CO-240024 are new, and 17 of those 18 are the
// picture of a product on the website (CO-240010 is a picture no product shows).
// Each was matched by picture to its product, all on temporary codes until then
// (tools/artcode-corrections.csv). CO codes are outside the art-code book, like
// AC and CP, so every pass leaves them as written and the SKU is the code.
// This checks, as a first-time visitor, for all 23:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads the CO code (not a card's)
//   - its SKU, from the Store API, is the CO code
//   - its temporary code is no longer in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-co-codes.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 33278, code: 'CO-240001-3825', sku: 'CO-240001-3825', was: 'TMP-1162' },
  { id: 25358, code: 'CO-240002-3050', sku: 'CO-240002-3050', was: 'TMP-1248' },
  { id: 25779, code: 'CO-240003-3050', sku: 'CO-240003-3050', was: 'TMP-1250' },
  { id: 25840, code: 'CO-240004-5030', sku: 'CO-240004-5030', was: 'TMP-1251' },
  { id: 33302, code: 'CO-240005-3050', sku: 'CO-240005-3050', was: 'TMP-1170' },
  { id: 28778, code: 'CO-240006-0000', sku: 'CO-240006-0000', was: 'TMP-1276' },
  // 1 Oct: 17 of the 18 new pictures
  { id: 22564, code: 'CO-240007-2035', sku: 'CO-240007-2035', was: 'TMP-1231' },
  { id: 26023, code: 'CO-240008-3520', sku: 'CO-240008-3520', was: 'TMP-1253' },
  { id: 25596, code: 'CO-240009-3020', sku: 'CO-240009-3020', was: 'TMP-1093' },
  { id: 25021, code: 'CO-240011-3020', sku: 'CO-240011-3020', was: 'TMP-1244' },
  { id: 24470, code: 'CO-240012-3020', sku: 'CO-240012-3020', was: 'TMP-1239' },
  { id: 23496, code: 'CO-240013-3020', sku: 'CO-240013-3020', was: 'TMP-1307' },
  { id: 23911, code: 'CO-240014-3020', sku: 'CO-240014-3020', was: 'TMP-1236' },
  { id: 23435, code: 'CO-240015-3020', sku: 'CO-240015-3020', was: 'TMP-1306' },
  { id: 26145, code: 'CO-240016-3020', sku: 'CO-240016-3020', was: 'TMP-1308' },
  { id: 26267, code: 'CO-240017-0000', sku: 'CO-240017-0000', was: 'TMP-1254' },
  { id: 14034, code: 'CO-240018-3030', sku: 'CO-240018-3030', was: 'TMP-1220' },
  { id: 25657, code: 'CO-240019-3030', sku: 'CO-240019-3030', was: 'TMP-1249' },
  { id: 25297, code: 'CO-240020-3030', sku: 'CO-240020-3030', was: 'TMP-1247' },
  { id: 26328, code: 'CO-240021-0000', sku: 'CO-240021-0000', was: 'TMP-1255' },
  { id: 24094, code: 'CO-240022-0000', sku: 'CO-240022-0000', was: 'TMP-1237' },
  { id: 24714, code: 'CO-240023-0000', sku: 'CO-240023-0000', was: 'TMP-1241' },
  { id: 22686, code: 'CO-240024-0000', sku: 'CO-240024-0000', was: 'TMP-1232' },
];
// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-co-codes: ' + SITE + '   ' + new Date().toISOString() + '\n');

// SKU and address of each, from the Store API, from inside the page (the
// host's bot protection refuses plain HTTP clients).
await go('/');
await page.waitForTimeout(1500);
const all = CHANGED;
const api = await page.evaluate(async ids => {
  const out = {};
  const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&include=' + ids.join(','), { headers: { Accept: 'application/json' } })
    .then(r => r.ok ? r.json() : []).catch(() => []);
  for (const p of r) out[p.id] = { sku: p.sku || '', link: p.permalink || '' };
  return out;
}, all.map(p => p.id)).catch(() => ({}));

const tally = { page: 0, line: 0, sku: 0, gone: 0, all: 0 };
for (const p of all) {
  const a = api[p.id] || { sku: '(not in the Store API)', link: '' };
  const r = a.link ? await go(a.link) : null;
  await page.waitForTimeout(1200);
  const d = r ? await page.evaluate(() => ({
    line: ((document.querySelector('.af-art-code--single') || {}).textContent || '').replace(/\s+/g, ' ').trim(),
    summary: ((document.querySelector('.summary, .entry-summary') || {}).textContent || ''),
  })).catch(() => ({ line: '', summary: '' })) : { line: '', summary: '' };
  const status = r ? r.status() : 0;
  const lineOk = key(d.line) === key(p.code);
  const skuOk = a.sku === p.sku;
  const gone = !key(d.summary).includes(key(p.was));
  const ok = status === 200 && lineOk && skuOk && gone;
  if (status === 200) tally.page++;
  if (lineOk) tally.line++;
  if (skuOk) tally.sku++;
  if (gone) tally.gone++;
  if (ok) tally.all++;
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + 'HTTP ' + status
    + ' · "' + (d.line || '(no Art Code line)') + '" · SKU ' + a.sku + (skuOk ? '' : ' (want ' + p.sku + ')')
    + ' · ' + (gone ? p.was + ' gone' : 'still shows ' + p.was));
}

console.log('\n— the Personal Pic products: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is the CO code   : ' + tally.line);
console.log('  SKU = the CO code                 : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('done ' + new Date().toISOString());
await browser.close();
