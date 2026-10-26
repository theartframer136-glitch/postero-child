/**
 * Follow-ups to the browser pass of the full site test, 2026-10-26, in a
 * real Chromium, one page at a time.
 *
 *   - the two category pages where the sweep saw "jQuery is not defined"
 *     while three stylesheet requests were closed by the host: opened again,
 *     alone, do they load clean
 *   - the sign-up form: which inputs it has, by name and type (the sweep
 *     found no input[type=email])
 *   - the 24 links with no accessible name, by href and markup, so they can
 *     be given one
 *   - the tap targets under 32px on a tablet, by text
 *   - the "Fetch API cannot load data:," console error on account pages:
 *     which script sends it
 *
 * Read-only. Usage: node tools/probe-browser-followups.mjs https://theartframer.us
 */
import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || 'https://theartframer.us').replace(/\/$/, '');
const sleep = ms => new Promise(r => setTimeout(r, ms));
const browser = await chromium.launch({ headless: true });

async function open(path, opts = {}) {
  const ctx = await browser.newContext({ locale: 'en-US', ...opts });
  const page = await ctx.newPage();
  const errs = [], failed = [], console_ = [];
  page.on('pageerror', e => errs.push(String(e.message).slice(0, 200)));
  page.on('requestfailed', r => failed.push(`${r.failure()?.errorText || '?'} ${r.url().slice(0, 160)}`));
  page.on('console', m => { if (m.type() === 'error') console_.push({ text: m.text().slice(0, 200), loc: m.location() }); });
  const res = await page.goto(SITE + path, { waitUntil: 'load', timeout: 90000 }).catch(e => ({ status: () => 0, err: e }));
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(3000);
  return { ctx, page, status: res ? res.status() : 0, errs, failed, console_ };
}

console.log(`=== BROWSER FOLLOW-UPS  ${SITE}  ${new Date().toISOString()} ===\n`);

console.log('--- the two category pages with script errors in the sweep, opened alone ---');
for (const p of ['/product-category/wall-art/', '/product-category/posters/', '/product-category/wall-art/']) {
  const r = await open(p, { viewport: { width: 1366, height: 800 } });
  const jq = await r.page.evaluate(() => typeof window.jQuery).catch(() => '?');
  console.log(`  ${r.status} ${p}  jQuery: ${jq}  js errors: ${r.errs.length}  failed requests: ${r.failed.length}`);
  for (const e of r.errs.slice(0, 6)) console.log(`        JS: ${e}`);
  for (const f of r.failed.slice(0, 6)) console.log(`        failed: ${f}`);
  await r.ctx.close();
  await sleep(4000);
}

console.log('\n--- the sign-up form: every input, by name and type ---');
{
  const r = await open('/sign-up/', { viewport: { width: 1366, height: 800 } });
  const inputs = await r.page.evaluate(() => [...document.querySelectorAll('form input, form select, form textarea, form button')].map(i => {
    const f = i.closest('form');
    return `${f ? (f.id || f.className || 'form').toString().slice(0, 40) : '-'} :: <${i.tagName.toLowerCase()} type=${i.type || '-'} name=${i.name || '-'} id=${i.id || '-'} placeholder="${(i.placeholder || '').slice(0, 30)}" autocomplete=${i.getAttribute('autocomplete') || '-'} inputmode=${i.getAttribute('inputmode') || '-'}>`;
  }));
  console.log(`  ${r.status} /sign-up/  ${inputs.length} form controls`);
  for (const i of inputs) console.log(`        ${i}`);
  await r.ctx.close();
  await sleep(3000);
}

console.log('\n--- links with no accessible name, on the home page and a product page ---');
for (const p of ['/', '/product/luxury-floral-birds-canvas-wall-art/']) {
  const r = await open(p, { viewport: { width: 1366, height: 800 } });
  const list = await r.page.evaluate(() => {
    const out = [];
    for (const a of document.querySelectorAll('a[href]')) {
      const name = (a.getAttribute('aria-label') || a.getAttribute('title') || a.innerText || '').trim()
        || [...a.querySelectorAll('img[alt], svg[aria-label], [aria-label]')].map(x => x.getAttribute('alt') || x.getAttribute('aria-label')).join(' ').trim();
      if (name) continue;
      const rect = a.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) continue;
      const html = a.outerHTML.replace(/\s+/g, ' ');
      out.push(`${a.getAttribute('href').slice(0, 70)}  class="${(a.className || '').toString().slice(0, 50)}"  parent=${a.parentElement ? a.parentElement.tagName.toLowerCase() + '.' + (a.parentElement.className || '').toString().split(' ')[0] : '-'}  ${html.slice(0, 140)}`);
    }
    return out;
  });
  console.log(`  ${r.status} ${p}  ${list.length} unnamed links`);
  for (const l of list.slice(0, 40)) console.log(`        ${l}`);
  await r.ctx.close();
  await sleep(3000);
}

console.log('\n--- tap targets under 32px tall on a tablet (home page) ---');
{
  const r = await open('/', { ...devices['iPad Mini'] });
  const list = await r.page.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('a[href], button, input[type=submit], [role=button]')) {
      const rc = el.getBoundingClientRect();
      if (rc.width === 0 || rc.height === 0 || rc.height >= 32) continue;
      const cs = getComputedStyle(el);
      if (cs.visibility === 'hidden' || cs.display === 'none') continue;
      const t = (el.innerText || el.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 30);
      out.push(`${Math.round(rc.width)}x${Math.round(rc.height)}  "${t}"  ${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`);
    }
    return out;
  });
  console.log(`  ${r.status} /  ${list.length} controls under 32px`);
  for (const l of list.slice(0, 30)) console.log(`        ${l}`);
  await r.ctx.close();
  await sleep(3000);
}

console.log('\n--- the "data:," fetch and other console errors on the account pages ---');
for (const p of ['/my-account/', '/login/', '/track-your-order/']) {
  const r = await open(p, { viewport: { width: 1366, height: 800 } });
  console.log(`  ${r.status} ${p}  console errors: ${r.console_.length}  js errors: ${r.errs.length}`);
  for (const c of r.console_.slice(0, 6)) console.log(`        ${c.text}   @ ${(c.loc && c.loc.url || '').replace(SITE, '').slice(0, 120)}:${c.loc && c.loc.lineNumber}`);
  for (const e of r.errs.slice(0, 4)) console.log(`        JS: ${e}`);
  await r.ctx.close();
  await sleep(3000);
}

await browser.close();
console.log('\ndone');
