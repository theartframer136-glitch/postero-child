// Is the JavaScript visitors run the JavaScript that was deployed?
//
// DEF-13's fix removed a block from assets/js/custom.js that set five
// currency cookies to USD on every page. After the deploy, the server stopped
// sending those cookies, but page script still wrote them, and on a freshly
// rendered page it still set woocs_session_currency back to USD. Only the old
// custom.js did that. DEF-01 was blocked the same way earlier: a stale
// custom.js kept serving after a deploy.
//
// This loads the home page as a fresh visitor, lists every script it runs
// (external files and LiteSpeed's combined bundles, plus inline blocks), and
// fetches each same-origin one to look for code that the deploy removed.
// A marker found in a file means that file is stale.
//
//   OLD custom.js     "chosen_currency=USD"   the removed USD-reset block
//   NEW <head> script "wmc_current_currency', 'wmc-currency'"   the expiry list
//
// It also reports custom.js's ?ver= in the HTML against the page's own
// x-litespeed-cache, to tell a stale page from a stale bundle.
//
// Read-only.
//
// Run: node tools/probe-stale-js.mjs [url] [path]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PATH = process.argv[3] || '/';
const MARKERS = [
  ['OLD custom.js USD reset', /chosen_currency=USD/],
  ['OLD <head> wmc write', /'wmc_current_currency='\s*\+\s*cur/],
  ['NEW <head> expiry list', /wmc_current_currency',\s*'wmc-currency'/],
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();

console.log('probe-stale-js: ' + SITE + PATH + '   ' + new Date().toISOString() + '\n');

const r = await page.goto(SITE + PATH, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
await page.waitForTimeout(3000);
const html = r ? await r.text().catch(() => '') : '';
let cache = '?';
try { cache = (await r.allHeaders())['x-litespeed-cache'] || '(none)'; } catch (e) {}
console.log('page HTTP ' + (r ? r.status() : 0) + '   x-litespeed-cache ' + cache + '   ' + html.length + ' bytes');

const customVer = (html.match(/postero-child\/assets\/js\/custom\.js\?ver=([^"'&\s]+)/) || [])[1];
console.log('custom.js linked directly: ' + (customVer ? 'yes, ?ver=' + customVer : 'no (combined into a bundle, or not on this page)'));

const srcs = [...new Set([...html.matchAll(/<script[^>]+src=["']([^"']+)["']/g)].map(m => m[1]))]
  .map(u => u.startsWith('//') ? 'https:' + u : (u.startsWith('/') ? SITE + u : u))
  .filter(u => u.startsWith(SITE));
console.log('same-origin script files: ' + srcs.length + '\n');

const found = [];
// inline blocks first
const inline = [...html.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/g)].map(m => m[1]).join('\n');
for (const [name, re] of MARKERS) if (re.test(inline)) found.push(['(inline in the HTML)', name]);
for (const u of srcs) {
  let body = '', status = 0, lm = '';
  try {
    const resp = await ctx.request.get(u, { timeout: 30000 });
    status = resp.status(); body = await resp.text();
    lm = resp.headers()['last-modified'] || '';
  } catch (e) { continue; }
  const hits = MARKERS.filter(([, re]) => re.test(body)).map(([n]) => n);
  const short = u.replace(SITE, '');
  if (hits.length || /custom\.js|litespeed\/js/.test(short)) {
    console.log('  ' + short.slice(0, 90) + '   HTTP ' + status + '   ' + body.length + ' B' + (lm ? '   last-modified ' + lm : ''));
    for (const h of hits) { console.log('      contains: ' + h); found.push([short, h]); }
  }
}

console.log('\n— verdict —');
const stale = found.filter(([, n]) => n.startsWith('OLD'));
const fresh = found.filter(([, n]) => n.startsWith('NEW'));
console.log('  new <head> script present: ' + (fresh.length ? 'yes (' + fresh.map(f => f[0]).join(', ') + ')' : 'NO'));
console.log('  removed code still served: ' + (stale.length ? 'YES — ' + stale.map(f => f[1] + ' in ' + f[0]).join('; ') : 'no'));

await browser.close();
console.log('\ndone ' + new Date().toISOString());
