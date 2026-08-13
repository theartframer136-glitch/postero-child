// Click a collection circle on the LIVE homepage with a real Chromium mouse
// and report exactly what happens — how custom.js is served (an optimizer may
// combine it), what element actually sits under the pointer, and whether the
// visible products change. Run from the Verify Circles Live workflow.
// Read-only against the site; exits 0 only when the click visibly works.
import { chromium } from 'playwright';

const SITE = 'https://theartframer.us/?af_debug_circles=1&afv=' + Math.floor(Date.now() / 1000);

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1920, height: 1080 },
  userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 AF-Verify',
});
const page = await ctx.newPage();

const ajax = [];
page.on('response', r => {
  const u = r.url();
  if (u.includes('admin-ajax.php')) {
    const body = (r.request().postData() || '').slice(0, 90);
    if (!body.includes('WebKitFormBoundary')) ajax.push(r.status() + ' <- ' + body);
  }
});
page.on('pageerror', e => console.log('PAGE ERROR:', e.message.slice(0, 200)));

console.log('loading', SITE);
await page.goto(SITE, { waitUntil: 'load', timeout: 120000 });
await page.waitForTimeout(9000);

// ── 1. How is our JS actually delivered? An optimizer may combine/minify it. ──
const scripts = await page.evaluate(() => Array.from(document.querySelectorAll('script[src]'))
  .map(s => s.src)
  .filter(u => /custom|litespeed|min\.js|combined|\/cache\//i.test(u))
  .slice(0, 12));
console.log('candidate script URLs:', JSON.stringify(scripts, null, 1));

// The fix is identifiable by source: old code arms af-dragging inside the
// pointerdown handler; fixed code arms it only after real movement.
const jsCheck = await page.evaluate(async (urls) => {
  const out = [];
  for (const u of urls) {
    try {
      const t = await (await fetch(u, { cache: 'reload' })).text();
      if (!t.includes('af-dragging')) continue;
      const i = t.indexOf('pointerdown');
      const seg = i >= 0 ? t.slice(i, i + 1200) : '';
      out.push({
        url: u.slice(-90),
        bytes: t.length,
        OLD_pointerdownArmsDragging: /af-dragging['"]\s*\)/.test(seg) && seg.indexOf('pointermove') === -1,
        NEW_hasStaleMovedFix: t.includes("pointerup") && t.includes("moved = false"),
      });
    } catch (e) { out.push({ url: u.slice(-90), error: String(e).slice(0, 80) }); }
  }
  return out;
}, scripts);
console.log('js carrying the drag guard:', JSON.stringify(jsCheck, null, 1));

// ── 2. Find a circle that a human can actually see and click ──
const target = await page.evaluate(() => {
  const strips = Array.from(document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content'));
  const report = strips.map(s => {
    const r = s.getBoundingClientRect();
    return { cls: (s.id || s.className).toString().slice(0, 40), w: Math.round(r.width), h: Math.round(r.height), items: s.querySelectorAll('li.cat-item, .sub-cat').length, visible: !!s.offsetParent && r.width > 0 && r.height > 0 };
  });
  for (const strip of strips) {
    if (!strip.offsetParent) continue;
    const items = Array.from(strip.querySelectorAll('li.cat-item, .sub-cat'));
    for (const it of items) {
      const r = it.getBoundingClientRect();
      if (r.width > 20 && r.height > 20 && it.offsetParent) {
        it.scrollIntoView({ block: 'center' });
        return { report, label: (it.textContent || '').trim().slice(0, 40), found: true };
      }
    }
  }
  return { report, found: false };
});
console.log('strips on page:', JSON.stringify(target.report));
if (!target.found) { console.log('RESULT: NO VISIBLE CIRCLE FOUND'); await page.screenshot({ path: 'before.png' }); await browser.close(); process.exit(2); }
await page.waitForTimeout(1200);

// Re-locate after scrolling and see WHAT IS ON TOP at the click point
const hit = await page.evaluate((label) => {
  const items = Array.from(document.querySelectorAll('li.cat-item, .sub-cat'))
    .filter(e => (e.textContent || '').trim().slice(0, 40) === label && e.offsetParent);
  const el = items[0];
  if (!el) return null;
  const r = el.getBoundingClientRect();
  const x = r.x + r.width / 2, y = r.y + Math.min(r.height / 2, 40);
  const top = document.elementFromPoint(x, y);
  return {
    x, y,
    topElement: top ? (top.tagName + '.' + (top.className || '').toString().slice(0, 60)) : 'none',
    topIsInsideCircle: !!(top && el.contains(top)),
  };
}, target.label);
console.log('click point:', JSON.stringify(hit));

const snap = () => page.evaluate(() => {
  const shell = document.querySelector('.af-shell');
  const g = document.querySelector('#productGrid, .product-slider');
  return {
    url: location.href.split('&afv=')[0],
    visibleProducts: (shell || g ? (shell || g).innerText : '').replace(/\s+/g, ' ').slice(0, 120),
    active: Array.from(document.querySelectorAll('.active')).map(e => (e.textContent || '').trim().slice(0, 25)).slice(0, 5),
    dbg: (document.getElementById('af-circdbg') || { innerText: 'absent' }).innerText.replace(/\s+/g, ' ').slice(0, 400),
  };
});

const before = await snap();
await page.screenshot({ path: 'before.png' });
console.log('clicking:', JSON.stringify(target.label));

await page.mouse.move(hit.x, hit.y);
await page.mouse.down();
await page.waitForTimeout(140);
await page.mouse.up();
await page.waitForTimeout(8000);

const after = await snap().catch(() => ({ url: page.url(), visibleProducts: 'NAVIGATED', dbg: '' }));
await page.screenshot({ path: 'after.png' }).catch(() => {});

console.log('ajax:', JSON.stringify(ajax));
console.log('BEFORE:', JSON.stringify(before));
console.log('AFTER :', JSON.stringify(after));

const worked = after.url !== before.url || after.visibleProducts !== before.visibleProducts;
console.log(worked ? 'RESULT: CLICK WORKED' : 'RESULT: CLICK DEAD');
await browser.close();
process.exit(worked ? 0 : 1);
