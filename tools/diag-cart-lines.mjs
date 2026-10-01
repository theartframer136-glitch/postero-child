/**
 * The cart page, live, with real lines in it (owner, 1 Oct: "same also this
 * page" - the checkout's "Your choices" editor on the cart too). Read-only: a
 * fresh guest basket, no order.
 *
 * Adds a 3x4 ft "Painting only", a 2x3 ft with bars and a frame, and a
 * download, then reads what the cart page is made of: the wrappers around the
 * form, the table's columns and cells, a line's markup, the buttons' look, the
 * phone layout, and how the page redraws itself after a quantity change (an
 * AJAX swap of the form, or a full reload). Prints pictures of the cart at six
 * widths, and a self-contained copy of the page (markup with every same-site
 * stylesheet inlined, gzip + base64) for designing against offline.
 *
 *   node tools/diag-cart-lines.mjs
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const errs = [];
function shrink(dataUrl, targetW, quality) {
  return new Promise((resolve) => { const im = new Image(); im.onload = function () { const sc = Math.min(1, targetW / im.naturalWidth); const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * sc); c.height = Math.round(im.naturalHeight * sc); c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); resolve(c.toDataURL('image/jpeg', quality)); }; im.onerror = () => resolve(''); im.src = dataUrl; });
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
async function picture(p, label, w) {
  const el = await p.evaluateHandle(() => { const f = document.querySelector('.woocommerce-cart-form'); return f ? (f.closest('.woocommerce') || f.parentElement) : document.body; });
  await el.evaluate(e => e.scrollIntoView({ block: 'start' })); await sleep(700);
  let raw = '';
  try { raw = await el.screenshot({ encoding: 'base64' }); } catch (e) { console.log('picture ' + label + ' failed: ' + e.message); return; }
  const small = await p.evaluate(shrink, 'data:image/png;base64,' + raw, w, 0.62);
  const b64 = small.replace(/^data:image\/jpeg;base64,/, '');
  console.log(`=== PICTURE ${label} (${b64.length} chars) ===`);
  for (let i = 0; i < b64.length; i += 180) console.log('B64 ' + b64.slice(i, i + 180));
  console.log('=== END PICTURE ' + label + ' ===');
}
async function addFromPage(p, size, kit) {
  await go(p, PRODUCT);
  await p.select('#af-size-select', size).catch(() => {}); await sleep(700);
  await p.evaluate((kit) => { const r = document.querySelector('input[name="af_kit"][value="' + kit + '"]'); if (r) r.closest('label').click(); }, kit); await sleep(1200);
  await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
  await sleep(2000);
}

const ctx = await b.createBrowserContext(); const p = await ctx.newPage();
p.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 160)));
p.on('console', m => { if (m.type() === 'error' && !/403|favicon|google|facebook|pinterest|doubleclick|clarity/i.test(m.text())) errs.push('console: ' + m.text().slice(0, 160)); });
p.on('dialog', async d => { errs.push('dialog: ' + d.message()); try { await d.dismiss(); } catch {} });
await p.setViewport({ width: 1366, height: 900 });
await addFromPage(p, '3×4 ft (36×48 in)', 'painting');
await addFromPage(p, '2×3 ft (24×36 in)', 'painting_bar_frame');
await go(p, PRODUCT);
await p.evaluate(async () => { const i = document.querySelector('form.cart [name="add-to-cart"]'); await fetch('/?wc-ajax=add_to_cart', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(i ? i.value : '') + '&quantity=1&af_digital=1' }); });

const resp = await p.goto(S + '/cart/', { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
const html = resp ? await resp.text().catch(() => '') : '';
await go(p, S + '/cart/');
await p.evaluate(() => { const b = [...document.querySelectorAll('button, a')].find(x => /necessary only/i.test(x.textContent || '')); if (b) b.click(); });
await sleep(1500);

console.log('=== SERVER HTML MARKERS ===');
console.log('notices hook: ' + ([...html.matchAll(/<!-- af-notices via ([^ ]+) -->/g)].map(m => m[1]).join(', ') || '(none)'));
console.log('elementor documents: ' + [...new Set([...html.matchAll(/data-elementor-type="([^"]+)"[^>]*data-elementor-id="(\d+)"/g)].map(m => m[1] + '#' + m[2]))].join(', '));
console.log('elementor widgets: ' + [...new Set([...html.matchAll(/elementor-widget-([a-z0-9-]+)/g)].map(m => m[1]))].join(', '));
console.log('wc cart shortcode/block: ' + (/wp-block-woocommerce-cart/.test(html) ? 'block' : /woocommerce-cart-form/.test(html) ? 'classic form' : 'neither'));

console.log('\n=== STRUCTURE 1366 ===');
const info = await p.evaluate(() => {
  const r = (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return Math.round(b.left) + ',' + Math.round(b.top + scrollY) + ' ' + Math.round(b.width) + 'x' + Math.round(b.height); };
  const chain = (el) => { const out = []; for (let n = el; n && n !== document.body && out.length < 14; n = n.parentElement) { const cls = (n.className && typeof n.className === 'string') ? '.' + n.className.trim().split(/\s+/).slice(0, 4).join('.') : ''; out.push(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + cls + ' [' + r(n) + ']'); } return out.reverse().join('\n   > '); };
  const form = document.querySelector('.woocommerce-cart-form');
  const t = form && form.querySelector('table');
  const cs = (e, props) => { if (!e) return ''; const c = getComputedStyle(e); return props.map(k => k + '=' + c[k]).join(' '); };
  const out = {};
  out.body = document.body.className;
  out.formChain = form ? chain(form) : 'NO FORM';
  out.formDirectChildOfDivWoocommerce = !!(form && form.parentElement && form.parentElement.matches('div.woocommerce'));
  out.totals = r(document.querySelector('.cart_totals')); out.collaterals = r(document.querySelector('.cart-collaterals')); out.form = r(form);
  out.table = t ? t.className + ' | ' + cs(t, ['tableLayout', 'width', 'borderCollapse', 'fontFamily', 'fontSize', 'color']) : null;
  out.thead = t ? [...t.querySelectorAll('thead th')].map(th => th.className + ' "' + th.textContent.trim() + '" w=' + Math.round(th.getBoundingClientRect().width) + ' ' + cs(th, ['textTransform', 'fontSize', 'fontWeight', 'paddingLeft', 'paddingRight', 'borderBottom'])) : null;
  out.rows = t ? [...t.querySelectorAll('tbody > tr')].map(tr => tr.className + ' :: ' + [...tr.children].map(td => td.tagName.toLowerCase() + '.' + (td.className || '-') + (td.dataset.title ? '[' + td.dataset.title + ']' : '') + ' w=' + Math.round(td.getBoundingClientRect().width) + ' h=' + Math.round(td.getBoundingClientRect().height) + ' colspan=' + (td.colSpan || 1) + ' ' + cs(td, ['verticalAlign', 'paddingTop', 'paddingLeft', 'borderTop', 'borderBottom', 'textAlign']) + ' "' + td.innerText.replace(/\s+/g, ' ').trim().slice(0, 90) + '"').join('\n      ')) : null;
  const line = t && t.querySelector('tr.cart_item');
  out.lineHtml = line ? line.outerHTML.replace(/\s+/g, ' ').slice(0, 7000) : null;
  const frameLine = t && [...t.querySelectorAll('tr.cart_item')].find(x => /Frame Color/i.test(x.innerText));
  out.frameLineHtml = frameLine ? frameLine.querySelector('td.product-name') && frameLine.querySelector('td.product-name').outerHTML.replace(/\s+/g, ' ').slice(0, 4000) : null;
  const act = t && t.querySelector('td.actions');
  out.actionsHtml = act ? act.outerHTML.replace(/\s+/g, ' ').slice(0, 3000) : null;
  out.totalsHtml = (document.querySelector('.cart_totals') || {}).outerHTML ? document.querySelector('.cart_totals').outerHTML.replace(/\s+/g, ' ').slice(0, 5000) : null;
  out.name = line ? cs(line.querySelector('td.product-name a') || line.querySelector('td.product-name'), ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'color', 'wordBreak']) : null;
  out.dl = line ? [...line.querySelectorAll('dl.variation > *')].map(e => e.tagName + '.' + e.className + ' "' + e.textContent.trim().slice(0, 40) + '" ' + cs(e, ['display', 'float', 'fontSize', 'color', 'margin'])).join('\n      ') : null;
  out.thumb = line ? (() => { const i = line.querySelector('td.product-thumbnail img'); return i ? Math.round(i.getBoundingClientRect().width) + 'x' + Math.round(i.getBoundingClientRect().height) + ' ' + cs(i, ['borderRadius', 'objectFit']) : 'none'; })() : null;
  const btns = [...document.querySelectorAll('.woocommerce-cart-form button, .cart_totals .checkout-button')];
  out.buttons = btns.map(bt => (bt.name || bt.className).slice(0, 30) + ' "' + bt.textContent.trim().slice(0, 20) + '" ' + cs(bt, ['display', 'textTransform', 'backgroundColor', 'color', 'borderRadius', 'paddingTop', 'paddingLeft', 'fontSize', 'fontWeight', 'letterSpacing', 'borderTopWidth', 'borderTopColor', 'boxShadow', 'minHeight', 'height', 'gap']));
  const qty = line && line.querySelector('.quantity');
  out.qtyHtml = qty ? qty.outerHTML.replace(/\s+/g, ' ').slice(0, 1500) : null;
  out.wcCartParams = typeof window.wc_cart_params !== 'undefined';
  out.scripts = [...document.scripts].map(s => s.src).filter(s => /cart|woocommerce\/assets\/js\/frontend/.test(s)).map(s => s.replace(/^https?:\/\/[^/]+/, '').slice(0, 110));
  try { const ev = window.jQuery && jQuery._data(document, 'events'); out.docEvents = ev ? Object.keys(ev).map(k => k + '(' + ev[k].length + ')').join(' ') : 'n/a'; const bev = window.jQuery && jQuery._data(document.body, 'events'); out.bodyEvents = bev ? Object.keys(bev).map(k => k + '(' + bev[k].length + ')').join(' ') : 'n/a'; } catch (e) { out.docEvents = 'err ' + e.message; }
  out.vars = (() => { const c = getComputedStyle(document.documentElement); return ['--af-gold', '--af-gold-text', '--af-co-line'].map(k => k + '=' + c.getPropertyValue(k).trim()).join(' '); })();
  out.overflow = document.documentElement.scrollWidth - document.documentElement.clientWidth;
  return out;
});
for (const [k, v] of Object.entries(info)) console.log(k + ': ' + (Array.isArray(v) ? '\n   ' + v.join('\n   ') : v));

for (const [w, h] of [[1918, 1000], [1366, 900], [1150, 900], [1024, 900], [768, 1000], [390, 844]]) {
  await p.setViewport({ width: w, height: h }); await sleep(1500);
  const lay = await p.evaluate(() => {
    const t = document.querySelector('.woocommerce-cart-form table'); if (!t) return 'no table';
    const line = t.querySelector('tr.cart_item'); const r = (e) => e ? Math.round(e.getBoundingClientRect().width) + 'x' + Math.round(e.getBoundingClientRect().height) + '@' + Math.round(e.getBoundingClientRect().left) : '-';
    const f = document.querySelector('.woocommerce-cart-form'), c = document.querySelector('.cart-collaterals');
    return 'form ' + r(f) + ' totals ' + r(c) + ' table ' + r(t) + ' tr.display=' + getComputedStyle(line).display + ' | ' + [...line.children].map(td => (td.className.split(' ')[0] || td.tagName) + ' ' + r(td) + ' d=' + getComputedStyle(td).display + (getComputedStyle(td, '::before').content !== 'none' ? ' before=' + getComputedStyle(td, '::before').content.slice(0, 20) : '')).join(' | ') + ' overflow=' + (document.documentElement.scrollWidth - document.documentElement.clientWidth);
  });
  console.log('\n--- ' + w + ' --- ' + lay);
  await picture(p, 'cart-' + w, w <= 400 ? 380 : 700);
}
await p.setViewport({ width: 1366, height: 900 }); await sleep(1200);

console.log('\n=== HOW THE CART REDRAWS AFTER A QUANTITY CHANGE ===');
const listen = () => p.evaluate(() => { window.__afEv = []; if (window.jQuery) jQuery(document.body).on('updated_wc_div updated_cart_totals wc_fragments_refreshed wc_cart_emptied', (e) => window.__afEv.push(e.type)); });
await listen();
await p.evaluate(() => { window.__afProbe = 1; const f = document.querySelector('.woocommerce-cart-form'); if (f) f.__afOld = 1; });
await p.evaluate(() => { const i = document.querySelector('.woocommerce-cart-form tr.cart_item input.qty'); if (i) { i.value = '2'; i.dispatchEvent(new Event('input', { bubbles: true })); if (window.jQuery) jQuery(i).trigger('change'); else i.dispatchEvent(new Event('change', { bubbles: true })); } });
await sleep(6000);
try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 30000 }); } catch {}
await sleep(1500);
console.log(await p.evaluate(() => 'events: ' + (window.__afEv || []).join(',') + ' | same page (no reload): ' + (window.__afProbe === 1) + ' | form replaced: ' + !(document.querySelector('.woocommerce-cart-form') || {}).__afOld + ' | first qty now ' + ((document.querySelector('.woocommerce-cart-form tr.cart_item input.qty') || {}).value) + ' | subtotal ' + ((document.querySelector('.cart_totals .cart-subtotal td') || {}).innerText || '').trim()));
await listen();
await p.evaluate(() => { window.__afProbe2 = 1; const f = document.querySelector('.woocommerce-cart-form'); if (f) f.__afOld2 = 1; });
await p.evaluate(() => { if (window.jQuery) jQuery(document.body).trigger('wc_update_cart'); });
await sleep(5000);
try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay'), { timeout: 30000 }); } catch {}
console.log(await p.evaluate(() => 'wc_update_cart event: same page ' + (window.__afProbe2 === 1) + ' | form replaced: ' + !(document.querySelector('.woocommerce-cart-form') || {}).__afOld2 + ' | events: ' + (window.__afEv || []).join(',')));
// put the quantity back
await p.evaluate(() => { const i = document.querySelector('.woocommerce-cart-form tr.cart_item input.qty'); if (i) { i.value = '1'; if (window.jQuery) jQuery(i).trigger('change'); } });
await sleep(6000);

console.log('\n=== SNAPSHOT (gzip+base64 html with inlined css, 1366) ===');
const snap = await p.evaluate(async () => {
  const css = [];
  for (const ss of document.styleSheets) {
    let rules; try { rules = ss.cssRules; } catch { css.push('/* skipped cross-origin ' + ss.href + ' */'); continue; }
    const media = ss.media && ss.media.mediaText ? ss.media.mediaText : '';
    let txt = [...rules].map(r => r.cssText).join('\n');
    if (media && media !== 'all') txt = '@media ' + media + '{\n' + txt + '\n}';
    css.push('/* ' + (ss.href || ('inline ' + ((ss.ownerNode && ss.ownerNode.id) || ''))) + ' */\n' + txt);
  }
  const doc = document.documentElement.cloneNode(true);
  doc.querySelectorAll('script, link[rel~="stylesheet"], style, noscript, link[rel="preload"], iframe').forEach(n => n.remove());
  doc.querySelectorAll('img').forEach(i => { const live = i.getAttribute('src'); i.setAttribute('src', i.currentSrc || i.src || live || ''); i.removeAttribute('srcset'); i.removeAttribute('sizes'); i.removeAttribute('loading'); });
  const st = document.createElement('style'); st.id = 'af-snap-css'; st.textContent = css.join('\n');
  doc.querySelector('head').appendChild(st);
  const html = '<!doctype html>\n' + doc.outerHTML;
  const gz = new Blob([html]).stream().pipeThrough(new CompressionStream('gzip'));
  const u = new Uint8Array(await new Response(gz).arrayBuffer());
  let bin = ''; for (let i = 0; i < u.length; i += 0x8000) bin += String.fromCharCode.apply(null, u.subarray(i, i + 0x8000));
  return { size: html.length, b64: btoa(bin) };
});
console.log(`=== SNAP cart-1366 (${snap.size} chars html, ${snap.b64.length} b64) ===`);
for (let i = 0; i < snap.b64.length; i += 180) console.log('SNP ' + snap.b64.slice(i, i + 180));
console.log('=== END SNAP ===');

console.log('\n=== console / page errors ===\n' + ([...new Set(errs)].join('\n') || 'none'));
await b.close();
