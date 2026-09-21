// Verifying someone else's audit, claim by claim.
//
// A 33-finding report was handed to us for checking. Roughly half of it is
// decidable from this repository — a string is in kit-options.php or it is
// not. The other half is only decidable in a browser on the live shop, and
// that is what this file does: it re-measures each live claim the way the
// report says it was measured, and prints CONFIRMED, CONTRADICTED, PARTLY or
// NO DATA against the claim as written.
//
// Two rules were kept while writing it. Measure the thing the claim actually
// says — "no focus ring" is not the same statement as "outline-style is none",
// and the report conflates them. And never let a checked box stand in for a
// measurement: every verdict below carries the number it was decided on, so a
// reader can disagree with the verdict without re-running the script.
//
// SAFETY. Live shop. The same NEVER_SEND wire guard qa-personas.mjs uses: no
// order, no email, no newsletter, no gift card. One item is added to a cart
// (a session write, not a ledger write) because three of the claims are about
// the cart and the checkout, and it is removed again at the end.
//
// Run: node tools/verify-audit.mjs [url]

import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const YES = 'CONFIRMED', NO = 'CONTRADICTED', PART = 'PARTLY', NA = 'NO DATA';
const rows = [];
const say = (id, verdict, claim, measured) => {
  rows.push({ id, verdict, claim, measured: String(measured).replace(/\s+/g, ' ').slice(0, 300) });
  console.log(`  ${verdict.padEnd(12)} ${id.padEnd(5)} ${measured}`.slice(0, 200));
};

const browser = await chromium.launch({ headless: true });

async function ctxFor(opts) {
  const ctx = await browser.newContext({ ...opts, ignoreHTTPSErrors: true });
  await ctx.route('**/*', route => {
    const req = route.request();
    const body = (req.postData() || '') + ' ' + req.url();
    if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json',
        body: '{"success":true,"data":{"message":"(intercepted by QA — not sent)"}}' });
    }
    return route.continue();
  });
  return ctx;
}

// Does the shop answer this runner at all? The first run spent 25 minutes
// timing out on every page and then printed verdicts, one of which was wrong
// because of it. A run that cannot reach the site must say so and stop, not
// grind through 33 checks converting timeouts into findings.
async function reachable() {
  const ctx = await ctxFor({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  const tries = [];
  for (let i = 0; i < 3; i++) {
    const r = await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(e => ({ err: String(e.message).slice(0, 80) }));
    if (r && r.status && r.status() < 400) { tries.push(`HTTP ${r.status()}`); await ctx.close(); return { ok: true, tries }; }
    tries.push(r && r.err ? r.err : (r && r.status ? `HTTP ${r.status()}` : 'no response'));
    await page.waitForTimeout(5000);
  }
  await ctx.close();
  return { ok: false, tries };
}

const go = async (page, path, wait = 1500) => {
  const url = path.startsWith('http') ? path : SITE + path;
  const r = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

// Put the shop back the way we found it.
//
// Clicking a.remove is what run 5 did, and at 375px it does not land — the
// remove control is laid out differently on a phone, so the click missed and
// the run finished saying "remove it by hand". WooCommerce puts the whole
// removal in the link's href, nonce and all, so following it is the same
// action without depending on where the theme drew the X. Three attempts,
// and the answer is whatever /cart/ says at the end rather than whether a
// click was dispatched.
async function emptyCart(page) {
  for (let i = 0; i < 3; i++) {
    await go(page, '/cart/', 2000);
    if (await page.evaluate(() => /your cart is currently empty/i.test(document.body.innerText))) return true;
    const href = await page.evaluate(() => {
      const a = document.querySelector('a[href*="remove_item"], a.remove, .product-remove a');
      return a ? a.href : null;
    });
    if (href && /remove_item/.test(href)) { await go(page, href, 2000); continue; }
    await page.click('a.remove, .product-remove a', { force: true, timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(2000);
  }
  await go(page, '/cart/', 2000);
  return page.evaluate(() => /your cart is currently empty/i.test(document.body.innerText));
}

// contrast, computed the way the report says it computed contrast. Defined as
// a string and re-declared inside each page.evaluate rather than eval()'d: a
// shop with a Content-Security-Policy would refuse eval and the measurement
// would come back as a crash instead of a number.
const RATIO = `
  const _lum = c => { const [r,g,b] = c.map(v => { v/=255; return v <= .03928 ? v/12.92 : Math.pow((v+.055)/1.055, 2.4); });
    return .2126*r + .7152*g + .0722*b; };
  const _parse = s => (s.match(/[\\d.]+/g) || []).slice(0,3).map(Number);
  const ratio = (fg, bg) => { const L1 = _lum(_parse(fg)), L2 = _lum(_parse(bg));
    return Math.round(((Math.max(L1,L2)+.05)/(Math.min(L1,L2)+.05)) * 100) / 100; };
`;

// ── 1. The home page: pop-up, focus, weight, leftovers ─────────────────────
const reach = await reachable();
if (!reach.ok) {
  console.log('\nThe shop did not answer this runner: ' + reach.tries.join(' | '));
  console.log('Nothing was measured. Every live claim stays unverified — a timeout is not a finding.');
  await browser.close();
  process.exit(0);
}
console.log(`\nthe shop answers (${reach.tries.join(' | ')})`);

try {
  console.log('\n— home page, desktop 1440 —');
  const ctx = await ctxFor({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const bad = [], vids = new Set();
  let bytes = 0, reqs = 0;
  page.on('response', async r => {
    reqs++;
    if (r.status() >= 400) { bad.push(`${r.status()} ${r.url().split('/').pop().slice(0, 48)}`); if (/\.mp4/i.test(r.url())) vids.add(r.url().split('/').pop()); }
  });
  await go(page, '/', 4000);

  const html = await page.content();
  bytes = Buffer.byteLength(html);

  // H-05 — the pop-up
  const pop = await page.evaluate(() => {
    const isCookie = el => /cookie|privacy|consent|gdpr/i.test((el.className || '') + ' ' + (el.id || '')) ||
                            /we value your privacy|we use cookies/i.test((el.innerText || '').slice(0, 120));
    const cands = [...document.querySelectorAll('div,section,aside,dialog')].filter(el => {
      if (isCookie(el)) return false;
      const s = getComputedStyle(el);
      if (!['fixed', 'absolute'].includes(s.position)) return false;
      if (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity || '1') === 0) return false;
      const r = el.getBoundingClientRect();
      return r.width > innerWidth * .35 && r.height > innerHeight * .3 && (parseInt(s.zIndex) || 0) >= 100;
    });
    if (!cands.length) return null;
    // the dialog rather than its backdrop: the smallest box that holds a control
    const withCtl = cands.filter(el => el.querySelector('input,button,a'));
    const el = (withCtl.length ? withCtl : cands).sort((a, b) =>
      (a.getBoundingClientRect().width * a.getBoundingClientRect().height) -
      (b.getBoundingClientRect().width * b.getBoundingClientRect().height))[0];
    el.setAttribute('data-afqa-pop', '1');
    const focusable = el.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
    const closers = [...el.querySelectorAll('*')].filter(n =>
      /close|dismiss|×|✕|✖/i.test((n.className || '') + ' ' + (n.getAttribute('aria-label') || '') + ' ' + (n.textContent || '').trim().slice(0, 3)));
    const c = closers[0] || null;
    const cr = c ? c.getBoundingClientRect() : null;
    return {
      role: el.getAttribute('role') || 'none', modal: el.getAttribute('aria-modal') || 'none',
      focusable: focusable.length,
      close: c ? `<${c.tagName.toLowerCase()} class="${(c.className || '').toString().slice(0, 30)}"> ${Math.round(cr.width)}x${Math.round(cr.height)} tabindex=${c.getAttribute('tabindex') ?? 'unset'} aria-label=${c.getAttribute('aria-label') || 'none'}` : 'no close control found',
      bodyOverflow: getComputedStyle(document.body).overflow,
      text: (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 220),
    };
  });

  if (!pop) {
    say('H-05', NO, 'a pop-up covers the home page on load', 'no fixed overlay over 35% of the viewport was present 4s after load');
    say('H-06', NA, 'four discount numbers in the pop-up', 'no pop-up appeared to read them from');
  } else {
    say('H-05', PART, 'pop-up traps keyboard users, hard to close', `${pop.focusable} focusable inside · role=${pop.role} aria-modal=${pop.modal} · close: ${pop.close} · body overflow=${pop.bodyOverflow}`);
    await page.keyboard.press('Escape'); await page.waitForTimeout(700);
    const afterEsc = await page.evaluate(() => { const e = document.querySelector('[data-afqa-pop]'); if (!e) return 'gone from DOM'; const s = getComputedStyle(e); return (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity || '1') === 0) ? 'closed' : 'still open'; });
    await page.mouse.click(6, 6); await page.waitForTimeout(700);
    const afterBack = await page.evaluate(() => { const e = document.querySelector('[data-afqa-pop]'); if (!e) return 'gone from DOM'; const s = getComputedStyle(e); return (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity || '1') === 0) ? 'closed' : 'still open'; });
    say('H-05b', afterEsc === 'still open' && afterBack === 'still open' ? YES : PART, 'Escape and backdrop click do nothing', `Escape → ${afterEsc} · backdrop click → ${afterBack}`);
    say('H-06', NA, 'pop-up copy', `verbatim: "${pop.text}"`);
  }

  // H-04 — does anything at all show where the keyboard is?
  await page.keyboard.press('Escape');
  let reached = 0, changed = 0, samples = [];
  for (let i = 0; i < 30; i++) {
    await page.keyboard.press('Tab');
    const st = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el || el === document.body || el === document.documentElement) return null;
      const read = e => { const s = getComputedStyle(e); return { o: s.outlineStyle, w: s.outlineWidth, c: s.outlineColor, sh: s.boxShadow, b: s.border, bg: s.backgroundColor, tx: s.textDecorationLine }; };
      const on = read(el);
      el.blur();
      const off = read(el);
      el.focus();
      const diff = Object.keys(on).filter(k => on[k] !== off[k]);
      return { tag: el.tagName.toLowerCase(), txt: (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 24), on, diff };
    });
    if (!st) continue;
    reached++;
    if (st.diff.length) { changed++; if (samples.length < 3) samples.push(`${st.tag} "${st.txt}" → ${st.diff.join(',')}`); }
    else if (samples.length < 3 && changed === 0) samples.push(`${st.tag} "${st.txt}" → outline:${st.on.c} ${st.on.o} ${st.on.w}, no change on focus`);
  }
  say('H-04', changed === 0 ? YES : (changed < reached / 2 ? PART : NO),
      'nothing shows where the keyboard is; 0 of 40 elements had a visible focus state',
      `${changed} of ${reached} tab stops changed any visual property when focused. e.g. ${samples.join(' | ')}`);
  const skip = await page.locator('a[href^="#"]').filter({ hasText: /skip/i }).count();
  say('H-04b', skip ? NO : YES, 'no skip link', skip ? `${skip} skip link(s) present` : 'no skip-to-content link found');

  // the claimed one-line fix: is a gold focus ring actually declared anywhere?
  const declared = await page.evaluate(() => {
    let hits = [];
    for (const sheet of document.styleSheets) {
      let rules; try { rules = sheet.cssRules; } catch { continue; }
      for (const r of rules || []) {
        const t = r.cssText || '';
        if (/:focus(-visible)?[^{]*\{[^}]*outline/i.test(t) && !/outline\s*:\s*(none|0)/i.test(t)) hits.push(t.slice(0, 110));
      }
    }
    return hits.slice(0, 4);
  });
  say('H-04c', declared.length ? PART : NO, 'a gold focus ring is DEFINED and then switched off — flip outline-style to solid',
      declared.length ? `${declared.length} :focus rule(s) do declare an outline: ${declared.join(' ~ ')}` : 'no :focus/:focus-visible rule declares an outline anywhere in the loaded CSS — nothing is being switched off; a ring would have to be added');

  // L-01/L-02/L-03/M-09 — the cheap counts
  const counts = await page.evaluate(() => ({
    imgs: document.images.length,
    noAlt: [...document.images].filter(i => !i.hasAttribute('alt')).length,
    emptyAlt: [...document.images].filter(i => i.getAttribute('alt') === '').length,
    hashLinks: document.querySelectorAll('a[href="#"]').length,
    links: document.querySelectorAll('a,button').length,
    nodes: document.getElementsByTagName('*').length,
    scripts: document.querySelectorAll('script').length,
    sheets: document.querySelectorAll('link[rel="stylesheet"]').length,
    tiny: [...document.querySelectorAll('a,button,[role="button"]')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && (r.width < 44 || r.height < 44); }).length,
    videoErr: [...document.querySelectorAll('video')].filter(v => v.error || (v.networkState === 3)).length,
  }));
  say('M-09', PART, 'home page: 1,047 KB HTML · 8,060 DOM nodes · 205 scripts · 162 stylesheets',
      `HTML ${Math.round(bytes / 1024)} KB · ${counts.nodes} DOM nodes · ${counts.scripts} script tags · ${counts.sheets} stylesheets · ${reqs} requests`);
  say('L-01', bad.length ? YES : NO, 'eight 404s on every home page load, five missing videos',
      bad.length ? `${bad.length} failed request(s): ${[...new Set(bad)].slice(0, 6).join(' | ')} · <video> in error state: ${counts.videoErr}` : 'no request returned 4xx/5xx on this load');
  say('L-02', counts.noAlt ? YES : NO, 'home page images: 339 · no alt at all 60 · alt="" 184',
      `images ${counts.imgs} · missing alt ${counts.noAlt} · alt="" ${counts.emptyAlt}`);
  say('L-03', counts.hashLinks ? YES : NO, 'href="#" 83 · targets under 44px 347 · links and buttons 1,250',
      `href="#" ${counts.hashLinks} · under 44px ${counts.tiny} · links+buttons ${counts.links}`);

  // L-06 — the old contact details, still in the markup
  const leftovers = [];
  if (/farmergmail/i.test(html)) leftovers.push('info@farmergmail.com in markup');
  if (/8107236836/.test(html)) leftovers.push('+91 8107236836 in markup');
  if (/ht_ctc_chat_var/.test(html)) leftovers.push('ht_ctc_chat_var (Click-to-Chat) loaded');
  const waNum = (html.match(/wa\.me\/(\d+)/) || [])[1] || 'none';
  say('L-06', leftovers.length ? YES : NO, 'old Indian contact details still in the markup and in a live plugin',
      leftovers.length ? `${leftovers.join(' · ')} · visible WhatsApp number ${waNum}` : `neither the old email nor the old number appears in the served HTML (WhatsApp: ${waNum})`);

  // C-02, part one — where is free shipping promised?
  const freeText = await page.evaluate(() => {
    const out = [];
    for (const el of document.querySelectorAll('h1,h2,h3,h4,p,span,strong,div,li')) {
      const t = (el.childNodes.length && [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join(' ') || '').trim();
      if (/free\s*(and\s*fast\s*)?(ship|deliver)/i.test(t)) out.push(t.replace(/\s+/g, ' ').slice(0, 90));
    }
    return [...new Set(out)].slice(0, 6);
  });
  say('C-02a', freeText.length ? YES : NO, 'the home page advertises Free Shipping',
      freeText.length ? freeText.join(' | ') : 'no visible text on the home page promises free shipping or free delivery');

  await ctx.close();
} catch (e) { say('CRASH', NA, 'this section stopped before it finished', String(e.message).slice(0, 160)); }

// ── 1b. Is the CSS the browser gets the CSS this repository shipped? ───────
//
// Three fixes landed on main and deployed, and all three measured unchanged:
// the focus ring, the checkout error colour and the Add to Cart label. What
// they have in common is that they are the only CSS among them — the PHP and
// JS in the same deploy took immediately. That is a delivery question, not a
// correctness one, and guessing at it (optimiser? bundle cache? filemtime?)
// would be guessing. So: list the stylesheets the page actually loads, fetch
// each one the browser is pointed at, and look for the rules by hand.
try {
  console.log('\n— is the shipped CSS the served CSS —');
  const ctx = await ctxFor({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await go(page, '/', 3000);
  const css = await page.evaluate(async () => {
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')].map(l => l.href);
    const ours  = links.filter(h => /postero|child|custom\.css|checkout\.css/i.test(h));
    const rows  = [];
    for (const href of ours.slice(0, 8)) {
      try {
        const r = await fetch(href, { cache: 'reload' });
        const t = (await r.text()).replace(/\s+/g, ' ');
        rows.push(href.split('/').slice(-1)[0].slice(0, 46) + ' → HTTP ' + r.status + ' ' + t.length + 'b'
          + ' focusRing=' + /:focus-visible *\{ *outline: *3px solid #c9a84c/i.test(t)
          + ' errStrong=' + /woocommerce-error strong/i.test(t));
      } catch (e) { rows.push(href.split('/').slice(-1)[0].slice(0, 46) + ' → fetch failed'); }
    }
    // an optimiser often drops the file and inlines or bundles it instead
    const inline = [...document.querySelectorAll('style')].map(s => s.textContent).join(' ').replace(/\s+/g, ' ');
    return {
      sheets: links.length, ours: ours.length, rows,
      inlineFocusRing: /:focus-visible *\{ *outline: *3px solid #c9a84c/i.test(inline),
      // When none of ours matched, the useful thing is what IS there — the
      // first run of this printed an empty list and so could not name the
      // optimiser it had just proved was in the way.
      names: links.slice(0, 10).map(h => h.replace(/^https?:\/\/[^/]+/, '').slice(0, 52)),
    };
  });
  say('DEPLOY-CSS', css.rows.some(r => /focusRing=true/.test(r)) || css.inlineFocusRing ? YES : NO,
      'the CSS this repository shipped is the CSS the browser is served',
      `${css.sheets} stylesheets, ${css.ours} ours${css.ours ? ': ' + css.rows.join(' | ') : ' — none of our files is linked; the page is served: ' + css.names.join(' | ')} · inlined focus ring: ${css.inlineFocusRing}`);
  await ctx.close();
} catch (e) { say('DEPLOY-CSS', NA, 'CSS delivery check', String(e.message).slice(0, 140)); }

// ── 2. Search: the claim that settles which code the site is running ───────
try {
  console.log('\n— search —');
  const ctx = await ctxFor({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  const counts = {};
  for (const term of ['krishan', 'krishna', 'ganesh', 'ganesha', 'dinosaur']) {
    await go(page, `/?s=${term}&post_type=product`, 1500);
    counts[term] = await page.evaluate(() => document.querySelectorAll('li.product, .product.type-product').length);
  }
  say('M-03a', counts.krishan === 0 ? YES : NO, '"krishan" returns 0, "ganesh"/"ganesha" return 14',
      Object.entries(counts).map(([k, v]) => `${k}=${v}`).join(' · '));

  await go(page, '/?s=zzqqxnothing&post_type=product', 1500);
  const empty = await page.evaluate(() => ({
    woo: /No products were found matching your selection/i.test(document.body.innerText),
    theme: /Nothing hanging under/i.test(document.body.innerText),
    form: document.querySelectorAll('input[type="search"], input[name="s"]').length,
    pills: document.querySelectorAll('.af-sr-pills a').length,
    h1: (document.querySelector('h1') || {}).innerText || '(no h1)',
    text: document.body.innerText.replace(/\s+/g, ' ').trim().slice(0, 160),
  }));
  say('M-03b', empty.woo && !empty.form ? YES : NO,
      'the empty search page says "No products were found matching your selection." and nothing else — no search box, no suggestions',
      `Woo wording: ${empty.woo} · theme wording: ${empty.theme} · search inputs on page: ${empty.form} · category pills: ${empty.pills} · h1: "${String(empty.h1).slice(0, 40)}"`);
  await ctx.close();
} catch (e) { say('CRASH', NA, 'this section stopped before it finished', String(e.message).slice(0, 160)); }

// ── 3. A product page ───────────────────────────────────────────────────────
let productUrl = null, brochureUrl = null, productId = null;
try {
  console.log('\n— product page —');
  const ctx = await ctxFor({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  await go(page, '/shop/?orderby=price', 2000);
  const sorted = await page.evaluate(() => {
    const cards = [...document.querySelectorAll('li.product, .product.type-product')];
    const por = cards.filter(c => /price on request/i.test(c.innerText)).length;
    return { total: cards.length, por, first: cards.slice(0, 6).map(c => (c.innerText.match(/Price on request|\$[\d,.]+/) || ['?'])[0]).join(', ') };
  });
  say('M-02', sorted.por > sorted.total / 2 ? YES : (sorted.por ? PART : NO),
      'sorting by price opens with 35 of 48 unpriced items, ~3 pages before a real price',
      `page 1 of ?orderby=price: ${sorted.por} of ${sorted.total} are "Price on request" · first six: ${sorted.first}`);

  // A canvas, not an accessory. The first run took its product from
  // ?orderby=price, whose entire first page is "Price on request" — so it
  // landed on a quote-only item with no frames, no swatches and no Add to
  // Cart, then reported the absent controls as CONTRADICTED and left the
  // cart empty, voiding every cart and checkout check below it. The product
  // has to be one a shopper could buy, and the run has to say so.
  await go(page, '/shop/', 2500);
  const picks = await page.evaluate(() => [...document.querySelectorAll('li.product, .product.type-product')]
    .filter(c => !/price on request/i.test(c.innerText) && /\$[\d,.]+/.test(c.innerText))
    .map(c => { const a = c.querySelector('a[href*="/product/"]'); return a ? a.href : null; })
    .filter(Boolean).slice(0, 4));
  for (const cand of picks) {
    await go(page, cand, 2500);
    const buyable = await page.evaluate(() =>
      !!document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]'));
    if (buyable) { productUrl = cand; break; }
  }
  if (!productUrl) { say('PROD', NA, 'a purchasable product page', `none of the ${picks.length} priced cards on /shop/ had an Add to Cart button`); }
  else {
    say('PROD', NA, 'the product the rest of this section was measured on', productUrl);
    await go(page, productUrl, 2500);
    const p = await page.evaluate(() => {
      const t = document.body.innerText;
      const chips = [...document.querySelectorAll('[data-type="frame"]')].map(c => `${(c.dataset.val || c.textContent).trim()}${c.disabled || /out of stock/i.test(c.innerText) ? ' [OUT OF STOCK]' : ''}`);
      const sizes = [...document.querySelectorAll('[data-type="size"]')].map(c => (c.dataset.val || c.textContent).trim());
      const colors = [...document.querySelectorAll('[data-type="color"]')].map(c => `${(c.dataset.val || '').trim()}${/\+\$/.test(c.innerText) ? ' (fee shown)' : ' (no fee shown)'}`);
      const attrs = [...document.querySelectorAll('.woocommerce-product-attributes tr, table.shop_attributes tr')]
        .map(r => r.innerText.replace(/\s+/g, ' ').trim()).slice(0, 12);
      return {
        kitNote: /while we finalise pricing|while we finalize pricing/i.test(t),
        viewers: (t.match(/(\d+)\s+(people|persons?)\s+(are\s+)?(viewing|watching)/i) || [])[0] || 'none',
        delivery: (t.match(/Estimated delivery[^\n]{0,60}/i) || [])[0] || 'none',
        headerPrice: (document.querySelector('.summary .price, .entry-summary .price, p.price') || {}).innerText || 'none',
        livePrice: (document.querySelector('#af-live-price') || {}).innerText || 'none',
        chips, sizes, colors, attrs,
        brochure: (([...document.querySelectorAll('a[href$=".pdf"]')][0] || {}).href) || 'none',
      };
    });
    say('H-03', p.kitNote ? YES : NO, '"Parts are included at no extra charge while we finalise pricing" is live on product pages',
        p.kitNote ? 'the string is on the product page' : `the string is not on ${productUrl}`);
    say('H-02', !p.chips.length ? NA : (/OUT OF STOCK/.test(p.chips.join(' ')) ? YES : NO),
        'Floating and Fibre frames are out of stock store-wide; only Aluminium can be bought',
        p.chips.length ? p.chips.join(' · ') : 'no frame chips on this product — nothing measured');
    say('M-10', p.viewers !== 'none' ? YES : NO, '"11 people are viewing this product right now" under every title', p.viewers);
    say('C-05a', p.delivery !== 'none' ? YES : NO, 'the product page prints a hard-coded delivery date range', p.delivery);
    say('M-04a', PART, 'the spec table lists 4×5 / 4×6 ft and White / Wooden that the selector does not offer',
        `selector sizes: ${p.sizes.join(', ') || 'none found'} || spec table: ${p.attrs.filter(a => /size|colou?r|frame/i.test(a)).join(' ~ ') || 'no attribute table'}`);
    say('M-04b', !p.colors.length ? NA : (/no fee shown/.test(p.colors.join(' ')) ? YES : NO),
        'Gold and Rose Gold cost +$10 but the product page shows no surcharge',
        p.colors.join(' · ') || 'no colour swatches on this product — nothing measured');
    brochureUrl = /\.pdf/i.test(p.brochure) ? p.brochure : null;
    say('M-06a', brochureUrl ? YES : NO, 'every product links the same brochure PDF', p.brochure.split('/').pop());

    // M-01 — two prices at once, after changing the size
    const before = { header: p.headerPrice.replace(/\s+/g, ' ').trim(), live: p.livePrice.trim() };
    // The size control is a <select> on this theme, not a chip. Clicking a
    // <select> opens it and changes nothing, which is why run 4 reported the
    // panel and header both unmoved and decided nothing: set the value and
    // fire the change event the theme's handler listens for.
    const switched = await page.evaluate(() => {
      const sel = document.querySelector('select[data-type="size"]');
      if (sel) {
        const opt = [...sel.options].find(o => /3×5|3x5/.test(o.value + ' ' + o.textContent));
        if (!opt) return false;
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        return opt.value.trim() || opt.textContent.trim();
      }
      const want = [...document.querySelectorAll('[data-type="size"]')]
        .filter(e => e.tagName !== 'SELECT')
        .find(e => /3×5|3x5/.test(e.dataset.val || e.textContent));
      if (!want) return false;
      want.click(); return (want.dataset.val || want.textContent).trim();
    });
    await page.waitForTimeout(1200);
    const after = await page.evaluate(() => ({
      header: (((document.querySelector('.summary .price, .entry-summary .price, p.price') || {}).innerText || '')
                 .match(/Current price is: (\$[\d,.]+)/) || [])[1] ||
              ((document.querySelector('.summary .price, .entry-summary .price, p.price') || {}).innerText || '').replace(/\s+/g, ' ').trim(),
      live: ((document.querySelector('#af-live-price') || {}).innerText || '').trim(),
    }));
    say('M-01', switched && after.live && after.header && after.live.replace(/\s/g, '') !== after.header.replace(/\s/g, '') ? YES : (switched ? NO : NA),
        'after changing size, the panel says $100 while the header still says $80',
        switched ? `size → ${switched} · panel ${before.live} → ${after.live} · header ${before.header} → ${after.header}` : 'no 3×5 size control on this product');

    // M-07 — the Add to Cart label's contrast
    const contrast = await page.evaluate(new Function(RATIO + `
      const btn = document.querySelector('.single_add_to_cart_button, button[name="add-to-cart"]');
      if (!btn) return null;
      const s = getComputedStyle(btn);
      let bgEl = btn, bg = s.backgroundColor;
      while (bg === 'rgba(0, 0, 0, 0)' && bgEl.parentElement) { bgEl = bgEl.parentElement; bg = getComputedStyle(bgEl).backgroundColor; }
      return { label: btn.innerText.trim().slice(0, 20), fg: s.color, bg, r: ratio(s.color, bg) };
    `));
    say('M-07', contrast ? (contrast.r < 4.5 ? YES : NO) : NA, 'ADD TO CART label measures 2.74:1 (needs 4.5:1)',
        contrast ? `"${contrast.label}" ${contrast.fg} on ${contrast.bg} = ${contrast.r}:1` : 'no add-to-cart button on this product');
  }
  await ctx.close();
} catch (e) { say('CRASH', NA, 'this section stopped before it finished', String(e.message).slice(0, 160)); }

// ── 4. Cart and checkout, at the widths the report names ───────────────────
try {
  console.log('\n— cart and checkout —');
  const ctx = await ctxFor({ ...devices['iPhone 12'], viewport: { width: 375, height: 812 } });
  const page = await ctx.newPage();

  let added = false;
  if (productUrl) {
    await go(page, productUrl, 1500);
    const pid = await page.evaluate(() => {
      const b = document.querySelector('button[name="add-to-cart"], .single_add_to_cart_button');
      return (b && (b.value || b.getAttribute('value'))) || (document.body.className.match(/postid-(\d+)/) || [])[1] || null;
    });
    if (pid) { productId = pid; await go(page, `/?add-to-cart=${pid}`, 2500); added = true; }
  }

  await go(page, '/cart/', 2500);
  const cart = await page.evaluate(() => {
    const t = document.body.innerText;
    return {
      empty: /your cart is currently empty/i.test(t),
      dest: (t.match(/Shipping to[^\n]{0,60}/i) || [])[0] || 'none',
      country: ((document.querySelector('#calc_shipping_country') || {}).value) || 'not on page',
      state: ((document.querySelector('#calc_shipping_state') || {}).value) || 'not on page',
      ship: (t.match(/(Delivery|Shipping)[^\n]{0,70}\$[\d,.]+/i) || [])[0] || 'none',
      notices: document.querySelectorAll('.woocommerce-notices-wrapper').length,
      coupon: document.querySelectorAll('#coupon_code, input[name="coupon_code"]').length,
      recs: [...document.querySelectorAll('.cross-sells li.product, .related li.product, .up-sells li.product')].map(l => l.innerText.split('\n')[0].trim().slice(0, 30)).slice(0, 8),
      payClaim: /cards, PayPal/i.test(t),
    };
  });
  say('CART', added && !cart.empty ? YES : NA, 'a test item is in the cart', cart.empty ? 'cart is empty — the cart/checkout claims below could not be measured' : 'one item added for measurement');
  const cartHas = added && !cart.empty;
  say('C-04', !cartHas ? NA : (/rajasthan|india/i.test(cart.dest + cart.country + cart.state) ? YES : NO),
      'the default shipping destination is Rajasthan, India (calc_shipping_country=IN, state=RJ)',
      `cart says "${cart.dest}" · country field ${cart.country} · state field ${cart.state}`);
  say('C-02b', /\$/.test(cart.ship) ? YES : NA, 'delivery is charged, not free', `${cart.ship}`);
  say('M-05', !cartHas ? NA : (cart.recs.length ? PART : NO), 'the cart recommends jute tote bags, business cards, an exhibition booth',
      cart.recs.length ? cart.recs.join(' · ') : 'no cross-sell or related products rendered on the cart');
  say('H-09b', !cartHas ? NA : (cart.payClaim ? YES : NO), 'the cart page claims "cards, PayPal & more" while PayPal is not offered',
      cart.payClaim ? 'the claim is on the cart page' : 'the phrase is not on the cart page');

  // H-01 — a coupon that does not exist: does anything at all appear?
  if (cart.coupon) {
    await page.fill('#coupon_code, input[name="coupon_code"]', 'af-qa-no-such-code').catch(() => {});
    await page.click('button[name="apply_coupon"], .coupon button').catch(() => {});
    await page.waitForTimeout(3500);
    const notice = await page.evaluate(() => {
      const n = [...document.querySelectorAll('.woocommerce-error,.woocommerce-message,.woocommerce-info,.wc-block-components-notice-banner')]
        .map(e => e.innerText.replace(/\s+/g, ' ').trim()).filter(Boolean);
      return n.slice(0, 2);
    });
    say('H-01', notice.length ? NO : YES, 'a bad coupon fails silently — the cart template never renders the notice area',
        notice.length ? `a notice did render: "${notice.join(' | ').slice(0, 120)}"` : 'nothing rendered after applying a coupon that does not exist');
  } else say('H-01', NA, 'coupon failures are silent', 'no coupon field on the cart page');

  // C-01 — the clipped total, at each width the report names
  for (const w of [375, 414]) {
    await page.setViewportSize({ width: w, height: 900 });
    await go(page, '/checkout/', 3000);
    const clip = await page.evaluate(() => {
      const t = document.querySelector('.woocommerce-checkout-review-order-table');
      if (!t) return null;
      const parent = t.parentElement;
      const tr = t.getBoundingClientRect(), pr = parent.getBoundingClientRect();
      const totalCell = t.querySelector('.order-total td, tr.order-total td');
      const cr = totalCell ? totalCell.getBoundingClientRect() : null;
      return {
        table: Math.round(tr.width), col: Math.round(pr.width),
        scroll: Math.round(t.scrollWidth), overflowX: getComputedStyle(parent).overflowX,
        past: cr ? Math.round(cr.right - innerWidth) : null,
        shown: totalCell ? totalCell.innerText.replace(/\s+/g, ' ').trim() : 'no total row',
        doc: Math.round(document.documentElement.scrollWidth - innerWidth),
      };
    });
    if (!clip) { say(`C-01@${w}`, NA, 'the checkout order total is cut off', 'no review-order table on the checkout page'); continue; }
    const cut = (clip.past !== null && clip.past > 0) || clip.scroll > clip.col + 2;
    say(`C-01@${w}`, cut ? YES : NO, `at ${w}px the order total runs off the edge`,
        `table ${clip.table}px (scrollWidth ${clip.scroll}) in a ${clip.col}px column, parent overflow-x:${clip.overflowX} · total cell ends ${clip.past}px past the viewport · reads "${clip.shown}" · page scrolls ${clip.doc}px sideways`);
  }

  // C-03 — what the stylesheet does to an error panel's field names
  const err = await page.evaluate(new Function(RATIO + `
    const host = document.querySelector('form.checkout, .woocommerce, main, body');
    const ul = document.createElement('ul');
    ul.className = 'woocommerce-error'; ul.setAttribute('role', 'alert');
    ul.innerHTML = '<li><strong>Billing First name</strong> is a required field.</li>';
    host.prepend(ul);
    const li = ul.querySelector('li'), strong = ul.querySelector('strong');
    const ss = getComputedStyle(strong), ls = getComputedStyle(li);
    let bgEl = ul, bg = getComputedStyle(ul).backgroundColor;
    while (bg === 'rgba(0, 0, 0, 0)' && bgEl.parentElement) { bgEl = bgEl.parentElement; bg = getComputedStyle(bgEl).backgroundColor; }
    const out = { strongColor: ss.color, restColor: ls.color, bg, strongR: ratio(ss.color, bg), restR: ratio(ls.color, bg) };
    ul.remove();
    return out;
  `));
  say('C-03', !cartHas ? NA : (err.strongR < 2 ? YES : NO), 'the field name in a checkout error is white on pink at 1.09:1',
      `.woocommerce-error strong ${err.strongColor} on ${err.bg} = ${err.strongR}:1 · rest of the line ${err.restColor} = ${err.restR}:1`);

  // H-08, H-09 — gift cards and payment methods
  const pay = await page.evaluate(() => ({
    gift: [...document.querySelectorAll('input')].filter(i => /TAF-|gift/i.test((i.placeholder || '') + (i.name || ''))).map(i => i.placeholder || i.name).slice(0, 4),
    square: /square gift card/i.test(document.body.innerText),
    methods: [...document.querySelectorAll('.wc_payment_method label, li.wc_payment_method')].map(l => l.innerText.replace(/\s+/g, ' ').trim().slice(0, 40)).filter(Boolean).slice(0, 8),
    terms: document.querySelectorAll('input[name="terms"], .woocommerce-terms-and-conditions-wrapper').length,
  }));
  say('H-08', !cartHas ? NA : (pay.gift.length > 1 || (pay.gift.length && pay.square) ? YES : NO), 'two gift-card fields in the same checkout summary',
      `gift inputs: ${pay.gift.join(' | ') || 'none'} · "Square Gift Card" text present: ${pay.square}`);
  say('H-09a', pay.methods.length ? PART : NA, 'payment options are Zelle, Square card, cash on delivery; no wallets; no terms checkbox',
      `offered: ${pay.methods.join(' · ') || 'none rendered'} · terms checkbox: ${pay.terms}`);

  // put the shop back the way we found it
  const left = await emptyCart(page);
  say('CLEANUP', left ? YES : NO, 'the test item was removed again',
      left ? 'cart is empty' : 'cart still holds an item — remove it by hand');
  await ctx.close();
} catch (e) { say('CRASH', NA, 'this section stopped before it finished', String(e.message).slice(0, 160)); }

// ── 5. The pages the report says are duplicated, and the two logins ────────
try {
  console.log('\n— odds and ends —');
  const ctx = await ctxFor({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();

  const dup = [];
  for (const p of ['/all-artists/', '/all-artists-2/', '/cart-2/', '/icons/', '/dashboard/']) {
    const s = await go(page, p, 400);
    dup.push(`${p} → ${s}`);
  }
  const answered = dup.filter(d => !/ → 0$/.test(d)).length;
  say('L-07a', answered === 0 ? NA : (dup.filter(d => / → 200/.test(d)).length > 1 ? YES : NO),
      'duplicate and internal pages are reachable and indexable',
      answered === 0 ? 'none of the five URLs answered at all — nothing measured' : dup.join(' · '));

  if (await go(page, '/artists/', 1200) === 200) {
    const admin = await page.evaluate(() => /This list updates automatically[^\n]{0,120}/i.exec(document.body.innerText));
    say('L-07b', admin ? YES : NO, '/artists/ shows shoppers an admin instruction', admin ? admin[0] : 'no admin instruction text on /artists/');
  } else say('L-07b', NA, '/artists/ admin text', '/artists/ did not return 200');

  for (const p of ['/login/', '/sign-up/', '/my-account/']) {
    const s = await go(page, p, 1200);
    if (s !== 200) { say(`H-07${p}`, NA, 'account system', `${p} → HTTP ${s}`); continue; }
    const f = await page.evaluate(() => ({
      eael: document.querySelectorAll('[name*="eael"], .eael-login-form, .eael-user-login').length,
      woo: document.querySelectorAll('input[name="username"], form.woocommerce-form-login').length,
      h1: document.querySelectorAll('h1').length,
      loginAndReg: document.querySelectorAll('form').length,
    }));
    say(`H-07 ${p}`, PART, 'two parallel account systems with different field names',
        `Essential Addons fields ${f.eael} · WooCommerce fields ${f.woo} · <h1> count ${f.h1} · forms ${f.loginAndReg}`);
  }

  // C-01 at 796px — the report's split-screen desktop case, in a desktop browser
  if (productId) {
    await go(page, `/?add-to-cart=${productId}`, 2500);
    await page.setViewportSize({ width: 796, height: 900 });
    await go(page, '/checkout/', 3000);
    const clip = await page.evaluate(() => {
      const t = document.querySelector('.woocommerce-checkout-review-order-table');
      if (!t) return null;
      const pr = t.parentElement.getBoundingClientRect();
      const cell = t.querySelector('.order-total td, tr.order-total td');
      const cr = cell ? cell.getBoundingClientRect() : null;
      return { table: Math.round(t.getBoundingClientRect().width), col: Math.round(pr.width), scroll: Math.round(t.scrollWidth),
               past: cr ? Math.round(cr.right - innerWidth) : null, shown: cell ? cell.innerText.replace(/\s+/g, ' ').trim() : 'no total row' };
    });
    if (!clip) say('C-01@796', NA, 'at 796px the total sits 11px past the viewport edge', 'no review-order table on the checkout page');
    else say('C-01@796', (clip.past > 0 || clip.scroll > clip.col + 2) ? YES : NO, 'at 796px the total sits 11px past the viewport edge',
             `table ${clip.table}px (scrollWidth ${clip.scroll}) in a ${clip.col}px column · total cell ends ${clip.past}px past the viewport · reads "${clip.shown}"`);
    await page.setViewportSize({ width: 1440, height: 900 });
    const left796 = await emptyCart(page);
    say('CLEANUP@796', left796 ? YES : NO, 'the second test item was removed again',
        left796 ? 'cart is empty' : 'cart still holds an item — remove it by hand');
  }

  // M-06 — how big is that brochure, really
  const head = brochureUrl ? await page.request.head(brochureUrl).catch(() => null) : null;
  say('M-06b', head && head.ok() ? PART : NA, 'the brochure is 42,997,533 bytes and 358 pages',
      head ? `HTTP ${head.status()} · content-length ${head.headers()['content-length'] || 'not sent'}` : 'HEAD request failed');

  // C-02, part two — the shipping and contact pages
  for (const p of ['/shipping-delivery/', '/shipping-and-delivery/', '/shipping/', '/contact/']) {
    const s = await go(page, p, 1200);
    if (s !== 200) continue;
    const t = await page.evaluate(() => {
      const hits = [];
      for (const el of document.querySelectorAll('h1,h2,h3,p,li,span,strong')) {
        const own = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent).join(' ').trim();
        if (/free[^.]{0,40}(ship|deliver)|(ship|deliver)[^.]{0,20}free/i.test(own)) hits.push(own.replace(/\s+/g, ' ').slice(0, 120));
      }
      return [...new Set(hits)].slice(0, 4);
    });
    say(`C-02 ${p}`, t.length ? YES : NO, 'free shipping is promised here too', t.length ? t.join(' | ') : 'no free-shipping promise found on this page');
  }
  await ctx.close();
} catch (e) { say('CRASH', NA, 'this section stopped before it finished', String(e.message).slice(0, 160)); }

await browser.close();

// ── the verdict table ──────────────────────────────────────────────────────
console.log('\n' + '='.repeat(78));
console.log('AUDIT VERIFICATION — ' + SITE);
console.log('='.repeat(78));
const order = [YES, PART, NO, NA];
for (const v of order) {
  const set = rows.filter(r => r.verdict === v);
  if (!set.length) continue;
  console.log(`\n${v} (${set.length})`);
  for (const r of set) {
    console.log(`  ${r.id.padEnd(12)} ${r.claim}`);
    console.log(`  ${''.padEnd(12)} measured: ${r.measured}`);
  }
}
console.log('\n' + '-'.repeat(78));
console.log(order.map(v => `${v} ${rows.filter(r => r.verdict === v).length}`).join(' · '));
