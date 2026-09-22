// DEF-06: no Content-Security-Policy on the pages that handle payment.
//
// Reported absent on /, /shop/, /cart/, /checkout/ and product pages, with
// /my-account/ sending frame-ancestors 'self' only. The stated reason is the
// right one: CSP is the main control against an injected card-skimming
// script, and PCI DSS v4.0 6.4.3 / 11.6.1 expect the scripts on a payment
// page to be managed and monitored.
//
// Two things to establish before writing a policy, because guessing at either
// would produce a policy that either reports nothing or breaks checkout:
//
//   1. WHICH headers actually arrive, per page, and whether that page was
//      served from cache. This host does not run PHP on a cache hit, so a
//      PHP-set header cannot reach a cached page at all — meaning "absent on
//      the home page" and "absent on checkout" have completely different
//      causes and different fixes.
//
//   2. WHAT is actually loading on checkout. A policy written from a guess at
//      the third-party hosts is worthless. The script inventory IS the
//      finding PCI 6.4.3 asks for, so this prints it.
//
// SAFETY. Read-only apart from putting one item in the cart so that checkout
// renders as a real checkout, which is emptied afterwards. The NEVER_SEND
// guard blocks wc-ajax=checkout, so no order can be placed.
//
// Every DOM read is guarded.
//
// Run: node tools/probe-csp.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const WANT = [
  'content-security-policy',
  'content-security-policy-report-only',
  'x-frame-options',
  'x-content-type-options',
  'referrer-policy',
  'permissions-policy',
  'strict-transport-security',
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

const visit = async (path, wait = 2200) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r;
};

const emptyCart = async () => {
  for (let i = 0; i < 6; i++) {
    await visit('/cart/', 1300);
    const h = await page.evaluate(() => {
      const a = document.querySelector('a.remove[href]');
      return a ? a.href : '';
    }).catch(() => '');
    if (!h) break;
    await visit(h, 1100);
  }
};

console.log('probe-csp: ' + SITE + '   ' + new Date().toISOString() + '\n');

try {
  // A product, so /cart/ and /checkout/ render as real pages rather than as
  // an empty-basket notice with a different template.
  await visit('/shop/', 2500);
  let pid = 0, productPath = '';
  try {
    const urls = await page.evaluate(() => [...document.querySelectorAll('a[href*="/product/"]')]
      .map(a => a.href).filter((v, i, a) => a.indexOf(v) === i).slice(0, 6));
    for (const u of urls) {
      await visit(u, 2000);
      const info = await page.evaluate(() => {
        const hidden = document.querySelector('input[name="add-to-cart"]');
        const m = ((document.body && document.body.className) || '').match(/postid-(\d+)/);
        const btn = document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]');
        if (!btn) return null;
        return { pid: Number((hidden && hidden.value) || (m && m[1]) || 0) };
      }).catch(() => null);
      if (info && info.pid) { pid = info.pid; productPath = u.replace(SITE, ''); break; }
    }
  } catch (e) { console.log('  product pick failed: ' + String(e.message).slice(0, 80)); }

  if (pid) {
    await emptyCart();
    await visit('/?add-to-cart=' + pid + '&quantity=1', 3000);
  }

  // ── which headers arrive, and was PHP even run ────────────────────────
  const pages = [
    ['/', 'home'],
    ['/shop/', 'shop'],
    [productPath || '/shop/', 'product'],
    ['/cart/', 'cart'],
    ['/checkout/', 'checkout'],
    ['/my-account/', 'my-account'],
  ];

  console.log('— which security headers arrive, per page —');
  console.log('  (ls-cache=hit means PHP never ran, so a PHP-set header cannot be there)\n');
  const seen = {};
  for (const [path, label] of pages) {
    const r = await visit(path, 2600);
    if (!r) { console.log('  ' + label.padEnd(12) + 'no response'); continue; }
    let h = {};
    try { h = await r.allHeaders(); } catch (e) { try { h = r.headers(); } catch (e2) { h = {}; } }
    const get = k => (h && h[k] ? String(h[k]) : '');
    const cache = get('x-litespeed-cache') || get('x-qc-cache') || '(none)';
    console.log('  ' + label.padEnd(12) + 'HTTP ' + r.status() + '   ls-cache=' + cache);
    for (const k of WANT) {
      const v = get(k);
      console.log('      ' + k.padEnd(38) + (v ? v.slice(0, 90) : '— absent'));
    }
    seen[label] = { csp: get('content-security-policy'), cspro: get('content-security-policy-report-only'), cache };
    console.log('');
  }

  // ── what is actually loading on checkout ──────────────────────────────
  console.log('— the script inventory on /checkout/, which is what PCI 6.4.3 asks you to manage —');
  await visit('/checkout/', 4000);
  const inv = await page.evaluate(() => {
    const here = location.hostname;
    const scripts = [...document.querySelectorAll('script')];
    const ext = scripts.map(s => s.src).filter(Boolean);
    const hosts = {};
    ext.forEach(u => {
      let h = '';
      try { h = new URL(u, location.href).hostname; } catch (e) { return; }
      const key = (h === here ? 'self' : h);
      hosts[key] = (hosts[key] || 0) + 1;
    });
    const frames = [...document.querySelectorAll('iframe')].map(f => {
      try { return f.src ? new URL(f.src, location.href).hostname : '(srcdoc/about:blank)'; }
      catch (e) { return '(unparseable)'; }
    });
    const fh = {};
    frames.forEach(h => { fh[h] = (fh[h] || 0) + 1; });
    return {
      total: scripts.length,
      inline: scripts.length - ext.length,
      external: ext.length,
      hosts,
      frames: fh,
      isCheckout: /checkout/i.test(((document.body && document.body.className) || '')) ||
                  !!document.querySelector('form.checkout, .woocommerce-checkout'),
    };
  }).catch(() => null);

  if (!inv) {
    console.log('  could not read the checkout DOM — NO DATA');
  } else {
    console.log('  checkout template rendered : ' + inv.isCheckout);
    console.log('  script tags total          : ' + inv.total
      + '   (' + inv.inline + ' inline, ' + inv.external + ' external)');
    console.log('\n  script hosts:');
    const entries = Object.entries(inv.hosts).sort((a, b) => b[1] - a[1]);
    if (!entries.length) console.log('    (none)');
    entries.forEach(([h, n]) => console.log('    ' + String(n).padStart(3) + '  ' + h));
    console.log('\n  iframe hosts (a card field is an iframe, so this is frame-src):');
    const fe = Object.entries(inv.frames).sort((a, b) => b[1] - a[1]);
    if (!fe.length) console.log('    (none)');
    fe.forEach(([h, n]) => console.log('    ' + String(n).padStart(3) + '  ' + h));
  }

  // ── verdict ───────────────────────────────────────────────────────────
  console.log('\n— verdict —');
  const co = seen['checkout'] || {};
  if (!co.csp && !co.cspro) {
    console.log('  → DEF-06 CONFIRMED on checkout — neither Content-Security-Policy nor');
    console.log('    Content-Security-Policy-Report-Only is sent. Checkout is uncached');
    console.log('    (ls-cache=' + (co.cache || '?') + '), so PHP does run there and a header set in PHP');
    console.log('    will reach it.');
  } else if (co.cspro && !co.csp) {
    console.log('  → Report-Only is present on checkout: ' + co.cspro.slice(0, 110));
    console.log('    Nothing is blocked by it. This is the collect-then-enforce stage.');
  } else {
    console.log('  → An enforcing CSP is present on checkout: ' + co.csp.slice(0, 110));
  }
  const cachedMissing = ['home', 'shop', 'product']
    .filter(k => seen[k] && !seen[k].csp && !seen[k].cspro && /hit/i.test(seen[k].cache || ''));
  if (cachedMissing.length) {
    console.log('\n  note: ' + cachedMissing.join(', ') + ' answered from cache, so PHP never ran on');
    console.log('  them. No PHP-set header can reach those — that part is a CDN/LiteSpeed');
    console.log('  setting in hPanel, not a code change, and it is a different job from this one.');
  }

  await emptyCart();
  const empty = await page.evaluate(() => /your cart is currently empty/i.test(((document.body && document.body.innerText) || ''))).catch(() => false);
  console.log('\n  cleanup: cart empty = ' + empty);
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
