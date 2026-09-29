// Do the 41 products changed after the Canva of 29 Sep carry their new codes?
//
// Owner, 29 Sep: the website's art codes follow the Canva brochure, matched by
// picture; the Canva code is final (#347). Five products matched a new or
// renumbered page, and the 36 accessories took their category's Art
// Accessories page code (AC-...), the first product of each category keeping
// the plain SKU and the others a letter. This checks, as a first-time visitor,
// for all 41 and for #18964, which shares RK-010004-4040 with #33952:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads the new code (not a card's)
//   - its SKU, from the Store API, is the code plus its letter
//   - it no longer shows its old code anywhere in the page summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-canva-0929.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const MATCHED = [
  { id: 19086, code: 'HD-080031-4040', sku: 'HD-080031-4040', was: 'HD-080008-4040' },
  { id: 24958, code: 'HD-080008-5030', sku: 'HD-080008-5030', was: 'TMP-1243' },
  { id: 31212, code: 'RK-010098-5040', sku: 'RK-010098-5040', was: 'TMP-1300' },
  { id: 7803, code: 'TP-050016-3060', sku: 'TP-050016-3060', was: 'TMP-1205' },
  { id: 33952, code: 'RK-010004-4040', sku: 'RK-010004-4040A', was: 'RK-010021-4030' },
  { id: 8604, code: 'AC-230001-0000', sku: 'AC-230001-0000', was: 'TMP-1035' },
  { id: 8607, code: 'AC-230001-0000', sku: 'AC-230001-0000A', was: 'TMP-1036' },
  { id: 8610, code: 'AC-230001-0000', sku: 'AC-230001-0000B', was: 'TMP-1037' },
  { id: 8276, code: 'AC-230002-0000', sku: 'AC-230002-0000', was: 'TMP-1009' },
  { id: 8337, code: 'AC-230002-0000', sku: 'AC-230002-0000A', was: 'TMP-1010' },
  { id: 8386, code: 'AC-230002-0000', sku: 'AC-230002-0000B', was: 'TMP-1011' },
  { id: 8440, code: 'AC-230003-0000', sku: 'AC-230003-0000', was: 'TMP-1208' },
  { id: 8444, code: 'AC-230003-0000', sku: 'AC-230003-0000A', was: 'TMP-1017' },
  { id: 8447, code: 'AC-230003-0000', sku: 'AC-230003-0000B', was: 'TMP-1209' },
  { id: 8515, code: 'AC-230003-0000', sku: 'AC-230003-0000C', was: 'TMP-1020' },
  { id: 8846, code: 'AC-230004-0000', sku: 'AC-230004-0000', was: 'TMP-1048' },
  { id: 8849, code: 'AC-230004-0000', sku: 'AC-230004-0000A', was: 'TMP-1049' },
  { id: 8853, code: 'AC-230004-0000', sku: 'AC-230004-0000B', was: 'TMP-1217' },
  { id: 8856, code: 'AC-230004-0000', sku: 'AC-230004-0000C', was: 'TMP-1051' },
  { id: 8859, code: 'AC-230004-0000', sku: 'AC-230004-0000D', was: 'TMP-1052' },
  { id: 8862, code: 'AC-230004-0000', sku: 'AC-230004-0000E', was: 'TMP-1053' },
  { id: 8613, code: 'AC-230005-0000', sku: 'AC-230005-0000', was: 'TMP-1038' },
  { id: 8616, code: 'AC-230005-0000', sku: 'AC-230005-0000A', was: 'TMP-1039' },
  { id: 8619, code: 'AC-230005-0000', sku: 'AC-230005-0000B', was: 'TMP-1040' },
  { id: 8571, code: 'AC-230006-0000', sku: 'AC-230006-0000', was: 'TMP-1025' },
  { id: 8576, code: 'AC-230006-0000', sku: 'AC-230006-0000A', was: 'TMP-1026' },
  { id: 8579, code: 'AC-230006-0000', sku: 'AC-230006-0000B', was: 'TMP-1027' },
  { id: 8582, code: 'AC-230007-0000', sku: 'AC-230007-0000', was: 'TMP-1211' },
  { id: 8585, code: 'AC-230007-0000', sku: 'AC-230007-0000A', was: 'TMP-1212' },
  { id: 8588, code: 'AC-230007-0000', sku: 'AC-230007-0000B', was: 'TMP-1213' },
  { id: 8591, code: 'AC-230008-0000', sku: 'AC-230008-0000', was: 'TMP-1214' },
  { id: 8594, code: 'AC-230008-0000', sku: 'AC-230008-0000A', was: 'TMP-1032' },
  { id: 8597, code: 'AC-230008-0000', sku: 'AC-230008-0000B', was: 'TMP-1215' },
  { id: 8601, code: 'AC-230008-0000', sku: 'AC-230008-0000C', was: 'TMP-1034' },
  { id: 8406, code: 'AC-230010-0000', sku: 'AC-230010-0000', was: 'TMP-1012' },
  { id: 8415, code: 'AC-230010-0000', sku: 'AC-230010-0000A', was: 'TMP-1013' },
  { id: 8427, code: 'AC-230010-0000', sku: 'AC-230010-0000B', was: 'TMP-1015' },
  { id: 8518, code: 'AC-230011-0000', sku: 'AC-230011-0000', was: 'TMP-1021' },
  { id: 8521, code: 'AC-230011-0000', sku: 'AC-230011-0000A', was: 'TMP-1022' },
  { id: 8526, code: 'AC-230011-0000', sku: 'AC-230011-0000B', was: 'TMP-1023' },
  { id: 8529, code: 'AC-230011-0000', sku: 'AC-230011-0000C', was: 'TMP-1024' },
];
// The product already on RK-010004-4040: its SKU must not have moved.
const PARTNERS = [{ id: 18964, code: 'RK-010004-4040' }].map(p => ({ ...p, sku: p.code, partner: true }));

// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-canva-0929: ' + SITE + '   ' + new Date().toISOString() + '\n');

// SKU and address of each, from the Store API, from inside the page (the
// host's bot protection refuses plain HTTP clients).
await go('/');
await page.waitForTimeout(1500);
const all = [...MATCHED, ...PARTNERS];
const api = await page.evaluate(async ids => {
  const out = {};
  const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&include=' + ids.join(','), { headers: { Accept: 'application/json' } })
    .then(r => r.ok ? r.json() : []).catch(() => []);
  for (const p of r) out[p.id] = { sku: p.sku || '', link: p.permalink || '' };
  return out;
}, all.map(p => p.id)).catch(() => ({}));

const tally = { page: 0, line: 0, sku: 0, gone: 0, all: 0 };
const ptally = { page: 0, line: 0, sku: 0, all: 0 };
for (const p of all) {
  if (p === PARTNERS[0]) console.log('\nthe product already on RK-010004-4040 (SKU must be unchanged):');
  const a = api[p.id] || { sku: '(not in the Store API)', link: '' };
  const r = a.link ? await go(a.link) : null;
  await page.waitForTimeout(1200);
  const d = r ? await page.evaluate(() => ({
    line: ((document.querySelector('.af-art-code--single') || {}).textContent || '').replace(/\s+/g, ' ').trim(),
    summary: ((document.querySelector('.summary, .entry-summary, main') || {}).textContent || ''),
  })).catch(() => ({ line: '', summary: '' })) : { line: '', summary: '' };
  const status = r ? r.status() : 0;
  const lineOk = key(d.line) === key(p.code);
  const skuOk = a.sku === p.sku;
  const gone = p.partner ? true : !d.summary.includes(p.was);
  const ok = status === 200 && lineOk && skuOk && gone;
  const t = p.partner ? ptally : tally;
  if (status === 200) t.page++;
  if (lineOk) t.line++;
  if (skuOk) t.sku++;
  if (!p.partner && gone) t.gone++;
  if (ok) t.all++;
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + 'HTTP ' + status
    + ' · "' + (d.line || '(no Art Code line)') + '" · SKU ' + a.sku + (skuOk ? '' : ' (want ' + p.sku + ')')
    + (p.partner ? '' : ' · ' + (gone ? p.was + ' gone' : 'still shows ' + p.was)));
}

console.log('\n— the changed products: ' + MATCHED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is the page code : ' + tally.line);
console.log('  SKU = the code and its letter     : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + MATCHED.length);
console.log('— #18964 —');
console.log('  page opens, own code, SKU unchanged: ' + ptally.all + ' of ' + PARTNERS.length);
console.log('done ' + new Date().toISOString());
await browser.close();
