/**
 * What the site actually looks like on a phone.
 *
 * Every mobile fix so far has been made from a screenshot the owner sent and
 * verified by sending them back to look again. That is a slow loop and it only
 * ever covers the one page they happened to be on. This loads the real site in
 * a real phone-sized Chrome and reports the faults that screenshots make you
 * squint for:
 *
 *   sideways scroll      the single worst mobile fault — and it names the
 *                        element responsible, which a screenshot never can
 *   crushed text         a column so narrow that words break mid-character;
 *                        this is what made the inventory table unreadable, and
 *                        it is invisible to any check that only measures widths
 *   buried furniture     fixed buttons sitting under the bottom navigation bar,
 *                        exactly how the WhatsApp and wishlist buttons were
 *                        lost on mobile
 *   tiny tap targets     anything under 40px that a finger has to hit
 *   unreadable type      body text under 12px
 *
 * Read-only. It loads pages and measures them; nothing is written anywhere.
 *
 * Usage: node tools/headless-mobile.js [width] [url ...]
 */
const puppeteer = require('puppeteer-core');

const argv = process.argv.slice(2);
const WIDTH = /^\d+$/.test(argv[0] || '') ? parseInt(argv.shift(), 10) : 420;
const BASE = 'https://theartframer.us';
const URLS = argv.length ? argv : [
  BASE + '/',
  BASE + '/?s=Krishna',
  BASE + '/shop/',
  BASE + '/inventory-management/',
];

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Runs inside the page. Returns plain data — no DOM nodes cross the bridge. */
function audit(vw) {
  const out = {
    scrollWidth: Math.round(document.documentElement.scrollWidth),
    clientWidth: Math.round(document.documentElement.clientWidth),
    overflowers: [], crushed: [], buried: [], tiny: [], smallText: [], fixed: [],
  };

  const name = (el) => {
    const id = el.id ? '#' + el.id : '';
    const cls = (typeof el.className === 'string' && el.className)
      ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '';
    return (el.tagName || '?').toLowerCase() + id + cls;
  };

  const all = Array.from(document.querySelectorAll('body *'));

  // ── Sideways scroll: who sticks out past the right edge? Report the
  //    OUTERMOST offenders only — a wide element makes every child look wide
  //    too, and a list of forty children names the symptom, not the cause.
  const wide = [];
  for (const el of all) {
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) continue;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    if (cs.position === 'fixed') continue;               // counted separately
    // An off-canvas drawer — the slide-in cart, the mobile menu — is PARKED
    // entirely off the right edge on purpose and slides in when opened. It is
    // not overflow, and reporting it every run trains you to ignore the
    // section that also holds the real faults.
    if (r.left >= vw - 1) continue;
    if (r.right > vw + 1) wide.push({ el, r });
  }
  for (const w of wide) {
    if (wide.some((o) => o !== w && o.el.contains(w.el))) continue;
    out.overflowers.push({
      el: name(w.el),
      right: Math.round(w.r.right),
      width: Math.round(w.r.width),
      over: Math.round(w.r.right - vw),
    });
  }

  // ── Crushed text: a box holding real words whose content box is narrower
  //    than a few characters. That is the "P R O D U C T stacked vertically"
  //    fault, and measuring width alone would not find it — the giveaway is a
  //    box far taller than one line while being only a few characters wide.
  for (const el of all) {
    if (el.children.length) continue;                    // leaf text only
    const txt = (el.textContent || '').trim();
    if (txt.length < 4 || !/[A-Za-z]{3}/.test(txt)) continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    const fs = parseFloat(getComputedStyle(el).fontSize) || 14;
    // Under about three characters wide, and tall enough to have wrapped many
    // times over: that is a column collapsed to nothing.
    if (r.width < fs * 3 && r.height > fs * 3) {
      out.crushed.push({
        el: name(el), w: Math.round(r.width), h: Math.round(r.height),
        text: txt.slice(0, 28),
      });
    }
  }

  // ── Fixed furniture, and whether anything is buried under something else.
  const fixed = all.filter((el) => {
    const cs = getComputedStyle(el);
    if (cs.position !== 'fixed' || cs.display === 'none' || cs.visibility === 'hidden') return false;
    if (parseFloat(cs.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    return r.width > 8 && r.height > 8;
  }).filter((el, _i, arr) => !arr.some((o) => o !== el && o.contains(el)));

  for (const el of fixed) {
    const r = el.getBoundingClientRect();
    out.fixed.push({
      el: name(el),
      box: [Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)],
      z: getComputedStyle(el).zIndex,
    });
    // Is its own centre painted by something else? That is the test that
    // caught the quick panel sitting under the bottom navigation bar.
    const cx = Math.round(r.left + r.width / 2);
    const cy = Math.round(r.top + r.height / 2);
    if (cx < 0 || cy < 0 || cx > vw || cy > window.innerHeight) continue;
    const top = document.elementFromPoint(cx, cy);
    if (top && !el.contains(top) && top !== el) {
      out.buried.push({ el: name(el), coveredBy: name(top) });
    }
  }

  // ── Tap targets and type size, on things a finger is meant to hit.
  const seenT = new Set();
  for (const el of document.querySelectorAll('a[href], button, input, select, [role="button"]')) {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    if (r.top > window.innerHeight * 4) continue;        // far below the fold
    const key = name(el) + Math.round(r.width) + 'x' + Math.round(r.height);
    if (seenT.has(key)) continue;
    seenT.add(key);
    if (r.height < 40 || r.width < 24) {
      out.tiny.push({ el: name(el), w: Math.round(r.width), h: Math.round(r.height) });
    }
    const fs = parseFloat(cs.fontSize) || 0;
    if (fs && fs < 12 && (el.textContent || '').trim().length > 2) {
      out.smallText.push({ el: name(el), px: Math.round(fs * 10) / 10 });
    }
  }

  const cap = (a, n) => a.slice(0, n);
  out.overflowers = cap(out.overflowers, 8);
  out.crushed = cap(out.crushed, 8);
  out.tiny = cap(out.tiny, 10);
  out.smallText = cap(out.smallText, 8);
  return out;
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  let problems = 0;
  try {
    console.log(`=== MOBILE AUDIT at ${WIDTH}px ===`);
    for (const url of URLS) {
      const page = await browser.newPage();
      // A real phone UA and touch: the crawl guard on this site redirects a
      // search that arrives without Sec-Fetch headers, and a desktop UA would
      // not get the mobile layout at all.
      await page.setUserAgent('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
        + '(KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36');
      await page.setViewport({ width: WIDTH, height: 900, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });

      let loaded = false;
      for (let attempt = 1; attempt <= 3 && !loaded; attempt++) {
        try {
          await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
          loaded = true;
        } catch (e) { await sleep(5000); }
      }
      console.log(`\n─── ${url}`);
      if (!loaded) { console.log('   the host did not answer three times — not measured'); await page.close(); continue; }
      await sleep(5000);                       // let late scripts place things
      await page.evaluate(() => window.scrollTo(0, 400));
      await sleep(1200);

      let r;
      try { r = await page.evaluate(audit, WIDTH); }
      catch (e) { console.log('   measure failed: ' + e.message); await page.close(); continue; }

      const sideways = r.scrollWidth > r.clientWidth + 1;
      console.log(`   page ${r.scrollWidth}px wide in a ${r.clientWidth}px window`
        + (sideways ? `  ← SCROLLS SIDEWAYS by ${r.scrollWidth - r.clientWidth}px` : '  ✓ no sideways scroll'));
      if (sideways) problems++;

      if (r.overflowers.length) {
        problems++;
        console.log('   sticking out past the right edge:');
        r.overflowers.forEach((o) => console.log(`     ${o.el}  ${o.width}px wide, ${o.over}px over`));
      }
      if (r.crushed.length) {
        problems++;
        console.log('   TEXT CRUSHED into a sliver (words breaking mid-character):');
        r.crushed.forEach((c) => console.log(`     ${c.el}  ${c.w}x${c.h}px  "${c.text}"`));
      }
      if (r.buried.length) {
        problems++;
        console.log('   FIXED BUTTONS BURIED under something else:');
        r.buried.forEach((b) => console.log(`     ${b.el}  covered by ${b.coveredBy}`));
      }
      if (r.fixed.length) {
        console.log('   floating furniture (left, top, w, h):');
        r.fixed.forEach((f) => console.log(`     ${f.el}  [${f.box.join(', ')}]  z=${f.z}`));
      }
      if (r.tiny.length) {
        console.log('   tap targets under 40px tall:');
        r.tiny.forEach((t) => console.log(`     ${t.el}  ${t.w}x${t.h}`));
      }
      if (r.smallText.length) {
        console.log('   text under 12px:');
        r.smallText.forEach((t) => console.log(`     ${t.el}  ${t.px}px`));
      }
      await page.close();
    }
    console.log(`\n=== ${problems ? problems + ' page(s) with a layout fault' : 'no layout faults found'} ===`);
  } catch (e) {
    console.log('AUDIT ERROR: ' + e.message);
  } finally {
    await browser.close();
  }
})();
