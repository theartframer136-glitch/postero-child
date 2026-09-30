// Does #22505 share RK-010008-3040 with the painting it shows, mirrored?
//
// Owner, 30 Sep: "check is painting with canva page no 12" for TMP-1072.
// #22505 "Sleeping Krishna Art" shows Canva page 12, RK-010008-3040, the
// sleeping Bal Krishna, flipped left to right; flipped back it matches #7819
// "Sleeping Baby Krishna", which carries the code. The owner chose to share
// it (tools/artcode-corrections.csv): #7819 keeps the plain SKU
// (tools/artcode-primary.csv) and #22505's SKU carries the letter. This
// checks, as a first-time visitor, for both:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads RK-010008-3040 (not a card's)
//   - its SKU, from the Store API, is the one expected
//   - #22505's temporary code is no longer in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-sleeping-krishna-code.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 22505, code: 'RK-010008-3040', sku: 'RK-010008-3040A', was: 'TMP-1072' },
  // the painting's own listing: unchanged, it keeps the plain code
  { id: 7819, code: 'RK-010008-3040', sku: 'RK-010008-3040', was: '' },
];
// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-sleeping-krishna-code: ' + SITE + '   ' + new Date().toISOString() + '\n');

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
  const gone = !p.was || !key(d.summary).includes(key(p.was));
  const ok = status === 200 && lineOk && skuOk && gone;
  if (status === 200) tally.page++;
  if (lineOk) tally.line++;
  if (skuOk) tally.sku++;
  if (gone) tally.gone++;
  if (ok) tally.all++;
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + 'HTTP ' + status
    + ' · "' + (d.line || '(no Art Code line)') + '" · SKU ' + a.sku + (skuOk ? '' : ' (want ' + p.sku + ')')
    + ' · ' + (!p.was ? 'no temporary code to lose' : gone ? p.was + ' gone' : 'still shows ' + p.was));
}

console.log('\n— #22505 and the painting it shows: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is RK-010008-3040 : ' + tally.line);
console.log('  SKU as expected                   : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('done ' + new Date().toISOString());
await browser.close();
