// DEF-15: missing meta descriptions.
//
// Reported: /blog/ and /wishlist/ have none; every other indexable template
// checked has one of 97–160 characters.
//
// /wishlist/ has been noindex since DEF-10, so it is no longer an indexable
// page and its description no longer reaches a search result. /blog/ is the
// one that matters, and it is unusual: inc/blog-hub.php takes the URL over in
// template_redirect, forces a 200, and renders the hub itself. Rank Math,
// which prints the description, only sees what WordPress resolved /blog/ to
// before that. If that was a 404, the hub may carry a 404's SEO as well: no
// description, but perhaps also a "Page not found" title or noindex, which
// would matter more than the description.
//
// So this reads, per page: HTTP status, <title>, meta description and its
// length, og:description, robots and canonical — for the hub, a topic view
// of it, a blog post, the wishlist, and the templates the report says are
// fine (home, shop, a category, a product) as controls.
//
// Read-only.
//
// Run: node tools/probe-meta-desc.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();

const read = async (path) => {
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) return { path, status: 0 };
  const html = await r.text().catch(() => '');
  const pick = (re) => { const m = html.match(re); return m ? m[1].replace(/&amp;/g, '&').replace(/&#0?39;|&#8217;/g, "'").trim() : null; };
  const metas = [...html.matchAll(/<meta\s+name="description"\s+content="([^"]*)"/gi)].map(m => m[1]);
  return {
    path, status: r.status(),
    title: pick(/<title[^>]*>([^<]*)<\/title>/i),
    desc: metas.length ? metas[0] : null, descCount: metas.length,
    og: pick(/<meta\s+property="og:description"\s+content="([^"]*)"/i),
    robots: pick(/<meta\s+name="robots"\s+content="([^"]*)"/i),
    canonical: pick(/<link\s+rel="canonical"\s+href="([^"]*)"/i),
    bodyClass: pick(/<body[^>]*class="([^"]*)"/i) || '',
  };
};

console.log('probe-meta-desc: ' + SITE + '   ' + new Date().toISOString() + '\n');

// find a category, a product and a blog post to stand as controls
await page.goto(SITE + '/shop/', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {});
const shopLinks = await page.evaluate(() => ({
  category: ([...document.querySelectorAll('a[href*="/product-category/"]')].map(a => new URL(a.href).pathname)[0] || ''),
  product: ([...document.querySelectorAll('a[href*="/product/"]')].map(a => new URL(a.href).pathname)[0] || ''),
})).catch(() => ({ category: '', product: '' }));
await page.goto(SITE + '/blog/', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {});
const blogLinks = await page.evaluate(() => {
  const post = [...document.querySelectorAll('.af-hub a[href]')].map(a => a.href)
    .find(h => { const p = new URL(h).pathname; return p !== '/blog/' && !/[?&](topic|bpage)=/.test(h) && p.split('/').filter(Boolean).length >= 1; }) || '';
  const topic = ([...document.querySelectorAll('.af-hub-cat[href*="topic="]')].map(a => new URL(a.href).search)[0] || '');
  return { post: post ? new URL(post).pathname : '', topic };
}).catch(() => ({ post: '', topic: '' }));

const pages = [
  ['blog hub', '/blog/'],
  ['blog topic', blogLinks.topic ? '/blog/' + blogLinks.topic : ''],
  ['blog post', blogLinks.post],
  ['wishlist', '/wishlist/'],
  ['home', '/'], ['shop', '/shop/'], ['category', shopLinks.category], ['product', shopLinks.product],
];
const rows = [];
for (const [label, path] of pages) {
  if (!path) { console.log('── ' + label + ': no URL found — skipped\n'); continue; }
  const d = await read(path);
  rows.push({ label, ...d });
  console.log('── ' + label + '   ' + path + '   HTTP ' + d.status);
  console.log('    title       : ' + (d.title || '(none)'));
  console.log('    description : ' + (d.desc === null ? 'NONE' : '(' + d.desc.length + ' chars) ' + d.desc.slice(0, 110)) + (d.descCount > 1 ? '   [' + d.descCount + ' tags]' : ''));
  console.log('    og:desc     : ' + (d.og === null ? '(none)' : d.og.slice(0, 90)));
  console.log('    robots      : ' + (d.robots || '(none)'));
  console.log('    canonical   : ' + (d.canonical || '(none)'));
  console.log('    body        : ' + (/error404|page-template|blog|home|archive|single/.exec(d.bodyClass) ? d.bodyClass.split(' ').filter(c => /error404|home|blog|page-id|page-template|archive|single|woocommerce/.test(c)).slice(0, 5).join(' ') : '(no telling classes)'));
  console.log('');
}

console.log('— verdict —');
for (const d of rows) {
  const indexable = !(d.robots && /noindex/i.test(d.robots));
  const len = d.desc ? d.desc.length : 0;
  let v;
  if (!d.status || d.status >= 400) v = 'HTTP ' + d.status;
  else if (!indexable) v = 'noindex' + (d.desc ? '' : ' (no description — not needed for search)');
  else if (!d.desc) v = 'INDEXABLE WITH NO DESCRIPTION';
  else if (d.descCount > 1) v = 'TWO description tags';
  else if (len < 50 || len > 160) v = 'description ' + len + ' chars — outside 50–160';
  else v = 'ok (' + len + ' chars)';
  const extra = /not found|404/i.test(d.title || '') ? '   + TITLE SAYS NOT FOUND' : '';
  console.log('  ' + d.label.padEnd(12) + v + extra);
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
