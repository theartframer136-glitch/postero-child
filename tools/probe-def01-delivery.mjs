// Did the DEF-01 fix reach the browser at all?
//
// The fix is deployed and the pop-up still sends nothing. Two very different
// things could be true, and they have opposite remedies:
//
//   the code never arrives   → custom.js is being served from the CDN's copy
//                              of a URL whose ?ver LiteSpeed strips, under a
//                              one-year max-age. Nothing wrong with the
//                              handler; the fix for THIS was af_asset_src,
//                              which had to be reverted this morning.
//
//   the code arrives and     → the handler is wrong. Most likely the click
//   does nothing               never matches .af-input-group, or it binds
//                              after the plugin has already swallowed the
//                              event.
//
// Guessing between them is how four attempts at H-01 were spent. So this
// separates the layers and reports each independently:
//
//   1. what the page links for custom.js, and whether it carries a version
//   2. whether the bytes actually served contain the new code
//   3. whether the PHP half arrived — window.af_ajax.nl_nonce comes from
//      functions.php and travels in the HTML, which is a different cache
//      from the static file, so the two can disagree
//
// A real browser, because the plain-HTTP probe's user agent is now getting
// 403 from the host's bot protection.
//
// Run: node tools/probe-def01-delivery.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

console.log('probe-def01-delivery: ' + SITE + '   ' + new Date().toISOString());

try {
  await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(4000);

  const found = await page.evaluate(async () => {
    const out = { scripts: [], afAjax: null, fetched: [] };

    // 1. every script this page links that looks like ours
    [...document.querySelectorAll('script[src]')].forEach(s => {
      if (/postero-child|custom\.js|litespeed\/js/i.test(s.src)) out.scripts.push(s.src);
    });

    // 3. the PHP half — localized into the HTML, a different cache entirely
    out.afAjax = window.af_ajax
      ? { url: window.af_ajax.url || '(none)',
          nl_nonce: window.af_ajax.nl_nonce ? String(window.af_ajax.nl_nonce).slice(0, 10) + '…' : '(ABSENT)' }
      : '(window.af_ajax is undefined)';

    // 2. the bytes actually served for each candidate
    for (const src of out.scripts.slice(0, 6)) {
      try {
        const r = await fetch(src, { cache: 'no-store' });
        const t = await r.text();
        out.fetched.push({
          src, status: r.status, bytes: t.length,
          lastModified: r.headers.get('last-modified') || '-',
          cacheControl: r.headers.get('cache-control') || '-',
          hasSubscribe: t.indexOf('af_nl_subscribe') !== -1,
          hasMarker: t.indexOf('afNlWired') !== -1,
          hasAdopt: t.indexOf('afNlAdopted') !== -1,
        });
      } catch (e) {
        out.fetched.push({ src, error: String(e.message).slice(0, 80) });
      }
    }
    return out;
  });

  console.log('\n— what the page links —');
  if (!found.scripts.length) console.log('  nothing matching postero-child / custom.js / litespeed js');
  for (const s of found.scripts) console.log('  ' + s.replace(SITE, ''));

  console.log('\n— the PHP half (functions.php → the HTML) —');
  console.log('  window.af_ajax: ' + JSON.stringify(found.afAjax));

  console.log('\n— the JS half (the bytes actually served) —');
  for (const f of found.fetched) {
    if (f.error) { console.log('  ' + f.src.replace(SITE, '') + '  ERROR ' + f.error); continue; }
    console.log('  ' + f.src.replace(SITE, ''));
    console.log('      HTTP ' + f.status + '  ' + f.bytes + 'b  last-modified=' + f.lastModified);
    console.log('      cache-control: ' + f.cacheControl);
    console.log('      af_nl_subscribe present = ' + f.hasSubscribe
      + '   afNlWired = ' + f.hasMarker + '   afNlAdopted = ' + f.hasAdopt);
  }

  const arrived = found.fetched.some(f => f.hasSubscribe);
  const phpArrived = found.afAjax && found.afAjax.nl_nonce && found.afAjax.nl_nonce !== '(ABSENT)';
  console.log('\n  → JS half arrived in the browser : ' + arrived);
  console.log('  → PHP half arrived in the HTML   : ' + !!phpArrived);
  console.log('\n  → ' + (arrived
    ? 'The code IS being served. The handler is not doing its job — that is a code fault, not a delivery one.'
    : 'The code is NOT being served. The handler is irrelevant until custom.js reaches the browser; this is the stripped-?ver / one-year-max-age problem that af_asset_src was written to fix and that was reverted this morning.'));
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
