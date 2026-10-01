/**
 * The cart page's "Your choices" editor, live (owner, 1 Oct: "same also this
 * page"). Read-only: a fresh guest basket, no order.
 *
 * Adds a 3x4 ft "Painting only", a 2x3 ft with bars and a frame, and a
 * download; opens the cart, checks each editor sits in its own row under its
 * line (none on the download, none in the header mini-cart), lined up with
 * the photo and the product name, styled by the editor and not the theme;
 * then clicks through kits, a colour and sizes and reads back each line's
 * price, its stored choices and the cart subtotal after the cart redraws.
 * Also checks the phone layout: the photo back at 90px beside the name, the
 * editor full width, colour chips two by two. Prints small pictures.
 *
 *   node tools/diag-cart-upgrade.mjs
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const PRODUCT = S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
let fails = 0, passes = 0;
const ok = (c, w, d = '') => { c ? passes++ : fails++; console.log((c ? '  PASS ' : '  FAIL ') + w + (d ? '  [' + d + ']' : '')); };
const money = (s) => { const all = [...String(s || '').replace(/,/g, '').matchAll(/([-−]?)\s*\$\s*(\d+(?:\.\d+)?)/g)]; if (!all.length) return NaN; const m = all[all.length - 1]; return (m[1] ? -1 : 1) * parseFloat(m[2]); };
const errs = [];
function shrink(dataUrl, targetW, quality) {
  return new Promise((resolve) => { const im = new Image(); im.onload = function () { const sc = Math.min(1, targetW / im.naturalWidth); const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * sc); c.height = Math.round(im.naturalHeight * sc); c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); resolve(c.toDataURL('image/jpeg', quality)); }; im.onerror = () => resolve(''); im.src = dataUrl; });
}
async function go(p, u) {
  for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } }
  for (let i = 0; i < 6; i++) { let bl = false; try { bl = await p.evaluate(() => /Checking your browser/.test(document.body.innerText) || !document.querySelector('header, #masthead, .site-header, footer')); } catch {} if (!bl) break; await sleep(4000); try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {} }
  await sleep(2000);
}
async function settle(p) { await sleep(700); try { await p.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay') && !document.querySelector('.af-co-up.is-busy') && !document.documentElement.classList.contains('af-co-up-saving'), { timeout: 30000 }); } catch {} await sleep(1500); }
async function picture(p, label, w) {
  const el = await p.$('.woocommerce-cart-form'); if (!el) return;
  await el.evaluate(e => e.scrollIntoView({ block: 'start' })); await sleep(600);
  const raw = await el.screenshot({ encoding: 'base64' });
  const small = await p.evaluate(shrink, 'data:image/png;base64,' + raw, w, 0.6);
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
// every line of the cart, with its editor (the row right after it) when it has one
async function state(p) {
  return p.evaluate(() => {
    const t = document.querySelector('.woocommerce-cart-form table.cart'); if (!t) return null;
    const txt = (e) => e ? e.innerText.replace(/\s+/g, ' ').trim() : '';
    const r = (e) => e ? e.getBoundingClientRect() : null;
    const lines = [...t.querySelectorAll('tr.cart_item')].map(tr => {
      const nx = tr.nextElementSibling, up = nx && nx.classList.contains('af-co-up-row') ? nx.querySelector('.af-co-up') : null;
      const dd = (cls) => { const e = tr.querySelector('dd.variation-' + cls); return e ? e.textContent.replace(/\s+/g, ' ').trim() : ''; };
      const name = tr.querySelector('td.product-name'), img = tr.querySelector('td.product-thumbnail img');
      const ctl = up && up.querySelector('.af-co-up-ctl'), lab = up && up.querySelector('.af-co-up-lab');
      return {
        size: dd('Size'), receive: dd('Youreceive'), frame: dd('FrameType'), color: dd('FrameColor'),
        price: txt(tr.querySelector('td.product-price')), subtotal: txt(tr.querySelector('td.product-subtotal')),
        editor: !!up, placed: up ? up.classList.contains('is-placed') : null,
        opts: up ? [...up.querySelectorAll('.af-co-up-opt')].map(x => (x.classList.contains('is-on') ? '*' : '') + txt(x)) : [],
        sizes: up ? [...up.querySelectorAll('.af-co-up-size')].map(x => (x.classList.contains('is-on') ? '*' : '') + txt(x)) : [],
        colors: up ? [...up.querySelectorAll('.af-co-up-color')].map(x => (x.classList.contains('is-on') ? '*' : '') + txt(x)) : [],
        ctlLeft: ctl ? Math.round(r(ctl).left) : null, nameLeft: Math.round(r(name).left), labLeft: lab ? Math.round(r(lab).left) : null, imgLeft: img ? Math.round(r(img).left) : null,
        imgW: img ? Math.round(r(img).width) : null, imgRight: img ? Math.round(r(img).right) : null,
        hidden: [...tr.querySelectorAll('dl.variation dt')].filter(d => getComputedStyle(d).display === 'none').map(d => d.textContent.trim()),
        stacked: up ? [...up.querySelectorAll('.af-co-up-size, .af-co-up-color')].every(bt => { const v = bt.querySelector('.af-co-up-v'), pr = bt.querySelector('.af-co-up-p'); const a = v.getBoundingClientRect(), c = pr.getBoundingClientRect(); return c.top >= a.bottom - 1 || c.left >= a.right - 1; }) : null,
        fits: up ? [...up.querySelectorAll('button')].every(bt => bt.scrollWidth <= bt.clientWidth + 1) : null,
        look: up ? (() => { const x = up.querySelector('.af-co-up-size') || up.querySelector('.af-co-up-opt'); const c = getComputedStyle(x); return c.textTransform + '|' + c.backgroundColor + '|' + c.borderRadius; })() : '',
        colorCols: up ? new Set([...up.querySelectorAll('.af-co-up-color')].map(x => Math.round(x.getBoundingClientRect().top))).size : 0,
        msg: up ? txt(up.querySelector('.af-co-up-msg')) : '',
      };
    });
    return {
      lines, editors: document.querySelectorAll('.af-co-up').length, outsideTable: [...document.querySelectorAll('.af-co-up')].filter(x => !x.closest('.woocommerce-cart-form table.cart tr.af-co-up-row')).length,
      style: !!document.querySelector('.woocommerce-cart-form style#af-co-up-css'), subtotal: txt(document.querySelector('.cart_totals .cart-subtotal td')),
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
}
// click a button in the editor of the n-th editor row (0 = the first canvas line)
async function choose(p, row, sel) {
  const done = await p.evaluate((row, sel) => { const r = document.querySelectorAll('.woocommerce-cart-form table.cart tr.af-co-up-row')[row]; const btn = r && r.querySelector(sel); if (!btn) return false; btn.scrollIntoView({ block: 'center' }); btn.click(); return true; }, row, sel);
  await settle(p); return done;
}

const ctx = await b.createBrowserContext(); const p = await ctx.newPage();
p.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 160)));
p.on('console', m => { if (m.type() === 'error' && !/403|404|favicon|google|facebook|pinterest|doubleclick|clarity/i.test(m.text())) errs.push('console: ' + m.text().slice(0, 160)); });
p.on('dialog', async d => { errs.push('dialog: ' + d.message()); try { await d.dismiss(); } catch {} });
await p.setViewport({ width: 1366, height: 900 });
await addFromPage(p, '3×4 ft (36×48 in)', 'painting');
await addFromPage(p, '2×3 ft (24×36 in)', 'painting_bar_frame');
await go(p, PRODUCT);
await p.evaluate(async () => { const i = document.querySelector('form.cart [name="add-to-cart"]'); await fetch('/?wc-ajax=add_to_cart', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'product_id=' + encodeURIComponent(i ? i.value : '') + '&quantity=1&af_digital=1' }); });
await go(p, S + '/cart/');
await p.evaluate(() => { const b = [...document.querySelectorAll('button, a')].find(x => /necessary only/i.test(x.textContent || '')); if (b) b.click(); });
await sleep(1200);

console.log('=== CART EDITOR: 3x4 ft Painting only, 2x3 ft with frame, a download ===');
let s = await state(p);
console.log('  start: ' + JSON.stringify(s));
const [a, f, d] = s ? s.lines : [];
ok(s && s.lines.length === 3 && a.editor && f.editor && !d.editor, 'an editor under each canvas line, none under the download', s ? s.lines.map(l => l.editor).join(',') : '');
ok(s && s.editors === 2 && s.outsideTable === 0, 'no editor anywhere else on the page (header mini-cart included)', s ? s.editors + ' / outside ' + s.outsideTable : '');
ok(s && a.placed && f.placed, 'each editor sits in its own row under its line');
ok(s && s.style, 'the editor\'s styles arrive with the cart');
ok(s && /^none\|rgb\(255, 255, 255\)\|8px/.test(a.look), 'buttons styled by the editor, not the theme (no capitals, white, 8px corners)', s ? a.look : '');
ok(s && ['Size:', 'Frame Type:', 'You receive:'].every(x => a.hidden.includes(x)) && f.hidden.includes('Frame Color:'), 'details the editor shows are not repeated', s ? a.hidden.join(' ') + ' | ' + f.hidden.join(' ') : '');
ok(s && Math.abs(a.ctlLeft - a.nameLeft) <= 2 && Math.abs(a.labLeft - a.imgLeft) <= 2, 'controls start under the product name, labels under the photo', s ? `ctl ${a.ctlLeft} name ${a.nameLeft} | lab ${a.labLeft} photo ${a.imgLeft}` : '');
ok(s && a.stacked && f.stacked && a.fits && f.fits, 'chips: name over price, nothing spills out of a button');
ok(s && /^\*Painting only/.test(a.opts[0]) && money(a.opts[0]) === 80 && money(a.opts[1]) === 128 && money(a.opts[2]) === 176, 'line 1 choices $80 / $128 / $176, Painting only marked', s ? a.opts.join(' | ') : '');
ok(s && f.colors.length === 4 && /^\*/.test(f.opts[2]), 'line 2: frame marked, four frame colours', s ? f.opts.join(' | ') + ' || ' + f.colors.join(' | ') : '');
ok(s && s.overflow <= 0, 'no sideways scroll at 1366');
await picture(p, 'start-1366', 560);

ok(await choose(p, 0, '.af-co-up-opt[data-kit="painting_bar"]'), 'clicked "Painting + structure bars + DIY kit" on line 1');
s = await state(p); console.log('  bars: ' + JSON.stringify(s.lines[0]) + ' subtotal ' + s.subtotal);
ok(money(s.lines[0].price) === 128 && /structure bars/i.test(s.lines[0].receive) && /Without Frame/i.test(s.lines[0].frame), 'line 1 $128, You receive: bars, no frame', s.lines[0].price + ' | ' + s.lines[0].receive);
ok(/^Updated: the price now includes your choice\.$/.test(s.lines[0].msg), 'says it is updated', s.lines[0].msg);
ok(s.lines[0].placed && s.lines[1].placed && s.outsideTable === 0, 'after the cart redraws, both editors are in their rows again');

ok(await choose(p, 1, '.af-co-up-color[data-color="Gold"]'), 'clicked Gold on line 2');
s = await state(p); console.log('  gold: ' + JSON.stringify(s.lines[1]) + ' subtotal ' + s.subtotal);
ok(money(s.lines[1].price) === 118 && /Gold/i.test(s.lines[1].color), 'line 2 $118 (+$10), Frame Color: Gold', s.lines[1].price + ' | ' + s.lines[1].color);

ok(await choose(p, 0, '.af-co-up-size[data-size="3×5 ft (36×60 in)"]'), 'clicked size 3x5 on line 1');
s = await state(p); console.log('  3x5: ' + JSON.stringify(s.lines[0]));
ok(money(s.lines[0].price) === 160 && /3×5/.test(s.lines[0].size) && /structure bars/i.test(s.lines[0].receive), 'line 1 3x5 with bars = $160, kit kept', s.lines[0].price + ' | ' + s.lines[0].size);

ok(await choose(p, 0, '.af-co-up-opt[data-kit="painting"]'), 'clicked "Painting only" on line 1');
ok(await choose(p, 0, '.af-co-up-size[data-size="3×4 ft (36×48 in)"]'), 'clicked size 3x4 on line 1');
s = await state(p); console.log('  back: ' + JSON.stringify(s.lines[0]) + ' subtotal ' + s.subtotal);
ok(money(s.lines[0].price) === 80 && /Painting only/i.test(s.lines[0].receive) && /3×4/.test(s.lines[0].size), 'line 1 back to 3x4 Painting only $80', s.lines[0].price);
const sum = s.lines.reduce((t, l) => t + money(l.subtotal), 0);
ok(Math.abs(sum - money(s.subtotal)) < 0.015, 'cart subtotal = the lines added up', s.subtotal + ' vs ' + sum.toFixed(2));

await p.setViewport({ width: 390, height: 844 }); await sleep(1500);
s = await state(p); console.log('  390: ' + JSON.stringify(s.lines.map(l => ({ imgW: l.imgW, imgRight: l.imgRight, nameLeft: l.nameLeft, colorRows: l.colorCols, fits: l.fits }))));
ok(s.lines.every(l => l.imgW <= 92 && l.imgRight <= l.nameLeft), 'phone: each photo 90px, beside the name (not over it)', s.lines.map(l => l.imgW + '/' + l.imgRight + '<' + l.nameLeft).join(' '));
ok(s.lines[1].colorRows === 2 && s.lines.every(l => l.fits !== false), 'phone: colour chips two by two, nothing spills', 'rows ' + s.lines[1].colorRows);
ok(s.overflow <= 0, 'phone: no sideways scroll');
await picture(p, 'phone-390', 340);

console.log('\n=== console / page errors ===\n' + ([...new Set(errs)].join('\n') || 'none'));
console.log(`\nSUMMARY cart-upgrade: ${passes} passed, ${fails} failed`);
await b.close();
