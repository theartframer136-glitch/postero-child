/**
 * Tests the browser-side half of the demo-link guard, in a real browser.
 *
 * The PHP half is tested by tools/test-demo-guard.php. It cannot cover the
 * case that actually reached the owner on 2026-09-11: tapping "Shop" on the
 * live site and landing on the theme vendor's demo store, a day after the PHP
 * guard shipped and deployed cleanly. A link the server never emitted is a
 * link an output buffer cannot rewrite — the parent theme builds that bar in
 * its own JavaScript, after the page arrives.
 *
 * So the test that matters is the one the PHP suite cannot express: a demo
 * link INJECTED AFTER LOAD, and a tap on it.
 *
 *   node tools/test-demo-guard-dom.js
 *
 * Needs nothing from the live site. The page is served from memory over a real
 * http:// origin, because about: has no origin and navigation does not work.
 */
const { execFileSync } = require('child_process');
const { chromium } = require('/opt/node22/lib/node_modules/playwright');
const path = require('path');

const ROOT = path.join(__dirname, '..');

// Render the guard exactly as WordPress would, so the test cannot drift from
// what ships: the script under test IS the module's own output.
const GUARD = execFileSync('php', ['-r', `
define("ABSPATH","/nowhere/");
function add_action(){} function add_filter(){} function apply_filters($t,$v){return $v;}
function home_url(){return "http://af.test/";}
function is_admin(){return false;}
function wp_json_encode($v){return json_encode($v);}
require "${ROOT}/inc/demo-guard.php";
af_demo_dom_net();
`], { encoding: 'utf8' });

const DEMO = 'https://demo2wpopal.b-cdn.net/postero';
let pass = 0, fail = 0;
const ok = (cond, msg, extra = '') => {
  if (cond) { pass++; console.log('  OK    ' + msg); }
  else { fail++; console.log('  FAIL  ' + msg + (extra ? '\n        ' + extra : '')); }
};

(async () => {
  const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const page = await browser.newPage();

  // Every request is answered from memory. Anything the guard failed to mend
  // shows up here as a request to a host that is not af.test.
  const wentTo = [];
  await page.route('**/*', route => {
    const url = route.request().url();
    if (route.request().resourceType() === 'document') wentTo.push(url);
    route.fulfill({ contentType: 'text/html', body: `<!doctype html><title>page</title>` });
  });

  async function load(bodyHtml) {
    wentTo.length = 0;
    await page.route('http://af.test/start', route => route.fulfill({
      contentType: 'text/html',
      body: `<!doctype html><html><body>${bodyHtml}${GUARD}</body></html>`,
    }));
    await page.goto('http://af.test/start');
    wentTo.length = 0;
  }
  const href = sel => page.$eval(sel, el => el.getAttribute('href'));

  console.log('=== links already in the HTML (what the PHP guard also covers) ===');
  await load(`<a id="shop" href="${DEMO}/shop/">Shop</a>`);
  ok(await href('#shop') === 'http://af.test/shop/', 'a server-rendered demo link is mended',
     'got ' + await href('#shop'));

  console.log('\n=== THE LIVE CASE: the bar is built by JavaScript after load ===');
  await load(`<nav id="bar"></nav>`);
  await page.evaluate(demo => {
    const nav = document.getElementById('bar');
    ['shop', 'my-account', 'wishlist'].forEach((slug, i) => {
      const a = document.createElement('a');
      a.id = 'late' + i;
      a.href = demo + '/' + slug + '/';
      nav.appendChild(a);
    });
  }, DEMO);
  await page.waitForTimeout(60);                      // observers are async
  ok(await href('#late0') === 'http://af.test/shop/',       'Shop, injected after load, is mended');
  ok(await href('#late1') === 'http://af.test/my-account/', 'Account, injected after load, is mended');
  ok(await href('#late2') === 'http://af.test/wishlist/',   'Wishlist, injected after load, is mended');

  console.log('\n=== an href CHANGED to a demo host after load ===');
  await load(`<a id="turn" href="/shop/">Shop</a>`);
  await page.evaluate(demo => { document.getElementById('turn').href = demo + '/shop/'; }, DEMO);
  await page.waitForTimeout(60);
  ok(await href('#turn') === 'http://af.test/shop/', 'a link turned bad after load is mended back');

  console.log('\n=== a tap is the last word, even on a link written moments before ===');
  await load(`<nav id="bar"></nav>`);
  await page.evaluate(demo => {
    const a = document.createElement('a');
    a.id = 'tap'; a.href = demo + '/shop/'; a.textContent = 'Shop';
    document.getElementById('bar').appendChild(a);
  }, DEMO);
  await page.click('#tap');
  await page.waitForTimeout(250);
  ok(!wentTo.some(u => u.includes('demo2wpopal')), 'the tap never reaches the demo store',
     'navigated to: ' + JSON.stringify(wentTo));
  ok(wentTo.some(u => u.startsWith('http://af.test/shop')), 'the tap lands on this site instead',
     'navigated to: ' + JSON.stringify(wentTo));

  console.log('\n=== THE GUARANTEE: a real link is never touched ===');
  await load(`
    <a id="own"    href="http://af.test/shop/">own</a>
    <a id="rel"    href="/cart/">relative</a>
    <a id="out"    href="https://www.paypal.com/checkout">outbound</a>
    <a id="other"  href="https://someoneelse.b-cdn.net/img.jpg">other tenant</a>
    <a id="alike"  href="https://demo2wpopal.b-cdn.net.evil.test/x">lookalike</a>
    <a id="hash"   href="#top">anchor</a>`);
  ok(await href('#own')   === 'http://af.test/shop/',                     'our own link is left alone');
  ok(await href('#rel')   === '/cart/',                                   'a relative link is left alone');
  ok(await href('#out')   === 'https://www.paypal.com/checkout',          'an outbound link is left alone');
  ok(await href('#other') === 'https://someoneelse.b-cdn.net/img.jpg',    'another b-cdn tenant is left alone');
  ok(await href('#alike') === 'https://demo2wpopal.b-cdn.net.evil.test/x','a lookalike host is left alone');
  ok(await href('#hash')  === '#top',                                     'an in-page anchor is left alone');

  console.log('\n=== forms post to this site too ===');
  await load(`<form id="f" action="${DEMO}/?s=x"></form>`);
  ok(await page.$eval('#f', el => el.getAttribute('action')) === 'http://af.test/?s=x',
     'a form action is mended');

  console.log('\n=== the old staging hostname, and the bare host ===');
  await load(`<a id="stage" href="https://chocolate-chicken-365829.hostingersite.com/about/">about</a>
              <a id="bare"  href="https://demo2wpopal.b-cdn.net">bare</a>`);
  ok(await href('#stage') === 'http://af.test/about/', 'the old staging hostname is mended');
  ok(await href('#bare')  === 'http://af.test',        'the bare host, with no path, is mended');

  await browser.close();
  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
