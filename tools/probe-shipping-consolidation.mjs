// DEF-03: does shipping still climb by $35.01 for every extra print?
//
// Before the fix, measured at checkout for a 36×48 in rolled print to CA
// 90001: $49.02 for one, then +$35.01 a piece, uncapped — $189.08 for five on
// a $400 order. The cause was billing each piece's dimensional weight, which
// is the volume of THE TUBE, so five prints in one tube were charged five
// tubes' worth of air.
//
// Expected after the fix, and these are the numbers to hold it to:
//
//     qty 1   $49.02   (unchanged — one print is one tube)
//     qty 2   $49.02   (was $84.03)
//     qty 3   $49.02   (was $119.05)
//     qty 5   $64.70   (was $189.08)
//
// Two and three matching one is CORRECT, not a broken measurement: the tube's
// dimensional weight still outweighs what is inside it. The fifth print is
// where real weight passes dimensional and the number moves again.
//
// SAFETY. The NEVER_SEND guard, which lists wc-ajax=checkout — no order can
// be placed. The cart is emptied at the end and the run says whether it
// managed it.
//
// Every DOM read is guarded. Four probes died on their own nulls today.
//
// Run: node tools/probe-shipping-consolidation.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const ZIP = '90001', STATE = 'CA';

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

const txt = el => ((el && el.innerText) || '') + '';
const go = async (path, wait = 2500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

// Money off the cart totals, read by row label rather than by position.
const readTotals = () => page.evaluate(() => {
  const money = s => {
    const m = String(s || '').replace(/\s+/g, ' ').match(/\$\s?([\d,]+\.\d{2})/);
    return m ? Number(m[1].replace(/,/g, '')) : null;
  };
  const rowFor = re => {
    const rows = [...document.querySelectorAll('tr, .cart_totals tr, li')];
    const hit = rows.find(r => re.test(((r.innerText || '') + '')));
    return hit ? ((hit.innerText || '') + '') : '';
  };
  const qtyInput = document.querySelector('input.qty, input[name^="cart"][name$="[qty]"]');
  return {
    subtotal: money(rowFor(/subtotal/i)),
    shipping: money(rowFor(/shipping|shipment|delivery/i)),
    total:    money(rowFor(/^\s*total/im)) ?? money(rowFor(/total/i)),
    qtyShown: qtyInput ? Number(qtyInput.value || 0) : null,
    lines: document.querySelectorAll('.woocommerce-cart-form__cart-item, tr.cart_item').length,
    raw: (rowFor(/shipping|shipment|delivery/i) || '').slice(0, 80),
  };
});

console.log('probe-shipping-consolidation: ' + SITE + '   ' + new Date().toISOString());
console.log('destination ' + STATE + ' ' + ZIP + '\n');

try {
  // ── a rolled, unframed, priced product ──────────────────────────────────
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

  let product = '';
  for (const url of picks) {
    await go(url, 2200);
    const ok = await page.evaluate(() => !!document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]'));
    if (ok) { product = url; break; }
  }
  if (!product) { console.log('  no buyable product found — NO DATA'); }
  else {
    console.log('  product: ' + product.replace(SITE, '') + '\n');
    console.log('  qty   subtotal     shipping     total        Δ shipping');

    let prevShip = null;
    const seen = [];
    for (const qty of [1, 2, 3, 5]) {
      // Rebuild the cart from empty each time, so a stale line cannot carry over.
      for (let i = 0; i < 4; i++) {
        await go('/cart/', 1500);
        const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; });
        if (!h) break;
        await go(h, 1200);
      }
      await go(product + (product.includes('?') ? '&' : '?') + 'add-to-cart=', 800);
      await go(product, 2000);
      // set quantity then add
      await page.evaluate(n => {
        const q = document.querySelector('input.qty, input[name="quantity"]');
        if (q) { q.value = String(n); q.dispatchEvent(new Event('change', { bubbles: true })); }
      }, qty).catch(() => {});
      await page.click('.single_add_to_cart_button, button[name="add-to-cart"]').catch(() => {});
      await page.waitForTimeout(4500);

      await go('/cart/', 3500);
      // give the shipping calculator the destination
      await page.evaluate(({ zip, state }) => {
        const z = document.querySelector('#calc_shipping_postcode, input[name="calc_shipping_postcode"]');
        if (z) { z.value = zip; z.dispatchEvent(new Event('change', { bubbles: true })); }
        const st = document.querySelector('#calc_shipping_state, select[name="calc_shipping_state"]');
        if (st) { st.value = state; st.dispatchEvent(new Event('change', { bubbles: true })); }
        const btn = document.querySelector('button[name="calc_shipping"], .shipping-calculator-form button');
        if (btn) btn.click();
      }, { zip: ZIP, state: STATE }).catch(() => {});
      await page.waitForTimeout(6000);

      const t = await readTotals();
      const d = (prevShip !== null && t.shipping !== null) ? (t.shipping - prevShip) : null;
      console.log('  ' + String(qty).padEnd(6)
        + ('$' + (t.subtotal ?? '?')).padEnd(13)
        + ('$' + (t.shipping ?? '?')).padEnd(13)
        + ('$' + (t.total ?? '?')).padEnd(13)
        + (d === null ? '—' : ('+$' + d.toFixed(2))));
      seen.push({ qty, ship: t.shipping });
      prevShip = t.shipping;
    }

    // ── verdict ───────────────────────────────────────────────────────────
    const s = Object.fromEntries(seen.map(x => [x.qty, x.ship]));
    console.log('\n— verdict —');
    console.log('    before the fix : 1=$49.02  2=$84.03  3=$119.05  5=$189.08   (+$35.01/piece)');
    console.log('    expected after : 1=$49.02  2=$49.02  3=$49.02   5=$64.70');
    console.log('    measured now   : '
      + [1, 2, 3, 5].map(q => q + '=$' + (s[q] ?? '?')).join('  '));
    if ([1, 2, 3, 5].some(q => s[q] == null)) {
      console.log('\n  → NO DATA — at least one shipping total could not be read.');
    } else if (s[2] > s[1] + 20) {
      console.log('\n  → DEF-03 NOT FIXED — shipping still climbs about $35 a piece.');
    } else if (Math.abs(s[2] - s[1]) < 0.02 && Math.abs(s[3] - s[1]) < 0.02 && s[5] > s[1]) {
      console.log('\n  → DEF-03 FIXED — two and three now ship for the same as one, and five'
                + '\n    costs $' + s[5] + ' instead of $189.08. Consolidation is working.');
    } else {
      console.log('\n  → PARTLY — the curve changed but not to the expected shape. Read the rows.');
    }
  }

  for (let i = 0; i < 4; i++) {
    await go('/cart/', 1500);
    const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; });
    if (!h) break;
    await go(h, 1200);
  }
  const empty = await page.evaluate(() => /your cart is currently empty/i.test(((document.body && document.body.innerText) || '')));
  console.log('\n  cleanup: cart empty = ' + empty);
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
