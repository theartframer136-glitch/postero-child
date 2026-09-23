// DEF-14: jQuery Migrate is shipped to production.
//
// Reported: "JQMIGRATE: Migrate is installed, version 3.4.1" on every page.
// The report's fix: disable it, load the site, fix whatever breaks.
//
// Doing that blind means finding the breakage in production. Migrate itself
// can say in advance what depends on it. Every call it patches goes through
// migrateWarn(), which logs "JQMIGRATE: <what>" with console.warn. So this
// wraps console.warn before any page script runs and keeps, for each warning,
// the script that made the call (the first stack frame outside Migrate).
//
// The warnings fall into two kinds, and only one of them matters:
//   REMOVED    — an API jQuery 3 no longer has (.size(), .andSelf(), the
//                'ready' event, .load(fn) …). Migrate puts it back. Without
//                Migrate that call throws or silently does nothing.
//   DEPRECATED — an API jQuery 3.7 still has (.bind(), .click() shorthand,
//                $.trim …). Migrate only warns. It keeps working without it.
// Only REMOVED entries are breakage. An empty REMOVED list means Migrate can go.
//
// Pages: home, shop, a category, a product (a size picked and added to the
// cart — the most jQuery-heavy path on the site), cart, checkout, wishlist,
// login. It also records uncaught page errors, so the run after the fix can
// be compared against this one: a new "is not a function" is breakage.
//
// SAFETY: wc-ajax=checkout is in NEVER_SEND, so no order can be placed. The
// cart is emptied at the end.
//
// Run: node tools/probe-jquery-migrate.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const NEVER_SEND = ['af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save'];

// Migrate 3.x warning texts for APIs jQuery 3 has removed. Anything else it
// warns about still exists in jQuery 3.7 and keeps working without Migrate.
const REMOVED = [
  /andSelf/i, /\.size\(\)|fn\.size/i, /'ready' event|ready event/i, /jQuery\.fn\.load\(\)|fn\.(load|unload|error)\(\) is/i,
  /event\.props|fixHooks/i, /jQuery\.browser/i, /\.selector|\.context/i, /jQuery\.sub/i,
  /HTML tags must be properly nested/i, /jQuery\.easing/i, /toggle\(\s*handler/i,
];
const kind = m => REMOVED.some(re => re.test(m)) ? 'REMOVED' : 'deprecated';

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
await ctx.addInitScript(() => {
  window.__afMigrate = [];
  // V8 keeps 10 frames by default. A 'ready' handler is warned about from
  // deep inside jQuery's event code, and the script that asked is further up.
  try { Error.stackTraceLimit = 40; } catch (e) {}
  // The first run found nothing, and could not have. Live, Migrate announces
  // itself as "Migrate is installed, version 3.4.1", without "with logging
  // active", so jQuery.migrateMute is true and migrateWarn() never calls
  // console.warn. So jQuery's own assignment of window.jQuery is caught, and
  // migrateMute is pinned to false before Migrate or anything else can set it.
  // Migrate still records every warning in jQuery.migrateWarnings either way;
  // read() below takes that too, so a warning cannot go uncounted.
  try {
    let jq;
    Object.defineProperty(window, 'jQuery', {
      configurable: true,
      get() { return jq; },
      set(v) {
        jq = v;
        try { Object.defineProperty(v, 'migrateMute', { configurable: true, get() { return false; }, set() {} }); } catch (e) {}
      },
    });
  } catch (e) {}
  const ol = console.log;
  console.log = function () {
    try { const m = String(arguments[0] || ''); if (m.indexOf('JQMIGRATE: Migrate is installed') === 0) window.__afMigrateBanner = m; } catch (e) {}
    return ol.apply(this, arguments);
  };
  const ow = console.warn;
  console.warn = function () {
    try {
      const m = String(arguments[0] || '');
      if (m.indexOf('JQMIGRATE') === 0) {
        const frames = (new Error().stack || '').split('\n').slice(1).map(s => s.trim());
        // Skip Migrate's frames and jQuery's own (a 'ready' handler is added
        // from inside jQuery's event code), to reach the script that asked.
        const caller = frames.find(f => !/jquery-migrate|\/jquery(\.min)?\.js|__afMigrate|console\.warn|migrateWarn/i.test(f) && /https?:/.test(f)) || '';
        window.__afMigrate.push({ msg: m.replace(/^JQMIGRATE:\s*/, ''), caller: caller.replace(/^at\s+/, '') });
      }
    } catch (e) {}
    return ow.apply(this, arguments);
  };
});
const page = await ctx.newPage();
let pageErrors = [];
page.on('pageerror', e => pageErrors.push(String(e.message).slice(0, 140)));

const go = async (path, wait = 3500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};
const read = () => page.evaluate(() => {
  const jq = window.jQuery;
  const caught = (window.__afMigrate || []).slice(0, 40);
  // Anything Migrate recorded that the console path did not see: listed, no caller.
  const seen = new Set(caught.map(w => w.msg));
  const recorded = ((jq && jq.migrateWarnings) || []).map(m => String(m).replace(/\s*\[[^\]]+\]\s*$/, ''));
  for (const m of recorded) if (!seen.has(m)) caught.push({ msg: m, caller: '(from jQuery.migrateWarnings; caller not captured)' });
  return {
    jq: (jq && jq.fn && jq.fn.jquery) || '(no jQuery)',
    migrate: (jq && jq.migrateVersion) || '(not loaded)',
    logging: window.__afMigrateBanner ? /logging active/.test(window.__afMigrateBanner) : null,
    recorded: recorded.length,
    warnings: caught,
  };
}).catch(() => ({ jq: '?', migrate: '?', logging: null, recorded: 0, warnings: [] }));

console.log('probe-jquery-migrate: ' + SITE + '   ' + new Date().toISOString() + '\n');

const all = new Map();   // msg -> { kind, pages:Set, callers:Set }
const perPage = [];
const note = (label, r, errs) => {
  perPage.push({ label, ...r, errors: errs });
  for (const w of r.warnings) {
    const e = all.get(w.msg) || { kind: kind(w.msg), pages: new Set(), callers: new Set() };
    e.pages.add(label);
    if (w.caller) e.callers.add(w.caller.replace(SITE, '').replace(/\?[^:)\s]*/, '').slice(0, 110));
    all.set(w.msg, e);
  }
};

// find a product and a category from the shop
await go('/shop/', 3000);
const links = await page.evaluate(() => ({
  product: ([...document.querySelectorAll('a[href*="/product/"]')].map(a => a.href)[0] || ''),
  category: ([...document.querySelectorAll('a[href*="/product-category/"]')].map(a => a.href)[0] || ''),
})).catch(() => ({ product: '', category: '' }));

const pages = [['home', '/'], ['shop', '/shop/'], ['category', links.category], ['product', links.product]];
for (const [label, path] of pages) {
  if (!path) { console.log('  ' + label + ': no URL found — skipped'); continue; }
  pageErrors = [];
  const s = await go(path);
  await page.mouse.wheel(0, 1500).catch(() => {}); await page.waitForTimeout(800);
  if (label === 'product') {
    // pick a size and add to the cart: the path most likely to use old jQuery
    await page.evaluate(() => {
      const sel = document.querySelector('#af-size-select, select[name^="attribute"], form.cart select');
      if (sel && sel.options.length > 1) { sel.selectedIndex = 1; sel.dispatchEvent(new Event('change', { bubbles: true })); if (window.jQuery) window.jQuery(sel).trigger('change'); }
    }).catch(() => {});
    await page.waitForTimeout(1200);
    await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(4500);
  }
  const r = await read();
  note(label, r, pageErrors.slice());
  console.log('  ' + label.padEnd(10) + 'HTTP ' + s + '   jQuery ' + r.jq + '   Migrate ' + r.migrate + '   logging ' + (r.logging === null ? '?' : r.logging ? 'on' : 'MUTED') + '   warnings ' + r.warnings.length + '   page errors ' + pageErrors.length);
}
for (const [label, path] of [['cart', '/cart/'], ['checkout', '/checkout/'], ['wishlist', '/wishlist/'], ['login', '/login/']]) {
  pageErrors = [];
  const s = await go(path);
  const r = await read();
  note(label, r, pageErrors.slice());
  console.log('  ' + label.padEnd(10) + 'HTTP ' + s + '   jQuery ' + r.jq + '   Migrate ' + r.migrate + '   logging ' + (r.logging === null ? '?' : r.logging ? 'on' : 'MUTED') + '   warnings ' + r.warnings.length + '   page errors ' + pageErrors.length);
}

console.log('\n— every distinct Migrate warning, with the script that made the call —');
if (!all.size) console.log('  none');
for (const [msg, e] of [...all.entries()].sort((a, b) => (a[1].kind === 'REMOVED' ? -1 : 1))) {
  console.log('  [' + e.kind + '] ' + msg.slice(0, 120));
  console.log('      pages: ' + [...e.pages].join(', '));
  for (const c of [...e.callers].slice(0, 3)) console.log('      from:  ' + c);
}

console.log('\n— uncaught page errors (compare with the run after the fix) —');
const errs = perPage.filter(p => p.errors.length);
if (!errs.length) console.log('  none');
for (const p of errs) for (const e of p.errors.slice(0, 3)) console.log('  ' + p.label.padEnd(10) + e);

const removed = [...all.values()].filter(e => e.kind === 'REMOVED').length;
const loaded = perPage.filter(p => p.migrate !== '(not loaded)' && p.migrate !== '?').length;
console.log('\n— verdict —');
console.log('  Migrate loaded on ' + loaded + ' of ' + perPage.length + ' pages');
console.log('  distinct warnings: ' + all.size + ' (' + removed + ' for APIs jQuery 3 removed)');
// An empty list only counts if Migrate was actually able to speak.
const heard = perPage.filter(p => p.migrate !== '(not loaded)' && p.migrate !== '?' && p.logging === true).length;
console.log('  Migrate logging forced on for ' + heard + ' of ' + loaded + ' pages where it loaded');
console.log('  ' + (loaded === 0 ? 'Migrate is gone.'
  : heard < loaded ? 'NO DATA — Migrate stayed muted on ' + (loaded - heard) + ' page(s); an empty list there proves nothing.'
  : removed === 0 ? 'Nothing on these paths depends on Migrate: ' + (all.size ? 'every warning is for an API jQuery 3.7 still has.' : 'it recorded no calls at all.') + ' Safe to remove.'
  : removed + ' removed API(s) in use — those callers must be fixed before Migrate can go.'));

// empty the cart the product step filled
for (let i = 0; i < 6; i++) {
  await go('/cart/', 1500);
  const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
  if (!h) break;
  await go(h, 1200);
}
await browser.close();
console.log('\ndone ' + new Date().toISOString());
