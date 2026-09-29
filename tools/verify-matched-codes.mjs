// Do the eleven products matched to a brochure page carry that page's code?
//
// Owner, 26 Sep: a temporary-code product whose picture is on a page of the
// Canva Master Brochure takes that page's final art code and SKU. Eleven did
// (#346). Each page already had its product on the site, so each code is now
// shared: the owner's-sheet product keeps the plain code as its SKU and the
// new listing takes a letter (tools/artcode-primary.csv). This checks, as a
// first-time visitor, for all eleven and for the ten products they share with:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads the page's code (not a card's)
//   - its SKU, from the Store API, is the code plus its letter (the partner's:
//     the plain code, unchanged)
//   - it no longer shows its temporary code anywhere in the page summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-matched-codes.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const MATCHED = [
  { id: 7781, code: 'SL-150001-4030', sku: 'SL-150001-4030A', was: 'TMP-1204' },
  { id: 7824, code: 'RK-010011-5030', sku: 'RK-010011-5030A', was: 'TMP-1206' },
  { id: 8424, code: 'TA-210001-6030', sku: 'TA-210001-6030A', was: 'TMP-1207' },
  { id: 8494, code: 'TA-210002-6030', sku: 'TA-210002-6030A', was: 'TMP-1210' },
  { id: 11541, code: 'RK-010002-3050', sku: 'RK-010002-3050A', was: 'TMP-1056' },
  { id: 11617, code: 'RK-010002-3050', sku: 'RK-010002-3050B', was: 'TMP-1058' },
  { id: 14678, code: 'RK-010090-5030', sku: 'RK-010090-5030A', was: 'TMP-1221' },
  { id: 17212, code: 'LR-070004-5030', sku: 'LR-070004-5030A', was: 'TMP-1223' },
  { id: 19453, code: 'RK-010044-5030', sku: 'RK-010044-5030A', was: 'TMP-1227' },
  { id: 24592, code: 'RK-010063-4030', sku: 'RK-010063-4030A', was: 'TMP-1083' },
  { id: 24836, code: 'RK-010087-5030', sku: 'RK-010087-5030A', was: 'TMP-1242' },
];
// The products already on those pages: their SKU must not have moved.
const PARTNERS = [
  { id: 8805, code: 'SL-150001-4030' }, { id: 33925, code: 'RK-010011-5030' },
  { id: 34725, code: 'TA-210001-6030' }, { id: 34732, code: 'TA-210002-6030' },
  { id: 11577, code: 'RK-010002-3050' }, { id: 34147, code: 'RK-010090-5030' },
  { id: 34243, code: 'LR-070004-5030' }, { id: 18600, code: 'RK-010044-5030' },
  { id: 34855, code: 'RK-010063-4030' }, { id: 27325, code: 'RK-010087-5030' },
].map(p => ({ ...p, sku: p.code, partner: true }));

// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-matched-codes: ' + SITE + '   ' + new Date().toISOString() + '\n');

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
  if (p === PARTNERS[0]) console.log('\nthe products already on those pages (SKU must be unchanged):');
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

console.log('\n— the eleven: ' + MATCHED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is the page code : ' + tally.line);
console.log('  SKU = the code and its letter     : ' + tally.sku);
console.log('  temporary code no longer shown    : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + MATCHED.length);
console.log('— the ten already on those pages —');
console.log('  page opens, own code, SKU unchanged: ' + ptally.all + ' of ' + PARTNERS.length);
console.log('done ' + new Date().toISOString());
await browser.close();
