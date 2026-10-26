/**
 * Follow-ups to the full site test of 2026-10-26, read one page at a time.
 *
 * The crawl (three requests at a time) reported six product-tag pages
 * answering 508, 1,523 pages with no canonical, 352 duplicate titles and four
 * empty-href "Sign In" buttons. Each of those is a question this answers with
 * a single, paced request per page, the way one shopper would arrive:
 *
 *   - do the six tag pages open for a lone visitor, and how slowly
 *   - what the robots meta and canonical say on tag, category, ?layout= and
 *     double-path category pages (a noindex page has no canonical on purpose)
 *   - whether the "-2" products are copies of their namesakes
 *   - where the empty "Sign In" buttons are and what links to ?page_id=6032
 *   - whether anything links to /Shop/ with a capital S
 *
 * Read-only. Usage: node tools/probe-crawl-followups.mjs https://theartframer.us
 */
const SITE = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 AF-Probe';
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function get(path) {
  const url = path.startsWith('http') ? path : SITE + path;
  const t0 = Date.now();
  try {
    const res = await fetch(url, { headers: { 'user-agent': UA, accept: 'text/html' }, redirect: 'manual' });
    const html = await res.text();
    return { url, status: res.status, ms: Date.now() - t0, loc: res.headers.get('location') || '', html };
  } catch (e) {
    return { url, status: 0, ms: Date.now() - t0, loc: '', html: '', err: String(e.message || e) };
  }
}
const m1 = (html, re) => { const m = html.match(re); return m ? m[1].trim() : ''; };
function head(p) {
  const h = p.html;
  const title = m1(h, /<title[^>]*>([^<]*)<\/title>/i);
  const robots = m1(h, /<meta[^>]+name=["']robots["'][^>]+content=["']([^"']*)["']/i) || m1(h, /<meta[^>]+content=["']([^"']*)["'][^>]+name=["']robots["']/i);
  const canonical = m1(h, /<link[^>]+rel=["']canonical["'][^>]+href=["']([^"']*)["']/i) || m1(h, /<link[^>]+href=["']([^"']*)["'][^>]+rel=["']canonical["']/i);
  const h1 = (h.match(/<h1\b/gi) || []).length;
  const products = (h.match(/<li[^>]+class=["'][^"']*\bproduct\b/gi) || []).length;
  return { title, robots, canonical, h1, products };
}
function line(p, extra = '') {
  const k = head(p);
  console.log(`  ${String(p.status).padEnd(4)} ${String(p.ms).padStart(5)} ms  ${p.url.replace(SITE, '')}${p.loc ? '  -> ' + p.loc : ''}`);
  if (p.status === 200) {
    console.log(`        title     : ${k.title.slice(0, 90)}`);
    console.log(`        robots    : ${k.robots || '(none)'}`);
    console.log(`        canonical : ${k.canonical ? k.canonical.replace(SITE, '') : '(none)'}`);
    console.log(`        h1 ${k.h1}, product cards ${k.products}${extra ? '  ' + extra : ''}`);
  } else if (p.err) console.log(`        ${p.err}`);
}

console.log(`=== CRAWL FOLLOW-UPS  ${SITE}  ${new Date().toISOString()} ===\n`);

console.log('--- the six product-tag pages that answered 508 to the crawl, one at a time ---');
for (const t of ['home-interior-decoration', 'housewarming-gift', 'nature', 'bharatanatyam-canvas-print', 'artistic-wall-decoration', 'bharatanatyam-dancer-painting']) {
  line(await get(`/product-tag/${t}/`));
  await sleep(2500);
}

console.log('\n--- robots and canonical on the kinds of page the crawl found without a canonical ---');
for (const u of ['/product-tag/floral/', '/product-tag/gift-for-her/',
  '/product-category/framed-canvases/', '/product-category/gifts/', '/product-category/home-decor/',
  '/product-category/art-accessories/frame-sizes/2x3-ft/', '/product-category/gold-foiled-uv/',
  '/shop/', '/shop/?layout=list', '/shop/?layout=grid', '/Shop/',
  '/product-category/digital-canvas-prints/', '/product-category/digital-canvas-prints/?layout=grid',
  '/product-category/digital-canvas-prints/page/2/',
  '/product-category/radha-krishna/', '/product-category/digital-canvas-prints/radha-krishna/',
  '/product-category/lord-shiva/', '/product-category/digital-canvas-prints/lord-shiva/',
  '/blog/', '/blog/?topic=vastu-decor-guides', '/my-account/', '/wishlist/', '/cart/']) {
  line(await get(u));
  await sleep(1500);
}

console.log('\n--- the "-2" products against their namesakes ---');
for (const s of ['radha-krishna-love-canvas-wall-art', 'krishna-playing-flute-canvas-wall-art', 'buddha-art-canvas-wall-art']) {
  for (const slug of [s, s + '-2']) {
    const p = await get(`/product/${slug}/`);
    const img = m1(p.html, /<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']*)["']/i);
    const price = m1(p.html, /"price":\s*"?([0-9.]+)/) || m1(p.html, /class="woocommerce-Price-amount amount"[^>]*>(?:<bdi>)?[^0-9]*([0-9.,]+)/);
    const sku = m1(p.html, /"sku":\s*"([^"]*)"/);
    line(p, `price ${price || '?'}  sku ${sku || '?'}  image ${img.split('/').pop()}`);
    await sleep(1500);
  }
}

console.log('\n--- empty-href buttons on the login and sign-up pages, and what links to ?page_id=6032 ---');
for (const u of ['/login/', '/sign-up/', '/?page_id=6032', '/?page_id=6030', '/my-account/']) {
  const p = await get(u);
  line(p);
  if (p.status !== 200) { await sleep(1500); continue; }
  const empties = [...p.html.matchAll(/<a\b[^>]*href=(["'])\1[^>]*>([\s\S]{0,200}?)<\/a>/gi)].map(m => m[2].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim());
  console.log(`        empty href anchors: ${empties.length}${empties.length ? '  [' + empties.join(' | ') + ']' : ''}`);
  const hash = [...p.html.matchAll(/<a\b[^>]*href=["']#["'][^>]*>([\s\S]{0,120}?)<\/a>/gi)].map(m => m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim()).filter(Boolean);
  console.log(`        href="#" anchors with text: ${hash.length}${hash.length ? '  [' + [...new Set(hash)].join(' | ') + ']' : ''}`);
  const pid = [...p.html.matchAll(/href=["']([^"']*page_id=60(30|32)[^"']*)["']/gi)].map(m => m[1]);
  if (pid.length) console.log(`        links to ?page_id=6030/6032: ${[...new Set(pid)].join(', ')}`);
  const shop = [...p.html.matchAll(/href=["']([^"']*\/Shop\/[^"']*)["']/g)].map(m => m[1]);
  if (shop.length) console.log(`        links to /Shop/ (capital S): ${[...new Set(shop)].join(', ')}`);
  await sleep(1500);
}

console.log('\n--- links to /Shop/ and ?page_id= on the home page, a category and a product ---');
for (const u of ['/', '/product-category/digital-canvas-prints/', '/product/luxury-floral-birds-canvas-wall-art/']) {
  const p = await get(u);
  const shop = [...new Set([...p.html.matchAll(/href=["']([^"']*\/Shop\/?[^"']*)["']/g)].map(m => m[1]))];
  const pid = [...new Set([...p.html.matchAll(/href=["']([^"']*[?&]page_id=\d+[^"']*)["']/g)].map(m => m[1]))];
  const tags = [...new Set([...p.html.matchAll(/href=["']([^"']*\/product-tag\/[^"']*)["']/g)].map(m => m[1].replace(SITE, '')))];
  console.log(`  ${p.status} ${u}: /Shop/ links ${shop.length}${shop.length ? ' [' + shop.join(', ') + ']' : ''}; ?page_id links ${pid.length}${pid.length ? ' [' + pid.join(', ') + ']' : ''}; product-tag links ${tags.length}${tags.length ? ' [' + tags.slice(0, 12).join(', ') + ']' : ''}`);
  await sleep(1500);
}

console.log('\n--- the Facebook link the crawl could not verify ---');
{
  const p = await get('https://www.facebook.com/theartframer');
  console.log(`  ${p.status} https://www.facebook.com/theartframer${p.loc ? '  -> ' + p.loc : ''}  (a 200 or a redirect to a login page means the page exists; 404 means it does not)`);
}
console.log('\ndone');
