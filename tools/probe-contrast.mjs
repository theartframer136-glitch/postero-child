// M-07: which text on the shop is below WCAG AA contrast, grouped by the
// colour pair that causes it, so the fix can go to the colour and not to one
// element at a time.
//
// Test Run 03, M-07: Add to Cart went from 2.74:1 to 6.77:1, but the mini
// cart's CHECKOUT is still 2.74:1, "(29% off)" 2.81:1, "PDF · 41 MB · 358
// PAGES" 2.72:1, and 49 visible texts on one product page are below AA.
//
// For every visible element with its own text: the text colour, the colour
// actually behind it (composited up through its ancestors; text over an image
// or gradient is skipped, as the report's own false positive was), the size
// and weight that decide AA (4.5:1, or 3:1 for 24px+, or 18.66px+ bold), and
// the ratio. Failures are grouped by text colour on background colour, with
// a count, the lowest ratio, sample texts and selectors. Text that is only
// for screen readers (1px boxes) and disabled controls (the out-of-stock
// chips) are counted separately: WCAG does not apply 1.4.3 to either.
//
// Then the items the report named, and the other buttons on the brand gold,
// one by one, each also with :hover forced, because a label that passes at
// rest can fail on the hover fill.
//
// Pages: a product page, the shop, the cart and the checkout, with one item
// in the cart so the cart and mini cart buttons render. The checkout is never
// submitted (wc-ajax=checkout is blocked), and the cart is emptied at the end.
//
// AF_PREVIEW=1 measures this checkout's CSS on top of the live pages, before
// it is deployed, and only in this test browser: custom.css is answered from
// this checkout, and each line this checkout changes in functions.php and
// inc/*.php against main is swapped into the page HTML (the style blocks
// those files print). It says how many of those lines it found in each page.
//
// Run: node tools/probe-contrast.mjs [url]
//      AF_PREVIEW=1 node tools/probe-contrast.mjs [url]

import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCT = '/product/kerala-mural-celebration-canvas-wall-art/';
const NEVER_SEND = ['wc-ajax=checkout', 'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply'];
const PREVIEW = !!process.env.AF_PREVIEW;

// ── preview: this checkout's changes, as line swaps ──────────────────────────
let swaps = [], css = '';
if (PREVIEW) {
  try { execSync('git fetch --depth=1 origin main', { stdio: 'ignore' }); } catch (e) {}
  const diff = execSync('git diff FETCH_HEAD -- functions.php inc', { encoding: 'utf8', maxBuffer: 256e6 });
  let minus = [], plus = [];
  const flush = () => { for (let i = 0; i < Math.min(minus.length, plus.length); i++) if (minus[i].trim().length > 8) swaps.push([minus[i].trim(), plus[i].trim()]); minus = []; plus = []; };
  for (const l of diff.split('\n')) {
    if (l.startsWith('---') || l.startsWith('+++')) continue;
    if (l.startsWith('-')) { if (plus.length) flush(); minus.push(l.slice(1)); }
    else if (l.startsWith('+')) plus.push(l.slice(1));
    else flush();
  }
  flush();
  // As loose as a minifier is: any whitespace around punctuation, either
  // quote, the last ; before } dropped, and any letter case (LiteSpeed
  // minifies the inline CSS of the pages it caches).
  const loose = a => {
    let r = '';
    for (const ch of a) {
      if (/\s/.test(ch)) { if (!r.endsWith('\\s*')) r += '\\s*'; }
      else if (ch === "'" || ch === '"') r += '[\'"]';
      else if (ch === ';') r += '\\s*;?\\s*';
      else if ('{}:,>'.includes(ch)) r += '\\s*\\' + ch + '\\s*';
      else r += ch.replace(/[.*+?^${}()|[\]\\\/!]/g, '\\$&');
    }
    return new RegExp(r, 'gi');
  };
  swaps = swaps.map(([a, b]) => [loose(a), b, a]);
  css = readFileSync(new URL('../assets/css/custom.css', import.meta.url), 'utf8');
}
const seen = new Set();   // the swaps found somewhere

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', async route => {
  const req = route.request();
  const url = req.url();
  const body = (req.postData() || '') + ' ' + url;
  if (NEVER_SEND.some(n => body.includes(n))) return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure"}' });
  if (PREVIEW && /\/postero-child\/assets\/css\/custom\.css/.test(url)) return route.fulfill({ status: 200, contentType: 'text/css', body: css });
  // The page, and the stylesheets and scripts LiteSpeed combines inline code into.
  if (PREVIEW && ['document', 'stylesheet', 'script'].includes(req.resourceType()) && req.method() === 'GET' && url.startsWith(SITE) && !/remove_item|add-to-cart/.test(url)) {
    const r = await route.fetch().catch(() => null);
    if (!r) return route.continue();
    let text = await r.text();
    swaps.forEach(([re, to], i) => { text = text.replace(re, () => { seen.add(i); return to; }); });
    return route.fulfill({ response: r, body: text });
  }
  return route.continue();
});
// The colour maths, in every page this context opens.
await ctx.addInitScript(() => {
  const lin = c => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
  const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const parse = s => { const m = String(s).match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
  const over = (top, under) => { const a = top[3]; return [0, 1, 2].map(i => Math.round(top[i] * a + under[i] * (1 - a))).concat(1); };
  const hex = c => '#' + c.slice(0, 3).map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
  const behind = el => {
    const layers = [];
    for (let e = el; e; e = e.parentElement) {
      const cs = getComputedStyle(e);
      if (cs.backgroundImage && cs.backgroundImage !== 'none') return null;   // image or gradient: not measurable this way
      const bg = parse(cs.backgroundColor);
      if (bg && bg[3] > 0) { layers.push(bg); if (bg[3] >= 1) break; }
    }
    let c = [255, 255, 255, 1];
    for (let i = layers.length - 1; i >= 0; i--) c = over(layers[i], c);
    return c;
  };
  const measure = el => {
    const cs = getComputedStyle(el);
    const fg = parse(cs.color); if (!fg) return null;
    const bg = behind(el); if (!bg) return null;
    const fgc = fg[3] < 1 ? over(fg, bg) : fg;
    const size = parseFloat(cs.fontSize), weight = parseInt(cs.fontWeight, 10) || 400;
    const need = (size >= 24 || (size >= 18.66 && weight >= 700)) ? 3 : 4.5;
    const cr = Math.round(ratio(fgc, bg) * 100) / 100;
    return { fg: hex(fgc), bg: hex(bg), ratio: cr, need, pass: cr >= need };
  };
  // Screen-reader-only text: its own box is 1px, or it is clipped away, here
  // or just above.
  const srOnly = el => {
    const r = el.getBoundingClientRect();
    if (r.width <= 1 || r.height <= 1) return true;
    for (let e = el, i = 0; e && i < 3; e = e.parentElement, i++) {
      const cs = getComputedStyle(e);
      if (/rect\(0(px)?,? 0(px)?,? 0(px)?,? 0(px)?\)/.test(cs.clip) || cs.clipPath === 'inset(50%)') return true;
    }
    return false;
  };
  const disabled = el => !!el.closest(':disabled, [aria-disabled="true"], .af-chip-oos');
  const ownText = el => [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join(' ').replace(/\s+/g, ' ').trim();
  window.__afc = { measure, srOnly, disabled, ownText };
});

const page = await ctx.newPage();
const go = async p => { await page.goto(SITE + p, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null); await page.waitForTimeout(3500); };

// Runs in the page. Includes elements that are styled but not on screen when
// `force` selectors name them (the mini cart's buttons live in a closed drawer).
const audit = (force) => {
  const { measure, srOnly, disabled, ownText } = window.__afc;
  const sel = el => { const out = []; for (let e = el, i = 0; e && e !== document.body && i < 3; e = e.parentElement, i++) out.unshift(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (e.getAttribute('name') ? '[name=' + e.getAttribute('name') + ']' : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '')); return out.join(' > '); };
  const forced = force ? [...document.querySelectorAll(force)] : [];
  const res = [], skipped = { sr: 0, disabled: 0 };
  for (const el of document.querySelectorAll('body *')) {
    if (/^(SCRIPT|STYLE|NOSCRIPT|SVG|PATH|OPTION|TEMPLATE|IFRAME)$/i.test(el.tagName)) continue;
    const own = ownText(el);
    if (!own || !/[A-Za-z0-9$%]/.test(own)) continue;
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const onScreen = r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0 && el.offsetParent !== null;
    const isForced = forced.some(f => f === el || f.contains(el));
    if (!onScreen && !isForced) continue;
    if (onScreen && srOnly(el)) { skipped.sr++; continue; }
    if (disabled(el)) { skipped.disabled++; continue; }
    const m = measure(el); if (!m) continue;
    res.push({ text: own.slice(0, 50), ...m, sel: sel(el), forced: !onScreen });
  }
  return { rows: res, skipped };
};

const totals = [];
const report = (label, { rows, skipped }) => {
  const fails = rows.filter(r => !r.pass);
  totals.push([label, rows.length, fails.length]);
  console.log('\n' + label + ':  ' + rows.length + ' texts measured, ' + fails.length + ' below AA'
    + '   (not counted: ' + skipped.sr + ' screen-reader-only, ' + skipped.disabled + ' disabled)');
  const groups = {};
  for (const f of fails) { const k = f.fg + ' on ' + f.bg; (groups[k] = groups[k] || []).push(f); }
  for (const [k, g] of Object.entries(groups).sort((a, b) => b[1].length - a[1].length)) {
    const min = Math.min(...g.map(x => x.ratio));
    console.log('  ' + String(g.length).padStart(3) + '  ' + k.padEnd(22) + ' ' + min.toFixed(2) + ':1');
    const seen = new Set();
    for (const x of g) { const key = x.text; if (seen.has(key) || seen.size >= 4) continue; seen.add(key);
      console.log('         "' + x.text + '"  ' + x.ratio + ':1 (needs ' + x.need + ')' + (x.forced ? ' [not on screen]' : '') + '\n           ' + x.sel); }
  }
};

// The report's named items and every button on the gold, one by one.
// [label, selector, also measure :hover]
const NAMED = {
  product: [
    ['Add to Cart', '.single_add_to_cart_button', 1],
    ['Buy Now', '.af-buynow', 1],
    ['sticky bar Add to Cart', '.postero-sticky-add-to-cart__content-button', 1],
    ['"(x% off)" under the price', '.af-live-disc', 0],
    ['"(x% off)" in the price', '.af-pct-off', 0],
    ['struck-through price', '.af-live-mrp', 0],
    ['"PDF · 41 MB · 358 pages"', '.taf-broch-note', 0],
    ['breadcrumb', 'nav.woocommerce-breadcrumb', 0],
    ['similar artwork price', '.af-mini-price', 0],
    ['frame fee on a chip', '.af-chip-opt:not(.af-chip-oos) em', 0],
    ['open FAQ question', '.af-faq-item[open] .af-faq-q', 0],
  ],
  minicart: [
    ['mini cart CHECKOUT', '.woocommerce-mini-cart__buttons a.checkout', 1],
    ['mini cart VIEW CART', '.woocommerce-mini-cart__buttons a:not(.checkout)', 1],
  ],
  shop: [
    ['Load More Artworks', '.af-inf-btn', 1],
    ['Enquire (price on request)', '.af-por-btn', 1],
    ['"Price on request"', '.af-por', 0],
    ['card Add to Cart', '.product-card .button', 1],
    ['card struck-through price', 'li.product .price del .amount', 0],
    ['review count', '.count-review .count', 0],
    ['"Show" per page', '.per-page-title', 0],
    ['filter count', '.wc-layered-nav-term .count', 0],
    ['price slider label', '.price_label', 0],
  ],
  cart: [
    ['Proceed to checkout', '.cart_totals .checkout-button', 1],
    ['Apply coupon', 'button[name="apply_coupon"]', 1],
    ['Update cart', 'button[name="update_cart"]', 1],
    ['"You save"', '.af-ct-save th', 0],
    ['gift card Apply', '.af-gc-apply', 1],
  ],
  checkout: [
    ['Place order', '#place_order', 1],
    ['coupon Apply', '.checkout_coupon button[name="apply_coupon"]', 1],
  ],
};
const named = [];
const measureNamed = async (key) => {
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
  const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
  for (const [label, sel, hover] of NAMED[key]) {
    const at = await page.evaluate(s => { const el = document.querySelector(s); if (!el) return null; const m = window.__afc.measure(el); return m && { ...m, text: (el.textContent || el.value || '').replace(/\s+/g, ' ').trim().slice(0, 28) }; }, sel).catch(() => null);
    if (!at) { named.push([key, label, null, {}, hover]); continue; }
    const states = {};
    if (hover) {
      const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel }).catch(() => ({ nodeId: 0 }));
      if (nodeId) {
        for (const [name, forced] of [['hover', ['hover']], ['focus', ['focus', 'focus-visible']]]) {
          await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: forced }).catch(() => {});
          await page.waitForTimeout(450);   // let colour transitions finish
          states[name] = await page.evaluate(s => window.__afc.measure(document.querySelector(s)), sel).catch(() => null);
          await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] }).catch(() => {});
        }
      }
    }
    named.push([key, label, at, states, hover]);
  }
  await cdp.detach().catch(() => {});
};

console.log('probe-contrast: ' + SITE + '   ' + new Date().toISOString() + (PREVIEW ? '\nPREVIEW: this checkout\'s custom.css, and ' + swaps.length + ' changed lines from functions.php and inc/, laid over the live pages in this browser only' : ''));

await go(PRODUCT);
report('product page ' + PRODUCT, await page.evaluate(audit, null));
await measureNamed('product');

await go('/shop/');
report('/shop/', await page.evaluate(audit, null));
await measureNamed('shop');

// one item in the cart, so the cart's and the mini cart's buttons render
await go(PRODUCT);
await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
await page.waitForTimeout(4500);
await go(PRODUCT);
report('product page, with one item in the cart (mini cart included)', await page.evaluate(audit, '.widget_shopping_cart, .woocommerce-mini-cart, .woocommerce-mini-cart__buttons, .mini_cart_content, .site-header-cart'));
await measureNamed('minicart');
await go('/cart/');
report('/cart/', await page.evaluate(audit, null));
await measureNamed('cart');
await go('/checkout/');
report('/checkout/', await page.evaluate(audit, null));
await measureNamed('checkout');

console.log('\nnamed items (ratio, needs, colour on background; then the same with :hover, and :focus, forced)');
const fmt = m => m ? (m.pass ? 'pass ' : 'FAIL ') + (m.ratio.toFixed(2) + ':1').padStart(7) + ' (' + m.need + ')  ' + m.fg + ' on ' + m.bg : 'not measurable (image or gradient behind)';
for (const [key, label, at, states, hover] of named) {
  if (!at) { console.log('  ' + key.padEnd(9) + label.padEnd(30) + 'not on this page'); continue; }
  console.log('  ' + key.padEnd(9) + label.padEnd(30) + fmt(at) + '   "' + at.text + '"');
  if (hover) for (const st of ['hover', 'focus']) console.log(' '.repeat(39) + st.padEnd(7) + fmt(states[st]));
}
const nf = named.filter(([, , at, st]) => (at && !at.pass) || Object.values(st).some(m => m && !m.pass)).length;
console.log('\n' + named.filter(n => n[2]).length + ' named items found, ' + nf + ' below AA at rest, on hover or on focus');

console.log('\ntotals');
for (const [label, n, f] of totals) console.log('  ' + String(f).padStart(4) + ' of ' + String(n).padStart(4) + ' below AA   ' + label);
if (PREVIEW) {
  console.log('\npreview: ' + seen.size + ' of ' + swaps.length + ' changed lines found in the pages and their CSS and JS; not found:');
  swaps.forEach(([, , a], i) => { if (!seen.has(i)) console.log('  ' + a.slice(0, 110)); });
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
