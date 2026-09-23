// DEF-09: templates that render with no H1.
//
// Reported, 0 h1 on each of:
//   /product-category/home-decor/   ← ranking page
//   /login/  /sign-up/  /wishlist/  /?s=…&post_type=product  /cart/
//
// Nothing in the child theme suppresses a heading — no woocommerce_show_page_title
// filter, no CSS hiding a page title — so wherever the H1 went, it went in a
// template this repository cannot see: the Postero parent theme, or an Elementor
// template living in the database. The fix therefore cannot be written from the
// code alone.
//
// And it cannot be written blind. Adding an H1 to a page that already has one
// gives it two, which is its own finding — the report's SEO-06 case is "exactly
// one H1 per template". So for every page this reads what headings actually
// exist, distinguishes the three situations that need different fixes:
//
//   none at all            → one needs adding
//   an H1 that is hidden   → display:none is not read by anyone; a
//                            visually-hidden one IS read by screen readers and
//                            is a legitimate H1, so the two are reported apart
//   an H2 doing the job    → the page's real title is at the wrong level
//
// and runs the home and shop pages as controls.
//
// Read-only. No cart, no writes.
//
// Run: node tools/probe-h1.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const PAGES = [
  ['/product-category/home-decor/', 'category'],
  ['/login/', 'login'],
  ['/sign-up/', 'sign-up'],
  ['/wishlist/', 'wishlist'],
  ['/?s=ganesh&post_type=product', 'search'],
  ['/cart/', 'cart (empty)'],
  ['/', 'home (control)'],
  ['/shop/', 'shop (control)'],
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 950 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

console.log('probe-h1: ' + SITE + '   ' + new Date().toISOString() + '\n');

const rows = [];
for (const [path, label] of PAGES) {
  let status = 0;
  try {
    const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 20000 });
    status = r ? r.status() : 0;
    await page.waitForTimeout(2500);
  } catch (e) { status = 0; }

  const d = status ? await page.evaluate(() => {
    const vis = el => {
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      if (cs.display === 'none' || cs.visibility === 'hidden') return 'none';
      // the visually-hidden pattern: 1px box, clipped — read by screen readers
      if (r.width <= 1 || r.height <= 1 || /rect\(0/.test(cs.clip || '') ||
          /inset\(50%/.test(cs.clipPath || '')) return 'sr-only';
      return 'visible';
    };
    const txt = el => (((el.innerText || el.textContent || '') + '').replace(/\s+/g, ' ').trim()).slice(0, 70);
    const h1 = [...document.querySelectorAll('h1')].map(el => ({ t: txt(el), v: vis(el), cls: (el.className || '') + '' }));
    const h2 = [...document.querySelectorAll('h2')].map(el => ({ t: txt(el), v: vis(el) }));
    const titled = [...document.querySelectorAll(
      '.page-title, .entry-title, .woocommerce-products-header__title, .elementor-page-title, [class*="page-title"]')]
      .slice(0, 3).map(el => ({ tag: el.tagName.toLowerCase(), t: txt(el), v: vis(el) }));
    return { h1, h2, titled, doc: (document.title || '').slice(0, 80),
             bodyCls: ((document.body && document.body.className) || '').slice(0, 160) };
  }).catch(() => null) : null;

  console.log('── ' + label.padEnd(16) + path + '   HTTP ' + status);
  if (!d) { console.log('    NO DATA — page did not answer\n'); rows.push({ label, ok: false }); continue; }

  const h1v = d.h1.filter(x => x.v !== 'none');
  console.log('    <title>          : ' + d.doc);
  console.log('    h1               : ' + d.h1.length + ' in the DOM, ' + h1v.length + ' read by anyone'
    + (d.h1.length ? '' : ''));
  d.h1.slice(0, 3).forEach(x => console.log('                       [' + x.v + '] "' + x.t + '"'
    + (x.cls ? '  .' + x.cls.split(/\s+/).slice(0, 3).join('.') : '')));
  console.log('    h2               : ' + d.h2.length
    + (d.h2.length ? '   first: "' + d.h2[0].t + '"' + (d.h2[1] ? ', "' + d.h2[1].t + '"' : '') : ''));
  if (d.titled.length) {
    d.titled.forEach(x => console.log('    title element    : <' + x.tag + '> [' + x.v + '] "' + x.t + '"'));
  } else {
    console.log('    title element    : none of .page-title / .entry-title / products-header');
  }
  const tpl = (d.bodyCls.match(/elementor-template-\S+|elementor-page-\d+|page-template-\S+|woocommerce-\S+|tax-product_cat|search-results|archive/g) || []).slice(0, 5);
  console.log('    template signals : ' + (tpl.join(' ') || '(none)'));

  let verdict;
  if (h1v.length === 1) verdict = 'OK — exactly one H1 that is read.';
  else if (h1v.length > 1) verdict = 'TWO OR MORE H1s read — adding one would make it worse.';
  else if (d.h1.length) verdict = 'H1 present but display:none — nobody reads it.';
  else if (d.titled.length && d.titled[0].tag !== 'h1') verdict = 'no H1 — the page title is a <' + d.titled[0].tag + '>. Wrong level.';
  else if (d.h2.length) verdict = 'no H1 — the first heading is an H2.';
  else verdict = 'no H1 and no title element at all.';
  console.log('    → ' + verdict + '\n');
  rows.push({ label, ok: true, h1: h1v.length, verdict });
}

console.log('— summary —');
rows.forEach(r => console.log('  ' + r.label.padEnd(18) + (r.ok ? (r.h1 + ' H1   ' + r.verdict) : 'NO DATA')));

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
