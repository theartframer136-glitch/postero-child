// N-02: does checkout ask for the Digital Download License only when the order
// contains a download?
//
// Test Run 03 found the licence box on an order for a rolled canvas ("You
// receive: Painting only"), marked * but without the required attribute, and
// the server refuses such an order if it is left unticked. Each case here is
// a first-time visitor with a cart of its own:
//   A  Painting only                        → no box
//   B  What you receive: Digital download   → box, required
//   C  the download modal (af_digital=1)    → box, required
//   D  A and B together                     → box, required
//
// SAFETY: items go in carts, but wc-ajax=checkout is never sent, so no order
// can be placed. Each cart is emptied afterwards.
//
// Run: node tools/verify-n02.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCT = '/product/kerala-mural-celebration-canvas-wall-art/';
const NEVER_SEND = ['wc-ajax=checkout', 'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply'];
const results = [];

const browser = await chromium.launch({ headless: true });
const fresh = async () => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
  await ctx.route('**/*', route => {
    const req = route.request();
    const body = (req.postData() || '') + ' ' + req.url();
    if (NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json', body: '{"result":"failure","messages":"blocked by probe"}' });
    }
    return route.continue();
  });
  return ctx;
};
const go = async (page, path, wait = 2500) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r;
};

// "What you receive" on the product page, then Add to Cart
const addWithKit = async (page, kit) => {
  await go(page, PRODUCT, 3000);
  const picked = await page.evaluate(k => {
    const r = document.querySelector('form.cart input[name="af_kit"][value="' + k + '"]');
    if (!r) return false;
    r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true }));
    return true;
  }, kit).catch(() => false);
  if (!picked) return 'no "' + kit + '" option on the product page';
  await page.waitForTimeout(600);
  await page.click('.single_add_to_cart_button', { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(4500);
  return '';
};

// exactly what the download modal sends
const addViaModal = async page => {
  await go(page, PRODUCT, 2500);
  return page.evaluate(async () => {
    const pid = (document.querySelector('form.cart [name="add-to-cart"]') || {}).value
      || ((document.body.className.match(/postid-(\d+)/) || [])[1]) || '';
    if (!pid) return 'no product id on the page';
    const r = await fetch('/?wc-ajax=add_to_cart', { method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'product_id=' + encodeURIComponent(pid) + '&quantity=1&af_digital=1' }).catch(() => null);
    return r && r.ok ? '' : 'add_to_cart answered ' + (r ? r.status : 'nothing');
  }).catch(e => String(e));
};

const readCartAndCheckout = async page => {
  await go(page, '/cart/', 2500);
  const lines = await page.evaluate(() => [...document.querySelectorAll('.cart_item')].map(li =>
    ((li.querySelector('.product-name') || li).innerText || '').replace(/\s+/g, ' ').trim().slice(0, 150))).catch(() => []);
  await go(page, '/checkout/', 4500);
  const box = await page.evaluate(() => {
    const cb = document.querySelector('#af_dl_license, input[name="af_dl_license"]');
    if (!cb) return null;
    const row = cb.closest('.form-row');
    return { required: cb.required, aria: cb.getAttribute('aria-required'), validate: !!(row && row.classList.contains('validate-required')) };
  }).catch(() => undefined);
  return { lines, box };
};

const emptyCart = async page => {
  for (let i = 0; i < 6; i++) {
    await go(page, '/cart/', 1500);
    const h = await page.evaluate(() => { const a = document.querySelector('a.remove[href]'); return a ? a.href : ''; }).catch(() => '');
    if (!h) break;
    await go(page, h, 1200);
  }
};

const cases = [
  { id: 'A', what: 'Painting only', wantBox: false, add: async p => addWithKit(p, 'painting') },
  { id: 'B', what: 'What you receive: Digital download', wantBox: true, add: async p => addWithKit(p, 'digital') },
  { id: 'C', what: 'download modal (af_digital=1)', wantBox: true, add: addViaModal },
  { id: 'D', what: 'Painting only + Digital download', wantBox: true, add: async p => (await addWithKit(p, 'painting')) || addWithKit(p, 'digital') },
];

console.log('verify-n02: ' + SITE + '   ' + new Date().toISOString() + '\n');
for (const c of cases) {
  const ctx = await fresh(); const page = await ctx.newPage();
  const err = await c.add(page);
  const { lines, box } = err ? { lines: [], box: undefined } : await readCartAndCheckout(page);
  let verdict, seen;
  if (err || !lines.length || box === undefined) {
    verdict = 'NO DATA'; seen = err || (lines.length ? 'checkout unreadable' : 'cart stayed empty');
  } else {
    const shown = box !== null;
    const reqOk = !shown || (box.required && box.aria === 'true' && box.validate);
    const ok = shown === c.wantBox && reqOk;
    results.push(ok);
    verdict = ok ? 'RIGHT' : 'WRONG';
    seen = 'box ' + (shown ? 'shown · required ' + box.required + ' · aria-required ' + box.aria + ' · validate-required ' + box.validate : 'not shown')
      + '\n' + ' '.repeat(52) + 'cart: ' + lines.join(' | ');
  }
  console.log('  ' + verdict.padEnd(8) + c.id + '  ' + c.what.padEnd(38) + (c.wantBox ? 'wants box ' : 'wants none') + '  → ' + seen);
  await emptyCart(page);
  await ctx.close();
}

const right = results.filter(Boolean).length;
console.log('\n' + (right === cases.length ? 'N-02 FIXED' : 'N-02 NOT FIXED') + ': ' + right + ' of ' + cases.length + ' checkouts right');
console.log('done ' + new Date().toISOString());
await browser.close();
