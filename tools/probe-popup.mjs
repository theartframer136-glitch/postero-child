// DEF-01: is the homepage pop-up's email field wired to anything?
//
// The claim is that there is no <form>, the input has no name, no handler is
// bound, and clicking the button fires no request — so every address typed
// into the site's most prominent call to action is discarded.
//
// That is a serious claim and it is not checkable from this repository: the
// overlay belongs to a plugin (.af-popup-left, noted in inc/motion-glide.php
// on 2026-09-09), so none of its markup is in the child theme. It has to be
// read off the live page.
//
// This also has to come back with the exact selectors, because a fix has to
// attach to whatever is really there rather than to what a report described.
//
// SAFETY. The NEVER_SEND guard from qa-personas.mjs, which already lists
// af_nl_subscribe — so if the button IS wired to the newsletter endpoint, the
// request is intercepted and answered locally rather than creating a
// subscriber. The address used is obviously fake. Read-only in effect.
//
// Run: node tools/probe-popup.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const EMAIL = 'af.qa.probe+def01@example.invalid';

const NEVER_SEND = [
  'af_nl_subscribe', 'af_contact_submit', 'af_save_preview', 'af_gc_apply',
  'wc-ajax=checkout', 'af_product_edit_save', 'af_inventory_save',
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const intercepted = [];
await ctx.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    intercepted.push(req.url().replace(SITE, '') + ' :: ' + (req.postData() || '').slice(0, 120));
    return route.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"message":"(intercepted by QA)"}}' });
  }
  return route.continue();
});
const page = await ctx.newPage();

const sent = [];
page.on('request', r => { if (r.method() === 'POST') sent.push(r.method() + ' ' + r.url().replace(SITE, '') + ' :: ' + (r.postData() || '').slice(0, 160)); });
const errors = [];
page.on('pageerror', e => errors.push(String(e.message).slice(0, 120)));

console.log('probe-popup: ' + SITE + '   ' + new Date().toISOString());

try {
  await page.goto(SITE + '/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  // The overlay is time-triggered; give it room to appear.
  await page.waitForTimeout(9000);

  // ── what is actually on the page ────────────────────────────────────────
  const dom = await page.evaluate(() => {
    const emails = [...document.querySelectorAll('input[type="email"], input[placeholder*="mail" i]')];
    const describe = el => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        tag: el.tagName.toLowerCase(),
        id: el.id || '', cls: (el.className || '').toString().slice(0, 90),
        name: el.getAttribute('name'), type: el.getAttribute('type'),
        required: el.hasAttribute('required'),
        placeholder: el.getAttribute('placeholder') || '',
        visible: s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0,
        box: Math.round(r.width) + 'x' + Math.round(r.height),
        inForm: !!el.closest('form'),
        formAction: el.closest('form') ? (el.closest('form').getAttribute('action') || '(none)') : null,
        // the chain of ancestors, so a fix knows what to hang off
        chain: (() => { const out = []; for (let p = el.parentElement, i = 0; p && i < 6; p = p.parentElement, i++)
          out.push(p.tagName.toLowerCase() + (p.id ? '#' + p.id : '') + (p.className ? '.' + (p.className || '').toString().trim().split(/\s+/).slice(0, 2).join('.') : '')); return out; })(),
      };
    };
    // the button that sits next to each email input
    const buttonFor = el => {
      const scope = el.closest('.af-input-group, form, div') || document;
      const b = scope.querySelector('button, input[type="submit"], a.button, [role="button"]');
      return b ? { tag: b.tagName.toLowerCase(), text: (b.innerText || b.value || '').trim().slice(0, 40),
                   type: b.getAttribute('type'), cls: (b.className || '').toString().slice(0, 70), id: b.id || '' } : null;
    };
    return {
      overlays: [...document.querySelectorAll('[class*="popup" i], [class*="modal" i], [role="dialog"]')]
        .filter(el => { const s = getComputedStyle(el); const r = el.getBoundingClientRect();
          return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 200 && r.height > 120; })
        .slice(0, 4)
        .map(el => ({ cls: (el.className || '').toString().slice(0, 90), id: el.id || '',
                      role: el.getAttribute('role') || '(none)', box: Math.round(el.getBoundingClientRect().width) + 'x' + Math.round(el.getBoundingClientRect().height),
                      hasForm: !!el.querySelector('form'), emails: el.querySelectorAll('input[type=email], input[placeholder*="mail" i]').length })),
      emailInputs: emails.map(e => ({ ...describe(e), button: buttonFor(e) })),
      formsOnPage: document.querySelectorAll('form').length,
      nlNonce: !!document.querySelector('[data-nonce]'),
    };
  });

  console.log('\n— overlays visible on the front page —');
  if (!dom.overlays.length) console.log('  none visible after 9s');
  for (const o of dom.overlays) console.log('  .' + o.cls + (o.id ? ' #' + o.id : '')
    + '  ' + o.box + '  role=' + o.role + '  form inside=' + o.hasForm + '  email inputs=' + o.emails);

  console.log('\n— every email input on the page —');
  if (!dom.emailInputs.length) console.log('  none found');
  for (const e of dom.emailInputs) {
    console.log('  <' + e.tag + '>' + (e.id ? ' #' + e.id : '') + ' .' + e.cls);
    console.log('      name=' + JSON.stringify(e.name) + ' type=' + e.type + ' required=' + e.required
      + ' visible=' + e.visible + ' ' + e.box);
    console.log('      inside <form>=' + e.inForm + (e.inForm ? ' action=' + e.formAction : ''));
    console.log('      placeholder="' + e.placeholder + '"');
    console.log('      ancestors: ' + e.chain.join(' < '));
    console.log('      button: ' + (e.button ? '<' + e.button.tag + ' type=' + e.button.type + '> "' + e.button.text + '" .' + e.button.cls : '(none found)'));
  }

  // ── the actual claim: does clicking send anything? ──────────────────────
  const target = dom.emailInputs.find(e => e.visible) || dom.emailInputs[0];
  if (!target) {
    console.log('\n  no email input to test — DEF-01 NOT REPRODUCED (no field present at all)');
  } else {
    console.log('\n— typing an address and pressing the button —');
    const before = sent.length;
    const clicked = await page.evaluate(email => {
      const el = [...document.querySelectorAll('input[type="email"], input[placeholder*="mail" i]')]
        .find(i => { const s = getComputedStyle(i); return s.display !== 'none' && i.getBoundingClientRect().width > 0; })
        || document.querySelector('input[type="email"]');
      if (!el) return { ok: false };
      el.focus(); el.value = email;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      const scope = el.closest('.af-input-group, form, div') || document;
      const b = scope.querySelector('button, input[type="submit"], a.button, [role="button"]');
      if (b) b.click();
      return { ok: true, clickedText: b ? (b.innerText || b.value || '').trim().slice(0, 40) : '(no button)' };
    }, EMAIL);
    await page.waitForTimeout(6000);

    const after = sent.slice(before);
    const state = await page.evaluate(() => {
      const el = [...document.querySelectorAll('input[type="email"], input[placeholder*="mail" i]')]
        .find(i => i.getBoundingClientRect().width > 0) || document.querySelector('input[type="email"]');
      const ov = [...document.querySelectorAll('[class*="popup" i], [class*="modal" i]')]
        .find(e => { const s = getComputedStyle(e); return s.display !== 'none' && e.getBoundingClientRect().height > 120; });
      return {
        inputValue: el ? el.value : '(gone)',
        overlayStillVisible: !!ov,
        anyMessage: /thank|subscrib|success|invalid|error|valid email/i.test(document.body.innerText),
      };
    });

    console.log('  button pressed: "' + (clicked.clickedText || '-') + '"');
    console.log('  POST requests that followed: ' + after.length);
    for (const s of after) console.log('      ' + s);
    console.log('  intercepted by the wire guard: ' + intercepted.length + (intercepted.length ? ' → ' + intercepted.join(' | ') : ''));
    console.log('  input value after click : ' + state.inputValue);
    console.log('  overlay still visible   : ' + state.overlayStillVisible);
    console.log('  any feedback message    : ' + state.anyMessage);
    console.log('  page errors             : ' + (errors.length ? errors.join(' | ') : 'none'));

    const wired = after.length > 0 || intercepted.length > 0;
    console.log('\n  → ' + (wired
      ? 'DEF-01 CONTRADICTED — the field does send something. ' + (intercepted.length ? 'It reached af_nl_subscribe and the guard stopped it.' : 'See the POST above.')
      : 'DEF-01 CONFIRMED — nothing left the browser. The address goes nowhere.'));
  }
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
