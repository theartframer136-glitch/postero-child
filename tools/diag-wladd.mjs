/**
 * Guest adds a piece to the wishlist, then opens /wishlist/. Owner's report,
 * 24 Sep: the header counter says 1 but the page says "no products".
 * Records: the add request and its reply, the wishlist cookie, where
 * /wishlist/ lands, the cache headers of that page, and how many rows show.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
p.on('response', async (r) => {
  const u = r.url();
  if (/admin-ajax|wc-ajax|woosw|wishlist/i.test(u) && !/\.(css|js|png|jpe?g|webp|svg)(\?|$)/.test(u)) {
    let body = ''; try { body = (await r.text()).replace(/\s+/g,' ').slice(0, 260); } catch {}
    const post = (r.request().postData() || '').slice(0, 160);
    console.log('NET ' + r.status() + ' ' + r.request().method() + ' ' + u.slice(0, 110) + (post ? ' POST=' + post : '') +
      ' | lscache=' + (r.headers()['x-litespeed-cache'] || '-') + ' cc=' + (r.headers()['cache-control'] || '-') + ' loc=' + (r.headers()['location'] || '-') + ' | ' + body);
  }
});
const go = async (u) => { for (let i = 0; i < 3; i++) { try { return await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 90000 }); } catch { await sleep(4000); } } };
const cookies = async (t) => console.log('COOKIES ' + t + ': ' + (await p.cookies(S)).filter(c => /woosw|wish|wp_woo|woocommerce/i.test(c.name)).map(c => c.name + '=' + c.value.slice(0, 40)).join('; '));
const rows = async (t) => console.log('PAGE ' + t + ': url=' + p.url() + ' ' + JSON.stringify(await p.evaluate(() => ({
  rows: document.querySelectorAll('.woosw-items tr.woosw-item, .woosw-item').length,
  empty: /no products on the Wishlist/i.test(document.body.innerText),
  counter: [...document.querySelectorAll('.woosw-menu-item-inner, .woosw-count, [class*="wishlist"] .count')].map(e => e.textContent.trim()).join(','),
  keyAttr: (document.querySelector('[data-key]') || {}).getAttribute ? document.querySelector('[data-key]').getAttribute('data-key') : '-',
  vars: typeof woosw_vars === 'object' ? JSON.stringify(woosw_vars).slice(0, 400) : '-'
}))));

await go(S + '/shop/');
await sleep(7000);
await cookies('before add');
const btn = await p.$('.woosw-btn');
if (!btn) { console.log('no .woosw-btn on /shop/'); process.exit(0); }
console.log('heart: ' + await p.evaluate(e => e.outerHTML.slice(0, 200), btn));
await p.evaluate(e => e.scrollIntoView({ block: 'center' }), btn); await sleep(800);
await btn.click();
await sleep(6000);
console.log('heart after: ' + await p.evaluate(e => e.className, btn));
await cookies('after add');

let r = await go(S + '/wishlist/'); await sleep(5000);
console.log('HDR /wishlist/ status=' + (r && r.status()) + ' lscache=' + (r && r.headers()['x-litespeed-cache']) + ' cc=' + (r && r.headers()['cache-control']) + ' chain=' + (r ? r.request().redirectChain().map(x => x.url()).join(' > ') : '-'));
await rows('/wishlist/');
await cookies('on wishlist');
const key = ((await p.cookies(S)).find(c => c.name === 'woosw_key') || {}).value;
if (key) { r = await go(S + '/wishlist/' + key + '/'); await sleep(4000);
  console.log('HDR /wishlist/' + key + '/ status=' + (r && r.status()) + ' lscache=' + (r && r.headers()['x-litespeed-cache']));
  await rows('by own key'); }
r = await go(S + '/wishlist/?nc=' + Date.now()); await sleep(4000);
console.log('HDR busted status=' + (r && r.status()) + ' lscache=' + (r && r.headers()['x-litespeed-cache']));
await rows('cache-busted');
await b.close();
