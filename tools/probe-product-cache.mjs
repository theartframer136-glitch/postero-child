// DEF-04: when does a product page come from the page cache, and when not?
//
// Test Run 03 measured miss, miss, miss on one product page in one browser
// session (about 2.5 s each). From the server itself on 23 Sep the same page
// went miss, miss, hit, and hit again with cookies. It also showed that a
// cached product page replays the Set-Cookie headers it was stored with:
// woocs_current_currency=USD, woocs_session_currency=USD, af_recent=<id>,
// af_recently_viewed=<id>, to every visitor it is served to.
//
// This measures what a shopper's browser gets. For each load: the cache
// verdict, the time to first byte, the cookies the browser sent, the cookies
// the response set, and the CDN's headers.
//   A  the product three times, each in a fresh session (no cookies)
//   B  the product three times in one session, after visiting /shop/,
//      the way Run 03 did it
//   C  a second product, the same way as B
//   D  /shop/ three times in one session, as a control
//
// Read-only.
//
// Run: node tools/probe-product-cache.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const P1 = '/product/kerala-mural-celebration-canvas-wall-art/';
const browser = await chromium.launch({ headless: true });
const fresh = () => browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });

const load = async (page, path) => {
  const t0 = Date.now();
  let sent = '';
  const onReq = req => { if (req.isNavigationRequest() && req.url().startsWith(SITE)) sent = req.headers()['cookie'] || ''; };
  page.on('request', onReq);
  const r = await page.goto(SITE + path, { waitUntil: 'commit', timeout: 45000 }).catch(() => null);
  page.off('request', onReq);
  const ttfb = ((Date.now() - t0) / 1000).toFixed(2);
  if (!r) return 'no answer';
  const all = await r.headersArray().catch(() => []);
  const h = n => all.filter(x => x.name.toLowerCase() === n).map(x => x.value);
  const set = h('set-cookie').map(v => v.split(';')[0]).join(' ');
  const cdn = all.filter(x => /^(x-hcdn|x-cache|via|age|server|cf-cache-status|x-litespeed-tag)/i.test(x.name)).map(x => x.name.toLowerCase() + '=' + x.value.slice(0, 40)).join(' ');
  await page.waitForTimeout(2500);   // let the page's own scripts write their cookies, as a shopper's would
  return (h('x-litespeed-cache')[0] || 'none').padEnd(5) + ' ' + ttfb + 's  HTTP ' + r.status()
    + '\n        sent: ' + (sent ? sent.split('; ').map(c => c.split('=')[0]).join(', ') : '(no cookies)')
    + '\n        set:  ' + (set || '-')
    + '\n        cc:   ' + (h('cache-control')[0] || '-') + (cdn ? '   ' + cdn : '');
};

console.log('probe-product-cache: ' + SITE + '   ' + new Date().toISOString());

console.log('\nA  ' + P1 + ' three times, each in a fresh session');
for (let i = 1; i <= 3; i++) {
  const ctx = await fresh(); const page = await ctx.newPage();
  console.log('  ' + i + '  ' + await load(page, P1));
  await ctx.close();
}

console.log('\nB  ' + P1 + ' three times in one session, after /shop/ (as Run 03)');
let second = '';
{
  const ctx = await fresh(); const page = await ctx.newPage();
  console.log('  shop  ' + await load(page, '/shop/'));
  second = await page.evaluate(p => ([...document.querySelectorAll('a[href*="/product/"]')].map(a => new URL(a.href).pathname).filter(x => x !== p)[3] || ''), P1).catch(() => '');
  for (let i = 1; i <= 3; i++) console.log('  ' + i + '     ' + await load(page, P1));
  await ctx.close();
}

if (second) {
  console.log('\nC  ' + second + ' three times in one session, after /shop/');
  const ctx = await fresh(); const page = await ctx.newPage();
  await load(page, '/shop/');
  for (let i = 1; i <= 3; i++) console.log('  ' + i + '  ' + await load(page, second));
  await ctx.close();
}

console.log('\nD  /shop/ three times in one session (control)');
{
  const ctx = await fresh(); const page = await ctx.newPage();
  for (let i = 1; i <= 3; i++) console.log('  ' + i + '  ' + await load(page, '/shop/'));
  await ctx.close();
}

console.log('\ndone ' + new Date().toISOString());
await browser.close();
