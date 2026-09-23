// DEF-09 follow-up: the empty cart still has no H1 after the fix deployed.
//
// The fix prepends "Your Cart" on the_content, once per request. Login and
// sign-up take the same path and now have their H1, so something about the
// cart differs. Two explanations fit, and they need different fixes:
//
//   1. The cart page's content never passes through the_content — an
//      Elementor template or the Cart block renders it some other way.
//   2. the_content DID run for the cart page, but earlier, in <head>: Rank
//      Math builds a meta description from the excerpt when a page has none,
//      and wp_trim_excerpt runs the_content. The heading would then be spent
//      on a description (tags stripped), and the real content call skips it.
//
// So this reads, for the empty cart with login as the comparison: the meta
// and og descriptions (does "Your Cart" appear in them?), whether the
// heading's <style id="af-page-headings"> reached the page at all, which
// af-notices hook fired, what Elementor document types wrap the page, and
// what renders the cart: classic shortcode, Cart block, or an Elementor
// widget.
//
// Read-only.
//
// Run: node tools/probe-cart-markup.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PAGES = [['/cart/', 'cart (empty)'], ['/login/', 'login (works)']];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

console.log('probe-cart-markup: ' + SITE + '   ' + new Date().toISOString() + '\n');

for (const [path, label] of PAGES) {
  let status = 0, html = '';
  try {
    const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 30000 });
    status = r ? r.status() : 0;
    // The server's HTML, before any script touches it: the heading is
    // server-rendered, so this is where it has to be.
    html = r ? await r.text() : '';
    await page.waitForTimeout(1500);
  } catch (e) {
    console.log('── ' + label + '  ' + path + '   NO RESPONSE: ' + String(e.message).slice(0, 80) + '\n');
    continue;
  }

  const pick = (re) => { const m = html.match(re); return m ? m[1] : '(none)'; };
  const desc = pick(/<meta\s+name="description"\s+content="([^"]*)"/i);
  const og = pick(/<meta\s+property="og:description"\s+content="([^"]*)"/i);
  const style = /id="af-page-headings"/.test(html);
  const h1src = (html.match(/<h1\b[^>]*>[\s\S]{0,80}?<\/h1>/gi) || []).map(s => s.replace(/\s+/g, ' '));
  const notices = [...html.matchAll(/<!-- af-notices via ([^ ]+) -->/g)].map(m => m[1]);
  const elTypes = [...html.matchAll(/data-elementor-type="([^"]+)"[^>]*data-elementor-id="(\d+)"/g)].map(m => m[1] + '#' + m[2]);
  const elTypes2 = [...html.matchAll(/data-elementor-id="(\d+)"[^>]*data-elementor-type="([^"]+)"/g)].map(m => m[2] + '#' + m[1]);
  const widgets = [...new Set([...html.matchAll(/elementor-widget-([a-z0-9-]+)/g)].map(m => m[1]))];

  const dom = await page.evaluate(() => {
    const chain = (el) => {
      const out = [];
      for (let n = el; n && n !== document.body && out.length < 12; n = n.parentElement) {
        const cls = (n.className && typeof n.className === 'string') ? '.' + n.className.trim().split(/\s+/).slice(0, 3).join('.') : '';
        out.push(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + cls);
      }
      return out.reverse().join(' > ');
    };
    const find = (sel) => { const el = document.querySelector(sel); return el ? chain(el) : null; };
    return {
      entryContent: !!document.querySelector('.entry-content'),
      classicForm: !!document.querySelector('.woocommerce-cart-form'),
      classicEmpty: find('.cart-empty, .wc-empty-cart-message'),
      block: find('.wp-block-woocommerce-cart, .wc-block-cart'),
      elCart: find('.elementor-widget-woocommerce-cart'),
      wooWrap: find('.woocommerce'),
      main: find('main, #main, #primary, .site-main'),
      headingsLive: [...document.querySelectorAll('h1,h2,h3')].slice(0, 8).map(h => h.tagName + ' "' + h.textContent.trim().slice(0, 50) + '"'),
    };
  });

  console.log('── ' + label + '  ' + path + '   HTTP ' + status + '   ' + html.length + ' bytes');
  console.log('    meta description   : ' + desc.slice(0, 160));
  console.log('    og:description     : ' + og.slice(0, 160));
  console.log('    "Your Cart" in desc: ' + (/your cart/i.test(desc + ' ' + og) ? 'YES — the heading was spent in <head>' : 'no'));
  console.log('    heading <style>    : ' + (style ? 'present' : 'absent'));
  console.log('    <h1> in server HTML: ' + (h1src.length ? h1src.join(' | ').slice(0, 200) : 'none'));
  console.log('    af-notices via     : ' + (notices.length ? notices.join(', ') : 'none'));
  console.log('    elementor docs     : ' + ([...elTypes, ...elTypes2].join(', ') || 'none'));
  console.log('    elementor widgets  : ' + (widgets.join(', ').slice(0, 200) || 'none'));
  console.log('    .entry-content     : ' + dom.entryContent + '   classic form: ' + dom.classicForm);
  console.log('    classic empty msg  : ' + (dom.classicEmpty || 'none'));
  console.log('    Cart block         : ' + (dom.block || 'none'));
  console.log('    Elementor cart     : ' + (dom.elCart || 'none'));
  console.log('    .woocommerce       : ' + (dom.wooWrap || 'none'));
  console.log('    main               : ' + (dom.main || 'none'));
  console.log('    headings (live DOM): ' + (dom.headingsLive.join(' | ') || 'none'));
  console.log('');
}

await browser.close();
console.log('done ' + new Date().toISOString());
