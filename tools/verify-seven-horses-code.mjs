// Does #19025 carry SH-040004-3050, Canva page 126?
//
// Owner, 1 Oct: "check is painting with canva page no 126" for TMP-1226.
// #19025 "Seven Horses Ocean Run" is the page 126 scene, SH-040004-3050: the
// same seven white horses, the same dark cliff on the left; the page's sky has
// a sunburst at the upper right where #19025 has brown mountains. The
// sunburst listing, #23191, was deleted on 29 Sep with #19025 kept as the one
// listing of the pair, so no product held the page and the owner gave it to
// #19025 (tools/artcode-corrections.csv). It holds the code alone, so the SKU
// is the plain code. This checks, as a first-time visitor:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line names page SH-040004 (with or without the size)
//   - its SKU, from the Store API, is SH-040004-3050
//   - its temporary code is no longer in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-seven-horses-code.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 19025, code: 'SH-040004-3050', sku: 'SH-040004-3050', was: 'TMP-1226' },
];
// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();
// The page the line names, size part off: a line may read "SH - 040004" or
// "SH-040004-3050", both Canva page 126. The SKU check below stays exact.
const pageKey = s => key(s).replace(/^([A-Z]+-\d{6})-\d{4}(?!\d)/, '$1');

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-seven-horses-code: ' + SITE + '   ' + new Date().toISOString() + '\n');

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
  const lineOk = d.line !== '' && pageKey(d.line) === pageKey(p.code);
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

console.log('\n— #19025 on Canva page 126: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is SH-040004-3050 : ' + tally.line);
console.log('  SKU as expected                   : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('done ' + new Date().toISOString());
await browser.close();
