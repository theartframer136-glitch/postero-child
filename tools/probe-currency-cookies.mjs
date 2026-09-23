// DEF-13: "two currency plugins installed at once".
//
// Reported: six currency cookies on every visit —
//   woocs_current_currency  woocs_session_currency   <- plugin A (FOX / WOOCS)
//   wmc_current_currency    wmc-currency             <- plugin B (Multi Currency)
//   currency                chosen_currency          <- generic
// and "two plugins writing overlapping price filters is a common source of
// prices that disagree between templates".
//
// The cookies do not prove the plugins. The child theme writes all six
// itself: a <head> script in functions.php sets every one to the visitor's
// currency, and assets/js/custom.js then sets five of them to USD, on every
// page, unconditionally. So this reads, for a fresh visitor:
//
//   1. which cookies exist, and who set each — a Set-Cookie header from the
//      server, or page script
//   2. which currency plugins' own scripts and styles the page loads, which
//      says from the outside which plugins are running
//   3. what happens after choosing CAD: whether the cookies agree with each
//      other on the next page, and what currency the prices are shown in.
//      custom.js resetting woocs_session_currency to USD straight after the
//      <head> script set it to CAD is exactly the "disagree" the report means.
//
// Read-only. CAD is chosen with ?currency=CAD, the theme's own parameter, and
// the context is thrown away afterwards.
//
// Run: node tools/probe-currency-cookies.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const NAMES = ['woocs_current_currency', 'woocs_session_currency', 'wmc_current_currency', 'wmc-currency', 'currency', 'chosen_currency'];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

const fromServer = new Map();   // cookie name -> first URL whose Set-Cookie carried it
const assets = new Set();
page.on('response', async r => {
  try {
    const u = r.url();
    if (/currency|woocs|wmc|multi-currency/i.test(u) && /\.(js|css)(\?|$)/.test(u)) assets.add(u.replace(SITE, '').split('?')[0]);
    const hs = await r.headersArray();
    for (const h of hs) {
      if (h.name.toLowerCase() !== 'set-cookie') continue;
      for (const line of h.value.split('\n')) {
        const name = line.split('=')[0].trim();
        if (NAMES.includes(name) && !fromServer.has(name)) fromServer.set(name, u.replace(SITE, '') || '/');
      }
    }
  } catch (e) {}
});

const jar = async () => {
  const all = await ctx.cookies(SITE);
  const out = {};
  for (const n of NAMES) { const c = all.find(x => x.name === n); out[n] = c ? c.value : '—'; }
  return out;
};
// A product's own price, not the header cart's "$0.00", which the first run
// read by mistake.
const priceSample = () => page.evaluate(() => {
  const el = document.querySelector('li.product .price .amount, .product-card .price .amount, li.product .price, .product-card .price, .summary .price .amount');
  return el ? ((el.innerText || '') + '').replace(/\s+/g, ' ').trim().slice(0, 30) : '(no product price)';
}).catch(() => '(unreadable)');
// What the page itself says: whether it came from LiteSpeed's cache, and the
// currency baked into the <head> script, which writes the cookies. A cached
// page carries whatever currency the page was cached with, for everyone.
let lastMeta = { cache: '?', baked: '?' };
const go = async (path) => {
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  lastMeta = { cache: '?', baked: '?' };
  if (r) {
    try { const h = await r.allHeaders(); lastMeta.cache = h['x-litespeed-cache'] || '(none)'; } catch (e) {}
    try { const t = await r.text(); const m = t.match(/var cur = '([A-Z]{3})'/); lastMeta.baked = m ? m[1] : '(not found)'; } catch (e) {}
  }
  await page.waitForTimeout(3500);
  return r ? r.status() : 0;
};

console.log('probe-currency-cookies: ' + SITE + '   ' + new Date().toISOString() + '\n');

const s1 = await go('/');
const first = await jar();
console.log('— a fresh visitor, after the home page (HTTP ' + s1 + ') —');
for (const n of NAMES) {
  console.log('  ' + n.padEnd(24) + String(first[n]).padEnd(6) + (first[n] === '—' ? '' : (fromServer.has(n) ? 'Set-Cookie from ' + fromServer.get(n) : 'set by page script')));
}
console.log('  cookies present: ' + NAMES.filter(n => first[n] !== '—').length + ' of 6\n');

console.log('— currency plugin assets the page loaded —');
if (assets.size) [...assets].slice(0, 12).forEach(a => console.log('  ' + a));
else console.log('  none with currency/woocs/wmc in the path');
const plugins = new Set([...assets].map(a => (a.match(/\/wp-content\/plugins\/([^/]+)/) || [])[1]).filter(Boolean));
console.log('  plugins: ' + (plugins.size ? [...plugins].join(', ') : '(none identified from assets)') + '\n');

const s2 = await go('/shop/?currency=CAD');
const m2 = lastMeta;
const afterChoose = await jar();
const p2 = await priceSample();
const s3 = await go('/shop/');
const m3 = lastMeta;
const nextPage = await jar();
const p3 = await priceSample();
console.log('— after choosing CAD (?currency=CAD, HTTP ' + s2 + '), then the next page (HTTP ' + s3 + ') —');
console.log('  ' + 'cookie'.padEnd(24) + 'on choosing'.padEnd(14) + 'next page');
for (const n of NAMES) console.log('  ' + n.padEnd(24) + String(afterChoose[n]).padEnd(14) + nextPage[n]);
console.log('  product price shown     ' + p2.padEnd(14) + p3);
console.log('  x-litespeed-cache       ' + String(m2.cache).padEnd(14) + m3.cache);
console.log('  currency baked in page  ' + String(m2.baked).padEnd(14) + m3.baked);

const vals = NAMES.map(n => nextPage[n]).filter(v => v !== '—');
const agree = new Set(vals).size <= 1;
console.log('\n— verdict —');
console.log('  cookies on a first visit: ' + NAMES.filter(n => first[n] !== '—').length
  + ' (' + NAMES.filter(n => first[n] !== '—' && !fromServer.has(n)).length + ' written by page script)');
console.log('  plugins seen from outside: ' + (plugins.size ? [...plugins].join(', ') : 'none identified'));
console.log('  on the page that chose CAD, the cookies ' + (new Set(NAMES.map(n => afterChoose[n]).filter(v => v !== '—')).size <= 1 ? 'agree' : 'DISAGREE: '
  + NAMES.filter(n => afterChoose[n] !== '—').map(n => n + '=' + afterChoose[n]).join(', ')));
console.log('  on the next page, the cookies ' + (agree ? 'agree (' + (vals[0] || '?') + ')' : 'DISAGREE: ' + NAMES.filter(n => nextPage[n] !== '—').map(n => n + '=' + nextPage[n]).join(', ')));
console.log('  the CAD choice ' + (nextPage.woocs_current_currency === 'CAD' ? 'held on the next page'
  : 'did NOT hold on the next page (woocs_current_currency=' + nextPage.woocs_current_currency + ', page ' + m3.cache + ', baked ' + m3.baked + ')'));

await browser.close();
console.log('\ndone ' + new Date().toISOString());
