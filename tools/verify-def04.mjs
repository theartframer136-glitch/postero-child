// DEF-04: product pages come from the page cache, and a cached product page
// carries nobody's Recently Viewed history.
//
// Before the fix, measured 23 Sep: product pages were cache hits for new
// visitors, but every product page response set af_recent and
// af_recently_viewed, and a cached copy replayed those cookies, with one fixed
// timestamp, to everyone it was served to. The Recently Viewed strips were
// built on the server from those cookies, so a copy built for a returning
// visitor stored their history in its HTML for everyone after them.
//
// Checks, each as a real browser:
//   1  visitor A opens product 1: no af_recent* Set-Cookie, the strip is an
//      empty placeholder in the HTML the server sent, and it stays hidden
//   2  A opens product 2: the strip shows product 1
//   3  A opens product 3: the strip shows products 2 and 1, newest first
//   4  visitor B, fresh, opens product 3: no strip at all (A's history is not
//      in the cached page), and no af_recent* Set-Cookie
//   5  visitors C and D, fresh, open product 1: at least the second is a hit
// It also reports, without failing on it, any other cookie a cached page sets.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-def04.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const P = ['/product/kerala-mural-celebration-canvas-wall-art/', '/product/tanjore-murugan-panel-canvas-wall-art/'];
const browser = await chromium.launch({ headless: true });
const results = [];
const say = (ok, what, seen) => { results.push(ok); console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + what.padEnd(58) + seen); };

const open = async (page, path) => {
  const t0 = Date.now();
  const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (!r) return null;
  const ttfb = ((Date.now() - t0) / 1000).toFixed(2);
  const hs = await r.headersArray().catch(() => []);
  const cookies = hs.filter(h => h.name.toLowerCase() === 'set-cookie').map(h => h.value.split('=')[0]);
  const html = await r.text().catch(() => '');
  await page.waitForTimeout(3000);   // the strip is drawn after a Store API call
  const strip = await page.evaluate(() => {
    const b = document.querySelector('.af-recent[data-af-recent]');
    return { box: !!b, hidden: b ? b.hidden : null, items: [...document.querySelectorAll('.af-recent-row a')].map(a => (a.textContent || '').trim().slice(0, 40)),
      title: ((document.querySelector('h1.product_title, h1') || {}).textContent || '').trim().slice(0, 40), pid: ((document.body.className.match(/postid-(\d+)/) || [])[1] || '') };
  }).catch(() => null);
  return { status: r.status(), cache: (hs.find(h => h.name.toLowerCase() === 'x-litespeed-cache') || {}).value || 'none', ttfb, cookies,
    // The markup the old server code printed. Not the bare class name: the
    // new script that draws the strip contains that text too.
    serverStrip: /class="af-recent-row"|class="af-pp-sec af-recent"/.test(html), strip };
};
const recentSet = c => c.cookies.filter(n => /^af_recent/.test(n));
const line = c => 'HTTP ' + c.status + ' · ' + c.cache + ' ' + c.ttfb + 's · sets: ' + (c.cookies.join(', ') || 'nothing');

console.log('verify-def04: ' + SITE + '   ' + new Date().toISOString() + '\n');

// A second product the shop links to, found the way a shopper would.
let third = '';
{
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const page = await ctx.newPage();
  await page.goto(SITE + '/shop/', { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  third = await page.evaluate(p => [...document.querySelectorAll('li.product a[href*="/product/"]')].map(a => new URL(a.href).pathname).find(x => !p.includes(x)) || '', P).catch(() => '');
  await ctx.close();
}
const P3 = third || '/product/kerala-mural-celebration-canvas-wall-art/';

const A = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const pa = await A.newPage();
const a1 = await open(pa, P[0]);
if (!a1) { console.log('  NO DATA  product 1 did not answer'); await browser.close(); process.exit(0); }
say(!recentSet(a1).length && !a1.serverStrip && a1.strip && a1.strip.box && a1.strip.hidden,
  '1  A on product 1: no af_recent cookie, empty placeholder', line(a1) + ' · server-built strip: ' + (a1.serverStrip ? 'YES' : 'no') + ' · shown: ' + (a1.strip && !a1.strip.hidden));
const name1 = a1.strip ? a1.strip.title : '';
const a2 = await open(pa, P[1]);
say(!!a2 && a2.strip && !a2.strip.hidden && a2.strip.items.length === 1 && name1.slice(0, 12) && a2.strip.items[0].startsWith(name1.slice(0, 12)),
  '2  A on product 2: strip shows product 1', a2 ? line(a2) + ' · strip: ' + JSON.stringify(a2.strip.items) : 'no answer');
const name2 = a2 && a2.strip ? a2.strip.title : '';
const a3 = await open(pa, P3);
say(!!a3 && a3.strip && a3.strip.items.length === 2 && a3.strip.items[0].startsWith(name2.slice(0, 12)) && a3.strip.items[1].startsWith(name1.slice(0, 12)),
  '3  A on ' + P3.replace(/^\/product\//, '').slice(0, 26) + ': products 2 then 1', a3 ? line(a3) + ' · strip: ' + JSON.stringify(a3.strip.items) : 'no answer');
const aCookie = ((await A.cookies()).find(c => c.name === 'af_recent') || {}).value || '';
console.log('                                                               A\'s af_recent cookie: ' + (aCookie || '(none)'));
await A.close();

const Bc = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const pb = await Bc.newPage();
const b3 = await open(pb, P3);
say(!!b3 && !recentSet(b3).length && !b3.serverStrip && b3.strip && b3.strip.hidden && !b3.strip.items.length,
  '4  fresh B on the same page: none of A\'s history', b3 ? line(b3) + ' · strip: ' + JSON.stringify(b3.strip.items) : 'no answer');
await Bc.close();

const hits = [];
for (const who of ['C', 'D']) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const page = await ctx.newPage();
  const c = await open(page, P[0]);
  hits.push(c ? c.cache + ' ' + c.ttfb + 's' : 'no answer');
  if (c && c.cookies.length) console.log('                                                               ' + who + ' was sent: ' + c.cookies.join(', ') + ' (reported, not failed)');
  await ctx.close();
}
say(/^hit/.test(hits[1] || ''), '5  fresh C then D on product 1: served from the page cache', hits.join(' · '));

const right = results.filter(Boolean).length;
console.log('\n' + (right === results.length ? 'DEF-04 FIXED' : 'DEF-04 NOT FIXED') + ': ' + right + ' of ' + results.length + ' checks right');
console.log('done ' + new Date().toISOString());
await browser.close();
