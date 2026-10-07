// N-03: what does an empty product search show, and where does the stray art
// code come from?
//
// Test Run 03 saw "This collection is empty right now." and a stray art code
// (e.g. "HD - 080028-4030") on /?s=krishan&post_type=product. The re-check on
// 23 Sep found the copy but no art code INSIDE the empty-state block, and it
// looked nowhere else. This lists every art code on the page, the element it
// was attached to and the heading it sits under, at desktop and phone widths,
// after the page has settled and been scrolled through.
//
// Read-only.
//
// Run: node tools/probe-search-empty.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });

console.log('probe-search-empty: ' + SITE + '   ' + new Date().toISOString());
for (const [w, q] of [[1280, 'krishan'], [390, 'krishan'], [1280, 'zzqxv']]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const r = await page.goto(SITE + '/?s=' + q + '&post_type=product', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) { console.log('\n' + w + 'px "' + q + '": no answer'); await ctx.close(); continue; }
  await page.waitForTimeout(3000);
  for (let y = 0; y < 8; y++) { await page.mouse.wheel(0, 900); await page.waitForTimeout(350); }
  await page.waitForTimeout(3000);
  const d = await page.evaluate(() => {
    const t = s => String(s || '').replace(/\s+/g, ' ').trim();
    const path = el => { const out = []; for (let e = el, i = 0; e && e !== document.body && i < 6; e = e.parentElement, i++) out.push(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 3).join('.') : '')); return out.join(' < '); };
    const headingBefore = el => {
      const all = [...document.querySelectorAll('h1, h2, h3, h4')];
      let last = null;
      for (const h of all) { if (h.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) last = h; }
      return last ? last.tagName.toLowerCase() + ' "' + t(last.textContent).slice(0, 60) + '"' : '-';
    };
    const codes = [...document.querySelectorAll('.af-art-code')].filter(c => c.offsetParent !== null && /\S/.test(t(c.textContent))).map(c => ({
      text: t(c.textContent).slice(0, 40),
      inCard: !!c.closest('li.product, .product-card, .trending-card, [class*="type-product"]'),
      inEmpty: !!c.closest('.af-empty-filter'),
      under: headingBefore(c),
      host: path(c.parentElement),
    }));
    return {
      h1: t((document.querySelector('h1') || {}).textContent).slice(0, 80),
      headings: [...document.querySelectorAll('main h1, main h2, main h3, #main h2, .site-main h2, h2, h3')].slice(0, 8).map(h => h.tagName.toLowerCase() + ' "' + t(h.textContent).slice(0, 50) + '"'),
      empty: t((document.querySelector('.af-empty-filter') || {}).innerText).slice(0, 180),
      woo: t((document.querySelector('.woocommerce-info, .woocommerce-no-products-found') || {}).innerText).slice(0, 120),
      cards: document.querySelectorAll('li.product').length,
      codes,
      body: (document.body.className || '').split(/\s+/).filter(c => /search|archive|shop|woocommerce/.test(c)).join(' '),
    };
  }).catch(e => ({ err: String(e) }));
  console.log('\n' + w + 'px  /?s=' + q + '&post_type=product   HTTP ' + r.status());
  if (d.err) { console.log('  unreadable: ' + d.err); await ctx.close(); continue; }
  console.log('  body     ' + d.body);
  console.log('  h1       "' + d.h1 + '"');
  console.log('  headings ' + d.headings.join(' · '));
  console.log('  empty    ' + (d.empty ? '"' + d.empty + '"' : 'none'));
  console.log('  woo      ' + (d.woo ? '"' + d.woo + '"' : 'none'));
  console.log('  cards    ' + d.cards);
  console.log('  art codes shown: ' + d.codes.length + (d.codes.length ? '' : ' (none)'));
  for (const c of d.codes) {
    console.log('    ' + (c.inCard ? 'in a card  ' : 'NOT IN CARD') + '  "' + c.text + '"  under ' + c.under + (c.inEmpty ? '  [inside the empty-state block]' : ''));
    if (!c.inCard) console.log('                 host: ' + c.host);
  }
  await ctx.close();
}
console.log('\ndone ' + new Date().toISOString());
await browser.close();
