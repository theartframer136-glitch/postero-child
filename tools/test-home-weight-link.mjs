// Tests for the "See all" link inc/home-weight.php draws under Shop by
// Collection — run with: node tools/test-home-weight-link.mjs (needs php and
// playwright).
//
// The module's footer script is rendered by PHP exactly as the site prints
// it, then run in Chromium over a page built like the homepage grid: the
// first fill, the homepage slider taking the cards (functions.php, "10.
// Product card slider"), a tab with every piece already showing, another big
// tab, and the slider rebuilding. It also checks the script does not wake
// itself: it runs from a MutationObserver, and a write on every pass would
// loop for ever.

import { chromium } from 'playwright';
import { execFileSync } from 'child_process';
import { fileURLToPath } from 'url';

const mod = fileURLToPath(new URL('../inc/home-weight.php', import.meta.url));
const php = `define('ABSPATH', '/');
$GLOBALS['h'] = array();
function add_action($h, $cb) { $GLOBALS['h'][$h][] = $cb; }
function add_filter($h, $cb) { $GLOBALS['h'][$h][] = $cb; }
function apply_filters($h, $v) { return $v; }
function is_front_page() { return true; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES); }
require ${JSON.stringify(mod)};
foreach ($GLOBALS['h']['wp_footer'] as $cb) $cb();
echo "\n@@MARKER@@" . af_home_collection_more_html('https://theartframer.us/product-category/radha-krishna/', 74, 12, 'Radha Krishna');`;
const [footer, marker] = execFileSync('php', ['-r', php], { encoding: 'utf8' }).split('@@MARKER@@');
const cards = Array.from({ length: 12 }, (_, i) => `<div class="product-card"><a href="/product/p${i}/">P${i}</a></div>`).join('');
const html = `<!doctype html><html><head><style>
#productGrid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
#productGrid > *{display:block !important;min-height:30px;background:#eee}
.af-grid-hidden{display:none !important}
.af-shell{display:flex;overflow:hidden}
</style></head><body class="home af-front-page">
<section class="product-container"><h2>Shop by Collection</h2><div id="productGrid">${cards}${marker}</div></section>
<footer>foot</footer>
${footer}
</body></html>`;
const wild = marker.replace('radha-krishna', 'wildlife').replace('"74"', '"51"').replace('Radha Krishna', 'Wildlife');
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
await p.setContent(html); await p.waitForTimeout(400);
let pass = 0, fail = 0; const ok = (c, w) => { (c ? pass++ : fail++); console.log((c ? '  ok   ' : '  FAIL ') + w); };
const state = () => p.evaluate(() => {
  const w = document.querySelectorAll('.af-coll-all-wrap'); const a = document.querySelector('.af-coll-all');
  const m = document.querySelector('.af-coll-more'); const r = a ? a.getBoundingClientRect() : null; const mr = m ? m.getBoundingClientRect() : null;
  return { wraps: w.length, text: a && a.textContent, href: a && a.getAttribute('href'), h: r && Math.round(r.height), vis: r && r.width > 0,
           prev: w[0] && w[0].previousElementSibling && (w[0].previousElementSibling.id || w[0].previousElementSibling.className),
           markerBox: mr ? mr.width + 'x' + mr.height : 'none' };
});
let s = await state();
ok(s.wraps === 1 && s.text === 'See all 74 in Radha Krishna →', 'first fill: one link, "' + s.text + '"');
ok(s.href === 'https://theartframer.us/product-category/radha-krishna/', 'pointing at the collection page');
ok(s.prev === 'productGrid', 'right under the grid while there is no slider');
ok(s.vis && s.h >= 44, 'visible, ' + s.h + 'px tall (44 or more)');
ok(s.markerBox === '0x0', 'the marker takes no room even under a "#productGrid > * {display:block !important}" rule');
// the homepage slider takes the cards (functions.php "10. Product card slider")
await p.evaluate(() => { const g = document.getElementById('productGrid'); const sh = document.createElement('div'); sh.className = 'af-shell';
  g.querySelectorAll('.product-card').forEach(c => sh.appendChild(c)); g.parentNode.insertBefore(sh, g.nextSibling); g.classList.add('af-grid-hidden'); });
await p.waitForTimeout(400); s = await state();
ok(s.wraps === 1 && s.prev === 'af-shell' && s.vis, 'slider built: the link moves under the slider and stays visible');
// idle: nothing keeps writing
const muts = await p.evaluate(() => new Promise(res => { let n = 0; const mo = new MutationObserver(r => n += r.length);
  mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, characterData: true }); setTimeout(() => { mo.disconnect(); res(n); }, 1500); }));
ok(muts === 0, 'left alone for 1.5 s: ' + muts + ' DOM changes (it does not wake itself)');
// a tab with every piece shown: no marker in the response
await p.evaluate(() => { const g = document.getElementById('productGrid'); document.querySelector('.af-shell').remove();
  g.innerHTML = '<div class="product-card">a</div>'.repeat(9); g.classList.remove('af-grid-hidden'); document.dispatchEvent(new Event('af_products_appended')); });
await p.waitForTimeout(400); s = await state();
ok(s.wraps === 0, 'a collection of 9, all shown: the link goes');
// another big tab, then the slider rebuilds
await p.evaluate((m) => { const g = document.getElementById('productGrid'); g.innerHTML = '<div class="product-card">w</div>'.repeat(12) + m; }, wild);
await p.waitForTimeout(400);
await p.evaluate(() => { const g = document.getElementById('productGrid'); const sh = document.createElement('div'); sh.className = 'af-shell';
  g.querySelectorAll('.product-card').forEach(c => sh.appendChild(c)); g.parentNode.insertBefore(sh, g.nextSibling); g.classList.add('af-grid-hidden'); });
await p.waitForTimeout(400); s = await state();
ok(s.wraps === 1 && s.text === 'See all 51 in Wildlife →' && s.href.indexOf('/wildlife/') > 0 && s.prev === 'af-shell', 'Wildlife tab: "' + s.text + '", under the new slider');
// the slider rebuilds again (it tears the old shell down first)
await p.evaluate(() => { const g = document.getElementById('productGrid'); const old = document.querySelector('.af-shell'); const cards = [...old.children];
  old.remove(); const sh = document.createElement('div'); sh.className = 'af-shell'; cards.forEach(c => sh.appendChild(c)); g.parentNode.insertBefore(sh, g.nextSibling); });
await p.waitForTimeout(400); s = await state();
ok(s.wraps === 1 && s.prev === 'af-shell', 'slider rebuilt: still exactly one link, under it');
console.log('\n' + (fail ? 'FAILED ' + fail + ' of ' + (pass + fail) : 'all ' + pass + ' passed'));
await b.close();
process.exit(fail ? 1 : 0);
