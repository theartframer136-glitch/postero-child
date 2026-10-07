// DEF-16 follow-up: which rules the live checkout actually obeys.
//
// After DEF-16 deployed, probe-checkout-layout.mjs measured:
//   table-layout            auto → fixed        the new base rule is live
//   790 px, total clipped   28 px → 0
//   320 px, total clipped   57 px → 57 px       table still 311 px wide inside 218
//   790–1100 px             still two columns, card field still 157–288 px
// So the rules outside a media query apply and the two @media blocks do not,
// although both are in the file on main, well-formed, and written with
// !important where they meet the parent theme's floats.
//
// It also turned up an older puzzle. The C-01 fix (21 Sep) already put
// table-layout:fixed in checkout.css, yet the pre-deploy run measured auto.
// So until this deploy the checkout was not getting the checkout.css this
// repo ships, and it may still be getting some other copy alongside it.
//
// This asks the browser instead of guessing:
//   1. every stylesheet the checkout loads whose URL mentions checkout, with
//      its size, its ?ver, and whether it holds the C-01 and DEF-16 blocks,
//      set against assets/css/checkout.css in this checkout of the repo
//   2. at 790 px, every rule that sets float / width on #customer_details
//      and #order_review, in cascade order, with its stylesheet, line, media
//      query and !important, through the DevTools protocol, which names the
//      rule that wins rather than only the value it produced
//   3. at 320 px, the element inside the summary table that holds it at
//      311 px, and the rules behind its white-space and min-width
//
// SAFETY: one item is added to the cart; wc-ajax=checkout is in NEVER_SEND,
// so no order can be placed, and Place Order is never clicked. The cart is
// emptied at the end.
//
// Run: node tools/diag-checkout-cascade.mjs [url]

import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save'];
const MARKS = [
  ['C-01 block', /table-layout:fixed drops that floor/],
  ['DEF-16 block', /DEF-16: the order summary never clips/],
  ['769–1149 media', /min-width:\s*769px\)\s*and\s*\(max-width:\s*1149px/],
  ['<500 media', /max-width:\s*499px/],
];

let repoCss = '';
try { repoCss = readFileSync(new URL('../assets/css/checkout.css', import.meta.url), 'utf8'); } catch (e) {}

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure","messages":"blocked by probe"}' });
  }
  return route.continue();
});
const page = await ctx.newPage();
const go = async (path, wait = 3000) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

console.log('diag-checkout-cascade: ' + SITE + '   ' + new Date().toISOString() + '\n');

// one buyable item into the cart, as probe-checkout-layout does
await go('/shop/', 2500);
const product = await page.evaluate(() => {
  const c = [...document.querySelectorAll('li.product, .product-card, .product')]
    .find(x => /\$\s?[\d,.]+/.test(x.innerText || '') && !/price on request/i.test(x.innerText || '') && x.querySelector('a[href*="/product/"]'));
  return c ? c.querySelector('a[href*="/product/"]').href.split('?')[0] : '';
}).catch(() => '');
let pid = 0;
if (product) {
  await go(product, 2500);
  pid = await page.evaluate(() => {
    const h = document.querySelector('input[name="add-to-cart"]'), b = document.querySelector('button[name="add-to-cart"], .single_add_to_cart_button');
    const m = ((document.body && document.body.className) || '').match(/postid-(\d+)/);
    return Number((h && h.value) || (b && b.value) || (m && m[1]) || 0);
  }).catch(() => 0);
  if (pid) await go(product + '?add-to-cart=' + pid + '&quantity=1', 3500);
}
console.log('item: ' + (product ? product.replace(SITE, '') + ' (id ' + pid + ')' : 'NONE FOUND') + '\n');

// ── 1. the stylesheets ─────────────────────────────────────────────────────
await page.setViewportSize({ width: 790, height: 900 });
const st = await go('/checkout/', 4500);
console.log('— 1. stylesheets on /checkout/ (HTTP ' + st + ') —');
const links = await page.evaluate(() => [...document.querySelectorAll('link[rel="stylesheet"], link[rel="preload"][as="style"], style[data-href]')]
  .map(l => ({ href: l.href || l.getAttribute('data-href') || '', media: l.media || '', id: l.id || '' }))).catch(() => []);
const lsBundles = links.filter(l => /\/litespeed\/(css|ucss)\//.test(l.href));
console.log('  <link> stylesheets: ' + links.length + ', LiteSpeed bundles among them: ' + lsBundles.length);
const want = links.filter(l => /checkout/i.test(l.href) || /\/litespeed\/(css|ucss)\//.test(l.href));
for (const l of want) {
  let body = '', status = 0;
  try { const r = await page.request.get(l.href, { timeout: 30000 }); status = r.status(); body = await r.text(); } catch (e) {}
  const has = MARKS.filter(([, re]) => re.test(body)).map(([n]) => n);
  if (!/checkout/i.test(l.href) && !has.length) continue;   // a bundle without our file in it
  console.log('  ' + l.href.replace(SITE, '').slice(0, 110) + (l.media && l.media !== 'all' ? '   media="' + l.media + '"' : '') + (l.id ? '   #' + l.id : ''));
  console.log('      HTTP ' + status + '   ' + body.length + ' bytes   holds: ' + (has.join(', ') || 'none of the markers'));
}
if (repoCss) console.log('  repo assets/css/checkout.css: ' + repoCss.length + ' bytes   holds: ' + MARKS.filter(([, re]) => re.test(repoCss)).map(([n]) => n).join(', '));
const inlineHit = await page.evaluate(() => [...document.querySelectorAll('style')].filter(s => /woocommerce-checkout-review-order-table/.test(s.textContent || '')).map(s => (s.id || '(no id)') + ' ' + (s.textContent || '').length + 'B')).catch(() => []);
console.log('  inline <style> blocks touching the summary table: ' + (inlineHit.join(', ') || 'none'));

// ── the cascade, from the DevTools protocol ────────────────────────────────
const cdp = await ctx.newCDPSession(page);
const sheets = new Map();   // styleSheetId -> sourceURL
cdp.on('CSS.styleSheetAdded', e => { sheets.set(e.header.styleSheetId, (e.header.sourceURL || '(inline)').replace(SITE, '')); });
await cdp.send('DOM.enable');
await cdp.send('CSS.enable');

const cascade = async (selector, props) => {
  const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
  const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector });
  if (!nodeId) { console.log('  ' + selector + ': not on the page'); return; }
  const m = await cdp.send('CSS.getMatchedStylesForNode', { nodeId });
  for (const prop of props) {
    // read from the page: Chromium reports white-space only as its longhands
    const val = await page.evaluate(([s, p]) => { const el = document.querySelector(s); return el ? getComputedStyle(el).getPropertyValue(p) : '?'; }, [selector, prop]).catch(() => '?');
    console.log('  ' + selector + '  ' + prop + ' = ' + val);
    const rows = [], seenRow = new Set();
    const add = (important, order, text) => { if (seenRow.has(text)) return; seenRow.add(text); rows.push({ important, order, text }); };
    const fmt = p => p.value.replace(/\s*!important\s*$/i, '') + (p.important ? ' !important' : '');
    if (m.inlineStyle) for (const p of m.inlineStyle.cssProperties || []) if (p.name === prop && p.value) add(!!p.important, 1e6, 'style=""  ' + fmt(p));
    // matchedCSSRules come in cascade order, lowest precedence first
    (m.matchedCSSRules || []).forEach((r, i) => {
      for (const p of r.rule.style.cssProperties || []) {
        if (p.name !== prop || !p.value || p.disabled) continue;
        const src = r.rule.origin === 'regular' ? (sheets.get(r.rule.styleSheetId) || '?') : r.rule.origin;
        const line = r.rule.style.range ? ':' + (r.rule.style.range.startLine + 1) : '';
        const media = (r.rule.media || []).map(x => '@media ' + x.text).join(' ');
        add(!!p.important, i, fmt(p).padEnd(20) + '  ' + r.rule.selectorList.text.slice(0, 68).padEnd(70) + src.split('?')[0].slice(-60) + line + (media ? '   ' + media : ''));
      }
    });
    // !important beats everything normal; within each, the later rule wins
    rows.sort((a, b) => (b.important - a.important) || (b.order - a.order));
    rows.slice(0, 6).forEach((t, i) => console.log('      ' + (i === 0 ? 'wins → ' : '        ') + t.text));
    if (!rows.length) console.log('      (no author rule — browser default)');
  }
};

// ── 2. the columns at 790 ──────────────────────────────────────────────────
console.log('\n— 2. the columns at 790 px (viewport inner width ' + await page.evaluate(() => innerWidth) + ', matches the 769–1149 query: '
  + await page.evaluate(() => matchMedia('(min-width: 769px) and (max-width: 1149px)').matches) + ') —');
await cascade('#customer_details', ['float', 'width']);
await cascade('#order_review', ['float', 'width', 'position']);

// ── 3. the 311 px floor at 320 ─────────────────────────────────────────────
await page.setViewportSize({ width: 320, height: 900 });
await go('/checkout/', 4500);
console.log('\n— 3. the summary table at 320 px (matches the <500 query: ' + await page.evaluate(() => matchMedia('(max-width: 499px)').matches) + ') —');
const floor = await page.evaluate(() => {
  const t = document.querySelector('.woocommerce-checkout-review-order-table');
  if (!t) return null;
  const tr = t.getBoundingClientRect();
  const out = [...t.querySelectorAll('*')].map(el => {
    const b = el.getBoundingClientRect(), cs = getComputedStyle(el);
    return { el, w: Math.round(b.width), right: Math.round(b.right), ws: cs.whiteSpace, mw: cs.minWidth, disp: cs.display };
  }).filter(x => x.w > 0).sort((a, b) => b.right - a.right);
  const tag = el => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
  return {
    table: { w: Math.round(tr.width), sw: t.scrollWidth, display: getComputedStyle(t).display, layout: getComputedStyle(t).tableLayout },
    widest: out.slice(0, 5).map(x => ({ tag: tag(x.el), w: x.w, right: x.right, ws: x.ws, mw: x.mw, disp: x.disp, text: (x.el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40) })),
    marker: (() => { const x = out[0]; if (!x) return ''; x.el.setAttribute('data-af-widest', '1'); return '[data-af-widest]'; })(),
  };
}).catch(e => ({ error: String(e.message).slice(0, 100) }));
if (!floor || floor.error) console.log('  NO DATA ' + (floor && floor.error || '(no summary table)'));
else {
  console.log('  table: ' + floor.table.w + ' px, scrollWidth ' + floor.table.sw + ', display ' + floor.table.display + ', table-layout ' + floor.table.layout);
  console.log('  what reaches furthest right:');
  floor.widest.forEach(x => console.log('    ' + x.tag.padEnd(40) + ' w ' + String(x.w).padEnd(5) + ' right ' + String(x.right).padEnd(5) + ' white-space ' + x.ws.padEnd(8) + ' min-width ' + x.mw.padEnd(6) + ' ' + x.disp.padEnd(12) + ' "' + x.text + '"'));
  if (floor.marker) await cascade(floor.marker, ['white-space', 'min-width', 'width']);
  await cascade('.woocommerce-checkout-review-order-table', ['display', 'table-layout', 'width']);
}

// empty the cart
for (let i = 0; i < 6; i++) {
  await go('/cart/', 1500);
  const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
  if (!h) break;
  await go(h, 1200);
}
await browser.close();
console.log('\ndone ' + new Date().toISOString());
