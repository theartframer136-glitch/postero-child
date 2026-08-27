// The same shop, tested by someone who has done this for twenty years. Where
// kid mode flails, this goes straight to the places defects actually live, in
// the order a release checklist would: the buying journey first, then the
// boundaries of every field on it, then the things that only break on a phone,
// then the quiet stuff nobody notices until a customer or Google does.
//
// The bias throughout is toward findings a developer can act on without having
// to reproduce them first: every check names the page, the input, and what it
// expected. Severity is decided here rather than left to the reader — FAIL is
// "a customer hits this and cannot buy", WARN is "this is wrong but survivable",
// and anything cosmetic is not reported at all, because a report nobody trusts
// is a report nobody reads.
//
// SAFETY. Live shop: no order is ever submitted, and every request that emails
// the owner or writes to the server is intercepted at the wire. The cart is used,
// because that is where the interesting defects are and a cart is per-session.
//
// Run: node tools/qa-veteran.mjs [url]
// Env: AF_QA_HEADED=1

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const results = [];
const FAIL = (area, what, detail) => results.push({ sev: 'FAIL', area, what, detail });
const WARN = (area, what, detail) => results.push({ sev: 'WARN', area, what, detail });
const PASS = (area, what, detail = '') => results.push({ sev: 'PASS', area, what, detail });

const browser = await chromium.launch({ headless: process.env.AF_QA_HEADED !== '1' });
const ctx = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-QA-Veteran',
});
const page = await ctx.newPage();

await page.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    // Answered, not aborted: an abort makes the page's own fetch reject and the
    // resulting "Failed to fetch" reads as a defect we introduced ourselves.
    return route.fulfill({ status: 200, contentType: 'application/json',
      body: '{"success":true,"data":{"message":"(intercepted by QA — not sent)"}}' });
  }
  return route.continue();
});

// Errors are attributed to whichever page was open when they fired, so a report
// says "the product page throws" rather than "something, somewhere, throws".
let where = '/';
const jsErrors = [];
const serverErrors = [];
page.on('pageerror', e => jsErrors.push({ where, msg: String(e.message).slice(0, 160) }));
page.on('response', r => {
  if (r.status() >= 500) serverErrors.push({ where, url: r.url().replace(SITE, ''), status: r.status() });
});

const go = async (path, waitMs = 3000) => {
  where = path || '/';
  const res = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
  await page.waitForTimeout(waitMs);
  return res;
};

const overflow = async () => page.evaluate(() => {
  const d = document.documentElement;
  return d.scrollWidth > d.clientWidth + 4 ? { scroll: d.scrollWidth, client: d.clientWidth } : null;
}).catch(() => null);

console.log(`veteran pass against ${SITE}\n`);

// ── 1. Does the front door work, and is it honest about itself ──────────────
{
  const res = await go('/', 5000);
  const status = res ? res.status() : 0;
  if (status !== 200) FAIL('Homepage', 'HTTP status', `expected 200, got ${status}`);
  else PASS('Homepage', 'loads', '200');

  const meta = await page.evaluate(() => ({
    title: document.title || '',
    desc: document.querySelector('meta[name="description"]')?.content || '',
    canonical: document.querySelector('link[rel="canonical"]')?.href || '',
    h1: document.querySelectorAll('h1').length,
    viewport: document.querySelector('meta[name="viewport"]')?.content || '',
  }));
  if (!meta.title) FAIL('Homepage', 'page title', 'empty');
  else if (meta.title.length > 65) WARN('Homepage', 'page title', `${meta.title.length} chars — Google truncates past ~60`);
  if (!meta.desc) WARN('Homepage', 'meta description', 'missing — Google writes its own');
  if (!meta.canonical) WARN('Homepage', 'canonical link', 'missing');
  if (meta.h1 === 0) WARN('Homepage', 'H1', 'none on the page');
  if (meta.h1 > 1) WARN('Homepage', 'H1', `${meta.h1} of them — should be one`);
  if (!meta.viewport) FAIL('Homepage', 'viewport meta', 'missing — the site cannot be responsive without it');
}

// ── 2. Images: the shop is pictures, so a broken one is a lost sale ─────────
{
  const imgs = await page.evaluate(() => Array.from(document.images).map(i => ({
    src: i.currentSrc || i.src, ok: i.complete && i.naturalWidth > 0,
    alt: i.getAttribute('alt'), w: i.naturalWidth, dw: i.width,
  })));
  const broken = imgs.filter(i => !i.ok && i.src);
  if (broken.length) FAIL('Homepage', 'broken images', `${broken.length} of ${imgs.length}: ${broken.slice(0, 3).map(b => b.src.replace(SITE, '')).join(', ')}`);
  else PASS('Homepage', 'images render', `${imgs.length} checked`);

  const noAlt = imgs.filter(i => i.alt === null || i.alt.trim() === '');
  if (noAlt.length > imgs.length * 0.3 && imgs.length > 5) {
    WARN('Homepage', 'image alt text', `${noAlt.length} of ${imgs.length} have none — screen readers and image search get nothing`);
  }

  // Shipping a 3000px file into a 400px slot is the commonest real cause of a
  // slow shop, and it is invisible until someone measures it.
  const oversized = imgs.filter(i => i.ok && i.dw > 0 && i.w > i.dw * 3);
  if (oversized.length) {
    WARN('Homepage', 'oversized images', `${oversized.length} served at 3x+ their display size, e.g. ${oversized[0].w}px shown at ${oversized[0].dw}px`);
  }
}

// ── 3. The buying journey, which is the only path that must never break ─────
let productUrl = null;
{
  productUrl = await page.evaluate(() => {
    const a = Array.from(document.querySelectorAll('a[href*="/product/"]')).find(x => x.href);
    return a ? a.href : null;
  });

  if (!productUrl) {
    const res = await go('/shop/', 4000);
    if (!res || res.status() !== 200) FAIL('Shop', 'shop page', `HTTP ${res ? res.status() : 0}`);
    productUrl = await page.evaluate(() => {
      const a = Array.from(document.querySelectorAll('a[href*="/product/"]')).find(x => x.href);
      return a ? a.href : null;
    });
  }

  if (!productUrl) {
    FAIL('Journey', 'find a product', 'no product link found from the homepage or /shop/ — the journey cannot be tested');
  } else {
    where = productUrl.replace(SITE, '');
    const res = await page.goto(productUrl, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
    await page.waitForTimeout(4000);
    if (!res || res.status() !== 200) FAIL('Product', 'page loads', `HTTP ${res ? res.status() : 0} at ${where}`);
    else PASS('Product', 'page loads', where);

    const pd = await page.evaluate(() => ({
      price: !!document.querySelector('.price, .woocommerce-Price-amount'),
      atc: !!document.querySelector('button[name="add-to-cart"], .single_add_to_cart_button, form.cart button'),
      title: (document.querySelector('h1')?.textContent || '').trim().slice(0, 60),
      img: !!document.querySelector('.woocommerce-product-gallery img, .images img'),
    }));
    if (!pd.price) FAIL('Product', 'price shown', 'no price element on the product page');
    if (!pd.atc) FAIL('Product', 'add to cart button', 'not present');
    if (!pd.img) WARN('Product', 'product image', 'no gallery image found');
    if (pd.atc && pd.price) PASS('Product', 'has price + buy button', pd.title);

    // 3b. Quantity boundaries. The field is the customer's, so it gets the
    // treatment: zero, negative, absurd, and not-a-number.
    const qty = await page.$('input.qty, input[name="quantity"]');
    if (qty) {
      for (const [val, expectation] of [['0', 'zero'], ['-3', 'negative'], ['99999', 'absurd'], ['abc', 'letters']]) {
        await qty.fill(val, { timeout: 3000 }).catch(() => {});
        const got = await qty.inputValue().catch(() => '');
        const asNum = parseFloat(got);
        if (expectation === 'negative' && asNum < 0) {
          FAIL('Product', 'quantity accepts a negative', `typed ${val}, field kept ${got} — a negative line item must never reach the cart`);
        }
        if (expectation === 'zero' && got === '0') {
          WARN('Product', 'quantity accepts zero', 'typed 0 and it stuck — adding zero of something should be refused or coerced to 1');
        }
        if (expectation === 'absurd' && asNum >= 99999) {
          WARN('Product', 'quantity has no upper bound', `typed ${val} and it stuck — one slip orders ${val} canvases`);
        }
      }
      await qty.fill('1').catch(() => {});
      PASS('Product', 'quantity field probed', 'zero / negative / absurd / letters');
    } else {
      WARN('Product', 'quantity field', 'not found — could not probe its bounds');
    }

    // 3c. The double-click. An impatient customer taps Add to Cart twice, and
    // the shop must not read that as two canvases.
    const before = await cartCount();
    const atc = await page.$('button[name="add-to-cart"], .single_add_to_cart_button, form.cart button');
    if (atc) {
      await atc.click({ timeout: 8000, force: true }).catch(() => {});
      await atc.click({ timeout: 3000, force: true }).catch(() => {});
      await page.waitForTimeout(6000);
      const after = await cartCount();
      if (after !== null && before !== null) {
        const added = after - before;
        if (added > 1) {
          FAIL('Cart', 'double-click adds twice', `cart went ${before} → ${after} on one impatient double-tap — the button needs to disable itself while the first request is in flight`);
        } else if (added === 1) {
          PASS('Cart', 'double-click is absorbed', `cart went ${before} → ${after}`);
        } else {
          WARN('Cart', 'add to cart', `cart did not change (${before} → ${after}) — either it silently failed or the count is not where this test looks`);
        }
      }
    }
  }
}

async function cartCount() {
  return page.evaluate(() => {
    const el = document.querySelector('.cart-contents-count, .af-cart-count, .cart-count, [class*="cart"] .count');
    if (el && /^\d+$/.test(el.textContent.trim())) return parseInt(el.textContent.trim(), 10);
    const m = document.body.innerHTML.match(/cart-contents-count[^>]*>(\d+)</);
    return m ? parseInt(m[1], 10) : null;
  }).catch(() => null);
}

// ── 4. The cart page itself ────────────────────────────────────────────────
{
  const res = await go('/cart/', 5000);
  if (!res || res.status() !== 200) {
    FAIL('Cart', 'cart page', `HTTP ${res ? res.status() : 0}`);
  } else {
    const c = await page.evaluate(() => ({
      empty: /your cart is currently empty/i.test(document.body.innerText),
      hasTotal: !!document.querySelector('.order-total, .cart-subtotal'),
      checkout: !!document.querySelector('.checkout-button, a[href*="checkout"]'),
    }));
    if (c.empty) WARN('Cart', 'cart is empty after adding', 'the add-to-cart above did not persist to the cart page');
    else {
      if (!c.hasTotal) FAIL('Cart', 'no total shown', 'a cart with items and no total is not checkoutable');
      if (!c.checkout) FAIL('Cart', 'no checkout button', 'the customer cannot proceed');
      if (c.hasTotal && c.checkout) PASS('Cart', 'shows a total and a way forward');
    }
  }
}

// ── 5. Checkout: loaded, never submitted ───────────────────────────────────
{
  const res = await go('/checkout/', 6000);
  if (!res || res.status() !== 200) {
    FAIL('Checkout', 'page loads', `HTTP ${res ? res.status() : 0}`);
  } else {
    const co = await page.evaluate(() => ({
      fields: document.querySelectorAll('#customer_details input, .woocommerce-checkout input').length,
      pay: !!document.querySelector('#payment, .wc_payment_methods, #place_order'),
      empty: /your cart is currently empty/i.test(document.body.innerText),
    }));
    if (co.empty) WARN('Checkout', 'checkout with an empty cart', 'nothing to test — the cart did not carry through');
    else if (!co.pay) FAIL('Checkout', 'no payment section', 'the order cannot be placed');
    else PASS('Checkout', 'reachable with a payment section', `${co.fields} form fields`);
    if (!page.url().startsWith('https://')) FAIL('Checkout', 'not HTTPS', 'card details on a plain connection');
  }
}

// ── 6. Search, with the inputs a real person produces ───────────────────────
for (const [q, label] of [['', 'empty'], ['   ', 'only spaces'], ['ganesha', 'a real word'],
                          ['<script>alert(1)</script>', 'a script tag'], ['zzzzzqqqqxxxx', 'no results'],
                          ['a'.repeat(300), '300 characters']]) {
  const res = await go('/?s=' + encodeURIComponent(q), 3500);
  const status = res ? res.status() : 0;
  if (status >= 500) {
    FAIL('Search', `search breaks on ${label}`, `HTTP ${status}`);
    continue;
  }
  const r = await page.evaluate(() => ({
    reflectedRaw: document.body.innerHTML.includes('<script>alert(1)</script>'),
    alerted: !!window.__afAlerted,
    hasMessage: document.body.innerText.trim().length > 50,
  }));
  if (r.reflectedRaw || r.alerted) {
    FAIL('Search', 'search term is reflected unescaped', `"${label}" came back as live markup — this is stored/reflected XSS`);
  } else if (!r.hasMessage) {
    WARN('Search', `search with ${label}`, 'returned a page with almost no text — no "nothing found" message');
  } else {
    PASS('Search', `handles ${label}`, `HTTP ${status}`);
  }
}

// ── 7. The price filter, where min > max is the classic ────────────────────
{
  const res = await go('/shop/?min_price=900&max_price=1', 4000);
  const status = res ? res.status() : 0;
  if (status >= 500) FAIL('Filters', 'min price above max price', `HTTP ${status} — inverted range crashes the shop`);
  else {
    const txt = await page.evaluate(() => document.body.innerText.trim().length);
    if (txt < 50) WARN('Filters', 'min price above max price', 'renders a near-empty page with no explanation');
    else PASS('Filters', 'survives an inverted price range', `HTTP ${status}`);
  }

  const res2 = await go('/shop/?min_price=-500&max_price=99999999', 3500);
  if (res2 && res2.status() >= 500) FAIL('Filters', 'negative / huge price range', `HTTP ${res2.status()}`);
  else PASS('Filters', 'survives a negative and a huge price');
}

// ── 8. A URL that does not exist ───────────────────────────────────────────
{
  const res = await go('/this-page-does-not-exist-' + Date.now() + '/', 3000);
  const status = res ? res.status() : 0;
  if (status !== 404) FAIL('404', 'wrong status for a missing page', `got ${status} — soft 404s get the whole site mis-indexed`);
  else {
    const useful = await page.evaluate(() => ({
      words: document.body.innerText.trim().split(/\s+/).length,
      wayOut: !!document.querySelector('a[href="/"], a[href*="shop"], form[role="search"], input[type="search"]'),
    }));
    if (!useful.wayOut) WARN('404', 'dead end', 'the 404 page offers no search box and no link home');
    else PASS('404', 'returns 404 with a way out', `${useful.words} words`);
  }
}

// ── 9. Narrow screens, where most of the traffic actually is ───────────────
for (const [w, h, name] of [[390, 844, 'iPhone'], [768, 1024, 'tablet']]) {
  await page.setViewportSize({ width: w, height: h });
  for (const path of ['/', '/shop/', '/cart/']) {
    await go(path, 3000);
    const o = await overflow();
    if (o) FAIL('Responsive', `${name} — ${path} scrolls sideways`, `${o.scroll}px of content in a ${o.client}px screen`);
  }
  // Tap targets under ~32px are the reason phone users mis-tap.
  await go('/', 3000);
  const small = await page.evaluate(() => {
    let n = 0, sample = '';
    for (const el of document.querySelectorAll('a, button, [role="button"]')) {
      const r = el.getBoundingClientRect();
      if (r.width > 0 && r.height > 0 && r.height < 32 && r.top < 2000) {
        n++; if (!sample) sample = (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 30);
      }
    }
    return { n, sample };
  }).catch(() => ({ n: 0, sample: '' }));
  if (small.n > 10) WARN('Responsive', `${name} — small tap targets`, `${small.n} controls under 32px tall, e.g. "${small.sample}"`);
}
await page.setViewportSize({ width: 1440, height: 900 });

// ── 10. Keyboard and basic accessibility ───────────────────────────────────
{
  await go('/', 3500);
  const a11y = await page.evaluate(() => {
    const unlabelled = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="submit"]), select, textarea'))
      .filter(i => !i.labels?.length && !i.getAttribute('aria-label') && !i.getAttribute('placeholder')).length;
    const emptyLinks = Array.from(document.querySelectorAll('a[href]'))
      .filter(a => !a.textContent.trim() && !a.getAttribute('aria-label') && !a.querySelector('img[alt]:not([alt=""])')).length;
    return { unlabelled, emptyLinks, lang: document.documentElement.lang || '' };
  });
  if (!a11y.lang) WARN('Accessibility', 'no lang attribute', 'screen readers guess the language');
  if (a11y.unlabelled > 0) WARN('Accessibility', 'unlabelled form fields', `${a11y.unlabelled} inputs with no label, aria-label or placeholder`);
  if (a11y.emptyLinks > 3) WARN('Accessibility', 'links with no accessible name', `${a11y.emptyLinks} — usually icon links missing aria-label`);

  await page.keyboard.press('Tab');
  const focus = await page.evaluate(() => {
    const el = document.activeElement;
    if (!el || el === document.body) return null;
    const s = getComputedStyle(el);
    return { tag: el.tagName, outline: s.outlineStyle, width: s.outlineWidth };
  });
  if (!focus) WARN('Accessibility', 'Tab does not move focus', 'keyboard users cannot navigate');
  else if (focus.outline === 'none' && parseFloat(focus.width || '0') === 0) {
    WARN('Accessibility', 'focus is invisible', `first tab stop (${focus.tag}) has no focus outline`);
  } else PASS('Accessibility', 'keyboard focus is visible', focus.tag);
}

// ── 11. Errors gathered across everything above ────────────────────────────
{
  const byPage = {};
  for (const e of jsErrors) (byPage[e.where] ||= new Set()).add(e.msg);
  for (const [p, set] of Object.entries(byPage)) {
    for (const msg of set) FAIL('JS errors', `uncaught exception on ${p}`, msg);
  }
  if (!jsErrors.length) PASS('JS errors', 'no uncaught exceptions on any page visited');

  for (const e of serverErrors) FAIL('Server', `HTTP ${e.status}`, `${e.url} (while on ${e.where})`);
  if (!serverErrors.length) PASS('Server', 'no 5xx responses');
}

// ── Report ─────────────────────────────────────────────────────────────────
const fails = results.filter(r => r.sev === 'FAIL');
const warns = results.filter(r => r.sev === 'WARN');
const passes = results.filter(r => r.sev === 'PASS');

console.log('\n' + '='.repeat(72));
console.log('VETERAN PASS — ' + SITE);
console.log('='.repeat(72));

for (const [label, list] of [['FAIL', fails], ['WARN', warns]]) {
  if (!list.length) continue;
  console.log(`\n${label} (${list.length})`);
  console.log('-'.repeat(72));
  for (const r of list) console.log(`  [${r.area}] ${r.what}\n      ${r.detail}`);
}

console.log(`\nPASS (${passes.length})`);
console.log('-'.repeat(72));
for (const r of passes) console.log(`  [${r.area}] ${r.what}${r.detail ? ' — ' + r.detail : ''}`);

console.log(`\n${'='.repeat(72)}`);
console.log(`${fails.length} blocking, ${warns.length} worth fixing, ${passes.length} verified good.`);

await page.screenshot({ path: 'veteran-final.png' }).catch(() => {});
await browser.close();
process.exit(fails.length ? 1 : 0);
