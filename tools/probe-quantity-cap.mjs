// DEF-05: is there any upper bound on quantity?
//
// Reported: POST add-to-cart with quantity=99999 is accepted with a success
// message, subtotal $7,999,920.00. Also at 500 units ($40,000.00). The
// quantity input carries min="1" and no max.
//
// Two different claims are bundled in there and they need separating, because
// they have different fixes:
//
//   1. the INPUT advertises no ceiling   → a max attribute
//   2. the SERVER accepts anything sent  → validation that cannot be bypassed
//
// A max attribute alone fixes nothing: anyone can send the request without a
// browser. So this drives the server directly, through WooCommerce's own
// add-to-cart URL, with no input involved at all. If the server is the thing
// that holds, it holds for everyone.
//
// Also checks the cart's own update path, which is a second way in that a
// product-page fix would miss.
//
// SAFETY. The NEVER_SEND guard, which lists wc-ajax=checkout — no order can
// be placed. The cart is emptied at the end and the run says whether it
// managed it.
//
// Every DOM read is guarded.
//
// Run: node tools/probe-quantity-cap.mjs [url]

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

const go = async (path, wait = 2200) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

const emptyCart = async () => {
  for (let i = 0; i < 6; i++) {
    await go('/cart/', 1400);
    const h = await page.evaluate(() => {
      const a = document.querySelector('a.remove[href]');
      return a ? a.href : '';
    }).catch(() => '');
    if (!h) break;
    await go(h, 1100);
  }
};

// What the cart actually holds, read by label rather than by position.
const readCart = () => page.evaluate(() => {
  const money = s => {
    const m = String(s || '').replace(/\s+/g, ' ').match(/\$\s?([\d,]+\.\d{2})/);
    return m ? Number(m[1].replace(/,/g, '')) : null;
  };
  const rowFor = re => {
    const rows = [...document.querySelectorAll('tr, .cart_totals tr, li')];
    const hit = rows.find(r => re.test(((r.innerText || '') + '')));
    return hit ? ((hit.innerText || '') + '') : '';
  };
  // The row selector missed the totals on the first run and printed "$?".
  // Cart totals are not always a <tr>, and the label and the amount are often
  // on separate lines, so fall back to reading the rendered text around the
  // label. A measurement that cannot read its own numbers is not a
  // measurement.
  const lineFor = re => {
    const lines = ((document.body && document.body.innerText) || '').split('\n');
    for (let i = 0; i < lines.length; i++) {
      if (!re.test(lines[i])) continue;
      const chunk = lines.slice(i, i + 3).join(' ');
      if (/\$\s?[\d,]+\.\d{2}/.test(chunk)) return chunk;
    }
    return '';
  };
  const q = document.querySelector('input[name^="cart"][name$="[qty]"], input.qty');
  const body = ((document.body && document.body.innerText) || '');
  return {
    qty:      q ? Number(q.value || 0) : null,
    qtyMax:   q ? (q.getAttribute('max') || '(none)') : '(no input)',
    subtotal: money(rowFor(/subtotal/i)) ?? money(lineFor(/subtotal/i)),
    total:    money(rowFor(/^\s*total/im)) ?? money(rowFor(/total/i)) ?? money(lineFor(/total/i)),
    lines:    document.querySelectorAll('.woocommerce-cart-form__cart-item, tr.cart_item').length,
    empty:    /your cart is currently empty/i.test(body),
    notice:   (body.match(/[^\n]*(?:cannot|can't|maximum|max(?:imum)?\s|too many|limit)[^\n]*/i) || [''])[0].trim().slice(0, 120),
  };
});

console.log('probe-quantity-cap: ' + SITE + '   ' + new Date().toISOString() + '\n');

try {
  // ── find a buyable product and learn its id ─────────────────────────────
  await go('/shop/', 2500);
  let picks = [];
  try {
    picks = await page.evaluate(() => [...document.querySelectorAll('li.product, .product.type-product, .product')]
      .map(c => ({ t: ((c.innerText || '') + ''), href: ((c.querySelector('a[href]') || {}).href) || '' }))
      .filter(x => x.href && !/price on request/i.test(x.t) && /\$\s?[\d,.]+/.test(x.t))
      .map(x => x.href).slice(0, 8));
  } catch (e) { console.log('  /shop/ read failed: ' + String(e.message).slice(0, 90)); }
  if (!picks.length) {
    try {
      picks = await page.evaluate(() => [...document.querySelectorAll('a[href*="/product/"]')]
        .map(a => a.href).filter((v, i, a) => a.indexOf(v) === i).slice(0, 8));
    } catch (e) {}
  }

  let product = '', pid = 0, inputAttrs = null;
  for (const url of picks) {
    await go(url, 2200);
    const info = await page.evaluate(() => {
      const btn = document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]');
      if (!btn) return null;
      const hidden = document.querySelector('input[name="add-to-cart"]');
      const bodyCls = ((document.body && document.body.className) || '');
      const m = bodyCls.match(/postid-(\d+)/);
      const q = document.querySelector('input.qty, input[name="quantity"]');
      return {
        pid: Number((hidden && hidden.value) || (btn.value) || (m && m[1]) || 0),
        min:  q ? (q.getAttribute('min')  || '(none)') : '(no input)',
        max:  q ? (q.getAttribute('max')  || '(none)') : '(no input)',
        step: q ? (q.getAttribute('step') || '(none)') : '(no input)',
      };
    }).catch(() => null);
    if (info && info.pid) { product = url; pid = info.pid; inputAttrs = info; break; }
  }

  if (!pid) {
    console.log('  no buyable product with a readable id — NO DATA');
  } else {
    console.log('  product: ' + product.replace(SITE, '') + '   (id ' + pid + ')\n');

    console.log('— what the quantity input advertises —');
    console.log('    min  : ' + inputAttrs.min);
    console.log('    max  : ' + inputAttrs.max);
    console.log('    step : ' + inputAttrs.step + '\n');

    // ── server side, no input involved ──────────────────────────────────
    console.log('— server side, driven through WooCommerce\'s own add-to-cart URL —');
    console.log('    sent      accepted?   cart qty     subtotal      notice');
    const results = {};
    for (const qty of [25, 500, 99999]) {
      await emptyCart();
      await go('/?add-to-cart=' + pid + '&quantity=' + qty, 3500);
      await go('/cart/', 3000);
      const c = await readCart();
      results[qty] = c;
      const accepted = !c.empty && c.qty === qty;
      console.log('    ' + String(qty).padEnd(10)
        + String(accepted ? 'YES' : (c.empty ? 'no (cart empty)' : 'clamped/other')).padEnd(12)
        + String(c.qty ?? '?').padEnd(13)
        + ('$' + (c.subtotal ?? '?')).padEnd(14)
        + (c.notice || '—'));
    }

    // ── the cart's own update path, a second way in ─────────────────────
    console.log('\n— the cart\'s update path —');
    await emptyCart();
    await go('/?add-to-cart=' + pid + '&quantity=1', 3000);
    await go('/cart/', 3000);
    let updated = null;
    try {
      await page.evaluate(() => {
        const q = document.querySelector('input[name^="cart"][name$="[qty]"], input.qty');
        if (q) { q.value = '99999'; q.dispatchEvent(new Event('change', { bubbles: true })); }
        const b = document.querySelector('button[name="update_cart"], input[name="update_cart"]');
        if (b) { b.disabled = false; b.removeAttribute('disabled'); b.click(); }
      });
      await page.waitForTimeout(6000);
      updated = await readCart();
      console.log('    set 99999 then Update cart → qty ' + (updated.qty ?? '?')
        + '   subtotal $' + (updated.subtotal ?? '?')
        + (updated.notice ? '   notice: ' + updated.notice : '   notice: —'));
      console.log('    cart input max attribute   : ' + updated.qtyMax);
    } catch (e) {
      console.log('    update step failed: ' + String(e.message).slice(0, 90));
    }

    // ── verdict ─────────────────────────────────────────────────────────
    const big = results[99999], mid = results[500], ok = results[25];
    console.log('\n— verdict —');
    console.log('    reported: 99999 accepted, subtotal $7,999,920.00; 500 accepted, $40,000.00;');
    console.log('              input min="1" and no max.');
    const heldBig = big && (big.empty || (big.qty !== null && big.qty < 99999));
    const heldMid = mid && (mid.empty || (mid.qty !== null && mid.qty < 500));
    const okPasses = ok && !ok.empty && ok.qty === 25;
    if (!big || big.qty === null) {
      console.log('\n  → NO DATA — the cart could not be read after the 99999 attempt.');
    } else if (!heldBig && !heldMid) {
      console.log('\n  → DEF-05 CONFIRMED — the server accepts ' + big.qty + ' units'
                + ' (subtotal $' + (big.subtotal ?? '?') + ').'
                + '\n    A max attribute on the input would not have stopped this request,'
                + '\n    because no input was used to make it.');
    } else if (heldBig && heldMid && okPasses) {
      console.log('\n  → DEF-05 FIXED — absurd quantities are refused server-side while a'
                + '\n    real order of 25 still goes through. The ceiling holds against a'
                + '\n    request that never touched the page.');
    } else if (heldBig && heldMid && !okPasses) {
      console.log('\n  → TOO TIGHT — big quantities are refused, but 25 did not go through'
                + '\n    either (qty ' + (ok && ok.qty) + '). That would block real orders.');
    } else {
      console.log('\n  → PARTLY — read the rows; the ceiling holds in some paths and not others.');
    }
  }

  await emptyCart();
  const fin = await readCart();
  console.log('\n  cleanup: cart empty = ' + (fin ? fin.empty : 'unknown'));
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
