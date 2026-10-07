// M-07: where each failing colour comes from, so it can be fixed at its
// source rather than element by element.
//
// probe-contrast.mjs (run 95, 23 Sep) grouped the texts below AA by colour
// pair. Most of those colours are not in this repository, so they come from
// the parent theme, Elementor or WooCommerce. For one example of each failing
// text this asks the browser, through the DevTools protocol, which rule wins
// its `color` (and, for buttons, its `background-color`): the value as
// written (a hex, or var(--something)), the selector, !important, and the
// stylesheet it lives in. It also lists every custom property on :root whose
// value is a colour, which is what a fix "at the token" would change.
//
// SAFETY: one item goes in the cart so the cart and mini cart render. The
// checkout is never submitted (wc-ajax=checkout is blocked), and the cart is
// emptied at the end.
//
// Run: node tools/diag-contrast-cascade.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCT = '/product/kerala-mural-celebration-canvas-wall-art/';
const NEVER_SEND = ['wc-ajax=checkout', 'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply'];
const PAGES = [
  [PRODUCT, ['.af-mini-price ins .amount', '.af-mini-price del .amount', 'nav.woocommerce-breadcrumb', 'nav.woocommerce-breadcrumb a',
    '.count-review .count', '.af-pct-off', '.af-live-disc', '.af-live-mrp', 'p.price del .amount', '.af-chip-opt em', '.af-ppt small',
    '.product-themes a', '.taf-broch-note', '.af-faq-q', '.af-pp-sub', '.postero-sticky-add-to-cart__content-button',
    '.woocommerce-mini-cart__buttons .checkout', '.af-ck-main p a', '.af-ck-opts small']],
  ['/shop/', ['.af-inf-btn', '.per-page-title', '.wc-layered-nav-term .count', '.af-por', '.af-por-btn', '.price_label',
    '.elementor-cta__description', '.c-primary', 'li.product .price del .amount']],
  ['/cart/', ['.checkout-button', 'td.actions .button', '.af-ct-save th', '.af-gc-apply']],
  ['/checkout/', ['#place_order']],
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (NEVER_SEND.some(n => body.includes(n))) return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure"}' });
  return route.continue();
});
const page = await ctx.newPage();
const go = async p => { await page.goto(SITE + p, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null); await page.waitForTimeout(3500); };

console.log('diag-contrast-cascade: ' + SITE + '   ' + new Date().toISOString());

// one item in the cart
await go(PRODUCT);
await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
await page.waitForTimeout(4500);

const winner = (rules, prop) => {
  // matchedCSSRules come in cascade order, lowest first: a later rule beats an
  // earlier one of the same importance, and !important beats normal.
  let best = null;
  for (const m of rules) {
    for (const p of (m.rule.style.cssProperties || [])) {
      if (p.name !== prop || p.disabled || !p.value) continue;
      const cand = { imp: !!p.important, value: p.value, sel: m.rule.selectorList.text, sheet: m.rule.styleSheetId, origin: m.rule.origin };
      if (!best || cand.imp || !best.imp) best = cand;
    }
  }
  return best;
};

for (const [path, sels] of PAGES) {
  await go(path);
  const cdp = await ctx.newCDPSession(page);
  const sheets = {};
  cdp.on('CSS.styleSheetAdded', e => { sheets[e.header.styleSheetId] = e.header.sourceURL || (e.header.isInline ? '<style> in page' : '(no url)'); });
  await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
  const { root } = await cdp.send('DOM.getDocument', { depth: -1 });
  console.log('\n' + path);
  if (path === PRODUCT) {
    const vars = await page.evaluate(() => {
      const out = {};
      for (const sh of document.styleSheets) {
        let rules; try { rules = sh.cssRules; } catch (e) { continue; }
        for (const r of rules || []) {
          if (!r.style || !/(^|,)\s*(:root|html|body)\b/.test(r.selectorText || '')) continue;
          for (const n of r.style) if (n.startsWith('--')) { const v = r.style.getPropertyValue(n).trim(); if (/^#[0-9a-f]{3,8}$|^rgba?\(/i.test(v)) out[n] = v; }
        }
      }
      return out;
    }).catch(() => ({}));
    console.log('  colour variables on :root: ' + (Object.keys(vars).length ? Object.entries(vars).map(([k, v]) => k + '=' + v).join('  ') : 'none'));
  }
  for (const sel of sels) {
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel }).catch(() => ({ nodeId: 0 }));
    if (!nodeId) { console.log('  ' + sel.padEnd(44) + ' (not on this page)'); continue; }
    const m = await cdp.send('CSS.getMatchedStylesForNode', { nodeId }).catch(() => null);
    const cs = await cdp.send('CSS.getComputedStyleForNode', { nodeId }).catch(() => ({ computedStyle: [] }));
    const comp = n => (cs.computedStyle.find(x => x.name === n) || {}).value || '';
    if (!m) { console.log('  ' + sel.padEnd(44) + ' (no styles)'); continue; }
    const own = [...(m.matchedCSSRules || [])];
    if (m.inlineStyle) own.push({ rule: { style: m.inlineStyle, selectorList: { text: 'style=""' }, styleSheetId: 'inline', origin: 'regular' } });
    let col = winner(own, 'color'), via = '';
    if (!col) {
      for (const inh of (m.inherited || [])) {
        const r = [...(inh.matchedCSSRules || [])];
        if (inh.inlineStyle) r.push({ rule: { style: inh.inlineStyle, selectorList: { text: 'style=""' }, styleSheetId: 'inline', origin: 'regular' } });
        col = winner(r, 'color'); if (col) { via = ' (inherited)'; break; }
      }
    }
    const bg = winner(own, 'background-color') || winner(own, 'background');
    const src = w => w ? '"' + w.sel.slice(0, 70) + '" → ' + w.value + (w.imp ? ' !important' : '') + '  [' + (w.sheet === 'inline' ? 'inline' : (sheets[w.sheet] || w.origin || '?').replace(SITE, '').replace(/\?.*$/, '')) + ']' : '-';
    console.log('  ' + sel);
    console.log('      color ' + comp('color') + via + '  ' + src(col));
    if (bg) console.log('      bg    ' + comp('background-color') + '  ' + src(bg));
  }
  await cdp.detach().catch(() => {});
}

for (let i = 0; i < 6; i++) {
  await go('/cart/');
  const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
  if (!h) break;
  await page.goto(h, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForTimeout(1500);
}
console.log('\ncart emptied · done ' + new Date().toISOString());
await browser.close();
