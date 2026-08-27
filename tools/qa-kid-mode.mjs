// Test the site the way a five-year-old does: don't read anything, click the
// biggest brightest thing, click it again before it has finished, type nonsense
// into every box, mash Enter, hit Back, and do it all on a tablet held in
// portrait. It is a monkey test with a grudge.
//
// What it is actually looking for is the class of bug that only appears when a
// person does not behave: uncaught exceptions, requests that fail, controls that
// swallow a click and do nothing, and pages that grow a sideways scrollbar the
// moment the screen is narrow.
//
// The walk is SEEDED. A run that finds something can be handed to someone else
// as "AF_QA_SEED=1234" and they will get the same run, which is the difference
// between a bug report and an anecdote.
//
// SAFETY. This runs against the live shop, so it refuses to complete the actions
// that would reach a real person: the newsletter and contact forms both email
// the owner, saved previews write to disk, and checkout takes money. Those
// requests are intercepted and answered locally rather than trusted not to be
// clicked — a five-year-old clicks everything, that being the entire point, so
// the block has to be at the wire and not in the walk. Add-to-cart is
// allowed, because a cart is per-session and throwing one away costs nothing.
//
// Run: node tools/qa-kid-mode.mjs [url]
// Env: AF_QA_SEED (default 1), AF_QA_STEPS (default 60), AF_QA_HEADED=1

import { chromium } from 'playwright';

const SITE  = process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us/';
const SEED  = parseInt(process.env.AF_QA_SEED || '1', 10);
const STEPS = parseInt(process.env.AF_QA_STEPS || '60', 10);

// Anything whose POST body carries one of these reaches a human or a ledger.
const NEVER_SEND = [
  'af_nl_subscribe',   // emails the owner, and burns the per-IP rate limit
  'af_contact_submit', // emails the owner
  'af_save_preview',   // writes an image to the server
  'af_gc_apply',       // gift-card redemption
  'wc-ajax=checkout',  // takes money
  'af_product_edit_save',
  'af_inventory_save',
];

let seed = SEED >>> 0;
const rnd = () => ((seed = (seed * 1664525 + 1013904223) >>> 0) / 4294967296);
const pick = a => a[Math.floor(rnd() * a.length)];

// What a five-year-old types. Long strings, emoji, and the characters that have
// historically ended up rendered instead of escaped.
const GIBBERISH = [
  'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
  '🦄🦄🦄🦄🦄',
  '<script>alert(1)</script>',
  "'; DROP TABLE--",
  '     ',
  '-99999999',
  '0',
  'ÄÖÜ日本語ไทย',
  'x'.repeat(500),
];

const issues = [];
const add = (kind, detail, extra = {}) => {
  const key = kind + '|' + detail;
  const seen = issues.find(i => i.key === key);
  if (seen) { seen.count++; return; }
  issues.push({ key, kind, detail, count: 1, ...extra });
};

const browser = await chromium.launch({ headless: process.env.AF_QA_HEADED !== '1' });
const ctx = await browser.newContext({
  // A tablet in portrait, which is what a small child is actually holding.
  viewport: { width: 768, height: 1024 },
  userAgent: 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15 AF-QA-Kid',
  ignoreHTTPSErrors: false,
});

const page = await ctx.newPage();

let blocked = 0;
await page.route('**/*', route => {
  const req = route.request();
  const body = (req.postData() || '') + ' ' + req.url();
  if (req.method() === 'POST' && NEVER_SEND.some(n => body.includes(n))) {
    blocked++;
    // Answered with a plausible success rather than aborted. An abort makes the
    // page's own fetch reject, and the resulting "Failed to fetch" looks exactly
    // like a bug we caused ourselves — which is worse than useless in a report.
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: '{"success":true,"data":{"message":"(intercepted by QA — not sent)"}}',
    });
  }
  return route.continue();
});

page.on('pageerror', e => add('Uncaught JS error', String(e.message).slice(0, 180)));
page.on('console', m => {
  if (m.type() === 'error') {
    const t = m.text().slice(0, 180);
    // A blocked-on-purpose request shows up here as a network error. Not a bug.
    if (!/ERR_FAILED|ERR_ABORTED/.test(t)) add('Console error', t);
  }
});
page.on('requestfailed', r => {
  const f = r.failure();
  if (f && /ERR_ABORTED/.test(f.errorText)) return; // ours
  add('Request failed', r.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 120));
});
page.on('response', r => {
  if (r.status() >= 500) add('Server error ' + r.status(), r.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 120));
  if (r.status() === 404 && r.request().resourceType() !== 'document') {
    add('Missing file (404)', r.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 120));
  }
});
// A child cannot dismiss a browser dialog, so a stray one is a dead end.
page.on('dialog', async d => { add('Blocking dialog', d.type() + ': ' + d.message().slice(0, 100)); await d.dismiss().catch(() => {}); });

console.log(`kid mode — seed ${SEED}, ${STEPS} steps, tablet 768x1024`);
console.log(`target: ${SITE}\n`);

await page.goto(SITE, { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForTimeout(4000);

// Count DOM changes so a click that does nothing at all can be told apart from
// one that worked. Re-installed after every navigation.
const installWatcher = async () => {
  await page.evaluate(() => {
    if (window.__afMut) return;
    window.__afMut = 0;
    new MutationObserver(m => { window.__afMut += m.length; }).observe(
      document.documentElement, { childList: true, subtree: true, attributes: true }
    );
  }).catch(() => {});
};
await installWatcher();
page.on('framenavigated', f => { if (f === page.mainFrame()) installWatcher(); });

const overflowCheck = async where => {
  const o = await page.evaluate(() => {
    const d = document.documentElement;
    return { scroll: d.scrollWidth, client: d.clientWidth };
  }).catch(() => null);
  if (o && o.scroll > o.client + 4) {
    add('Page scrolls sideways on a narrow screen', `${where} — ${o.scroll}px of content in a ${o.client}px window`);
  }
};

await overflowCheck('/');

for (let step = 1; step <= STEPS; step++) {
  const action = pick(['click', 'click', 'click', 'doubleclick', 'type', 'key', 'back', 'scroll']);

  try {
    if (action === 'click' || action === 'doubleclick') {
      // Whatever looks tappable and is actually on screen.
      const targets = await page.$$('a, button, [role="button"], input[type="submit"], .button, [onclick]');
      const visible = [];
      for (const t of targets.slice(0, 120)) {
        const box = await t.boundingBox().catch(() => null);
        if (box && box.width > 8 && box.height > 8 && box.y < 4000) visible.push({ t, box });
      }
      if (!visible.length) { await page.goto(SITE, { waitUntil: 'domcontentloaded' }).catch(() => {}); continue; }

      // Biggest thing wins, most of the time — that is how a child chooses.
      visible.sort((a, b) => (b.box.width * b.box.height) - (a.box.width * a.box.height));
      const chosen = rnd() < 0.6 ? visible[Math.floor(rnd() * Math.min(6, visible.length))] : pick(visible);

      const label = (await chosen.t.innerText().catch(() => '') || await chosen.t.getAttribute('aria-label').catch(() => '') || '(no label)')
        .replace(/\s+/g, ' ').trim().slice(0, 48);
      const urlBefore = page.url();

      // A link pointing at the page you are already on legitimately changes
      // nothing, so it must not be reported as a control that swallows taps.
      const href = await chosen.t.getAttribute('href').catch(() => null);
      let selfLink = false;
      if (href !== null) {
        try {
          const target = new URL(href, urlBefore);
          const here = new URL(urlBefore);
          selfLink = (target.origin + target.pathname + target.search) === (here.origin + here.pathname + here.search);
        } catch { selfLink = href.startsWith('#') || href === ''; }
      }

      await page.evaluate(() => { window.__afMut = 0; }).catch(() => {});

      await chosen.t.scrollIntoViewIfNeeded({ timeout: 3000 }).catch(() => {});
      await chosen.t.click({ timeout: 4000, force: true });
      if (action === 'doubleclick') {
        // The impatient second and third tap, before anything has responded.
        await chosen.t.click({ timeout: 3000, force: true }).catch(() => {});
        await chosen.t.click({ timeout: 3000, force: true }).catch(() => {});
      }
      await page.waitForTimeout(1200);

      const moved = page.url() !== urlBefore;
      const mutated = await page.evaluate(() => window.__afMut || 0).catch(() => 1);
      if (!moved && mutated === 0 && !selfLink && label !== '(no label)') {
        add('Tapping this does nothing', `"${label}" on ${urlBefore.replace(/^https?:\/\/[^/]+/, '') || '/'}`);
      }
      if (moved) await overflowCheck(page.url().replace(/^https?:\/\/[^/]+/, '') || '/');

    } else if (action === 'type') {
      const boxes = await page.$$('input[type="text"], input[type="search"], input[type="number"], input:not([type]), textarea');
      if (boxes.length) {
        const b = pick(boxes);
        await b.fill(pick(GIBBERISH), { timeout: 3000 }).catch(() => {});
        if (rnd() < 0.5) await b.press('Enter', { timeout: 3000 }).catch(() => {});
        await page.waitForTimeout(1200);
      }

    } else if (action === 'key') {
      await page.keyboard.press(pick(['Enter', 'Escape', 'Tab', 'ArrowDown', 'Backspace', ' ']));
      await page.waitForTimeout(400);

    } else if (action === 'back') {
      await page.goBack({ timeout: 15000 }).catch(() => {});
      await page.waitForTimeout(1000);

    } else {
      await page.mouse.wheel(0, Math.floor(rnd() * 6000) - 1500);
      await page.waitForTimeout(400);
    }
  } catch (e) {
    const msg = String(e.message).split('\n')[0].slice(0, 120);
    if (!/Timeout|not visible|detached|intercepts pointer|closed/i.test(msg)) {
      add('Interaction broke', msg);
    }
  }

  if (step % 10 === 0) process.stdout.write(`  ...${step}/${STEPS} steps, ${issues.length} distinct issues\n`);
}

await page.screenshot({ path: 'kid-mode-final.png', fullPage: false }).catch(() => {});

console.log(`\n${'='.repeat(64)}`);
console.log(`KID MODE RESULT — seed ${SEED}`);
console.log('='.repeat(64));
console.log(`ended on: ${page.url()}`);
console.log(`writes blocked on purpose: ${blocked}\n`);

if (!issues.length) {
  console.log('Nothing broke. A five-year-old could not find a loose thread.');
} else {
  const order = ['Uncaught JS error', 'Server error 500', 'Blocking dialog', 'Interaction broke',
                 'Request failed', 'Console error', 'Page scrolls sideways on a narrow screen',
                 'Tapping this does nothing', 'Missing file (404)'];
  issues.sort((a, b) => {
    const ai = order.findIndex(o => a.kind.startsWith(o.split(' ')[0]));
    const bi = order.findIndex(o => b.kind.startsWith(o.split(' ')[0]));
    return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi) || b.count - a.count;
  });
  for (const i of issues) {
    console.log(`  [${String(i.count).padStart(2)}x] ${i.kind}: ${i.detail}`);
  }
}
console.log(`\n${issues.length} distinct issue(s). Re-run this exact walk with AF_QA_SEED=${SEED}.`);

await browser.close();
// A monkey test that fails the build on every stray console warning gets muted
// within a week, so only the things that are unambiguously broken do that.
const hard = issues.filter(i => /Uncaught JS error|Server error|Blocking dialog|Interaction broke/.test(i.kind));
process.exit(hard.length ? 1 : 0);
