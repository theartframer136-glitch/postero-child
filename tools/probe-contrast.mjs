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
// a count, the lowest ratio, sample texts and selectors.
//
// Pages: a product page, the shop, the cart and the checkout, with one item
// in the cart so the cart and mini cart buttons render. The checkout is never
// submitted (wc-ajax=checkout is blocked), and the cart is emptied at the end.
//
// Run: node tools/probe-contrast.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCT = '/product/kerala-mural-celebration-canvas-wall-art/';
const NEVER_SEND = ['wc-ajax=checkout', 'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply'];

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

// Runs in the page. Includes elements that are styled but not on screen when
// `force` selectors name them (the mini cart's buttons live in a closed drawer).
const audit = (force) => {
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
  const sel = el => { const out = []; for (let e = el, i = 0; e && e !== document.body && i < 3; e = e.parentElement, i++) out.unshift(e.tagName.toLowerCase() + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '')); return out.join(' > '); };
  const forced = new Set(force ? [...document.querySelectorAll(force)] : []);
  const res = [];
  for (const el of document.querySelectorAll('body *')) {
    if (/^(SCRIPT|STYLE|NOSCRIPT|SVG|PATH|OPTION|TEMPLATE|IFRAME)$/i.test(el.tagName)) continue;
    const own = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join(' ').replace(/\s+/g, ' ').trim();
    if (!own || !/[A-Za-z0-9$%]/.test(own)) continue;
    const cs = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const onScreen = r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0 && el.offsetParent !== null;
    const isForced = [...forced].some(f => f === el || f.contains(el));
    if (!onScreen && !isForced) continue;
    const fg = parse(cs.color); if (!fg) continue;
    const bg = behind(el); if (!bg) continue;
    const fgc = fg[3] < 1 ? over(fg, bg) : fg;
    const size = parseFloat(cs.fontSize), weight = parseInt(cs.fontWeight, 10) || 400;
    const large = size >= 24 || (size >= 18.66 && weight >= 700);
    const need = large ? 3 : 4.5;
    const cr = ratio(fgc, bg);
    res.push({ text: own.slice(0, 50), fg: hex(fgc), bg: hex(bg), ratio: Math.round(cr * 100) / 100, need, pass: cr >= need, sel: sel(el), forced: !onScreen });
  }
  return res;
};

const report = (label, rows) => {
  const fails = rows.filter(r => !r.pass);
  console.log('\n' + label + ':  ' + rows.length + ' texts measured, ' + fails.length + ' below AA');
  const groups = {};
  for (const f of fails) { const k = f.fg + ' on ' + f.bg; (groups[k] = groups[k] || []).push(f); }
  for (const [k, g] of Object.entries(groups).sort((a, b) => b[1].length - a[1].length)) {
    const min = Math.min(...g.map(x => x.ratio));
    console.log('  ' + String(g.length).padStart(3) + '  ' + k.padEnd(22) + ' ' + min.toFixed(2) + ':1');
    const seen = new Set();
    for (const x of g) { const key = x.text; if (seen.has(key) || seen.size >= 4) continue; seen.add(key);
      console.log('         "' + x.text + '"  ' + x.ratio + ':1 (needs ' + x.need + ')' + (x.forced ? ' [not on screen]' : '') + '\n           ' + x.sel); }
  }
  return fails.length;
};

console.log('probe-contrast: ' + SITE + '   ' + new Date().toISOString());

await go(PRODUCT);
report('product page ' + PRODUCT, await page.evaluate(audit, null));

await go('/shop/');
report('/shop/', await page.evaluate(audit, null));

// one item in the cart, so the cart's and the mini cart's buttons render
await go(PRODUCT);
await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
await page.waitForTimeout(4500);
await go(PRODUCT);
report('product page, with one item in the cart (mini cart included)', await page.evaluate(audit, '.widget_shopping_cart, .woocommerce-mini-cart, .woocommerce-mini-cart__buttons, .mini_cart_content, .site-header-cart'));
await go('/cart/');
report('/cart/', await page.evaluate(audit, null));
await go('/checkout/');
report('/checkout/', await page.evaluate(audit, null));

for (let i = 0; i < 6; i++) {
  await go('/cart/');
  const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
  if (!h) break;
  await page.goto(h, { waitUntil: 'domcontentloaded' }).catch(() => {});
  await page.waitForTimeout(1500);
}
console.log('\ncart emptied · done ' + new Date().toISOString());
await browser.close();
