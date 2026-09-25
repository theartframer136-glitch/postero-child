// Verify, on the live shop, the Canva brochure pages the status workbook says
// are on the website.
//
// The rows come from tools/canva-status-2026-09-25.json, exported from the
// owner's "pg-yh-updated.xlsx" (sheet "Canva to Website": 368 pages, after the
// five licensed-character pages were taken out). The 23 Sep run read
// tools/canva-status-2026-09-23.json, from "theartframer-product-status-per-
// page-updated.xlsx", and opened only the 147 pages uploaded on 22 Sep; this
// data sets open_all, so every page on the site is opened, and the second
// product of every page that has two. Nothing here reads or writes Canva: the
// brochure side is that workbook, and the website side is what this measures.
//
// 1. WooCommerce's public Store API, fetched from inside a real browser page
//    (the host's bot protection refuses plain HTTP clients):
//      include=<ids>  every product id the workbook names: does it exist and is
//                     it published, and do its title, link, SKU, price,
//                     purchasability, stock, category and images agree?
//      sku=<codes>    every art code: which products carry it. That catches a
//                     code on two products, a code on a product other than the
//                     one the workbook names, and the pages still marked "No".
//      each product's first image is fetched: does it answer 200 as an image?
// 2. Every product uploaded on 22 Sep, opened as a shopper would: HTTP status,
//    the H1, whether the main image has actually rendered, the Add to Cart
//    button, whether the art code is shown, and the size options offered,
//    set against the size the workbook records.
//
// Each product prints one "ROW\t{json}" line, so the results can be turned
// back into a workbook. Read-only: nothing is added to a cart.
//
// Run: node tools/verify-canva-products.mjs [url]

import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const DATA = JSON.parse(readFileSync(new URL('./canva-status-2026-09-25.json', import.meta.url), 'utf8'));
// Opening every page of 368 can outrun the workflow's 30-minute limit, so the
// workflow's "only" input (AF_QA_ONLY) can take half: 1 = the first half of the
// pages, 2 = the second. Blank = all of them.
const HALF = (process.env.AF_QA_ONLY || '').trim();
const ALL_ROWS = DATA.rows;
const MID = Math.ceil(ALL_ROWS.length / 2);
const ROWS = HALF === '1' ? ALL_ROWS.slice(0, MID) : HALF === '2' ? ALL_ROWS.slice(MID) : ALL_ROWS;
if (HALF === '1' || HALF === '2') console.log('half ' + HALF + ' of 2: pages ' + ROWS[0].page + '–' + ROWS[ROWS.length - 1].page);

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

const norm = s => String(s || '').toLowerCase().replace(/&amp;/g, '&').replace(/&#0?38;/g, '&').replace(/&#8211;|–|—/g, '-')
  .replace(/&#215;|×/g, 'x').replace(/&#8217;|’/g, "'").replace(/\s+/g, ' ').trim();
const path = u => { try { return new URL(u, SITE).pathname.replace(/\/+$/, '/'); } catch (e) { return String(u || ''); } };
// "5x3" and "3x5" are the same canvas turned; "2.5x3" stays as written
const sizeKey = s => { const m = String(s || '').replace(/×/g, 'x').match(/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)/i); if (!m) return ''; const a = [parseFloat(m[1]), parseFloat(m[2])].sort((x, y) => x - y); return a[0] + 'x' + a[1]; };

// JSON from inside the page, so the request is the browser's own
const api = async (q) => page.evaluate(async (url) => {
  for (let i = 0; i < 3; i++) {
    try {
      const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const t = await r.text();
      if (r.status === 200) return { status: 200, body: JSON.parse(t) };
      if (i === 2) return { status: r.status, body: null, text: t.slice(0, 120) };
    } catch (e) { if (i === 2) return { status: 0, body: null, text: String(e).slice(0, 120) }; }
    await new Promise(res => setTimeout(res, 3000));
  }
}, SITE + '/wp-json/wc/store/v1/products?' + q);

console.log('verify-canva-products: ' + SITE + '   ' + new Date().toISOString());
console.log('workbook rows: ' + ROWS.length + '   on the site per the workbook: ' + ROWS.filter(r => r.on_site).length
  + '   uploaded 22 Sep: ' + ROWS.filter(r => r.uploaded_0922).length + '   not on the site: ' + ROWS.filter(r => !r.on_site).length + '\n');

await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {});
await page.waitForTimeout(2000);

// ── 1a. every id the workbook names ────────────────────────────────────────
const byId = new Map();
const allIds = [...new Set(ROWS.flatMap(r => r.ids))];
let apiTrouble = [];
for (let i = 0; i < allIds.length; i += 50) {
  const chunk = allIds.slice(i, i + 50);
  const r = await api('include=' + chunk.join(',') + '&per_page=100');
  if (r.status !== 200 || !Array.isArray(r.body)) { apiTrouble.push('include batch ' + (i / 50 + 1) + ': HTTP ' + r.status + ' ' + (r.text || '')); continue; }
  for (const p of r.body) byId.set(p.id, p);
}
// ── 1b. every art code: which products carry it ────────────────────────────
const bySku = new Map();
const codes = [...new Set(ROWS.map(r => r.code).filter(Boolean))];
let skuFilterWorks = true;
for (let i = 0; i < codes.length; i += 40) {
  const chunk = codes.slice(i, i + 40);
  const r = await api('sku=' + encodeURIComponent(chunk.join(',')) + '&per_page=100');
  if (r.status !== 200 || !Array.isArray(r.body)) { apiTrouble.push('sku batch ' + (i / 40 + 1) + ': HTTP ' + r.status); continue; }
  // If the API ignored the filter it returns its default list, whose SKUs are not the ones asked for.
  if (r.body.length && !r.body.some(p => chunk.includes(p.sku))) skuFilterWorks = false;
  for (const p of r.body) { if (!chunk.includes(p.sku)) continue; if (!bySku.has(p.sku)) bySku.set(p.sku, []); bySku.get(p.sku).push(p); if (!byId.has(p.id)) byId.set(p.id, p); }
}
// ── 1b'. the same code with a letter on the end (SKUs are unique, so a second
//        product made from the same page gets "…A", "…B"): the twins ────────
const byBase = new Map();
const lettered = codes.flatMap(c => ['A', 'B', 'C'].map(l => c + l));
for (let i = 0; i < lettered.length; i += 40) {
  const chunk = lettered.slice(i, i + 40);
  const r = await api('sku=' + encodeURIComponent(chunk.join(',')) + '&per_page=100');
  if (r.status !== 200 || !Array.isArray(r.body)) { apiTrouble.push('lettered sku batch ' + (i / 40 + 1) + ': HTTP ' + r.status); continue; }
  for (const p of r.body) {
    if (!chunk.includes(p.sku)) continue;
    const base = p.sku.slice(0, -1);
    if (!byBase.has(base)) byBase.set(base, []);
    byBase.get(base).push(p);
    if (!byId.has(p.id)) byId.set(p.id, p);
  }
}
console.log('Store API: ' + byId.size + ' products read' + (apiTrouble.length ? '   PROBLEMS: ' + apiTrouble.join('; ') : '') + (skuFilterWorks ? '' : '   (the sku filter was ignored — code lookups are not reliable)'));

// ── 1c. first image of every product found ─────────────────────────────────
const imgStatus = new Map();
const imgs = [...byId.values()].map(p => (p.images && p.images[0] && p.images[0].src) || '').filter(Boolean);
for (let i = 0; i < imgs.length; i += 20) {
  const chunk = imgs.slice(i, i + 20);
  const res = await page.evaluate(async (urls) => Promise.all(urls.map(async u => {
    try { const r = await fetch(u, { method: 'HEAD' }); return [u, r.status + ' ' + (r.headers.get('content-type') || '')]; }
    catch (e) { return [u, 'ERR']; }
  })), chunk).catch(() => chunk.map(u => [u, 'ERR']));
  for (const [u, s] of res) imgStatus.set(u, s);
}

// ── per-row verdict from the API ───────────────────────────────────────────
const results = [];
for (const r of ROWS) {
  const out = { page: r.page, code: r.code, category: r.category, size: r.size, on_site: r.on_site, uploaded_0922: r.uploaded_0922, when: r.when, note: r.note, ids: r.ids, problems: [], notes: [] };
  const carriers = bySku.get(r.code) || [];
  out.sku_carriers = carriers.map(p => p.id);
  // every product on the site made from this page: the exact code and the lettered ones
  const priceOf = p => p.prices && p.prices.price ? Number(p.prices.price) / Math.pow(10, p.prices.currency_minor_unit || 0) : 0;
  out.code_products = carriers.concat(byBase.get(r.code) || []).map(p => ({ id: p.id, sku: p.sku, name: p.name, link: p.permalink,
    price: priceOf(p), purchasable: !!p.is_purchasable, in_stock: !!p.is_in_stock }));
  if (!r.on_site) {
    if (carriers.length) out.problems.push('marked NOT on the site, but product ' + carriers.map(p => p.id).join(', ') + ' carries this code');
    else out.notes.push('absent, as the workbook says');
    results.push(out); continue;
  }
  const id = r.ids[0];
  const p = byId.get(id);
  if (!p) {
    out.problems.push('product ' + id + ' not returned by the Store API (missing, unpublished or hidden)');
    results.push(out); continue;
  }
  out.live_title = p.name; out.live_link = p.permalink; out.sku = p.sku;
  const price = p.prices && p.prices.price ? Number(p.prices.price) / Math.pow(10, p.prices.currency_minor_unit || 0) : 0;
  out.price = price; out.purchasable = !!p.is_purchasable; out.in_stock = !!p.is_in_stock;
  out.categories = (p.categories || []).map(c => c.name.replace(/&amp;/g, '&'));
  out.images = (p.images || []).length;
  const img0 = (p.images && p.images[0] && p.images[0].src) || '';
  out.image_status = img0 ? (imgStatus.get(img0) || '?') : 'none';
  if (r.title && norm(p.name) !== norm(r.title)) out.problems.push('title differs: live "' + p.name.slice(0, 70) + '"');
  if (r.link && path(p.permalink) !== path(r.link)) out.problems.push('link differs: live ' + path(p.permalink));
  if (p.sku !== r.code) out.problems.push('SKU is "' + (p.sku || '(none)') + '", not the art code');
  if (!(price > 0)) out.problems.push('no price');
  if (!p.is_purchasable) out.problems.push('cannot be bought');
  if (!p.is_in_stock) out.problems.push('out of stock');
  if (!out.images) out.problems.push('no images');
  else if (!/^200 image\//.test(out.image_status)) out.problems.push('main image answers ' + out.image_status);
  const want = norm(r.category).split('/').map(s => s.trim()).filter(Boolean);
  const have = out.categories.map(norm).join(' | ');
  if (want.length && !want.some(w => have.includes(w.replace(/'s /, "'s ").split(' ')[0]))) out.notes.push('categories: ' + out.categories.join(', '));
  if (carriers.length > 1) out.notes.push('code also on product ' + carriers.filter(c => c.id !== id).map(c => c.id).join(', '));
  if (carriers.length && !carriers.some(c => c.id === id)) out.problems.push('the code is on product ' + carriers.map(c => c.id).join(', ') + ', not ' + id);
  results.push(out);
}

// ── 2. product pages, opened as a shopper would ─────────────────────────────
// The 22 Sep uploads, or — when the data asks for it (open_all) — every page on
// the site, and the second product of every page that has two.
const OPEN_ALL = DATA.open_all === true;
async function openPage(url, code, size) {
  const o = { problems: [], notes: [] };
  let status = 0;
  for (let attempt = 0; attempt < 2; attempt++) {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
    status = resp ? resp.status() : 0;
    if (status === 200) break;
    await page.waitForTimeout(5000);
  }
  o.page_status = status;
  if (status !== 200) { o.problems.push('product page answered HTTP ' + status); return o; }
  const d = await page.evaluate(async (code) => {
    const seen = el => { if (!el) return false; const s = getComputedStyle(el), b = el.getBoundingClientRect(); return s.display !== 'none' && s.visibility !== 'hidden' && b.width > 2 && b.height > 2; };
    const img = document.querySelector('.woocommerce-product-gallery__image img, .woocommerce-product-gallery img, .product .images img');
    if (img && !img.complete) await new Promise(res => { img.addEventListener('load', res, { once: true }); img.addEventListener('error', res, { once: true }); setTimeout(res, 6000); });
    const btn = document.querySelector('.single_add_to_cart_button');
    const sizeSel = document.querySelector('#af-size-select, select[name*="size" i], select[name^="attribute_pa_size"]');
    const sizes = sizeSel ? [...sizeSel.options].map(o => o.textContent.replace(/\s+/g, ' ').trim()).filter(t => t && !/choose|select/i.test(t)) : [];
    // the code as the page prints it ("RK - 010074-5030") against the SKU form
    const flat = t => String(t || '').replace(/[‐-―−]/g, '-').replace(/\s+/g, '').toUpperCase();
    return {
      h1: ((document.querySelector('h1.product_title, .product_title, h1') || {}).textContent || '').replace(/\s+/g, ' ').trim(),
      img: img ? { ok: img.complete && img.naturalWidth > 0, w: img.naturalWidth } : null,
      btn: !!btn && seen(btn), btnDisabled: !!btn && (btn.disabled || btn.classList.contains('disabled')),
      codeShown: flat(document.body.innerText).includes(flat(code)),
      // the product's own line, word for word: is it the Canva page label?
      codeLine: ((document.querySelector('.af-art-code--single') || {}).textContent || '').replace(/\s+/g, ' ').trim(),
      sizes: sizes.slice(0, 12),
    };
  }, code).catch(e => ({ error: String(e.message).slice(0, 80) }));
  if (d.error) { o.problems.push('page not readable: ' + d.error); return o; }
  o.h1 = d.h1; o.sizes = d.sizes; o.code_shown = d.codeShown;
  o.code_line = d.codeLine; o.code_exact = d.codeLine === 'Art Code: ' + code;
  o.main_image = d.img ? (d.img.ok ? 'rendered ' + d.img.w + 'px' : 'NOT rendered') : 'none';
  if (!d.img || !d.img.ok) o.problems.push('main image did not render');
  if (!d.btn) o.problems.push('no Add to Cart button');
  const want = sizeKey(size);
  if (d.sizes.length) {
    o.size_offered = d.sizes.some(s => sizeKey(s) === want);
    if (!o.size_offered) o.notes.push('workbook size ' + size + ' is not among the options');
  }
  return o;
}

const toOpen = results.filter(x => x.live_link && (OPEN_ALL || x.uploaded_0922));
console.log('\nopening ' + toOpen.length + (OPEN_ALL ? ' product pages (every page on the site) …' : ' product pages uploaded on 22 Sep …'));
let n = 0;
for (const x of toOpen) {
  n++;
  const o = await openPage(x.live_link, x.code, x.size);
  x.problems.push(...o.problems); x.notes.push(...o.notes);
  for (const k of ['page_status', 'h1', 'sizes', 'code_shown', 'code_line', 'code_exact', 'main_image', 'size_offered']) if (k in o) x[k] = o[k];
  if (n % 50 === 0) console.log('  … ' + n + ' opened');
  await page.waitForTimeout(500);
}
if (OPEN_ALL) {
  // the other product of a page that has two: is it a working page too?
  for (const x of results) {
    const others = (x.code_products || []).filter(p => !x.ids.includes(p.id) && p.link);
    if (!others.length) continue;
    x.twin_pages = [];
    for (const p of others) {
      const o = await openPage(p.link, x.code, x.size);
      x.twin_pages.push({ id: p.id, sku: p.sku, status: o.page_status, ok: !o.problems.length, problems: o.problems });
      await page.waitForTimeout(500);
    }
  }
}

// ── report ────────────────────────────────────────────────────────────────
for (const x of results) console.log('ROW\t' + JSON.stringify(x));

if (OPEN_ALL) {
  const on = results.filter(x => x.live_link || (x.code_products || []).length);
  const bad = results.filter(x => x.problems.some(p => !/^SKU is /.test(p) && !/^the code is on product/.test(p)));
  const twins = results.filter(x => x.twin_pages);
  console.log('\n— every brochure page: ' + results.length + ' —');
  console.log('  found on the site                : ' + on.length + '   (by the product the workbook names: ' + results.filter(x => x.live_link).length + ')');
  console.log('  page HTTP 200                    : ' + results.filter(x => x.page_status === 200).length);
  console.log('  priced, buyable, in stock        : ' + results.filter(x => x.price > 0 && x.purchasable && x.in_stock).length);
  console.log('  main image renders               : ' + results.filter(x => /^rendered/.test(x.main_image || '')).length);
  console.log('  Add to Cart button               : ' + results.filter(x => x.page_status === 200 && !x.problems.includes('no Add to Cart button')).length);
  console.log('  art code shown on the page       : ' + results.filter(x => x.code_shown).length);
  const exact = results.filter(x => x.code_exact);
  console.log('  printed exactly as the Canva label: ' + exact.length + '   ("Art Code: RK-010001-3050")');
  const shapes = new Map();
  for (const x of results.filter(x => x.page_status === 200 && !x.code_exact)) {
    const k = (x.code_line || '(no line)').replace(/[A-Z]{2}(\s*[-\u2013]\s*)\d{6}-\d{4}/, 'XX$1NNNNNN-SSSS');
    shapes.set(k, (shapes.get(k) || 0) + 1);
  }
  for (const [k, v] of shapes) console.log('    not exact: ' + v + ' × "' + k + '"   e.g. ' + (results.find(x => x.page_status === 200 && !x.code_exact && (x.code_line || '(no line)').replace(/[A-Z]{2}(\s*[-\u2013]\s*)\d{6}-\d{4}/, 'XX$1NNNNNN-SSSS') === k) || {}).code);
  console.log('  pages with two products          : ' + twins.length + '   (second product\'s page works: ' + twins.filter(x => x.twin_pages.every(t => t.ok)).length + ')');
  console.log('  with a problem a shopper would hit: ' + bad.length);
  for (const x of bad) console.log('    p' + x.page + ' ' + x.code + ' (' + (x.ids[0] || '-') + '): ' + x.problems.join('; '));
  const missing = results.filter(x => !on.includes(x));
  console.log('  NOT on the site                  : ' + missing.length);
  for (const x of missing) console.log('    p' + x.page + ' ' + x.code + ': ' + (x.problems.join('; ') || x.notes.join('; ')));
}

const up = results.filter(x => x.uploaded_0922);
const upBad = up.filter(x => x.problems.length);
console.log('\n— uploaded 22 Sep: ' + up.length + ' products —');
console.log('  on the site and published      : ' + up.filter(x => x.live_link).length);
console.log('  page HTTP 200                  : ' + up.filter(x => x.page_status === 200).length);
console.log('  SKU = art code                 : ' + up.filter(x => x.sku === x.code).length);
console.log('  priced, buyable, in stock      : ' + up.filter(x => x.price > 0 && x.purchasable && x.in_stock).length);
console.log('  main image renders             : ' + up.filter(x => /^rendered/.test(x.main_image || '')).length + '   (gallery images per product: '
  + (up.length ? Math.min(...up.map(x => x.images || 0)) + '–' + Math.max(...up.map(x => x.images || 0)) : '-') + ')');
console.log('  Add to Cart button             : ' + up.filter(x => x.page_status === 200 && !x.problems.includes('no Add to Cart button')).length);
console.log('  art code visible on the page   : ' + up.filter(x => x.code_shown).length);
console.log('  workbook size among the options: ' + up.filter(x => x.size_offered === true).length + ' of ' + up.filter(x => x.size_offered !== undefined).length + ' with a size list');
console.log('  with any problem               : ' + upBad.length);
for (const x of upBad) console.log('    p' + x.page + ' ' + x.code + ' (' + (x.ids[0] || '-') + '): ' + x.problems.join('; '));

const earlier = results.filter(x => x.on_site && !x.uploaded_0922);
const eBad = earlier.filter(x => x.problems.length);
console.log('\n— already on the site before 22 Sep: ' + earlier.length + ' products (Store API checks only) —');
console.log('  with any problem: ' + eBad.length);
for (const x of eBad.slice(0, 60)) console.log('    p' + x.page + ' ' + x.code + ' (' + (x.ids[0] || '-') + '): ' + x.problems.join('; '));

const absent = results.filter(x => !x.on_site);
console.log('\n— marked not on the site: ' + absent.length + ' —');
for (const x of absent) console.log('    p' + x.page + ' ' + x.code + ': ' + (x.problems.join('; ') || x.notes.join('; ')));

const twice = results.filter(x => (x.code_products || []).length > 1);
console.log('\n— pages with more than one product on the site (the code, or the code plus A/B/C): ' + twice.length + ' —');
for (const x of twice) console.log('    p' + x.page + ' ' + x.code + ': products ' + x.code_products.map(p => p.id + ' (' + p.sku + ')').join(', '));

await browser.close();
console.log('\ndone ' + new Date().toISOString());
