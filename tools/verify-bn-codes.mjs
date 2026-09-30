// Do the five banner products carry their BN codes?
//
// Owner, 30 Sep: five banner pages were added to the Canva brochure, pages
// 391-395, labelled BN-250001-0000 to BN-250005-0000. Each shows one of the five
// banner products, all on temporary codes until now
// (tools/artcode-corrections.csv). BN codes are outside the art-code book, like
// AC, CP and CO, so every pass leaves them as written and the SKU is the code.
// This checks, as a first-time visitor, for all five:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads the BN code (not a card's)
//   - its SKU, from the Store API, is the BN code
//   - its temporary code is no longer in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-bn-codes.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 8643, code: 'BN-250001-0000', sku: 'BN-250001-0000', was: 'TMP-1041' },
  { id: 8646, code: 'BN-250002-0000', sku: 'BN-250002-0000', was: 'TMP-1042' },
  { id: 8649, code: 'BN-250003-0000', sku: 'BN-250003-0000', was: 'TMP-1043' },
  { id: 8653, code: 'BN-250004-0000', sku: 'BN-250004-0000', was: 'TMP-1044' },
  { id: 8656, code: 'BN-250005-0000', sku: 'BN-250005-0000', was: 'TMP-1045' },
];
// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-bn-codes: ' + SITE + '   ' + new Date().toISOString() + '\n');

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

console.log('\n— the five banner products: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is the BN code   : ' + tally.line);
console.log('  SKU = the BN code                 : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('done ' + new Date().toISOString());
await browser.close();
