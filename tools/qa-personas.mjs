// Ten people, ages 5 to 65, each on their own pages, each using the shop the
// way they actually would. kid-mode and veteran already cover the extremes —
// one flails, one works a checklist. The middle is where a shop quietly loses
// people: the student on a throttled phone, the keyboard-only designer, the
// grandmother at 200% zoom looking for a phone number.
//
// Each persona names the pages it visits and the things it cannot do without.
// A finding says who hit it, on what page, and what they were trying to do,
// because "tap target too small" is an argument and "a 65-year-old could not
// find your phone number in 30 seconds" is a decision.
//
// SAFETY. Live shop. No order is placed and no email is sent: every request
// that would reach a person or a ledger is intercepted at the wire and answered
// locally, exactly as qa-veteran.mjs does it. Nothing is written to the server.
//
// Run: node tools/qa-personas.mjs [url]
// Env: AF_QA_ONLY=4,9   run only those personas   AF_QA_HEADED=1

import { chromium, devices } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const ONLY = (process.env.AF_QA_ONLY || '').split(',').map(s => s.trim()).filter(Boolean);

// Identical to qa-veteran.mjs. Anything here is answered, never sent.
const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const results = [];
let P = null, WHERE = '/';
const add = (sev, what, detail) =>
  results.push({ sev, persona: P ? `${P.n}. ${P.who} (${P.age})` : '—', where: WHERE, what, detail });
const FAIL = (w, d) => add('FAIL', w, d);
const WARN = (w, d) => add('WARN', w, d);
const PASS = (w, d = '') => add('PASS', w, d);

const browser = await chromium.launch({ headless: process.env.AF_QA_HEADED !== '1' });

// ── measurements every persona can ask for ─────────────────────────────────
const M = {
  async sideways(page) {
    return page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - window.innerWidth));
  },
  async smallTaps(page, min) {
    return page.evaluate(m => {
      const bad = [];
      for (const el of document.querySelectorAll('a,button,[role="button"],input[type="submit"]')) {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        if (r.width < m || r.height < m) {
          const t = (el.innerText || el.getAttribute('aria-label') || el.className || '').trim().slice(0, 40);
          if (t) bad.push(`${Math.round(r.width)}x${Math.round(r.height)} "${t}"`);
        }
      }
      return [...new Set(bad)];
    }, min);
  },
  async tinyText(page, min) {
    return page.evaluate(m => {
      const bad = [];
      for (const el of document.querySelectorAll('p,span,li,a,td,label,div')) {
        if (!el.childNodes.length) continue;
        const txt = [...el.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent.trim()).join(' ');
        if (txt.length < 8) continue;
        const px = parseFloat(getComputedStyle(el).fontSize);
        if (px && px < m) bad.push(`${px}px "${txt.slice(0, 48)}"`);
      }
      return [...new Set(bad)].slice(0, 8);
    }, min);
  },
  async brokenImages(page) {
    return page.evaluate(() => [...document.images]
      .filter(i => i.complete && i.naturalWidth === 0)
      .map(i => i.currentSrc || i.src).slice(0, 8));
  },
  async imagesNoAlt(page) {
    return page.evaluate(() => [...document.images]
      .filter(i => i.width > 80 && i.height > 80 && !(i.getAttribute('alt') || '').trim())
      .map(i => (i.currentSrc || i.src).split('/').pop()).slice(0, 8));
  },
};

async function open(page, path, label) {
  WHERE = path;
  const url = path.startsWith('http') ? path : SITE + path;
  try {
    const r = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    const code = r ? r.status() : 0;
    if (!r || code >= 400) { FAIL(`${label} did not load`, `${url} → HTTP ${code}`); return false; }
    await page.waitForTimeout(1200);
    return true;
  } catch (e) {
    FAIL(`${label} did not load`, `${url} → ${String(e.message).slice(0, 110)}`);
    return false;
  }
}

// ── the ten ────────────────────────────────────────────────────────────────
const PERSONAS = [
  {
    n: 1, age: 5, who: 'child on a tablet', ctx: () => ({ ...devices['iPad (gen 7)'] }),
    pages: ['/', '/product-category/kids-room-decor/'],
    async run(page) {
      if (!await open(page, '/', 'Home')) return;
      const taps = await M.smallTaps(page, 44);
      taps.length ? WARN('targets a child cannot hit', `${taps.length} under 44px, e.g. ${taps.slice(0, 3).join(' | ')}`)
                  : PASS('every tap target is at least 44px');
      // taps the biggest thing, twice, before it has answered
      const big = await page.evaluate(() => {
        let best = null, area = 0;
        for (const el of document.querySelectorAll('a,button')) {
          const r = el.getBoundingClientRect();
          if (r.top < 0 || r.top > window.innerHeight) continue;
          if (r.width * r.height > area) { area = r.width * r.height; best = el; }
        }
        if (!best) return null;
        best.setAttribute('data-afqa', '1'); return best.innerText.trim().slice(0, 40);
      });
      if (big) {
        await page.click('[data-afqa="1"]', { timeout: 5000 }).catch(() => {});
        await page.click('[data-afqa="1"]', { timeout: 2000 }).catch(() => {});
        await page.waitForTimeout(1500);
        PASS('double-tapping the biggest button survived', `"${big}"`);
      }
      await page.goBack().catch(() => {});
      await page.goBack().catch(() => {});
      if (await open(page, '/product-category/kids-room-decor/', "Kids' Room")) {
        const n = await page.locator('.product, li.product, .wc-block-grid__product').count();
        n === 0 ? FAIL('Kids’ Room shows nothing', 'a category a child is sent to is empty')
                : PASS(`Kids’ Room shows ${n} products`);
      }
    },
  },
  {
    n: 2, age: 12, who: 'school project, phone on slow 3G',
    ctx: () => ({ ...devices['Pixel 5'] }), throttle: true,
    pages: ['/', '/?s=krishna&post_type=product'],
    async run(page) {
      const t0 = Date.now();
      if (!await open(page, '/', 'Home')) return;
      const secs = ((Date.now() - t0) / 1000).toFixed(1);
      secs > 12 ? WARN('home is slow on a throttled phone', `${secs}s to DOM ready`)
                : PASS(`home reached DOM ready in ${secs}s on throttled 3G`);
      const weight = await page.evaluate(() =>
        performance.getEntriesByType('resource').reduce((a, r) => a + (r.transferSize || 0), 0));
      const mb = (weight / 1048576).toFixed(1);
      mb > 5 ? WARN('home downloads a lot for a phone', `${mb} MB of resources`)
             : PASS(`home weighs ${mb} MB`);
      if (await open(page, '/?s=krishna&post_type=product', 'Search "krishna"')) {
        const n = await page.locator('.product, li.product').count();
        n === 0 ? FAIL('search for "krishna" returns nothing', 'the shop’s largest section is unsearchable')
                : PASS(`search "krishna" returns ${n} products`);
      }
    },
  },
  {
    n: 3, age: 19, who: 'student hunting a bargain, phone',
    ctx: () => ({ ...devices['iPhone 12'] }),
    pages: ['/product-category/deals-discounts/'],
    async run(page) {
      if (!await open(page, '/product-category/deals-discounts/', 'Deals & Discounts')) return;
      const side = await M.sideways(page);
      side > 4 ? FAIL('the deals page scrolls sideways on a phone', `${side}px of overflow`)
               : PASS('no sideways scroll on the deals page');
      const prices = await page.evaluate(() =>
        [...document.querySelectorAll('.price')].slice(0, 12).map(p => p.innerText.replace(/\s+/g, ' ').trim()));
      const noPrice = prices.filter(p => !/\d/.test(p));
      noPrice.length ? FAIL('products on the deals page show no price', `${noPrice.length} of ${prices.length} price blocks have no number`)
                     : PASS(`all ${prices.length} sampled cards show a price`);
      const tiny = await M.tinyText(page, 12);
      tiny.length ? WARN('type under 12px on a phone', tiny.slice(0, 3).join(' | ')) : PASS('no type under 12px');
    },
  },
  {
    n: 4, age: 24, who: 'designer, keyboard only, desktop',
    ctx: () => ({ viewport: { width: 1440, height: 900 } }),
    pages: ['/', '/shop/'],
    async run(page) {
      if (!await open(page, '/', 'Home')) return;
      let reached = 0, invisible = 0;
      for (let i = 0; i < 25; i++) {
        await page.keyboard.press('Tab');
        const st = await page.evaluate(() => {
          const el = document.activeElement;
          if (!el || el === document.body) return null;
          const s = getComputedStyle(el);
          const ring = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) ||
                       s.boxShadow !== 'none' || s.borderColor !== s.backgroundColor;
          return { tag: el.tagName, txt: (el.innerText || '').trim().slice(0, 30), ring };
        });
        if (!st) continue;
        reached++;
        if (!st.ring) invisible++;
      }
      reached === 0 ? FAIL('keyboard cannot reach anything', 'Tab moves focus nowhere on the home page')
                    : PASS(`Tab reaches ${reached} controls on the home page`);
      invisible > reached / 2
        ? FAIL('focus is invisible for keyboard users', `${invisible} of ${reached} focused controls show no focus ring`)
        : PASS(`${reached - invisible} of ${reached} focused controls show a visible focus ring`);
      const skip = await page.locator('a[href^="#"]:has-text("Skip")').count();
      skip ? PASS('a skip-to-content link exists') : WARN('no skip-to-content link', 'keyboard users tab the whole header on every page');
    },
  },
  {
    n: 5, age: 30, who: 'buying a gift, phone, first visit',
    ctx: () => ({ ...devices['iPhone 12'] }),
    pages: ['/', '/?s=gift&post_type=product'],
    async run(page) {
      if (!await open(page, '/', 'Home')) return;
      if (await open(page, '/?s=gift&post_type=product', 'Search "gift"')) {
        const n = await page.locator('.product, li.product').count();
        n === 0 ? WARN('searching "gift" finds nothing', 'a gift card exists but does not surface for the word "gift"')
                : PASS(`search "gift" returns ${n} results`);
      }
      // first product card → does a price and an add-to-cart exist
      if (await open(page, '/shop/', 'Shop')) {
        const href = await page.evaluate(() => {
          const a = document.querySelector('.product a[href*="/product/"], li.product a[href*="/product/"]');
          return a ? a.href : null;
        });
        if (!href) { FAIL('no product link on the shop page', 'nothing to click through to'); return; }
        if (await open(page, href, 'A product page')) {
          const price = (await page.locator('.price').first().innerText().catch(() => '')).trim();
          /\d/.test(price) ? PASS('the product page shows a price', price.replace(/\s+/g, ' ').slice(0, 40))
                           : FAIL('the product page shows no price', href);
          const cart = await page.locator('button[name="add-to-cart"], .single_add_to_cart_button').count();
          cart ? PASS('an add-to-cart button is present') : FAIL('no add-to-cart button', href);
        }
      }
    },
  },
  {
    n: 6, age: 35, who: 'returning customer, digital downloads, desktop',
    ctx: () => ({ viewport: { width: 1440, height: 900 } }),
    pages: ['/product-category/digital-downloads-2/'],
    async run(page) {
      if (!await open(page, '/product-category/digital-downloads-2/', 'Digital Downloads')) return;
      const cards = await page.evaluate(() =>
        [...document.querySelectorAll('.product, li.product')].slice(0, 8).map(c => ({
          t: (c.querySelector('.woocommerce-loop-product__title, h2, h3') || {}).innerText || '',
          p: (c.querySelector('.price') || {}).innerText || '',
        })));
      if (!cards.length) { FAIL('the Digital Downloads category is empty', 'nothing to buy'); return; }
      const sizey = cards.filter(c => /\bsizes?\b|\bframes?\b/i.test(c.p + c.t));
      sizey.length ? FAIL('a downloadable file is advertised with sizes or frames',
                          sizey.map(c => c.t.trim().slice(0, 34)).join(' | '))
                   : PASS('no card offers sizes or frames for a file');
      const noPrice = cards.filter(c => !/\d/.test(c.p));
      noPrice.length ? FAIL('a download card shows no price', `${noPrice.length} of ${cards.length}`)
                     : PASS(`all ${cards.length} download cards show a price`);
      const broke = await M.brokenImages(page);
      broke.length ? FAIL('broken images on the downloads page', broke.slice(0, 2).join(' | '))
                   : PASS('no broken images on the downloads page');
    },
  },
  {
    n: 7, age: 42, who: 'corporate buyer, desktop, wants a quote',
    ctx: () => ({ viewport: { width: 1600, height: 1000 } }),
    pages: ['/corporate-printing/', '/contact/'],
    async run(page) {
      let found = false;
      for (const p of ['/corporate-printing/', '/corporate/', '/contact/']) {
        if (await open(page, p, `Page ${p}`)) { found = true; break; }
      }
      if (!found) { FAIL('no corporate or contact page answered', 'a bulk buyer has nowhere to ask'); return; }
      const form = await page.locator('form').count();
      form ? PASS(`a form is present on ${WHERE}`) : WARN('no form on the page a buyer lands on', WHERE);
      // an obviously invalid address must be refused BEFORE anything is sent
      const email = page.locator('input[type="email"], input[name*="mail" i]').first();
      if (await email.count()) {
        await email.fill('not-an-email').catch(() => {});
        await page.keyboard.press('Tab');
        const bad = await email.evaluate(el => el.checkValidity ? !el.checkValidity() : null).catch(() => null);
        bad === true ? PASS('an invalid email address is refused by the field')
                     : WARN('the email field accepts "not-an-email"', 'validation is server-side only, or absent');
      } else {
        WARN('no email field found to test', WHERE);
      }
      const phone = await page.evaluate(() => /(\+?\d[\d\s\-()]{8,})/.test(document.body.innerText));
      phone ? PASS('a phone number is on the page') : WARN('no phone number on the page a buyer lands on', WHERE);
    },
  },
  {
    n: 8, age: 50, who: 'devotional shopper, tablet',
    ctx: () => ({ ...devices['iPad (gen 7)'] }),
    pages: ['/product-category/radha-krishna/', '/product-category/hindu-deities/'],
    async run(page) {
      let any = false;
      for (const p of ['/product-category/radha-krishna/', '/product-category/hindu-deities/']) {
        if (!await open(page, p, `Category ${p}`)) continue;
        any = true;
        const n = await page.locator('.product, li.product').count();
        n === 0 ? FAIL(`${p} shows no products`, 'a customer arriving from the brochure sees an empty shelf')
                : PASS(`${p} shows ${n} products`);
        const noalt = await M.imagesNoAlt(page);
        noalt.length ? WARN(`artwork without alt text on ${p}`, `${noalt.length} images, e.g. ${noalt.slice(0, 2).join(', ')}`)
                     : PASS(`every artwork on ${p} has alt text`);
      }
      if (!any) FAIL('neither devotional category answered', 'the two biggest sections of the book');
    },
  },
  {
    n: 9, age: 58, who: 'low vision, desktop at 200% zoom',
    ctx: () => ({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 }),
    pages: ['/', '/shop/'],
    async run(page) {
      if (!await open(page, '/', 'Home')) return;
      // 200% zoom is a 640px-wide viewport in CSS pixels
      await page.setViewportSize({ width: 640, height: 800 });
      await page.waitForTimeout(1200);
      const side = await M.sideways(page);
      side > 4 ? FAIL('the page scrolls sideways at 200% zoom', `${side}px of overflow — text is cut off`)
               : PASS('the page reflows at 200% zoom without sideways scroll');
      const tiny = await M.tinyText(page, 14);
      tiny.length ? WARN('type under 14px, hard at low vision', tiny.slice(0, 3).join(' | '))
                  : PASS('no body type under 14px');
      const contrast = await page.evaluate(() => {
        const lum = c => {
          const m = c.match(/\d+/g); if (!m) return null;
          const [r, g, b] = m.slice(0, 3).map(v => {
            v = v / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
          });
          return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        const bad = [];
        for (const el of [...document.querySelectorAll('p,li,span,a')].slice(0, 300)) {
          const t = el.innerText && el.innerText.trim(); if (!t || t.length < 10) continue;
          const s = getComputedStyle(el);
          let bg = s.backgroundColor, n = el;
          while (bg === 'rgba(0, 0, 0, 0)' && n.parentElement) { n = n.parentElement; bg = getComputedStyle(n).backgroundColor; }
          const a = lum(s.color), b = lum(bg); if (a === null || b === null) continue;
          const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
          if (ratio < 4.5) bad.push(`${ratio.toFixed(1)}:1 "${t.slice(0, 34)}"`);
        }
        return [...new Set(bad)].slice(0, 6);
      });
      contrast.length ? WARN('text below 4.5:1 contrast', contrast.slice(0, 3).join(' | '))
                      : PASS('sampled text meets 4.5:1 contrast');
    },
  },
  {
    n: 10, age: 65, who: 'not confident with computers, desktop',
    ctx: () => ({ viewport: { width: 1366, height: 768 } }),
    pages: ['/', '/shipping-returns/', '/refund_returns/'],
    async run(page) {
      if (!await open(page, '/', 'Home')) return;
      const body = await page.evaluate(() => document.body.innerText);
      /(\+?\d[\d\s\-()]{8,})/.test(body)
        ? PASS('a phone number is on the home page without hunting')
        : FAIL('no phone number on the home page', 'the first thing this customer looks for');
      const links = await page.evaluate(() =>
        [...document.querySelectorAll('a')].map(a => (a.innerText || '').trim().toLowerCase()));
      for (const [want, label] of [[/contact/, 'Contact'], [/return|refund/, 'Returns'], [/shipping|delivery/, 'Shipping']]) {
        links.some(t => want.test(t)) ? PASS(`a "${label}" link is on the home page`)
                                      : WARN(`no "${label}" link on the home page`, 'has to be searched for');
      }
      const vague = links.filter(t => ['click here', 'read more', 'more', 'here'].includes(t));
      vague.length ? WARN('links labelled only "read more" / "click here"', `${vague.length} of them — no idea where they go`)
                   : PASS('no vague link labels');
      let reached = false;
      for (const p of ['/shipping-returns/', '/refund_returns/', '/returns/']) {
        if (await open(page, p, `Policy page ${p}`)) { reached = true; PASS(`a returns policy page answers at ${p}`); break; }
      }
      if (!reached) WARN('no returns policy page answered', 'tried /shipping-returns/, /refund_returns/, /returns/');
    },
  },
];

// ── run them ───────────────────────────────────────────────────────────────
const jsErrors = [], serverErrors = [];
for (const persona of PERSONAS) {
  if (ONLY.length && !ONLY.includes(String(persona.n))) continue;
  P = persona;
  const ctx = await browser.newContext({ ...persona.ctx(), ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  await page.route('**/*', route => {
    const req = route.request();
    const body = (req.postData() || '') + ' ' + req.url();
    if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
      return route.fulfill({ status: 200, contentType: 'application/json',
        body: '{"success":true,"data":{"message":"(intercepted by QA — not sent)"}}' });
    }
    return route.continue();
  });
  page.on('pageerror', e => jsErrors.push({ p: persona.n, where: WHERE, msg: String(e.message).slice(0, 140) }));
  page.on('response', r => {
    if (r.status() >= 500) serverErrors.push({ p: persona.n, where: WHERE, status: r.status(), url: r.url() });
  });
  if (persona.throttle) {
    const cdp = await ctx.newCDPSession(page).catch(() => null);
    if (cdp) await cdp.send('Network.emulateNetworkConditions', {
      offline: false, downloadThroughput: 200000, uploadThroughput: 100000, latency: 300 }).catch(() => {});
  }

  try { await persona.run(page); }
  catch (e) { FAIL('the journey stopped', String(e.message).slice(0, 160)); }
  await page.screenshot({ path: `persona-${persona.n}.png`, fullPage: false }).catch(() => {});
  await ctx.close();
}

P = null;
for (const e of jsErrors) {
  P = PERSONAS.find(x => x.n === e.p); WHERE = e.where;
  FAIL('an uncaught JavaScript error fired', e.msg);
}
for (const e of serverErrors) {
  P = PERSONAS.find(x => x.n === e.p); WHERE = e.where;
  FAIL(`the server answered HTTP ${e.status}`, e.url);
}
if (!jsErrors.length && !serverErrors.length) { P = null; WHERE = '—'; PASS('no JS errors and no 5xx across all personas'); }

// ── report ─────────────────────────────────────────────────────────────────
const bar = '='.repeat(76);
console.log(`\n${bar}\nTEN PEOPLE, AGES 5 TO 65 — ${SITE}\n${bar}`);
for (const persona of PERSONAS) {
  if (ONLY.length && !ONLY.includes(String(persona.n))) continue;
  const mine = results.filter(r => r.persona.startsWith(`${persona.n}.`));
  const f = mine.filter(r => r.sev === 'FAIL'), w = mine.filter(r => r.sev === 'WARN');
  console.log(`\n${persona.n}. ${persona.who} — age ${persona.age}`);
  console.log('-'.repeat(76));
  console.log(`   pages: ${persona.pages.join('  ')}`);
  if (!mine.length) { console.log('   (nothing recorded)'); continue; }
  for (const r of f) console.log(`   FAIL  ${r.what}\n         ${r.detail}   [${r.where}]`);
  for (const r of w) console.log(`   WARN  ${r.what}\n         ${r.detail}   [${r.where}]`);
  for (const r of mine.filter(r => r.sev === 'PASS')) console.log(`   ok    ${r.what}${r.detail ? ' — ' + r.detail : ''}`);
}
const F = results.filter(r => r.sev === 'FAIL'), W = results.filter(r => r.sev === 'WARN');
console.log(`\n${bar}`);
console.log(`${F.length} blocking, ${W.length} worth fixing, ${results.filter(r => r.sev === 'PASS').length} verified good.`);
if (F.length) {
  console.log('\nBlocking, by who hit it:');
  for (const r of F) console.log(`  ${r.persona.padEnd(42)} ${r.what}`);
}
console.log(bar);
await browser.close();
process.exit(0);   // the report is the product; a finding is not a build failure
