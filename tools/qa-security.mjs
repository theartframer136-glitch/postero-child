/**
 * Live security probes for the 12 Oct audit (read-only; creates nothing).
 *
 *   node tools/qa-security.mjs baseline   before the fixes: probes that would
 *                                         make the old code send an email or
 *                                         write a file are reported, not sent
 *   node tools/qa-security.mjs            after the fixes: every probe, and
 *                                         exit 1 when one fails
 *
 * Every request goes in as a browser would (Sec-Fetch headers), so the crawl
 * guard answers it as it answers a visitor. Carts made here are emptied
 * again through the Store API. Nothing logs in.
 */
const S = process.env.QA_BASE || 'https://theartframer.us';
const BASELINE = process.argv[2] === 'baseline';
const T = '/wp-content/themes/postero-child';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
const NAV = { 'User-Agent': UA, Accept: 'text/html,application/xhtml+xml,*/*;q=0.8', 'Sec-Fetch-Mode': 'navigate', 'Sec-Fetch-Dest': 'document', 'Sec-Fetch-Site': 'none' };
let fails = 0;
const out = (tag, name, info) => console.log(`${tag.padEnd(5)} ${name}${info !== undefined ? '  ' + String(typeof info === 'string' ? info : JSON.stringify(info)).slice(0, 300) : ''}`);
const check = (name, ok, info) => { if (!ok) fails++; out(ok ? 'PASS' : 'FAIL', name, info); };
const note = (name, info) => out('INFO', name, info);
const sleep = (ms) => new Promise(r => setTimeout(r, ms));

const jar = {};
const cookie = () => Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
function keep(res) {
  for (const c of (res.headers.getSetCookie ? res.headers.getSetCookie() : [])) {
    const kv = c.split(';')[0]; const i = kv.indexOf('=');
    if (i > 0) jar[kv.slice(0, i).trim()] = kv.slice(i + 1).trim();
  }
}
async function req(path, opt = {}) {
  const url = path.startsWith('http') ? path : S + path;
  for (let i = 0; i < 3; i++) {
    try {
      const res = await fetch(url, { redirect: 'manual', ...opt, headers: { ...NAV, Cookie: cookie(), ...(opt.headers || {}) } });
      keep(res);
      if (res.status === 508 && i < 2) { await sleep(8000); continue; } // the host's throttle, not the site
      return { status: res.status, h: res.headers, text: await res.text() };
    } catch (e) { if (i === 2) return { status: 0, h: new Headers(), text: String(e) }; await sleep(3000); }
  }
}
const ajax = (fields, extra = {}) => req('/wp-admin/admin-ajax.php' + (extra.qs || ''), {
  method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: '*/*', 'Sec-Fetch-Mode': 'cors', 'Sec-Fetch-Dest': 'empty', 'Sec-Fetch-Site': 'same-origin', ...(extra.headers || {}) },
  body: new URLSearchParams(fields).toString(),
});

console.log(`=== security probes on ${S} (${BASELINE ? 'baseline' : 'full'})`);

/* 1. response headers, cached and uncached */
const want = { 'x-content-type-options': /nosniff/i, 'x-frame-options': /sameorigin|deny/i, 'referrer-policy': /./, 'strict-transport-security': /max-age=\d{7,}/, 'permissions-policy': /./ };
const prod = await req('/wp-json/wc/store/v1/products?per_page=1', { headers: { Accept: 'application/json', 'Sec-Fetch-Mode': 'cors' } });
let productUrl = '/shop/';
try { productUrl = JSON.parse(prod.text)[0].permalink.replace(S, ''); } catch (e) {}
for (const [label, path] of [['home (cached)', '/'], ['product page (cached)', productUrl], ['my-account', '/my-account/'], ['wp-login.php', '/wp-login.php'], ['admin-ajax.php', '/wp-admin/admin-ajax.php'], ['REST', '/wp-json/']]) {
  const r = await req(path);
  const missing = Object.keys(want).filter(k => !want[k].test(r.h.get(k) || ''));
  check(`headers on ${label}: all five security headers`, missing.length === 0, missing.length ? 'missing ' + missing.join(', ') + ` (HTTP ${r.status}, cache ${r.h.get('x-litespeed-cache') || '-'}, server ${r.h.get('server') || '-'})` : `HTTP ${r.status}`);
}

/* 2. classic WordPress exposures */
for (const [name, path, method, ok] of [
  ['xmlrpc.php blocked', '/xmlrpc.php', 'POST', s => s === 403 || s === 404 || s === 405 || s === 429],
  ['user list hidden from visitors', '/wp-json/wp/v2/users', 'GET', s => s === 401 || s === 403 || s === 404],
  ['single user hidden from visitors', '/wp-json/wp/v2/users/1', 'GET', s => s === 401 || s === 403 || s === 404],
  ['readme.html not served', '/readme.html', 'GET', s => s === 403 || s === 404],
]) {
  const r = await req(path, { method, headers: method === 'POST' ? { 'Content-Type': 'text/xml' } : {}, body: method === 'POST' ? '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>' : undefined });
  check(name, ok(r.status), `HTTP ${r.status}`);
}
{
  const r = await req('/?author=1');
  const loc = r.h.get('location') || '';
  check('?author=1 does not reveal a login name', !/\/author\/[^/]+/.test(loc) && !/author\/[a-z0-9_-]+/i.test(r.text.slice(0, 20000).match(/<link rel="canonical"[^>]*>/)?.[0] || ''), `HTTP ${r.status} ${loc}`);
}

{
  const r = await req('/?af_debug_circles=1&qa=' + Date.now().toString(36));
  check('the circle debug panel is not shown to visitors', r.status === 200 && !/id="af-circdbg"/.test(r.text), `HTTP ${r.status}`);
}

/* 3. files that must not be public */
for (const [name, path] of [
  ['theme tools/ denied', `${T}/tools/diag-security.php`],
  ['theme tools/ data denied', `${T}/tools/.htaccess`],
  ['theme ops/ denied', `${T}/ops/diag-agg.php`],
  ['theme ops/ guard copy denied', `${T}/ops/mu/af-crawl-guard.php`],
  // Files that hold no guard line of their own: a 403 here can only come
  // from the folder's deny rule (a PHP script may answer 403 by itself).
  ['theme tools/ script (not PHP) denied', `${T}/tools/qa-security.mjs`],
  ['theme tools/ data file denied', `${T}/tools/artcode-primary.csv`],
  ['theme ops/ shell script denied', `${T}/ops/diag-cpu.sh`],
  ['root report APPLIED.txt not public', `${T}/APPLIED.txt`],
  ['root report SKU.txt not public', `${T}/SKU.txt`],
  ['README.md not public', `${T}/README.md`],
  ['docs/ not public', `${T}/docs/brochure-sections.md`],
  ['.gitignore not public', `${T}/.gitignore`],
  ['fatals log not public', '/wp-content/af-fatals.log'],
  ['profiler request log absent', '/wp-content/uploads/af-req-log.txt'],
  ['debug.log not public', '/wp-content/debug.log'],
]) {
  // A fresh query string each run, so no cache in front of the server answers.
  const r = await req(path + '?qa=' + Date.now().toString(36));
  const info = `HTTP ${r.status}${r.status === 200 ? ' (' + r.text.length + ' bytes)' : ''} server ${r.h.get('server') || '-'}`;
  const closed = r.status === 403 || r.status === 404 || r.status === 410;
  // Two files cannot be closed on this host (it ignores a folder's .htaccess)
  // and must stay deployed: the crawl guard's copy (runs, prints nothing) and
  // the art-code CSV that inc/product-pictures.php reads. Cloudflare's edge
  // rule closes them; until the site is behind Cloudflare they are notes.
  const edgeOnly = /guard copy|data file/.test(name) && !/cloudflare/i.test(r.h.get('server') || '');
  if (edgeOnly && !closed) note(`${name} (closed at Cloudflare's edge in Part B)`, info);
  else check(name, closed, info);
}
{
  const r = await req('/wp-content/uploads/af-php-probe.php');
  if (r.status === 404) note('uploads PHP probe file not present (written only by the deploy that installs the uploads rule)');
  else check('PHP under uploads is not executed', !/AF-PHP-EXECUTED/.test(r.text), `HTTP ${r.status} ${r.text.slice(0, 60)}`);
}

/* 3b. image protection (uploads/.htaccess today, Cloudflare's edge in Part B) */
{
  const p = await req('/wp-json/wc/store/v1/products?per_page=1', { headers: { Accept: 'application/json', 'Sec-Fetch-Mode': 'cors' } });
  let img = ''; try { img = JSON.parse(p.text)[0].images[0].src.replace(S, ''); } catch (e) {}
  if (!img) note('image protection: no product picture found to test');
  else {
    const asPage = await req(img, { headers: { 'Sec-Fetch-Dest': 'document', 'Sec-Fetch-Mode': 'navigate', Referer: '' } });
    const inPage = await req(img, { headers: { Accept: 'image/avif,image/webp,*/*', 'Sec-Fetch-Dest': 'image', 'Sec-Fetch-Mode': 'no-cors', 'Sec-Fetch-Site': 'same-origin', Referer: S + '/' } });
    check('a product picture still loads inside a page', inPage.status === 200, `HTTP ${inPage.status} cache ${inPage.h.get('x-litespeed-cache') || inPage.h.get('cf-cache-status') || '-'}`);
    const r = `HTTP ${asPage.status} server ${asPage.h.get('server') || '-'} cache ${asPage.h.get('cf-cache-status') || asPage.h.get('x-hcdn-cache-status') || '-'}`;
    if (/cloudflare/i.test(asPage.h.get('server') || '')) check('a picture opened as a page is refused', asPage.status === 403, r);
    else note('a picture opened as a page (403 = refused)', r);
  }
}

/* 4. redirects */
{
  const r = await req('//evil.example/?currency=USD');
  const loc = r.h.get('location') || '';
  check('?currency= cannot redirect to another site', !/^(https?:)?\/\/(?!theartframer\.us)/i.test(loc), `HTTP ${r.status} Location: ${loc || '-'}`);
}
{
  const r = await req('/wp-admin/admin-ajax.php?action=tp_cookie_bck', { headers: { Referer: 'https://evil.example/landing' } });
  const loc = r.h.get('location') || '';
  check('Transposh language cookie cannot redirect to another site', !/evil\.example/i.test(loc), `HTTP ${r.status} Location: ${loc || '-'}`);
}

/* 5. crashes from crafted requests */
for (const path of ['/?currency[]=x', '/shop/?product_tag[]=x', '/shop/?filter_colors[]=x', '/shop/?orderby[]=x']) {
  const r = await req(path);
  check(`no server error for ${path}`, r.status < 500, `HTTP ${r.status}`);
}
for (const a of ['tp_oht', 'tp_ohtcallback']) {
  const r = await ajax({ action: a });
  check(`no server error for admin-ajax action=${a}`, r.status < 500, `HTTP ${r.status} ${r.text.slice(0, 80)}`);
}

/* 6. ported plugins cannot be switched off by a visitor's URL */
{
  const fields = { action: 'woocs_get_custom_price_html', value: '10', currency: 'USD' };
  const normal = await ajax(fields);
  const bypass = await ajax(fields, { qs: '?x=wp/v2/plugins/woocommerce-currency-switcher' });
  check('a visitor cannot unload a ported plugin with wp/v2/plugins/<folder> in the URL', !(normal.text.trim() !== '0' && bypass.text.trim() === '0'), `normal: ${normal.text.trim().slice(0, 40)} | with the URL: ${bypass.text.trim().slice(0, 40)}`);
}

/* 7. endpoints that wrote files or sent mail for anyone */
if (BASELINE) {
  note('tow_send / ftm_send / af_ac_capture not posted in baseline: the old code would write a file and send an email');
} else {
  for (const a of ['tow_send', 'ftm_send']) {
    const r = await ajax({ action: a, preview: 'data:image/png;base64,iVBORw0KGgo=', product: 'QA probe', category: 'QA', size: '1', frame_color: '#000' });
    check(`${a} refuses a post without its security token`, !/"success":true/.test(r.text), `HTTP ${r.status} ${r.text.slice(0, 120)}`);
  }
  const r = await ajax({ action: 'af_ac_capture', email: 'qa-security-probe@example.com', first: '<b>x</b>', nonce: 'x' });
  check('af_ac_capture refuses an address without a basket', !/"success":true/.test(r.text), `HTTP ${r.status} ${r.text.slice(0, 120)}`);
}

/* 7b. translations: visitors may not save any */
{
  const r = await ajax({ action: 'tp_translation', items: '1', ln0: 'hi', sr0: '1', tk0: 'QA probe text', tr0: 'QA probe text' });
  check('a visitor cannot save a Hindi translation', r.status === 401 || r.status === 403, `HTTP ${r.status} ${r.text.slice(0, 60)}`);
}

/* 8. price of the Digital Download path on products that are not artwork */
async function storeCart() {
  const r = await req('/wp-json/wc/store/v1/cart', { headers: { Accept: 'application/json', 'Sec-Fetch-Mode': 'cors' } });
  let j = null; try { j = JSON.parse(r.text); } catch (e) {}
  return { j, nonce: r.h.get('nonce') || r.h.get('x-wc-store-api-nonce') || '' };
}
async function emptyCart() {
  const { j, nonce } = await storeCart();
  for (const it of (j && j.items) || []) {
    await req('/wp-json/wc/store/v1/cart/remove-item', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Nonce: nonce, 'Sec-Fetch-Mode': 'cors' }, body: JSON.stringify({ key: it.key }) });
  }
}
async function findProduct(q) {
  const r = await req('/wp-json/wc/store/v1/products?' + q, { headers: { Accept: 'application/json', 'Sec-Fetch-Mode': 'cors' } });
  try { return JSON.parse(r.text)[0] || null; } catch (e) { return null; }
}
for (const [label, q, extra] of [
  ['gift card', 'slug=the-art-framer-gift-card', '&af_gc_amount=200'],
  ['art accessory (stretcher bars / frames)', 'category=art-accessories&per_page=1', ''],
]) {
  const p = await findProduct(q);
  if (!p) { note(`Digital Download path on a ${label}: no such product found`); continue; }
  await emptyCart();
  await req(`/?add-to-cart=${p.id}&af_digital=1${extra}`);
  const { j } = await storeCart();
  const line = j && j.items && j.items[0];
  const digital = !!(line && (line.item_data || []).some(d => /digital/i.test(String(d.value || d.display || ''))));
  const price = line ? Number(line.prices.price) / Math.pow(10, line.prices.currency_minor_unit) : null;
  check(`a ${label} cannot be bought through the Digital Download path`, !line || !digital, line ? `price ${price}, marked digital: ${digital}` : 'not added');
  await emptyCart();
}

/* 9. currency REST: no unbounded batch */
{
  const ids = Array.from({ length: 300 }, (_, i) => i + 1);
  const r = await req('/wp-json/woocs/v3/products-prices', { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'Sec-Fetch-Mode': 'cors' }, body: JSON.stringify({ product_ids: ids }) });
  let n = 0; try { const j = JSON.parse(r.text); n = Array.isArray(j) ? j.length : Object.keys(j || {}).length; } catch (e) {}
  check('currency REST answers at most 100 products per call', n <= 100, `HTTP ${r.status}, ${n} answered`);
}

console.log(`=== ${fails ? fails + ' FAILED' : 'all passed'}`);
process.exit(BASELINE ? 0 : (fails ? 1 : 0));
