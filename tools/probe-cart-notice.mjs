// H-01: the coupon error that never appears. Stop guessing, ask the server.
//
// Three fixes have missed this. Each time I diagnosed it from the outside and
// each time the diagnosis was wrong — I said the hook does not fire (it does),
// I said the wrapper is missing (it is there, twice). What has never been
// measured is the one thing that decides it: does the notice exist in the
// bytes the server sends back?
//
// So this splits the question into layers and reports each one separately:
//
//   1. raw POST     the coupon form submitted from the page, response read as
//                   text. If "woocommerce-error" is in that string, the server
//                   is doing its job and the loss is in the browser. If it is
//                   not, the loss is in PHP and no amount of CSS will help.
//   2. the marker   our print-once hook emits <!-- af-notices via HOOK -->.
//                   Present means the hook ran; absent means it did not.
//   3. the cache    x-litespeed-cache on /cart/ through the whole redirect
//                   chain. A cached cart page cannot carry a notice that was
//                   created one request ago, and this site is already serving
//                   one stylesheet from two different copies.
//   4. the DOM      if a notice element exists but is not visible, say what is
//                   hiding it — display, visibility, opacity, height, and the
//                   first ancestor that is display:none.
//   5. the control  removing the cart item makes WooCommerce set an ordinary
//                   message. If that one renders and the coupon error does not,
//                   this is about coupons; if neither renders, notices are
//                   broken for everything.
//
// SAFETY. Live shop, the NEVER_SEND wire guard from qa-personas.mjs. One item
// added to a cart and removed again. The coupon is deliberate nonsense, so the
// only thing it can ever do is fail.
//
// Run: node tools/probe-cart-notice.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const COUPON = 'AFPROBE-NOT-A-REAL-CODE';

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true}' });
  }
  return route.continue();
});
const page = await ctx.newPage();

// Every response, so the redirect chain can be read back afterwards.
const wire = [];
page.on('response', r => wire.push({
  url: r.url().replace(SITE, ''), status: r.status(),
  ls: r.headers()['x-litespeed-cache'] || '-', loc: r.headers()['location'] || '',
}));

const go = async (path, wait = 1500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 40000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r;
};

// What the page says about notices, from the DOM's point of view.
const noticeState = () => page.evaluate(() => {
  const html = document.documentElement.outerHTML;
  const els = [...document.querySelectorAll('.woocommerce-error, .woocommerce-message, .woocommerce-info, ul.woocommerce-error li')];
  return {
    markers: (html.match(/<!-- af-notices via [a-z_]+ -->/g) || []),
    wrappers: document.querySelectorAll('.woocommerce-notices-wrapper').length,
    wrapperText: [...document.querySelectorAll('.woocommerce-notices-wrapper')].map(w => (w.innerHTML || '').trim().length),
    inHtml: {
      error: /woocommerce-error/.test(html),
      message: /woocommerce-message/.test(html),
      info: /woocommerce-info/.test(html),
      coupon: /coupon/i.test(html),
      lsFooter: (html.match(/<!--\s*Page (?:generated|optimized) by LiteSpeed[^>]*-->/i) || ['-'])[0].slice(0, 120),
    },
    elements: els.map(el => {
      const s = getComputedStyle(el);
      let hiddenBy = '';
      for (let p = el; p && p !== document.documentElement; p = p.parentElement) {
        const ps = getComputedStyle(p);
        if (ps.display === 'none' || ps.visibility === 'hidden') {
          hiddenBy = p.tagName.toLowerCase() + '.' + (p.className || '').toString().split(/\s+/).slice(0, 2).join('.') + ' ' + ps.display + '/' + ps.visibility;
          break;
        }
      }
      return {
        cls: (el.className || '').toString().slice(0, 60),
        text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 90),
        display: s.display, visibility: s.visibility, opacity: s.opacity,
        h: Math.round(el.getBoundingClientRect().height), hiddenBy,
      };
    }),
  };
});

const show = (label, st) => {
  console.log('  ' + label);
  console.log('    hook markers      : ' + (st.markers.length ? st.markers.join(' ') : '(none — our print-once hook did not run)'));
  console.log('    notice wrappers   : ' + st.wrappers + '  inner lengths: [' + st.wrapperText.join(', ') + ']');
  console.log('    in raw html       : error=' + st.inHtml.error + ' message=' + st.inHtml.message + ' info=' + st.inHtml.info);
  console.log('    litespeed footer  : ' + st.inHtml.lsFooter);
  if (!st.elements.length) console.log('    notice elements   : none in the DOM');
  for (const e of st.elements) {
    console.log('    notice element    : .' + e.cls);
    console.log('        text="' + e.text + '"');
    console.log('        display=' + e.display + ' visibility=' + e.visibility + ' opacity=' + e.opacity + ' height=' + e.h + 'px'
      + (e.hiddenBy ? '   HIDDEN BY ancestor ' + e.hiddenBy : ''));
  }
};

// A cart page pulls in 150 scripts, images and fonts, and printing all of
// them is how the first run buried its own answer. The question here is only
// about documents and redirects.
const STATIC = /\.(?:js|css|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|mp4|webm)(?:\?|$)/i;
const chain = from => {
  const seg = wire.slice(from).filter(w => !STATIC.test(w.url) && !/get_refreshed_fragments/.test(w.url));
  for (const w of seg) console.log('    ' + String(w.status).padEnd(5) + 'ls-cache=' + String(w.ls).padEnd(9)
    + w.url.slice(0, 90) + (w.loc ? '  → ' + w.loc.replace(SITE, '') : ''));
  return seg;
};

console.log('probe-cart-notice: ' + SITE + '   ' + new Date().toISOString());

try {
  // ── a product that can actually be bought ────────────────────────────────
  await go('/shop/', 2500);
  const picks = await page.evaluate(() => [...document.querySelectorAll('li.product, .product.type-product')]
    .filter(c => !/price on request/i.test(c.innerText) && /\$[\d,.]+/.test(c.innerText))
    .map(c => (c.querySelector('a[href]') || {}).href).filter(Boolean).slice(0, 6));
  let added = false;
  for (const url of picks) {
    await go(url, 2000);
    const buyable = await page.evaluate(() => !!document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]'));
    if (!buyable) continue;
    await page.click('.single_add_to_cart_button, button[name="add-to-cart"]').catch(() => {});
    await page.waitForTimeout(4000);
    const n = await page.evaluate(() => {
      const t = document.body.innerText;
      const m = t.match(/(\d+)\s*item/i);
      return m ? Number(m[1]) : (/added to your cart|view cart/i.test(t) ? 1 : 0);
    });
    if (n > 0) { added = true; console.log('\n  cart: added from ' + url.replace(SITE, '')); break; }
  }
  if (!added) console.log('\n  cart: NOTHING ADDED — everything below is about an empty cart, read it that way');

  // ── 1. the cart page as it stands, before any coupon ─────────────────────
  console.log('\n— /cart/ before the coupon —');
  let mark = wire.length;
  await go('/cart/', 2500);
  chain(mark);
  show('state:', await noticeState());

  // ── 2. the coupon submitted the way a person submits it ──────────────────
  console.log('\n— coupon submitted through the form —');
  const hasForm = await page.evaluate(() => !!document.querySelector('#coupon_code, input[name="coupon_code"]'));
  if (!hasForm) {
    console.log('  no coupon field on /cart/ — cannot test this path');
  } else {
    mark = wire.length;
    await page.fill('#coupon_code, input[name="coupon_code"]', COUPON);
    await Promise.all([
      page.waitForNavigation({ timeout: 25000 }).catch(() => null),
      page.click('button[name="apply_coupon"], input[name="apply_coupon"], [name="apply_coupon"]').catch(() => {}),
    ]);
    // A slow host: wait for a notice OR for the page to settle, up to 20s.
    await page.waitForFunction(() => document.querySelector('.woocommerce-error, .woocommerce-message, .woocommerce-info'),
      null, { timeout: 20000 }).catch(() => {});
    await page.waitForTimeout(1500);
    console.log('  the requests that followed the click:');
    chain(mark);
    show('state after:', await noticeState());
  }

  // ── 3. the same POST, read as bytes, with the DOM taken out of it ────────
  console.log('\n— the same coupon POST, response read as text —');
  const raw = await page.evaluate(async coupon => {
    const form = document.querySelector('form.woocommerce-cart-form, form[action*="cart"]') || document.querySelector('form');
    if (!form) return { err: 'no form on the page to take the fields from' };
    const fd = new FormData(form);
    fd.set('coupon_code', coupon);
    fd.set('apply_coupon', 'Apply coupon');
    // No timeout here is how the first attempt at this hung for seventeen
    // minutes and had to be cancelled: a page that takes seven seconds to
    // answer a GET can take longer than forever to answer a POST, and fetch
    // will wait for all of it.
    const res = await fetch(form.action || location.href, {
      method: 'POST', body: fd, redirect: 'follow', credentials: 'same-origin',
      signal: AbortSignal.timeout(45000),
    });
    const t = await res.text();
    const m = t.match(/<ul class="woocommerce-error"[\s\S]{0,400}?<\/ul>/i)
           || t.match(/class="woocommerce-(?:error|message|info)"[\s\S]{0,300}/i);
    return {
      status: res.status, url: res.url, len: t.length, redirected: res.redirected,
      ls: res.headers.get('x-litespeed-cache') || '-',
      hasError: /woocommerce-error/.test(t), hasMessage: /woocommerce-message/.test(t),
      hasWrapper: (t.match(/woocommerce-notices-wrapper/g) || []).length,
      marker: (t.match(/<!-- af-notices via [a-z_]+ -->/g) || []),
      couponWord: /coupon/i.test(t),
      snippet: m ? m[0].replace(/\s+/g, ' ').slice(0, 300) : '',
    };
  }, COUPON).catch(e => ({ err: String(e.message).slice(0, 160) }));
  if (raw.err) {
    console.log('  could not read it: ' + raw.err);
  } else {
    console.log('    HTTP ' + raw.status + '  ' + raw.len + 'b  redirected=' + raw.redirected + '  ls-cache=' + raw.ls);
    console.log('    landed on        : ' + String(raw.url).replace(SITE, ''));
    console.log('    hook markers     : ' + (raw.marker.length ? raw.marker.join(' ') : '(none)'));
    console.log('    notices-wrapper  : ' + raw.hasWrapper + ' occurrences');
    console.log('    woocommerce-error in the bytes : ' + raw.hasError);
    console.log('    woocommerce-message in the bytes: ' + raw.hasMessage);
    if (raw.snippet) console.log('    the notice itself: ' + raw.snippet);
    console.log('\n    → ' + (raw.hasError
      ? 'THE SERVER SENDS THE NOTICE. The loss is in the browser — CSS, JS, or the theme replacing the page after load.'
      : 'THE SERVER DOES NOT SEND IT. No CSS fix can help; the notice is created and dropped before the HTML is built.'));
  }

  // ── 4. the control: does ANY notice render? ──────────────────────────────
  console.log('\n— control: remove the cart item, which sets an ordinary message —');
  mark = wire.length;
  await go('/cart/', 2000);
  const href = await page.evaluate(() => {
    const a = document.querySelector('.woocommerce-cart-form a.remove[href], a.remove[href*="remove_item"]');
    return a ? a.href : '';
  });
  if (!href) {
    console.log('  no remove link on /cart/ (cart may already be empty) — no control available');
  } else {
    await go(href, 2500);
    chain(mark);
    const st = await noticeState();
    show('state after removal:', st);
    const rendered = st.elements.some(e => e.h > 0 && e.display !== 'none' && e.visibility !== 'hidden');
    console.log('\n    → ' + (rendered
      ? 'An ordinary WooCommerce message DOES render. So notices work; the coupon path is what is broken.'
      : 'Not even the removal message renders. Notices are broken for everything, not just coupons.'));
  }

  // ── leave the cart as we found it ────────────────────────────────────────
  for (let i = 0; i < 3; i++) {
    await go('/cart/', 1500);
    const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; });
    if (!h) break;
    await go(h, 1500);
  }
  const left = await page.evaluate(() => /your cart is currently empty/i.test(document.body.innerText));
  console.log('\n  cleanup: cart empty = ' + left);
} catch (e) {
  console.log('\n  the probe stopped early: ' + String(e.message).slice(0, 200));
}

// A hung page keeps the browser alive and the job with it. Nothing below this
// line matters more than the log getting written.
setTimeout(() => process.exit(0), 5000).unref();

await browser.close();
console.log('\ndone ' + new Date().toISOString());
