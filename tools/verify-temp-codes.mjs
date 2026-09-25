// Do shoppers see the temporary art code on products that have no brochure page?
//
// Every product carries an art code in _taf_art_code: a brochure code (RK-010001-
// 3050 …), one of the three AL codes, or a temporary TMP-1000 … TMP-1305 given by
// tools/assign-temp-artcodes.php on every deploy. The SKU is the code
// (tools/sku-to-artcode.php), so the public Store API tells which products are
// on a temporary one. Until now af_get_art_code() hid TMP codes, so those
// products showed no "Art Code" line at all. Owner, 25 Sep: show them.
//
// As a first-time visitor, checks:
//   1  every TMP product's page shows "Art Code: <its code>"
//   2  on the category grids that hold most of them, every TMP card shows it
//   3  products with a brochure code still show their own code, unchanged
//   4  no product page prints the code twice in its summary
// Read-only: nothing goes in a cart.
//
// Run: node tools/verify-temp-codes.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const TMP = /^TMP-\d+$/i;
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const results = [];
const say = (ok, what, seen) => { results.push(ok); console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + what.padEnd(64) + seen); };
const path = u => { try { return new URL(u, SITE).pathname.replace(/\/+$/, '/'); } catch (e) { return String(u || ''); } };

// JSON from inside the page, so the request is the browser's own (the host's
// bot protection refuses plain HTTP clients)
const api = async (q) => page.evaluate(async (url) => {
  for (let i = 0; i < 3; i++) {
    try {
      const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (r.status === 200) return { status: 200, body: await r.json(), total: +(r.headers.get('X-WP-Total') || 0) };
      if (i === 2) return { status: r.status, body: null };
    } catch (e) { if (i === 2) return { status: 0, body: null }; }
    await new Promise(res => setTimeout(res, 3000));
  }
}, SITE + '/wp-json/wc/store/v1/products?' + q);

console.log('verify-temp-codes: ' + SITE + '   ' + new Date().toISOString() + '\n');
await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
await page.waitForTimeout(1500);

// ── every product the shop lists ─────────────────────────────────────────────
const all = [];
for (let p = 1; p <= 20; p++) {
  const r = await api('per_page=100&page=' + p);
  if (!r || r.status !== 200 || !Array.isArray(r.body)) { console.log('  Store API page ' + p + ': HTTP ' + (r ? r.status : 0)); break; }
  all.push(...r.body);
  if (r.body.length < 100) break;
}
const temp = all.filter(p => TMP.test(p.sku || ''));
const real = all.filter(p => /^[A-Z]{2}-\d{6}-\d{4}[A-Z]?$/.test(p.sku || ''));
console.log('Store API: ' + all.length + ' products · on a temporary code (SKU TMP-…): ' + temp.length + ' · on a brochure code: ' + real.length);

// ── 1 + 4: every TMP product page ────────────────────────────────────────────
const readPage = async (url) => {
  let status = 0;
  for (let a = 0; a < 2; a++) {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
    status = resp ? resp.status() : 0;
    if (status === 200) break;
    await page.waitForTimeout(4000);
  }
  if (status !== 200) return { status };
  await page.waitForTimeout(400);
  const d = await page.evaluate(() => {
    const t = s => String(s || '').replace(/\s+/g, ' ').trim();
    const lines = [...document.querySelectorAll('.af-art-code')].filter(el => !el.classList.contains('af-art-code--empty')).map(el => t(el.textContent));
    const summary = document.querySelectorAll('.summary .af-art-code:not(.af-art-code--empty), .entry-summary .af-art-code:not(.af-art-code--empty)').length;
    return { lines, summary };
  }).catch(() => ({ lines: [], summary: 0 }));
  return { status, ...d };
};
const pageRows = [];
let n = 0;
for (const p of temp) {
  const d = await readPage(p.permalink);
  const want = 'Art Code: ' + p.sku.toUpperCase();
  pageRows.push({ id: p.id, sku: p.sku, name: p.name, status: d.status, shown: (d.lines || []).some(l => l.toUpperCase() === want.toUpperCase()), summary: d.summary || 0, lines: d.lines || [] });
  if (++n % 40 === 0) console.log('  … ' + n + ' product pages opened');
}
const shown = pageRows.filter(r => r.shown);
const twice = pageRows.filter(r => r.summary > 1);
const fails = pageRows.filter(r => r.status !== 200);

// ── 3: a sample of brochure-coded products, spread across the catalogue ─────
const step = Math.max(1, Math.floor(real.length / 15));
const sample = real.filter((_, i) => i % step === 0).slice(0, 15);
const realRows = [];
for (const p of sample) {
  const d = await readPage(p.permalink);
  const code = p.sku.replace(/[A-Z]$/, '');   // a twin's SKU carries a letter; its art code does not
  realRows.push({ id: p.id, sku: p.sku, status: d.status, ok: (d.lines || []).some(l => l.toUpperCase() === ('Art Code: ' + code).toUpperCase()), summary: d.summary || 0, lines: d.lines || [] });
}

// ── 2: the category grids holding most TMP products ─────────────────────────
const catCount = new Map();
for (const p of temp) for (const c of (p.categories || [])) {
  const k = c.link || ''; if (!k) continue;
  catCount.set(k, (catCount.get(k) || 0) + 1);
}
const cats = [...catCount.entries()].sort((a, b) => b[1] - a[1]).map(e => e[0])
  .filter(u => !/deals-discounts|all-art-prints/.test(u)).slice(0, 5);
const tempByPath = new Map(temp.map(p => [path(p.permalink), p.sku.toUpperCase()]));
const cardRows = [];
for (const url of cats) {
  const resp = await page.goto(url, { waitUntil: 'load', timeout: 60000 }).catch(() => null);
  if (!resp || resp.status() !== 200) { cardRows.push({ url, status: resp ? resp.status() : 0, cards: [] }); continue; }
  await page.waitForTimeout(2500);
  const cards = await page.evaluate(() => {
    const t = s => String(s || '').replace(/\s+/g, ' ').trim();
    return [...document.querySelectorAll('ul.products li.product, .product-card')].map(card => {
      const a = card.querySelector('a[href*="/product/"]');
      const lines = [...card.querySelectorAll('.af-art-code--card')].filter(el => !el.classList.contains('af-art-code--empty')).map(el => t(el.textContent));
      return { href: a ? a.getAttribute('href') : '', lines };
    });
  }).catch(() => []);
  cardRows.push({ url, status: 200, cards: cards.filter(c => tempByPath.has(path(c.href))).map(c => ({ ...c, want: 'Art Code: ' + tempByPath.get(path(c.href)) })) });
}
const tempCards = cardRows.flatMap(r => r.cards);
const cardsShown = tempCards.filter(c => c.lines.some(l => l.toUpperCase() === c.want.toUpperCase()));

// ── report ───────────────────────────────────────────────────────────────────
console.log('\n  temporary-code product pages opened: ' + pageRows.length + (fails.length ? ' (' + fails.length + ' did not answer 200: ' + fails.slice(0, 5).map(r => r.id + ' HTTP ' + r.status).join(', ') + ')' : ''));
console.log('  showing "Art Code: TMP-…"          : ' + shown.length + ' of ' + pageRows.length);
const notShown = pageRows.filter(r => r.status === 200 && !r.shown);
for (const r of notShown.slice(0, 12)) console.log('    not shown: #' + r.id + ' ' + r.sku + '  "' + String(r.name).slice(0, 50) + '"' + (r.lines.length ? '  (page shows: ' + r.lines.join(' | ') + ')' : ''));
if (notShown.length > 12) console.log('    … and ' + (notShown.length - 12) + ' more');
console.log('  category grids read: ' + cardRows.map(r => path(r.url) + ' (' + r.cards.length + ' TMP cards)').join(', '));
console.log('  TMP cards showing their code: ' + cardsShown.length + ' of ' + tempCards.length);
for (const c of tempCards.filter(c => !cardsShown.includes(c)).slice(0, 8)) console.log('    card without it: ' + path(c.href) + (c.lines.length ? ' (shows ' + c.lines.join(' | ') + ')' : ''));
console.log('  brochure-code sample: ' + realRows.filter(r => r.ok).length + ' of ' + realRows.length + ' show their own code');
for (const r of realRows.filter(r => !r.ok)) console.log('    #' + r.id + ' ' + r.sku + ' HTTP ' + r.status + ' shows: ' + (r.lines.join(' | ') || 'nothing'));
console.log('');

say(pageRows.length > 0 && shown.length === pageRows.length, '1  every TMP product page shows "Art Code: TMP-…"', shown.length + ' of ' + pageRows.length);
say(tempCards.length > 0 && cardsShown.length === tempCards.length, '2  every TMP card on the category grids shows it', cardsShown.length + ' of ' + tempCards.length);
say(realRows.length > 0 && realRows.every(r => r.ok), '3  brochure-coded products still show their own code', realRows.filter(r => r.ok).length + ' of ' + realRows.length);
say(twice.length === 0 && realRows.every(r => r.summary <= 1), '4  no product page prints the code twice in its summary',
  twice.length ? twice.slice(0, 5).map(r => '#' + r.id).join(' ') : 'none');

const right = results.filter(Boolean).length;
console.log('\n' + (right === results.length ? 'TEMP CODES SHOWN' : 'TEMP CODES NOT SHOWN') + ': ' + right + ' of ' + results.length + ' checks right');
console.log('done ' + new Date().toISOString());
await browser.close();
