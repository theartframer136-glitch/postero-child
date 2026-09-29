// Do the ten products the full Canva check corrected carry their page's code?
//
// Owner, 29 Sep: "check the whole canva properly". Every product picture was
// matched against every page of the brochure as it stands. Ten products held a
// code whose page shows a different picture:
//   - two pairs had traded codes (the white-horse pages SH-040001/SH-040002,
//     and the Krishna pages RK-010059/RK-010075): each takes its own back, and
//     each SKU follows (tools/sku-to-artcode.php now lets a pair swap SKUs);
//   - six show the same picture as a product already holding that page's code:
//     they share it, the product already there keeping the plain SKU and the
//     newcomer taking a letter (tools/artcode-primary.csv).
// #19453 is the picture on RK-010044-5030; #18600 leaves that code, so #19453
// drops its letter and takes the plain SKU.
//
// This checks, as a first-time visitor, for the ten and #19453, and for the
// six products already on the shared pages:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads the page's code (not a card's)
//   - its SKU, from the Store API, is the code plus its letter
//   - its old code is no longer in the product summary
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-canva-recheck.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const CHANGED = [
  { id: 232, code: 'SH-040002-3050', sku: 'SH-040002-3050', was: 'SH-040001-3050' },
  { id: 7662, code: 'SH-040001-3050', sku: 'SH-040001-3050', was: 'SH-040002-3050' },
  { id: 34015, code: 'RK-010075-5030', sku: 'RK-010075-5030', was: 'RK-010059-5030' },
  { id: 34099, code: 'RK-010059-5030', sku: 'RK-010059-5030', was: 'RK-010075-5030' },
  { id: 7769, code: 'PA-120001-4030', sku: 'PA-120001-4030A', was: 'RK-010076-5030' },
  { id: 13355, code: 'RK-010097-3020', sku: 'RK-010097-3020A', was: 'RK-010080-5030' },
  { id: 18600, code: 'RK-010040-5030', sku: 'RK-010040-5030A', was: 'RK-010044-5030' },
  { id: 33945, code: 'TP-050008-5030', sku: 'TP-050008-5030A', was: 'RK-010017-5030' },
  { id: 34003, code: 'RK-010090-5030', sku: 'RK-010090-5030A', was: 'RK-010051-5030' },
  { id: 34601, code: 'WL-170001-4030', sku: 'WL-170001-4030A', was: 'LI-190036-4040' },
  { id: 19453, code: 'RK-010044-5030', sku: 'RK-010044-5030', was: 'RK-010044-5030A', skuOnly: true },
];
// The products already on the shared pages: code and SKU must not have moved.
const PARTNERS = [
  { id: 34305, code: 'PA-120001-4030' }, { id: 34164, code: 'RK-010097-3020' },
  { id: 33993, code: 'RK-010040-5030' }, { id: 34214, code: 'TP-050008-5030' },
  { id: 34147, code: 'RK-010090-5030' }, { id: 7767, code: 'WL-170001-4030' },
].map(p => ({ ...p, sku: p.code, partner: true }));

// The line may read "RK - 010044-5030" or "RK – 010044-5030": compare without
// spaces and with one kind of dash.
const key = s => String(s || '').replace(/^\s*Art Code:\s*/i, '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async url => page.goto(url.startsWith('http') ? url : SITE + url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-canva-recheck: ' + SITE + '   ' + new Date().toISOString() + '\n');

// SKU and address of each, from the Store API, from inside the page (the
// host's bot protection refuses plain HTTP clients).
await go('/');
await page.waitForTimeout(1500);
const all = [...CHANGED, ...PARTNERS];
const api = await page.evaluate(async ids => {
  const out = {};
  const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&include=' + ids.join(','), { headers: { Accept: 'application/json' } })
    .then(r => r.ok ? r.json() : []).catch(() => []);
  for (const p of r) out[p.id] = { sku: p.sku || '', link: p.permalink || '' };
  return out;
}, all.map(p => p.id)).catch(() => ({}));

const tally = { page: 0, line: 0, sku: 0, gone: 0, all: 0 };
const ptally = { all: 0 };
for (const p of all) {
  if (p === PARTNERS[0]) console.log('\nthe products already on the shared pages (code and SKU must be unchanged):');
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
  // #19453 kept its code; only its SKU lost the letter, which the SKU check covers.
  const gone = p.partner || p.skuOnly ? true : !key(d.summary).includes(key(p.was));
  const ok = status === 200 && lineOk && skuOk && gone;
  if (p.partner) { if (ok) ptally.all++; }
  else {
    if (status === 200) tally.page++;
    if (lineOk) tally.line++;
    if (skuOk) tally.sku++;
    if (gone) tally.gone++;
    if (ok) tally.all++;
  }
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + 'HTTP ' + status
    + ' · "' + (d.line || '(no Art Code line)') + '" · SKU ' + a.sku + (skuOk ? '' : ' (want ' + p.sku + ')')
    + (p.partner || p.skuOnly ? '' : ' · ' + (gone ? p.was + ' gone' : 'still shows ' + p.was)));
}

console.log('\n— the ten corrected, and #19453: ' + CHANGED.length + ' —');
console.log('  page opens (HTTP 200)             : ' + tally.page);
console.log('  "Art Code:" line is the page code : ' + tally.line);
console.log('  SKU = the code and its letter     : ' + tally.sku);
console.log('  old code no longer shown          : ' + tally.gone);
console.log('  all four                          : ' + tally.all + ' of ' + CHANGED.length);
console.log('— the six already on the shared pages —');
console.log('  page opens, own code, SKU unchanged: ' + ptally.all + ' of ' + PARTNERS.length);
console.log('done ' + new Date().toISOString());
await browser.close();
