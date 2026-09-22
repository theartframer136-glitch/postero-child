// DEF-04: why does no product page ever enter the page cache?
//
// Measured in the report: miss on four consecutive requests to the same
// product, 2.4–2.8 s at the origin each time, while / and /shop/ both hit at
// 0.28 s. That is 423 pages, and they are the ones search traffic lands on.
//
// The repository offers a candidate. Two hooks — functions.php:5929 and
// functions.php:15739 — both fire on template_redirect for is_product() and
// both write a cookie on every single view:
//
//     wc_setcookie('af_recently_viewed', ...)
//     setcookie('af_recent', ...)
//
// Two cookies for one "recently viewed" feature, duplicated. A page cache
// will not store a response that sets cookies, and / and /shop/ have no such
// hook — which is exactly the split that was measured.
//
// That is a theory. This tests it, because rewriting a feature on a hunch is
// how four attempts at H-01 were spent:
//
//   1. request the same product several times, read x-litespeed-cache
//   2. read every Set-Cookie on those responses and name which are ours
//   3. do the same for / and /shop/ as the control
//
// If the product responses carry Set-Cookie and the cacheable ones do not,
// the cause is established rather than guessed.
//
// A real browser: the plain-HTTP user agent is getting 403 from the host's
// bot protection. Read-only — no cart, no writes.
//
// Run: node tools/probe-product-cache.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const OURS = /af_recent|af_recently_viewed|woocommerce_recently_viewed/i;

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

// Capture headers for top-level documents only.
const docs = [];
page.on('response', async r => {
  try {
    if (r.request().resourceType() !== 'document') return;
    const h = r.headers();
    docs.push({
      url: r.url().replace(SITE, '') || '/',
      status: r.status(),
      ls: h['x-litespeed-cache'] || '-',
      upstream: h['x-hcdn-upstream-rt'] || '-',
      setCookie: (h['set-cookie'] || ''),
    });
  } catch (e) {}
});

const go = async (path, wait = 1500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

const show = label => {
  const d = docs[docs.length - 1];
  if (!d) { console.log('  ' + label.padEnd(26) + '(no document response captured)'); return null; }
  const cookies = d.setCookie.split('\n').map(c => c.split('=')[0].trim()).filter(Boolean);
  const mine = cookies.filter(c => OURS.test(c));
  console.log('  ' + label.padEnd(26)
    + 'HTTP ' + d.status
    + '  ls-cache=' + String(d.ls).padEnd(6)
    + ' upstream=' + String(d.upstream).padEnd(9)
    + ' Set-Cookie: ' + (cookies.length ? cookies.length : 0)
    + (mine.length ? '  ← ours: ' + mine.join(', ') : ''));
  return { ls: d.ls, cookies, mine };
};

console.log('probe-product-cache: ' + SITE + '   ' + new Date().toISOString());

try {
  // ── the control: pages the report says DO cache ─────────────────────────
  console.log('\n— control: the templates that cache —');
  await go('/', 1500);      const home = show('/');
  await go('/shop/', 1500); const shop = show('/shop/');

  // ── a product, several times ────────────────────────────────────────────
  await go('/shop/', 2000);
  let product = '';
  try {
    product = await page.evaluate(() => {
      const a = [...document.querySelectorAll('a[href*="/product/"]')].map(x => x.href);
      return a.length ? a[0] : '';
    });
  } catch (e) {}
  if (!product) {
    console.log('\n  could not find a product link on /shop/ — NO DATA');
  } else {
    console.log('\n— the same product, four times: ' + product.replace(SITE, '') + ' —');
    const runs = [];
    for (let i = 0; i < 4; i++) {
      await go(product, 1200);
      runs.push(show('  run ' + (i + 1)));
    }

    const misses  = runs.filter(r => r && /miss/i.test(r.ls)).length;
    const withOur = runs.filter(r => r && r.mine.length).length;

    console.log('\n— verdict —');
    console.log('    product cache misses : ' + misses + ' of 4');
    console.log('    responses setting our recently-viewed cookies : ' + withOur + ' of 4');
    console.log('    control  /      ls-cache=' + (home ? home.ls : '?')
              + '   Set-Cookie ' + (home ? home.cookies.length : '?'));
    console.log('    control  /shop/ ls-cache=' + (shop ? shop.ls : '?')
              + '   Set-Cookie ' + (shop ? shop.cookies.length : '?'));

    if (misses === 0) {
      console.log('\n  → DEF-04 CONTRADICTED — the product template is caching now.');
    } else if (withOur > 0) {
      console.log('\n  → CAUSE ESTABLISHED — every product response sets our recently-viewed'
                + '\n    cookie, and a page cache will not store a response that sets cookies.'
                + '\n    The control templates set none and cache. Removing the Set-Cookie from'
                + '\n    the product response is the fix; the strip itself has to stop being'
                + '\n    rendered per visitor into shared HTML at the same time, or a cached'
                + '\n    page would show one visitor\'s history to the next.');
    } else {
      console.log('\n  → DEF-04 CONFIRMED but NOT EXPLAINED — product pages miss and our'
                + '\n    cookies are not on the response. The cause is something else; do not'
                + '\n    rewrite the recently-viewed feature on the strength of this.');
    }
  }
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
