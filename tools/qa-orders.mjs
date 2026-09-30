/**
 * Two REAL test orders, on the owner's instruction (30 Sep: "Yes, and also
 * Zelle"): one Cash on delivery, one Pay via Zelle. No money moves with
 * either. Each is placed ONCE - never retried - with an unmistakable name,
 * a reserved example.com email and an order note, and is cancelled on the
 * server by the next workflow step. Writes the order numbers to qa-orders.txt.
 */
import { createRequire } from 'module';
import fs from 'fs';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const placed = [];
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
for (const method of ['cod', 'zelle']) {
  console.log('\n=== TEST ORDER: ' + method + ' ===');
  const ctx = await b.createBrowserContext(); const p = await ctx.newPage(); await p.setViewport({ width: 1366, height: 900 });
  await go(p, PRODUCT);
  await p.select('#af-size-select', '2×3 ft (24×36 in)').catch(() => {}); await sleep(800);
  await p.evaluate(() => { const r = document.querySelector('input[name="af_kit"][value="painting"]'); if (r) r.closest('label').click(); }); await sleep(1200);
  const pagePrice = await p.evaluate(() => (document.getElementById('af-live-price') || {}).textContent);
  await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
  await sleep(2500);
  await go(p, S + '/checkout/');
  await p.evaluate(() => {
    const set = (id, v) => { const e = document.getElementById(id); if (!e) return; if (window.jQuery) jQuery(e).val(v).trigger('change'); else e.value = v; };
    set('billing_first_name', 'TEST ORDER'); set('billing_last_name', 'QA do not ship'); set('billing_country', 'US');
    set('billing_address_1', '350 5th Ave'); set('billing_city', 'New York'); set('billing_state', 'NY'); set('billing_postcode', '10001');
    set('billing_phone', '2125550142'); set('billing_email', 'qa-test-order@example.com');
    const n = document.getElementById('order_comments'); if (n) n.value = 'QA TEST ORDER placed by the site tester on the owner\'s instruction (30 Sep). Do not ship. Cancelled straight after.';
    if (window.jQuery) jQuery(document.body).trigger('update_checkout');
  });
  try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 25000 }); } catch {}
  await sleep(2500);
  const has = await p.evaluate((m) => !!document.querySelector('.wc_payment_methods input[value="' + m + '"]'), method);
  if (!has) { console.log('  payment method "' + method + '" not offered - no order placed'); await ctx.close(); continue; }
  await p.evaluate((m) => { const r = document.querySelector('.wc_payment_methods input[value="' + m + '"]'); r.click(); if (window.jQuery) jQuery(r).trigger('change'); }, method);
  await sleep(2500);
  // any required field inside the chosen method's box (e.g. a reference)
  await p.evaluate((m) => { const li = document.querySelector('.wc_payment_methods li.payment_method_' + m); if (!li) return; li.querySelectorAll('input[type=text], textarea').forEach(i => { if (!i.value) i.value = 'QA-TEST'; }); }, method);
  const before = await p.evaluate(() => { const t = document.querySelector('.woocommerce-checkout-review-order-table'); return t ? t.innerText.replace(/\s+/g, ' ') : ''; });
  console.log('  product page price: ' + pagePrice);
  console.log('  checkout review before placing: ' + before.slice(0, 400));
  console.log('  payment box: ' + (await p.evaluate((m) => { const d = document.querySelector('.wc_payment_methods li.payment_method_' + m + ' .payment_box'); return d ? d.innerText.replace(/\s+/g, ' ').slice(0, 300) : '(none)'; }, method)));
  // ONE press, no retry
  await p.evaluate(() => { const t = document.getElementById('terms'); if (t) t.checked = true; document.getElementById('place_order').click(); });
  try { await p.waitForFunction(() => /order-received/.test(location.href) || document.querySelector('.woocommerce-NoticeGroup-checkout .woocommerce-error, ul.woocommerce-error'), { timeout: 60000 }); } catch {}
  await sleep(4000);
  const url = p.url();
  if (!/order-received/.test(url)) {
    console.log('  NOT placed. Page says: ' + (await p.evaluate(() => { const e = document.querySelector('.woocommerce-error'); return e ? e.innerText.replace(/\s+/g, ' ') : '(no message)'; })).slice(0, 300));
    await ctx.close(); continue;
  }
  const m = url.match(/order-received\/(\d+)/); const id = m ? m[1] : '';
  const thanks = await p.evaluate(() => {
    const q = (s) => { const e = document.querySelector(s); return e ? e.innerText.replace(/\s+/g, ' ').trim() : ''; };
    return { notice: q('.woocommerce-thankyou-order-received, .woocommerce-notice--success'), overview: q('.woocommerce-order-overview, ul.order_details'),
      details: q('.woocommerce-order-details, .woocommerce-table--order-details'), instructions: q('.woocommerce-order > p, .woocommerce-bacs-bank-details, .wc-zelle-instructions') };
  });
  console.log('  PLACED order #' + id + '  ' + url.replace(S, ''));
  for (const [k, v] of Object.entries(thanks)) console.log('  ' + k + ': ' + v.slice(0, 500));
  placed.push(id + ' ' + method);
  await ctx.close();
}
fs.writeFileSync('qa-orders.txt', placed.join('\n') + '\n');
console.log('\nplaced: ' + (placed.join(', ') || 'none'));
await b.close();
