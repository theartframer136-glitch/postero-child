// What Elementor Pro puts on the live pages for the three things visitors see
// from it (tools/diag-elementor-pro-widgets.php, diag-elementor-pro-extras.php,
// 5 Oct): the two hero slideshows on the home page (slides #80f8de4 desktop,
// #0971963 phone), the WooCommerce breadcrumbs on shop and category pages, and
// the custom CSS that pins the footer bar (#2592, container bec7134) to the
// bottom of the screen. So the theme can do the same with free parts once
// Elementor Pro is switched off (owner: "keep everything free").
//
// Prints, from the pages and files the site already serves to everyone:
//   - each widget's HTML (shortened) and its data-settings
//   - the rules for those elements in Elementor's generated CSS files
//   - every Elementor Pro stylesheet and script the pages load, with the
//     size of each and the rules/handlers that concern slides and breadcrumbs
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/probe-elementor-pro-markup.mjs [site]
import { chromium } from 'playwright';

const SITE = (process.argv.slice(2).find(a => a.includes('://')) || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const short = (s, n = 1800) => (s || '').replace(/\s+/g, ' ').slice(0, n);
console.log('probe-elementor-pro-markup: ' + SITE + '   ' + new Date().toISOString());

async function grab(path, ids, cssPosts) {
  await page.goto(SITE + path, { waitUntil: 'load', timeout: 60000 }).catch(() => null);
  await page.waitForTimeout(2500);
  console.log('\n######## ' + path);
  const info = await page.evaluate(ids => ids.map(id => {
    const el = document.querySelector('.elementor-element-' + id);
    if (!el) return { id, missing: true };
    const clone = el.cloneNode(true);
    return { id, classes: el.className, settings: el.getAttribute('data-settings'), widget: el.getAttribute('data-widget_type'),
      html: clone.outerHTML, rect: el.getBoundingClientRect().height };
  }), ids);
  for (const i of info) {
    if (i.missing) { console.log('\n--- #' + i.id + ': not on this page'); continue; }
    console.log('\n--- #' + i.id + '  widget ' + i.widget + '  height ' + Math.round(i.rect) + 'px\n    class: ' + i.classes + '\n    data-settings: ' + i.settings);
    console.log('    html: ' + short(i.html, 2600));
  }
  const links = await page.evaluate(() => ({
    css: [...document.querySelectorAll('link[rel=stylesheet]')].map(l => l.href),
    js: [...document.querySelectorAll('script[src]')].map(s => s.src),
    inline: [...document.querySelectorAll('script:not([src])')].map(s => s.textContent).filter(t => /elementorProFrontendConfig|ElementorProFrontendConfig/.test(t)).map(t => t.slice(0, 600)),
  }));
  console.log('\n    Elementor Pro files this page loads:');
  for (const u of [...links.css, ...links.js].filter(u => /elementor-pro/.test(u))) console.log('      ' + u);
  for (const t of links.inline) console.log('    Pro inline config: ' + short(t, 600));
  for (const p of cssPosts) {
    const css = await page.evaluate(async u => { const r = await fetch(u, { cache: 'no-store' }).catch(() => null); return r && r.ok ? r.text() : ''; }, SITE + '/wp-content/uploads/elementor/css/post-' + p + '.css');
    console.log('\n    post-' + p + '.css: ' + css.length + ' bytes; rules for ' + ids.join(', ') + ':');
    for (const id of ids) {
      const re = new RegExp('[^}]*elementor-element-' + id + '[^{]*\\{[^}]*\\}', 'g');
      const media = new RegExp('@media[^{]*\\{[^@]*?elementor-element-' + id + '[^}]*\\{[^}]*\\}', 'g');
      for (const m of css.match(re) || []) console.log('      ' + short(m, 400));
      for (const m of css.match(media) || []) console.log('      [media] ' + short(m, 400));
    }
  }
  return links;
}

const home = await grab('/', ['80f8de4', '0971963', 'ded3cf9', '810fb7a'], ['75']);
await grab('/shop/', ['f002787', 'ed50724'], ['3014', '1853']);
await grab('/product-category/digital-canvas-prints/', ['287225b', 'ee9cd86', 'ed50724'], ['3377', '3425', '5660', '5661']);
await grab('/', ['bec7134'], ['2592']);

// The Pro stylesheets and scripts: size, and what in them is about slides or breadcrumbs.
console.log('\n######## Elementor Pro assets on the home page');
for (const u of [...home.css, ...home.js].filter(u => /elementor-pro/.test(u))) {
  const body = await page.evaluate(async u => { const r = await fetch(u).catch(() => null); return r && r.ok ? r.text() : ''; }, u);
  console.log('\n--- ' + u.replace(SITE, '') + '  (' + body.length + ' bytes)');
  const hits = [...new Set((body.match(/[^{}]{0,120}(slides|swiper-slide-bg|elementor-slides|woocommerce-breadcrumb)[^{}]{0,80}\{[^}]{0,300}\}/g) || []))].slice(0, 30);
  for (const h of hits) console.log('    ' + short(h, 420));
  const handlers = [...new Set((body.match(/["'](slides|woocommerce-breadcrumb)["'][^;]{0,200}/g) || []))].slice(0, 10);
  for (const h of handlers) console.log('    js: ' + short(h, 260));
}
console.log('\ndone ' + new Date().toISOString());
await browser.close();
