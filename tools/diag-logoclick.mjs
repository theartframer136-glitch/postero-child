/**
 * Clicking the site logo opens the Digital Download modal instead of going
 * home. This reports WHICH branch of the modal's trigger detection claims the
 * logo: the selector match, or the "walk up four levels and compare the
 * stripped text to digitaldownload" fallback - and what that text actually is.
 */
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const puppeteer = require('puppeteer-core');

const URL_ = process.env.AF_URL || 'https://theartframer.us/product-category/digital-downloads-2/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900 });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto(URL_, { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise((r) => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise((r) => setTimeout(r, 9000));
for (let i = 0; i < 6; i++) {
  let blocked = false;
  try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {}
  if (!blocked) break;
  await new Promise((r) => setTimeout(r, 3000));
  try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {}
  await new Promise((r) => setTimeout(r, 6000));
}
await p.evaluate(() => {
  const a = document.getElementById('af-ck-accept'); if (a) a.click();
  ['#afOverlay', '#af-consent', '.af-overlay'].forEach((s) => document.querySelectorAll(s).forEach((e) => e.remove()));
});
await new Promise((r) => setTimeout(r, 800));

console.log('url: ' + p.url());
console.log(await p.evaluate(() => {
  const logo = document.querySelector('.custom-logo-link, .elementor-widget-site-logo a, .hfe-site-logo a, .site-logo a, [class*="site-logo"] a, header a[href$="/"], header a[href*="theartframer"]');
  if (!logo) return 'no logo link found';
  const label = (e) => e ? (e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className || '').split(/\s+/).filter(Boolean).slice(0, 3).join('.')) : '(none)';
  const target = logo.querySelector('img') || logo;     // a real click lands on the image
  const out = { logo: label(logo), href: logo.getAttribute('href'), clickTargetWouldBe: label(target) };
  // branch 1: the selector
  const SEL = '.digital-download, .digital-download-btn, [class*="digital-download"], [data-digital-download]';
  const hit = target.closest(SEL);
  out.selectorMatch = hit ? label(hit) : '(no match)';
  // branch 2: the four-level text walk
  const walk = [];
  let node = target;
  for (let i = 0; i < 4 && node && node !== document.body; i++) {
    const txt = (node.textContent || '').replace(/[^a-z]/gi, '').toLowerCase();
    walk.push({ el: label(node), strippedText: txt.slice(0, 60), matches: txt === 'digitaldownload' });
    node = node.parentElement;
  }
  out.textWalk = walk;
  const CARD_SEL = '.product-card, li.product, .product, .product-block, [class*="product-block"]';
  const trg = hit || (walk.find((w) => w.matches) ? 'text-walk' : null);
  out.wouldTrigger = !!trg;
  out.cardFromLogo = label(target.closest(CARD_SEL));
  return JSON.stringify(out, null, 1).replace(/\n\s*/g, ' ');
}));

// and what actually happens
const before = p.url();
await p.evaluate(() => { const o = document.getElementById('af-dd-overlay'); if (o) o.classList.remove('open'); });
try {
  await p.click('.custom-logo-link, .elementor-widget-site-logo a, .hfe-site-logo a, .site-logo a, [class*="site-logo"] a');
} catch (e) { console.log('logo click threw: ' + e.message.slice(0, 80)); }
await new Promise((r) => setTimeout(r, 2500));
const after = await p.evaluate(() => ({
  ddOpen: !!(document.getElementById('af-dd-overlay') || {}).classList?.contains('open'),
  url: location.href,
}));
console.log('before: ' + before);
console.log('after clicking the logo: ' + JSON.stringify(after));
const P = (ok2, what) => console.log((ok2 ? 'PASS' : 'FAIL') + ': ' + what);
P(!after.ddOpen, 'the Digital Download modal does not open from the logo');
P(after.url !== before && /theartframer\.us\/?$/.test(after.url.replace(/[?#].*$/, '')), 'the logo goes home (' + after.url + ')');
await b.close();
