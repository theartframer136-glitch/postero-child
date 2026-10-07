/**
 * Clicking CORPORATE PRINTING changes nothing: the grid and the circle row
 * both keep whatever the previous tab put there. The tab exists and sits in
 * the right place, so the question is what the theme's own products endpoint
 * answers for that slug.
 *
 * Ask it directly, and ask it for a slug that is known to work, so the two
 * answers can be compared rather than judged on their own.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900 });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 6000));

const slugs = ['banners-signage', 'corporate-printing', 'art-accessories', 'digital-canvas-prints'];
const out = await p.evaluate(async (slugs) => {
  const url = (window.ajaxurl) || '/wp-admin/admin-ajax.php';
  const res = {};
  for (const slug of slugs) {
    const body = new URLSearchParams();
    body.set('action', 'load_products');
    body.set('subcategory', slug);
    try {
      const r = await fetch(url, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
      const t = await r.text();
      const d = document.createElement('div'); d.innerHTML = t;
      res[slug] = {
        status: r.status,
        bytes: t.length,
        cards: d.querySelectorAll('li.product, .product-card, .product').length,
        head: t.replace(/\s+/g, ' ').slice(0, 160),
      };
    } catch (e) { res[slug] = { error: String(e) }; }
  }
  return res;
}, slugs);

console.log('THEME ENDPOINT  action=load_products&subcategory=<slug>\n');
for (const [slug, r] of Object.entries(out)) {
  if (r.error) { console.log('  ' + slug.padEnd(24) + ' ERROR ' + r.error); continue; }
  console.log('  ' + slug.padEnd(24) + ' HTTP ' + r.status + '  ' + String(r.bytes).padStart(7) + ' bytes  ' +
              String(r.cards).padStart(3) + ' cards');
  console.log('      ' + r.head);
}
const corp = out['corporate-printing'], ban = out['banners-signage'];
if (corp && ban && !corp.error && !ban.error) {
  console.log('\n  ' + (corp.cards > 0
    ? 'The endpoint DOES return cards for corporate-printing - the fault is on the page, not the server.'
    : 'The endpoint returns NO cards for corporate-printing while banners-signage returns ' + ban.cards + '.'));
}
await b.close();
