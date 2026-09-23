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
const priceSample = () => page.evaluate(() => {
  const el = document.querySelector('.price .amount, .woocommerce-Price-amount, .price');
  return el ? ((el.innerText || '') + '').replace(/\s+/g, ' ').trim().slice(0, 30) : '(no price on page)';
}).catch(() => '(unreadable)');
const go = async (path) => {
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
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
const afterChoose = await jar();
const p2 = await priceSample();
const s3 = await go('/shop/');
const nextPage = await jar();
const p3 = await priceSample();
console.log('— after choosing CAD (?currency=CAD, HTTP ' + s2 + '), then the next page (HTTP ' + s3 + ') —');
console.log('  ' + 'cookie'.padEnd(24) + 'on choosing'.padEnd(14) + 'next page');
for (const n of NAMES) console.log('  ' + n.padEnd(24) + String(afterChoose[n]).padEnd(14) + nextPage[n]);
console.log('  price shown             ' + p2.padEnd(14) + p3);

const vals = NAMES.map(n => nextPage[n]).filter(v => v !== '—');
const agree = new Set(vals).size <= 1;
console.log('\n— verdict —');
console.log('  cookies on a first visit: ' + NAMES.filter(n => first[n] !== '—').length
  + ' (' + NAMES.filter(n => first[n] !== '—' && !fromServer.has(n)).length + ' written by page script)');
console.log('  plugins seen from outside: ' + (plugins.size ? [...plugins].join(', ') : 'none identified'));
console.log('  after choosing CAD, the cookies ' + (agree ? 'agree (' + (vals[0] || '?') + ')' : 'DISAGREE: ' + NAMES.filter(n => nextPage[n] !== '—').map(n => n + '=' + nextPage[n]).join(', ')));

await browser.close();
console.log('\ndone ' + new Date().toISOString());
