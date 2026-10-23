/**
 * The shop as its people use it: a first-time visitor, then a customer with
 * an account. Each step is what a shopper does, and each finding says what
 * they would have run into.
 *
 *   FIRST VISIT (no cookies, desktop and phone)
 *     what covers the page on arrival and whether it closes; the cookie
 *     choice; finding art from the menu; sorting; a product page (price,
 *     sizes, buy button in view on a phone); add to cart and the feedback it
 *     gives; the cart; checkout as a guest up to the payment choice; the
 *     help pages a cautious buyer looks for before paying.
 *
 *   SIGNED-IN CUSTOMER (AF_QA_SESSION_FILE: the session made by
 *   tools/qa-customer-session.php for the throwaway account qa-customer-test)
 *     the header knows them; every My Account page; the empty orders page;
 *     saving a delivery address and finding it kept; the account details
 *     form; the wishlist kept across a reload; checkout filled in from the
 *     saved address; downloads; signing out.
 *
 * SAFETY. Live shop. No order is placed and nothing is emailed: the checkout
 * submit and every request that would reach a person are answered at the wire
 * (the same list as qa-veteran.mjs). The address is saved only on the
 * throwaway account, which the workflow deletes afterwards.
 *
 * Usage: node tools/qa-customer.mjs https://theartframer.us
 */
import fs from 'node:fs';
import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const HOST = new URL(SITE).host;
const PAUSE = 2500;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply', 'wc-ajax=checkout',
  'af_product_edit_save', 'af_inventory_save', 'action=af_chat', 'lost_password', 'eael-register', 'af_quote'];
const NOISE = /googletagmanager|google-analytics|doubleclick|facebook|cdninstagram|instagram\.com|clarity\.ms|cloudflareinsights|challenges\.cloudflare|turnstile|recaptcha|stats\.wp\.com|squarecdn|squareup|cash\.app|pay\.google|paypal|youtube|ytimg|favicon/i;

const results = [];
let WHO = '', WHERE = '';
const add = (sev, what, detail = '') => results.push({ sev, who: WHO, where: WHERE, what, detail: String(detail).slice(0, 260) });
const FAIL = (w, d) => add('FAIL', w, d), WARN = (w, d) => add('WARN', w, d), PASS = (w, d) => add('PASS', w, d), INFO = (w, d) => add('INFO', w, d);
const rel = u => { try { const x = new URL(u); return x.host === HOST ? x.pathname + x.search : u; } catch { return u; } };

const browser = await chromium.launch({ headless: true });

async function context(opts, cookies) {
  const ctx = await browser.newContext({ locale: 'en-US', ...opts });
  if (cookies) await ctx.addCookies(cookies);
  await ctx.route('**/*', route => {
    const r = route.request();
    const body = (r.postData() || '') + ' ' + r.url();
    if (r.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure","messages":"(intercepted by QA - not sent)","success":true,"data":{"message":"(intercepted by QA - not sent)"}}' });
    }
    return route.continue();
  });
  const page = await ctx.newPage();
  page.__errs = [];
  page.on('pageerror', e => page.__errs.push(`JS: ${String(e.message).slice(0, 160)}`));
  page.on('response', r => { const s = r.status(); if (s >= 400 && r.url().includes(HOST) && !NOISE.test(r.url())) page.__errs.push(`${s} ${rel(r.url())}`); });
  return { ctx, page };
}

async function go(page, path) {
  WHERE = path;
  page.__errs.length = 0;
  let res = null;
  try { res = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'load', timeout: 60000 }); } catch (e) { FAIL('page did not load', String(e.message).split('\n')[0]); return 0; }
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(800);
  const st = res ? res.status() : 0;
  if (st === 403) { await sleep(40000); return go(page, path); }   // the host throttling the test, not the shop
  if (st >= 400) FAIL(`page answered ${st}`, rel(page.url()));
  return st;
}
function reportErrors(page, step) {
  const e = [...new Set(page.__errs)];
  for (const m of e.filter(m => m.startsWith('JS:')).slice(0, 3)) FAIL(`script error while ${step}`, m);
  for (const m of e.filter(m => !m.startsWith('JS:')).slice(0, 3)) WARN(`failed request while ${step}`, m);
  page.__errs.length = 0;
}
const visible = async (loc) => (await loc.count()) > 0 && await loc.first().isVisible().catch(() => false);
async function text(page) { return page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ')); }

/* ------------------------------------------------------- first visit */

async function firstVisit(label, opts) {
  WHO = `new visitor, ${label}`;
  const { ctx, page } = await context(opts);
  const phone = !!opts.isMobile;

  // 1. Arrival: what covers the page, and can it be closed.
  await go(page, '/');
  await page.waitForTimeout(6000);    // timed popups
  const measureCovers = () => page.evaluate(() => {
    const vw = innerWidth, vh = innerHeight, out = [];
    for (const el of document.querySelectorAll('body *')) {
      const cs = getComputedStyle(el);
      if (cs.position !== 'fixed' || cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity < 0.1) continue;
      const r = el.getBoundingClientRect();
      const area = Math.max(0, Math.min(r.right, vw) - Math.max(r.left, 0)) * Math.max(0, Math.min(r.bottom, vh) - Math.max(r.top, 0));
      if (area / (vw * vh) < 0.25) continue;
      if (el.closest('header, .site-header, #masthead, .elementor-location-header')) continue;
      if (el.parentElement && el.parentElement.closest('*') && getComputedStyle(el.parentElement).position === 'fixed') continue;
      out.push({ sel: el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''), pct: Math.round(100 * area / (vw * vh)), text: (el.innerText || '').replace(/\s+/g, ' ').slice(0, 70) });
    }
    return out.slice(0, 4);
  });
  const covers = await measureCovers();
  if (covers.length) {
    for (const c of covers) INFO(`on arrival ${c.pct}% of the screen is covered`, `${c.sel} "${c.text}"`);
    // What a person tries: the close button, then Escape.
    const closer = page.locator('[aria-label*="close" i]:visible, button:has-text("×"):visible, .close:visible, [class*="close"]:visible').first();
    if (await closer.count()) await closer.click({ timeout: 3000 }).catch(() => {});
    await page.keyboard.press('Escape').catch(() => {});
    await page.waitForTimeout(1000);
    // The cookie notice asks for a choice on purpose (it is checked on its
    // own below); anything else still covering the page is a fault.
    const still = (await measureCovers()).filter(c => !/af-consent|af-ck\b/.test(c.sel));
    if (still.length) FAIL('something covering the page could not be closed with a close button or Escape', still.map(c => `${c.sel} ${c.pct}% "${c.text}"`).join(' | '));
    else PASS('what covered the page on arrival closes with its close button or Escape', covers.map(c => c.sel).join(', '));
  } else PASS('nothing covers the page on arrival');

  // 2. The cookie choice.
  if (await visible(page.locator('#af-ck-accept'))) {
    await page.locator('#af-ck-necessary').click({ timeout: 4000 }).catch(() => {});
    await page.waitForTimeout(600);
    if (await visible(page.locator('#af-ck-accept'))) FAIL('"Necessary only" does not close the cookie banner');
    else PASS('cookie banner offers a choice and closes');
    await page.reload({ waitUntil: 'load' }).catch(() => {});
    await page.waitForTimeout(1500);
    if (await visible(page.locator('#af-ck-accept'))) WARN('the cookie banner comes back after the choice was made');
  } else INFO('no cookie banner on the first visit');
  reportErrors(page, 'arriving');

  // 3. Finding art from the menu.
  let catUrl = null;
  if (phone) {
    const toggle = page.locator('button[aria-label*="menu" i], .hfe-nav-menu__toggle, .menu-mobile-nav-button, .postero-mobile-nav-button, .elementor-menu-toggle, a[href="#"]:has-text("Menu"), [class*="menu-toggle"]').filter({ visible: true }).first();
    if (await toggle.count()) {
      await toggle.click({ force: true }).catch(() => {});
      await page.waitForTimeout(1200);
    } else FAIL('no menu button on the phone');
  }
  catUrl = await page.evaluate(() => {
    const links = [...document.querySelectorAll('a[href*="/product-category/"]')].filter(a => { const r = a.getBoundingClientRect(); return r.width > 0 && r.height > 0 && r.top >= 0 && r.top < innerHeight * 1.5; });
    return links.length ? links[0].href : null;
  });
  if (!catUrl) WARN('no category link visible near the top of the home page', phone ? 'after opening the menu' : '');
  await sleep(PAUSE);
  await go(page, catUrl ? rel(catUrl) : '/shop/');
  const cards = await page.locator('li.product, .product-card, .type-product').count();
  if (cards) PASS(`a category shows its art (${cards} pieces on the first screen of results)`, rel(page.url()));
  else FAIL('the category opened from the menu shows no products', rel(page.url()));

  // 4. Sorting, with the dropdown the shopper sees: the theme draws its own
  // in front of the real select, which stays hidden.
  const shownSort = page.locator('.woocommerce-ordering .select-selected:visible').first();
  const sort = page.locator('select.orderby:visible, select[name="orderby"]:visible').first();
  const before = page.url();
  let sorted = null;
  if (await shownSort.count()) {
    await shownSort.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(500);
    const low = page.locator('.woocommerce-ordering .select-items div', { hasText: /low to high/i }).first();
    await Promise.all([page.waitForNavigation({ timeout: 15000 }).catch(() => null), low.click({ timeout: 5000 }).catch(() => {})]);
    sorted = 'price: low to high';
  } else if (await sort.count()) {
    await Promise.all([page.waitForNavigation({ timeout: 15000 }).catch(() => null), sort.selectOption('price').catch(() => {})]);
    sorted = 'price';
  }
  if (sorted) {
    await page.waitForTimeout(1500);
    const prices = await page.evaluate(() => [...document.querySelectorAll('li.product, .product-card, .type-product')].slice(0, 6)
      .map(c => { const a = c.querySelectorAll('.price ins .amount, .price .amount'); return a.length ? parseFloat(a[a.length - 1].innerText.replace(/[^0-9.]/g, '')) : NaN; }).filter(n => !isNaN(n)));
    const inOrder = prices.length > 1 && prices.every((v, i) => i === 0 || v >= prices[i - 1] - 0.01);
    if (page.url() === before) FAIL(`sorting by ${sorted} did nothing`);
    else if (!prices.length) FAIL(`sorting by ${sorted} emptied the list`);
    else if (!inOrder) FAIL(`sorted by ${sorted}, but the prices are out of order`, prices.join(', '));
    else PASS(`sorting by ${sorted} works`, prices.join(', '));
  } else INFO('no sort control on the category page');
  reportErrors(page, 'browsing a category');

  // 5. A product page.
  let prodUrl = await page.locator('a[href*="/product/"]').first().getAttribute('href').catch(() => null);
  if (!prodUrl) {
    FAIL('no product to open from the category');
    await sleep(PAUSE); await go(page, '/shop/');
    prodUrl = await page.locator('a[href*="/product/"]').first().getAttribute('href').catch(() => null);
    if (!prodUrl) { FAIL('no product to open from the shop either'); await ctx.close(); return; }
  }
  await sleep(PAUSE);
  await go(page, rel(prodUrl));
  const p = await page.evaluate(() => {
    const vis = el => { if (!el) return false; const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
    const atc = document.querySelector('form.cart .single_add_to_cart_button, .single_add_to_cart_button');
    const price = document.querySelector('#af-live-price, .summary .price, .product .price');
    return {
      title: (document.querySelector('h1') || {}).innerText || '',
      price: price ? price.innerText.trim() : '',
      priceInView: price ? price.getBoundingClientRect().top < innerHeight : false,
      atc: vis(atc), atcInView: atc ? atc.getBoundingClientRect().top < innerHeight * 1.6 : false,
      sizes: document.querySelectorAll('#af-size-select option, .variations select option').length,
      img: !!document.querySelector('.woocommerce-product-gallery img, .wp-post-image'),
      returns: /return|refund/i.test(document.body.innerText), shipping: /deliver|shipping/i.test(document.body.innerText),
    };
  });
  if (!p.price) FAIL('the product page shows no price', p.title);
  else if (!p.priceInView) WARN('the price is below the first screen', `${p.title.slice(0, 50)}: ${p.price.slice(0, 30)}`);
  else PASS('price shown on the first screen', p.price.replace(/\s+/g, ' ').slice(0, 40));
  if (!p.atc) FAIL('no Add to cart button on the product page');
  else if (phone && !p.atcInView) WARN('on a phone the Add to cart button is more than a screen and a half down');
  if (phone && p.atc) {
    // The buy bar at the foot of a phone screen keeps price and Add to Cart in reach.
    await page.waitForTimeout(1200);
    const bb = await page.evaluate(() => {
      const b = document.getElementById('af-buybar'); if (!b) return null;
      const r = b.getBoundingClientRect(), live = document.getElementById('af-live-price');
      const stack = document.elementsFromPoint(r.left + r.width - 40, r.top + r.height / 2);
      return { on: b.classList.contains('af-on'), inView: r.top < innerHeight && r.bottom <= innerHeight + 1,
        price: (b.querySelector('.af-bb-price') || {}).textContent || '', live: live ? live.textContent.trim() : '',
        onTop: !!stack.length && b.contains(stack[0]) };
    });
    if (!bb) WARN('no buy bar on the phone product page');
    else if (!bb.on || !bb.inView) WARN('the phone buy bar is not showing while Add to cart is out of sight', JSON.stringify(bb));
    else if (!bb.onTop) WARN('something covers the phone buy bar', JSON.stringify(bb));
    else if (bb.live && bb.price !== bb.live) FAIL('the buy bar price differs from the page price', `${bb.price} vs ${bb.live}`);
    else PASS('a buy bar keeps price and Add to Cart in reach on a phone', bb.price);
  }
  if (!p.img) FAIL('no product picture');
  if (!p.shipping) WARN('the product page says nothing about delivery');
  if (!p.returns) WARN('the product page says nothing about returns or refunds');

  // Choosing a size changes the price.
  if (await page.locator('#af-size-select').count()) {
    const opts = await page.locator('#af-size-select option').evaluateAll(o => o.map(x => x.value).filter(Boolean));
    const before = await page.locator('#af-live-price').innerText().catch(() => '');
    if (opts.length > 1) {
      await page.selectOption('#af-size-select', opts[opts.length - 1]).catch(() => {});
      await page.waitForTimeout(1200);
      const after = await page.locator('#af-live-price').innerText().catch(() => '');
      if (before && after && before !== after) PASS('choosing another size updates the price', `${before.trim()} → ${after.trim()}`);
      else WARN('choosing another size did not change the shown price', `${before} / ${after}`);
    }
  }
  reportErrors(page, 'reading a product page');

  // 6. Add to cart, and what the shopper is told.
  const countBefore = await page.evaluate(() => { const c = document.querySelector('.cart-contents .count, .cart-count, .header-cart-count, .mini-cart-count, [class*="cart"] .count'); return c ? c.innerText.trim() : ''; });
  await page.locator('form.cart .single_add_to_cart_button, .single_add_to_cart_button').first().click({ timeout: 8000 }).catch(e => FAIL('Add to cart could not be pressed', String(e.message).split('\n')[0]));
  await page.waitForTimeout(5000);
  const fb = await page.evaluate(() => ({
    notice: [...document.querySelectorAll('.woocommerce-message, .woocommerce-notices-wrapper *, .wc-block-components-notice-banner')].map(n => n.innerText.trim()).filter(Boolean).join(' | ').slice(0, 120),
    drawer: [...document.querySelectorAll('.site-header-cart-side, .cart-side, .mini-cart, .widget_shopping_cart')].some(d => { const r = d.getBoundingClientRect(); const cs = getComputedStyle(d); return r.width > 0 && r.right > 0 && r.left < innerWidth && cs.visibility !== 'hidden' && +cs.opacity > 0.1; }),
    count: (() => { const c = document.querySelector('.cart-contents .count, .cart-count, .header-cart-count, .mini-cart-count, [class*="cart"] .count'); return c ? c.innerText.trim() : ''; })(),
    err: [...document.querySelectorAll('.woocommerce-error')].map(n => n.innerText.trim()).join(' | ').slice(0, 160),
  }));
  if (fb.err) FAIL('adding to cart showed an error', fb.err);
  else if (fb.notice || fb.drawer || (fb.count && fb.count !== countBefore)) PASS('adding to cart gives clear feedback', fb.notice || (fb.drawer ? 'cart panel opened' : `cart count ${countBefore} → ${fb.count}`));
  else WARN('after Add to cart nothing on the page says it worked', 'no notice, no cart panel, no change in the cart count');
  reportErrors(page, 'adding to cart');

  // 7. Cart.
  await sleep(PAUSE);
  await go(page, '/cart/');
  const cart = await page.evaluate(() => ({
    lines: document.querySelectorAll('.cart_item, .wc-block-cart-items__row, tr.cart_item').length,
    total: !!document.querySelector('.order-total, .cart_totals, .wc-block-components-totals-footer-item'),
    checkout: !!document.querySelector('a.checkout-button, .wc-proceed-to-checkout a, a[href*="/checkout"]'),
    empty: /cart is currently empty/i.test(document.body.innerText),
    remove: !!document.querySelector('a.remove, .wc-block-cart-item__remove-link'),
  }));
  if (cart.empty || !cart.lines) FAIL('the cart is empty after adding a piece');
  else {
    PASS(`cart shows ${cart.lines} line(s)${cart.total ? ' and a total' : ''}`);
    if (!cart.checkout) FAIL('the cart has no way on to checkout');
    if (!cart.remove) WARN('the cart has no way to remove a line');
  }
  reportErrors(page, 'opening the cart');

  // 8. Checkout as a guest, up to the payment choice.
  await sleep(PAUSE);
  await go(page, '/checkout/');
  await page.waitForTimeout(2500);
  const co = await page.evaluate(() => ({
    forcedLogin: !document.querySelector('#billing_email, input[type="email"]') && /log ?in|sign ?in/i.test(document.body.innerText.slice(0, 3000)),
    email: !!document.querySelector('#billing_email, input[type="email"]'),
    fields: [...document.querySelectorAll('form.checkout input, form.checkout select')].filter(i => i.type !== 'hidden' && i.getBoundingClientRect().height > 0).length,
    pay: [...document.querySelectorAll('.wc_payment_methods li label, .wc-block-components-radio-control__label')].map(l => l.innerText.trim()).filter(Boolean),
    place: !!document.querySelector('#place_order, .wc-block-components-checkout-place-order-button'),
  }));
  if (co.forcedLogin) FAIL('checkout asks a new visitor to log in before they can buy');
  else if (!co.email) FAIL('checkout has no email field');
  else PASS(`guest checkout open: ${co.fields} fields`);
  if (co.pay.length) PASS(`payment choices offered: ${co.pay.slice(0, 5).join(', ')}`); else FAIL('no payment choices on checkout');
  if (!co.place) FAIL('no Place order button');
  // A US address: does the delivery cost appear?
  await page.evaluate(() => {
    const set = (id, v) => { const e = document.getElementById(id); if (!e) return; if (window.jQuery) jQuery(e).val(v).trigger('change'); else { e.value = v; e.dispatchEvent(new Event('change', { bubbles: true })); } };
    set('billing_first_name', 'QA'); set('billing_last_name', 'Visitor'); set('billing_country', 'US'); set('billing_address_1', '350 5th Ave');
    set('billing_city', 'New York'); set('billing_state', 'NY'); set('billing_postcode', '10001'); set('billing_phone', '2125550142'); set('billing_email', 'qa-visitor@example.com');
    if (window.jQuery) jQuery(document.body).trigger('update_checkout');
  });
  await page.waitForTimeout(6000);
  const totals = await page.evaluate(() => (document.querySelector('.woocommerce-checkout-review-order-table, #order_review, .wc-block-components-sidebar') || document.body).innerText.replace(/\s+/g, ' '));
  if (/deliver|shipping/i.test(totals) && /\$\s?\d/.test(totals)) PASS('the delivery cost appears once a US address is entered', (totals.match(/(Delivery[^$]{0,30}\$[\d.,]+)|(Shipping[^$]{0,30}\$[\d.,]+)/i) || [''])[0]);
  else WARN('after entering a US address the order summary shows no delivery cost', totals.slice(0, 160));
  reportErrors(page, 'checking out');

  // 9. The pages a careful buyer reads before paying.
  const trust = await page.evaluate(() => {
    const want = { returns: /return|refund/i, shipping: /shipping|delivery/i, contact: /contact/i, privacy: /privacy/i, terms: /terms/i };
    const out = {};
    for (const a of document.querySelectorAll('footer a, .elementor-location-footer a, .site-footer a')) for (const [k, re] of Object.entries(want)) if (!out[k] && re.test(a.innerText)) out[k] = a.href;
    return out;
  });
  const missing = ['returns', 'shipping', 'contact', 'privacy', 'terms'].filter(k => !trust[k]);
  if (missing.length) WARN('the footer has no link to: ' + missing.join(', '));
  else PASS('returns, shipping, contact, privacy and terms are all linked from the footer');
  for (const [k, u] of Object.entries(trust).slice(0, 5)) {
    const r = await fetch(u, { headers: { 'User-Agent': 'AF-QA' } }).catch(() => null);
    if (!r || r.status >= 400) FAIL(`the footer's ${k} link is broken`, `${r ? r.status : 'ERR'} ${rel(u)}`);
  }
  await page.screenshot({ path: `customer-first-visit-${phone ? 'phone' : 'desktop'}.png` }).catch(() => {});
  await ctx.close();
}

/* --------------------------------------------------- signed-in customer */

async function customer(session) {
  WHO = 'signed-in customer';
  const cookies = [{ name: session.name, value: session.value, domain: session.domain, path: session.path || '/', expires: session.expires, httpOnly: true, secure: true, sameSite: 'Lax' }];
  const { ctx, page } = await context({ viewport: { width: 1366, height: 900 } }, cookies);

  // 1. The header knows them.
  await go(page, '/');
  const head = await page.evaluate(() => {
    const h = document.querySelector('header, .elementor-location-header, #masthead') || document.body;
    return { text: h.innerText.replace(/\s+/g, ' ').slice(0, 400), body: document.body.className };
  });
  if (/logged-in/.test(head.body)) PASS('the site recognises the signed-in customer');
  else { FAIL('signed in, but the site treats the visitor as a guest', 'body has no logged-in class: the session was not accepted'); await ctx.close(); return; }
  if (/\b(sign ?in|log ?in|register)\b/i.test(head.text) && !/log ?out|my account|account/i.test(head.text)) WARN('the header still offers Sign in / Register to a signed-in customer', head.text.slice(0, 120));
  reportErrors(page, 'arriving signed in');

  // 2. My Account and each of its pages.
  await sleep(PAUSE);
  await go(page, '/my-account/');
  const nav = await page.evaluate(() => [...document.querySelectorAll('.woocommerce-MyAccount-navigation a')].map(a => ({ label: a.innerText.trim(), href: a.href })));
  const dash = await text(page);
  if (!nav.length) FAIL('My Account shows no account menu', dash.slice(0, 120));
  else PASS(`My Account menu: ${nav.map(n => n.label).join(', ')}`);
  if (/log ?in|password/i.test(dash.slice(0, 600)) && !nav.length) FAIL('My Account shows the login form to a signed-in customer');
  reportErrors(page, 'opening My Account');
  let logout = null;
  for (const n of nav) {
    if (/log ?out|sign ?out/i.test(n.label) || /customer-logout/.test(n.href)) { logout = n.href; continue; }
    await sleep(PAUSE);
    const st = await go(page, rel(n.href));
    const t = await text(page);
    const wcErr = await page.locator('.woocommerce-error').allInnerTexts().catch(() => []);
    if (st === 200 && !wcErr.length) PASS(`"${n.label}" opens`, rel(n.href));
    else if (wcErr.length) FAIL(`"${n.label}" shows an error`, wcErr.join(' | ').slice(0, 140));
    if (/orders/.test(n.href) && /no order has been made|have not placed|no orders/i.test(t)) {
      const browse = await page.locator('.woocommerce-info a, .woocommerce-message a, a.woocommerce-Button').count();
      if (browse) PASS('the empty orders page offers a way back to the shop'); else WARN('the empty orders page offers no way back to the shop');
    }
    reportErrors(page, `opening "${n.label}"`);
  }

  // 3. Save a delivery address, then find it kept.
  await sleep(PAUSE);
  await go(page, '/my-account/edit-address/billing/');
  if (await page.locator('#billing_first_name').count()) {
    const vals = { billing_first_name: 'QA', billing_last_name: 'Customer Test', billing_address_1: '350 5th Ave', billing_city: 'New York', billing_postcode: '10001', billing_phone: '2125550142' };
    for (const [id, v] of Object.entries(vals)) await page.fill('#' + id, v).catch(() => {});
    await page.evaluate(() => { const s = (id, v) => { const e = document.getElementById(id); if (e) { if (window.jQuery) jQuery(e).val(v).trigger('change'); else e.value = v; } }; s('billing_country', 'US'); s('billing_state', 'NY'); });
    await page.waitForTimeout(800);
    await Promise.all([page.waitForLoadState('load').catch(() => {}), page.locator('button[name="save_address"], [name="save_address"]').first().click({ timeout: 6000 }).catch(e => FAIL('the Save address button could not be pressed', String(e.message).split('\n')[0]))]);
    await page.waitForTimeout(2500);
    const msg = await page.locator('.woocommerce-message, .woocommerce-error').allInnerTexts().catch(() => []);
    if (msg.some(m => /changed successfully|saved|updated/i.test(m))) PASS('saving the delivery address confirms it', msg.join(' | ').slice(0, 80));
    else if (msg.length) FAIL('saving the delivery address was refused', msg.join(' | ').slice(0, 160));
    else WARN('saving the address gave no confirmation');
    await sleep(PAUSE);
    await go(page, '/my-account/edit-address/');
    const t = await text(page);
    if (/350 5th Ave/i.test(t)) PASS('the saved address is shown on the Addresses page'); else FAIL('the saved address is not shown afterwards');
  } else FAIL('the billing address form did not open');
  reportErrors(page, 'saving an address');

  // 4. Account details form.
  await sleep(PAUSE);
  await go(page, '/my-account/edit-account/');
  const acct = await page.evaluate(() => ({ email: (document.querySelector('#account_email') || {}).value || '', pass: !!document.querySelector('#password_current, #password_1'), save: !!document.querySelector('[name="save_account_details"]') }));
  if (acct.email === 'qa-customer-test@example.com' && acct.save) PASS('account details show the customer\'s email and can be saved'); else FAIL('account details form incomplete', JSON.stringify(acct));
  if (!acct.pass) WARN('no way to change the password from account details');
  reportErrors(page, 'opening account details');

  // 5. Wishlist kept across a reload.
  await sleep(PAUSE);
  await go(page, '/shop/');
  const prod = await page.locator('a[href*="/product/"]').first().getAttribute('href').catch(() => null);
  if (prod) {
    await sleep(PAUSE);
    await go(page, rel(prod));
    const pname = (await page.locator('h1').first().innerText().catch(() => '')).trim();
    // The piece's own heart: a woosw button carrying this product's id. The
    // header's wishlist link also has "wishlist" in its class, so a loose
    // class match clicked that link instead of adding anything.
    const pid = await page.evaluate(() => { const m = document.body.className.match(/\bpostid-(\d+)/); return m ? m[1] : ''; });
    const wl = page.locator(pid ? `.woosw-btn[data-id="${pid}"]` : '.summary .woosw-btn[data-id], .product .woosw-btn[data-id]')
      .filter({ visible: true }).first();
    if (await wl.count()) {
      await wl.click({ timeout: 6000 }).catch(() => {});
      await page.waitForTimeout(3000);
      await page.keyboard.press('Escape').catch(() => {});
      await sleep(PAUSE);
      await go(page, '/wishlist/');
      await page.reload({ waitUntil: 'load' }).catch(() => {});
      await page.waitForTimeout(1500);
      const t = await text(page);
      const short = pname.split(/[–\-|]/)[0].trim().slice(0, 30);
      const listed = pid ? await page.locator(`.woosw-item-${pid}`).count() : 0;
      if (listed || (short && t.includes(short))) PASS('the wishlist keeps the piece across a reload', short);
      else if (/empty|no products/i.test(t)) FAIL('a piece added to the wishlist is not on the wishlist page', short);
      else WARN('could not find the added piece on the wishlist page', short);
    } else WARN('no wishlist button on the product page');
    reportErrors(page, 'using the wishlist');

    // 6. Checkout filled in from the saved address.
    await sleep(PAUSE);
    await go(page, rel(prod));
    await page.locator('form.cart .single_add_to_cart_button, .single_add_to_cart_button').first().click({ timeout: 8000 }).catch(() => {});
    await page.waitForTimeout(4000);
    await sleep(PAUSE);
    await go(page, '/checkout/');
    await page.waitForTimeout(3000);
    const pre = await page.evaluate(() => ({ addr: (document.querySelector('#billing_address_1') || {}).value || '', email: (document.querySelector('#billing_email') || {}).value || '', city: (document.querySelector('#billing_city') || {}).value || '' }));
    if (pre.addr === '350 5th Ave' && pre.email === 'qa-customer-test@example.com') PASS('checkout is filled in from the saved address and email');
    else WARN('checkout did not fill in the saved details', JSON.stringify(pre));
    reportErrors(page, 'checking out signed in');
  }

  // 7. Signing out.
  if (logout) {
    await sleep(PAUSE);
    await go(page, rel(logout));
    const confirm = page.locator('a:has-text("Confirm and log out"), a[href*="customer-logout"][href*="_wpnonce"]').first();
    if (await confirm.count()) { await Promise.all([page.waitForLoadState('load').catch(() => {}), confirm.click().catch(() => {})]); await page.waitForTimeout(2000); }
    await go(page, '/my-account/');
    const body = await page.evaluate(() => document.body.className);
    if (!/logged-in/.test(body)) PASS('Log out signs the customer out'); else FAIL('after Log out the customer is still signed in');
    reportErrors(page, 'signing out');
  } else FAIL('My Account has no Log out link');
  await page.screenshot({ path: 'customer-signed-in.png' }).catch(() => {});
  await ctx.close();
}

/* ------------------------------------------------------------------- run */

console.log(`=== THE SHOP AS ITS PEOPLE USE IT  ${SITE} ===\n`);
await firstVisit('desktop', { viewport: { width: 1366, height: 900 }, userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-QA-Customer' });
await sleep(8000);
await firstVisit('phone', { ...devices['iPhone 13'] });
await sleep(8000);
const sf = process.env.AF_QA_SESSION_FILE;
if (sf && fs.existsSync(sf)) {
  try { await customer(JSON.parse(fs.readFileSync(sf, 'utf8'))); }
  catch (e) { WHO = 'signed-in customer'; FAIL('the signed-in journey stopped', String(e.message).split('\n')[0]); }
} else { WHO = 'signed-in customer'; WARN('no session given: the signed-in journey was not run'); }
await browser.close();

const order = { FAIL: 0, WARN: 1, INFO: 2, PASS: 3 };
results.sort((a, b) => a.who.localeCompare(b.who) || order[a.sev] - order[b.sev]);
let last = '';
for (const r of results) {
  if (r.who !== last) { console.log(`\n--- ${r.who} ---`); last = r.who; }
  console.log(`  ${r.sev.padEnd(4)} ${r.what}${r.where ? '  [' + r.where + ']' : ''}${r.detail ? '\n        ' + r.detail : ''}`);
}
const c = results.reduce((a, r) => (a[r.sev] = (a[r.sev] || 0) + 1, a), {});
console.log(`\n--- SUMMARY ---  FAIL ${c.FAIL || 0}   WARN ${c.WARN || 0}   INFO ${c.INFO || 0}   PASS ${c.PASS || 0}`);
fs.writeFileSync('customer-findings.json', JSON.stringify(results, null, 1));
