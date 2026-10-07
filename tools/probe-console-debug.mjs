// DEF-12: debug logging left on in production.
//
// Reported, for every visitor:
//   [af-header-row] 796px ONE LINE row=… :: …   ~246 B, once per resize
//   [AF] product-card HTML: <div class="product-card" …   ~2.5 KB, once per load
//
// inc/debug-flag.php turns them off unless ?af_debug=1 is in the URL or
// localStorage.af_debug is '1'. This reads the console as a visitor would
// (home, shop and a product page at a phone-ish 800 px, where the header
// script runs, with three resizes each) and then once more with ?af_debug=1,
// to show the switch still brings them back. A fix that deleted the logging
// would pass the first half and fail the second.
//
// Anything else the page writes to the console is listed too, so a logger the
// report did not name does not hide behind the two it did.
//
// Read-only.
//
// Run: node tools/probe-console-debug.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const DEBUG = [/^\[af-header-row\] \d+px/, /^\[AF\] product-card HTML/, /^\[af-cp\] (tab|grid|answer)/];

const browser = await chromium.launch({ headless: true });
console.log('probe-console-debug: ' + SITE + '   ' + new Date().toISOString() + '\n');

async function visit(path) {
  const ctx = await browser.newContext({ viewport: { width: 800, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const logs = [];
  page.on('console', m => { if (m.type() === 'log' || m.type() === 'info' || m.type() === 'debug') logs.push(m.text()); });
  let status = 0;
  try {
    const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 });
    status = r ? r.status() : 0;
    await page.waitForTimeout(4000);
    for (const w of [790, 760, 800]) { await page.setViewportSize({ width: w, height: 900 }); await page.waitForTimeout(700); }
    await page.waitForTimeout(1500);
  } catch (e) { status = 'ERR ' + String(e.message).slice(0, 50); }
  await ctx.close();
  const debug = logs.filter(t => DEBUG.some(re => re.test(t)));
  const other = logs.filter(t => !DEBUG.some(re => re.test(t)));
  return { status, debug, other, bytes: debug.join('').length };
}

let product = '';
try {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  await p.goto(SITE + '/shop/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(2500);
  product = await p.evaluate(() => {
    const a = [...document.querySelectorAll('a[href*="/product/"]')].map(x => x.href)[0] || '';
    return a ? new URL(a).pathname : '';
  });
  await ctx.close();
} catch (e) {}

const pages = ['/', '/shop/'].concat(product ? [product] : []);
const rows = [];
for (const [mode, suffix] of [['visitor', ''], ['?af_debug=1', 'af_debug=1']]) {
  console.log('— ' + mode + ' —');
  for (const path of pages) {
    const url = suffix ? path + (path.includes('?') ? '&' : '?') + suffix : path;
    const r = await visit(url);
    rows.push({ mode, path, ...r });
    const kinds = DEBUG.map(re => r.debug.filter(t => re.test(t)).length);
    console.log('  ' + path.slice(0, 50).padEnd(52) + 'HTTP ' + String(r.status).padEnd(5)
      + ' header-row ' + kinds[0] + '  card ' + kinds[1] + '  af-cp ' + kinds[2] + '  (' + r.bytes + ' B)');
    for (const t of r.other.slice(0, 4)) console.log('      other log: ' + t.slice(0, 110));
  }
  console.log('');
}

console.log('— verdict —');
const vis = rows.filter(r => r.mode === 'visitor' && typeof r.status === 'number' && r.status > 0);
const dbg = rows.filter(r => r.mode !== 'visitor' && typeof r.status === 'number' && r.status > 0);
if (!vis.length) console.log('  NO DATA — no page answered');
else {
  const leaked = vis.reduce((s, r) => s + r.debug.length, 0);
  console.log('  visitors: ' + (leaked === 0 ? 'no debug logging on ' + vis.length + ' pages — FIXED'
    : leaked + ' debug lines (' + vis.reduce((s, r) => s + r.bytes, 0) + ' B) across ' + vis.length + ' pages — STILL ON'));
  const back = dbg.reduce((s, r) => s + r.debug.length, 0);
  console.log('  ?af_debug=1: ' + (back > 0 ? back + ' debug lines — the switch works'
    : 'nothing — the switch does not bring them back (or the header was wide enough not to run)'));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
