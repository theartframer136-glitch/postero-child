// A before/after fingerprint of the shop's key pages, for plugin updates that
// can change how a page is built without breaking it outright (Elementor and
// Elementor Pro, 3 Oct: 4.1.5 -> 4.3.3 and 3.28 -> 4.3.1). Run it before and
// after and compare the two logs line by line: a lost header or footer, a menu
// that shrank, a page that grew or collapsed, products that went missing, or a
// script error shows up as a changed line. Screenshots go to persona-*.png,
// which qa-personas.yml keeps.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/snap-layout.mjs [site]
import { chromium } from 'playwright';

const SITE = (process.argv.slice(2).find(a => a.includes('://')) || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PAGES = ['/', '/shop/', '/product-category/digital-canvas-prints/',
  '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/',
  '/cart/', '/contact/'];
const SIZES = [['desktop', 1440, 900], ['phone', 390, 844]];
const browser = await chromium.launch({ headless: true });
console.log('snap-layout: ' + SITE + '   ' + new Date().toISOString());
for (const [label, w, h] of SIZES) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, isMobile: label === 'phone', hasTouch: label === 'phone' });
  const page = await ctx.newPage();
  let errs = [];
  page.on('pageerror', e => errs.push(String(e.message || e).slice(0, 120)));
  page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 120)); });
  for (const p of PAGES) {
    errs = [];
    const r = await page.goto(SITE + p, { waitUntil: 'load', timeout: 60000 }).catch(() => null);
    await page.waitForTimeout(2500);
    const d = await page.evaluate(() => {
      const vis = el => { if (!el) return false; const b = el.getBoundingClientRect(), s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
      const header = document.querySelector('[data-elementor-type="header"], header.elementor-location-header, #masthead, header');
      const footer = document.querySelector('[data-elementor-type="footer"], footer.elementor-location-footer, #colophon, footer');
      const nav = header ? [...header.querySelectorAll('a')].filter(vis).length : 0;
      const r = el => el ? Math.round(el.getBoundingClientRect().height) : 0;
      return {
        header: header ? (vis(header) ? 'shown ' + r(header) + 'px' : 'hidden') : 'MISSING',
        nav, footer: footer ? (vis(footer) ? 'shown' : 'hidden') : 'MISSING',
        height: Math.round(document.documentElement.scrollHeight / 100) * 100,
        sideways: document.documentElement.scrollWidth > window.innerWidth + 2,
        widgets: document.querySelectorAll('.elementor-widget').length,
        elementorDocs: [...document.querySelectorAll('[data-elementor-type]')].map(e => e.getAttribute('data-elementor-type')).join(','),
        products: [...document.querySelectorAll('li.product, .product.type-product, .products .product')].filter(vis).length,
        h1: ((document.querySelector('h1') || {}).innerText || '').trim().slice(0, 50),
        cart: !!document.querySelector('form.cart button[type=submit], .single_add_to_cart_button'),
        css: [...document.querySelectorAll('link[rel=stylesheet]')].filter(l => /elementor/.test(l.href)).length,
      };
    }).catch(e => ({ fail: String(e).slice(0, 80) }));
    const name = 'persona-snap-' + label + '-' + (p.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').slice(0, 40) || 'home') + '.png';
    await page.screenshot({ path: name, fullPage: false }).catch(() => {});
    console.log('\n[' + label + '] ' + p.slice(0, 60) + '  HTTP ' + (r ? r.status() : 0));
    for (const [k, v] of Object.entries(d)) console.log('   ' + k.padEnd(14) + String(v));
    console.log('   ' + 'script errors'.padEnd(14) + (errs.length ? errs.length + ': ' + [...new Set(errs)].slice(0, 3).join(' | ') : 'none'));
  }
  await ctx.close();
}
console.log('\ndone ' + new Date().toISOString());
await browser.close();
