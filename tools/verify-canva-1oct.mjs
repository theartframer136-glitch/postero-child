// Do the 15 products on the brochure's 1 Oct pages carry their codes?
//
// Owner, 1 Oct: "i made some changes in the canva please reverify it". The
// Master Brochure gained 14 pages (RK 99-100, LS 19-21, TP 17, MG 5, LR 11,
// HD 32-33, LB 14-15, SA 6, WL 24) and RK 98 now prints a new picture; its old
// one, #31212's, is RK 99. inc/artcode-book.php records the new pages and
// tools/artcode-corrections.csv gives each product the page its picture is on.
// This checks, as a first-time visitor, for all 15:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line names its page (with or without the size)
//   - its SKU, from the Store API, is the code
//   - its old code (a temporary one, or #31212's RK-010098-5040) is no longer
//     in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-canva-1oct.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 25962, code: 'RK-010098-1515', sku: 'RK-010098-1515', was: 'TMP-1252' },
  { id: 31212, code: 'RK-010099-5040', sku: 'RK-010099-5040', was: 'RK-010098-5040' },
  { id: 28595, code: 'RK-010100-5030', sku: 'RK-010100-5030', was: 'TMP-1123' },
  { id: 29023, code: 'LS-030019-5030', sku: 'LS-030019-5030', was: 'TMP-1279' },
  { id: 17856, code: 'LS-030020-5030', sku: 'LS-030020-5030', was: 'TMP-1225' },
  { id: 22077, code: 'LS-030021-4040', sku: 'LS-030021-4040', was: 'TMP-1230' },
  { id: 26084, code: 'TP-050017-3050', sku: 'TP-050017-3050', was: 'TMP-1099' },
  { id: 31588, code: 'MG-060005-5030', sku: 'MG-060005-5030', was: 'TMP-1305' },
  { id: 28300, code: 'LR-070011-5030', sku: 'LR-070011-5030', was: 'TMP-1272' },
  { id: 29639, code: 'HD-080032-5030', sku: 'HD-080032-5030', was: 'TMP-1287' },
  { id: 29690, code: 'HD-080033-6020', sku: 'HD-080033-6020', was: 'TMP-1288' },
  { id: 16191, code: 'LB-090014-3050', sku: 'LB-090014-3050', was: 'TMP-1222' },
  { id: 25124, code: 'LB-090015-3050', sku: 'LB-090015-3050', was: 'TMP-1245' },
  { id: 21771, code: 'SA-100006-3050', sku: 'SA-100006-3050', was: 'TMP-1228' },
  { id: 24409, code: 'WL-170024-3060', sku: 'WL-170024-3060', was: 'TMP-1238' },
];
// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();
// The page the line names, size part off: a line may read "LS - 030019" or
// "LS-030019-5030", both the same page. The SKU check below stays exact.
const pageKey = s => key(s).replace(/^([A-Z]+-\d{6})-\d{4}(?!\d)/, '$1');

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-canva-1oct: ' + SITE + '   ' + new Date().toISOString() + '\n');

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

console.log('\n— the 1 Oct brochure pages: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line names its page   : ' + tally.line);
console.log('  SKU as expected                   : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('done ' + new Date().toISOString());
await browser.close();
