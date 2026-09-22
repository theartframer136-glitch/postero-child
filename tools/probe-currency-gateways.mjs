// DEF-02: does switching to CAD remove the Credit Card payment method?
//
// The claim is that a shopper who uses the currency switcher in the site's own
// header arrives at a checkout with Zelle and cash on delivery and no way to
// pay by card. If true it is a complete payment blocker for anyone who takes
// the header at its word, and CAD is not an accident — af_allowed_currencies()
// returns USD and CAD with the comment "spec: USD + CAD", and
// tools/switch-currency-cad.php exists to have added it.
//
// So this is measured before anything is changed, and measured in a way that
// names which gateway disappears rather than just counting them:
//
//   /checkout/?currency=USD   list the payment methods and the total
//   /checkout/?currency=CAD   list them again
//   /checkout/?currency=USD   and again, to show it comes back
//
// af_active_currency() reads ?currency= directly, so the switch needs no
// clicking and no guessing at the switcher's markup.
//
// SAFETY. The NEVER_SEND guard, which lists wc-ajax=checkout — no order can
// be placed. One item is added to a cart and removed again at the end.
//
// Run: node tools/probe-currency-gateways.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 950 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true}' });
  }
  return route.continue();
});
const page = await ctx.newPage();

const go = async (path, wait = 2500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

// What the checkout offers, by gateway id as well as by label — the id is what
// a fix would have to act on, and the label is what a shopper reads.
const readCheckout = () => page.evaluate(() => {
  const inputs = [...document.querySelectorAll('input[name="payment_method"]')];
  const methods = inputs.map(i => {
    const lab = document.querySelector('label[for="' + i.id + '"]');
    return { id: i.value, label: lab ? (lab.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 48) : '(no label)' };
  });
  const totalEl = document.querySelector('.order-total .amount, tr.order-total td');
  const cur = (((document.body && document.body.innerText) || '').match(/(CA\$|US\$|\$)\s?[\d,]+\.\d{2}/) || [''])[0];
  return {
    methods,
    count: methods.length,
    total: totalEl ? (totalEl.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 30) : '(none)',
    firstPrice: cur,
    isCheckout: !!document.querySelector('form.checkout, .woocommerce-checkout'),
  };
});

console.log('probe-currency-gateways: ' + SITE + '   ' + new Date().toISOString());

try {
  // ── a product that can actually be bought ────────────────────────────────
  await go('/shop/', 2500);
  // innerText came back null here on the first run and took the whole probe
  // down with it. Every read off the DOM is coerced now: a probe that dies on
  // its own null is worth nothing, and this is the third time today one of
  // these harnesses has reported a fault of mine as a fact about the site.
  let picks = [];
  try {
    picks = await page.evaluate(() => [...document.querySelectorAll('li.product, .product.type-product, .product')]
      .map(c => ({ t: (c.innerText || '') + '', href: ((c.querySelector('a[href]') || {}).href) || '' }))
      .filter(x => x.href && !/price on request/i.test(x.t) && /\$\s?[\d,.]+/.test(x.t))
      .map(x => x.href).slice(0, 8));
  } catch (e) {
    console.log('  could not read /shop/: ' + String(e.message).slice(0, 120));
  }
  if (!picks.length) {
    // Fall back to any product link at all, priced or not — the checkout test
    // only needs something in the cart.
    try {
      picks = await page.evaluate(() => [...document.querySelectorAll('a[href*="/product/"]')]
        .map(a => a.href).filter((v, i, arr) => arr.indexOf(v) === i).slice(0, 8));
    } catch (e) {}
    console.log('  priced-card pick found nothing; falling back to ' + picks.length + ' product links');
  }
  let added = false;
  for (const url of picks) {
    await go(url, 2000);
    const buyable = await page.evaluate(() => !!document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]'));
    if (!buyable) continue;
    await page.click('.single_add_to_cart_button, button[name="add-to-cart"]').catch(() => {});
    await page.waitForTimeout(4000);
    const ok = await page.evaluate(() => /added to your cart|view cart|\b1\s*item/i.test((document.body && document.body.innerText) || ''));
    if (ok) { added = true; console.log('\n  cart: added from ' + url.replace(SITE, '')); break; }
  }
  if (!added) console.log('\n  cart: NOTHING ADDED — a checkout with an empty cart shows no payment methods at all, so read the rest with that in mind');

  // ── the same checkout in each currency ──────────────────────────────────
  const seen = {};
  for (const cur of ['USD', 'CAD', 'USD']) {
    const status = await go('/checkout/?currency=' + cur, 4000);
    const st = await readCheckout();
    const key = cur + (seen[cur] ? '(again)' : '');
    seen[cur] = true;
    console.log('\n— /checkout/?currency=' + cur + '  (HTTP ' + status + ') —');
    console.log('    is a checkout page : ' + st.isCheckout);
    console.log('    order total        : ' + st.total + (st.firstPrice ? '   first price on page: ' + st.firstPrice : ''));
    console.log('    payment methods    : ' + st.count);
    for (const m of st.methods) console.log('        [' + m.id + ']  ' + m.label);
    seen[key] = st;
  }

  const usd = seen['USD'], cad = seen['CAD'];
  if (!usd || !cad) {
    console.log('\n  → NO DATA — could not read both checkouts');
  } else {
    const usdIds = usd.methods.map(m => m.id);
    const cadIds = cad.methods.map(m => m.id);
    const missing = usdIds.filter(id => !cadIds.includes(id));
    const gained  = cadIds.filter(id => !usdIds.includes(id));
    console.log('\n— the difference —');
    console.log('    USD offers : ' + (usdIds.join(', ') || '(none)'));
    console.log('    CAD offers : ' + (cadIds.join(', ') || '(none)'));
    console.log('    lost in CAD: ' + (missing.join(', ') || '(nothing)'));
    console.log('    gained     : ' + (gained.join(', ') || '(nothing)'));
    const cardish = id => /square|stripe|card|paypal/i.test(id);
    console.log('\n  → ' + (missing.length
      ? (missing.some(cardish)
          ? 'DEF-02 CONFIRMED — switching to CAD removes ' + missing.join(', ') + ', which is the card gateway.'
          : 'PARTLY — CAD loses ' + missing.join(', ') + ', but none of them looks like a card gateway.')
      : 'DEF-02 CONTRADICTED — CAD offers the same methods as USD.'));
  }

  // ── put the cart back ───────────────────────────────────────────────────
  for (let i = 0; i < 3; i++) {
    await go('/cart/?currency=USD', 1800);
    const href = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; });
    if (!href) break;
    await go(href, 1800);
  }
  const empty = await page.evaluate(() => /your cart is currently empty/i.test((document.body && document.body.innerText) || ''));
  console.log('\n  cleanup: cart empty = ' + empty);
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
