/**
 * The black pill in the phone's slide-out menu. Two things the first passes
 * got wrong: they clicked a header nav item rather than the burger, and they
 * looked for the theme's own .postero-mobile-nav, which is not the panel in
 * the owner's screenshot - that one is the Elementor (HFE) off-canvas.
 *
 * So: try every plausible toggle until something wide and tall appears on the
 * left; then list EVERY element on the page that is dark, rounded and at least
 * 150px wide, wherever it lives, with its text, ancestors and colour sources.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 423, height: 642, isMobile: true, hasTouch: true });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 8000));

const panelOpen = () => p.evaluate(() => {
  const vw = window.innerWidth;
  return [...document.querySelectorAll('*')].some(e => {
    const c = getComputedStyle(e), r = e.getBoundingClientRect();
    return (c.position === 'fixed' || c.position === 'absolute') && c.display !== 'none' && c.visibility !== 'hidden'
      && r.height > 400 && r.width > 200 && r.width < vw && r.left >= -2 && r.left < 40 && c.opacity !== '0';
  });
});

const TOGGLES = [
  '.hfe-nav-menu__toggle', '.hfe-nav-menu-icon', '[class*="hfe-nav-menu"] .hfe-nav-menu__toggle',
  '.elementor-menu-toggle', '.hfe-menu-toggle', '[aria-label*="Menu" i]', '[aria-label*="menu" i]',
  '.menu-toggle', '.mobile-menu-toggle', '.hamburger', '[class*="burger"]', '.header-mobile-menu-toggle',
];
let used = null;
for (const sel of TOGGLES) {
  const clicked = await p.evaluate((sel) => {
    const els = [...document.querySelectorAll(sel)].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0; });
    if (!els.length) return false;
    els[0].click(); return true;
  }, sel);
  if (!clicked) continue;
  await new Promise(r => setTimeout(r, 3500));
  if (await panelOpen()) { used = sel; break; }
}
console.log('menu opened via: ' + (used || 'NOTHING WORKED'));

const out = await p.evaluate(() => {
  const lum = css => { const m = (css||'').match(/\d+(\.\d+)?/g); if (!m || m.length < 3) return null;
    const a = m[3] !== undefined ? parseFloat(m[3]) : 1; if (a === 0) return null;
    return 0.2126*+m[0] + 0.7152*+m[1] + 0.0722*+m[2]; };
  const chain = el => { const out = []; let n = el.parentElement;
    while (n && n !== document.body && out.length < 6) { out.push(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + '.' + String(n.className).split(/\s+/).slice(0,2).join('.')); n = n.parentElement; }
    return out.join(' < '); };
  return [...document.querySelectorAll('*')].map(e => {
    const c = getComputedStyle(e), r = e.getBoundingClientRect();
    if (c.display === 'none' || c.visibility === 'hidden' || r.width < 150 || r.height < 30) return null;
    const bgL = lum(c.backgroundColor);
    if (bgL === null || bgL > 60) return null;
    const rad = parseFloat(c.borderTopLeftRadius) || 0;
    return { tag: e.tagName.toLowerCase(), id: e.id, cls: String(e.className).slice(0, 70),
             txt: (e.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 50),
             box: Math.round(r.left)+','+Math.round(r.top)+' '+Math.round(r.width)+'x'+Math.round(r.height),
             bg: c.backgroundColor, color: c.color, radius: c.borderTopLeftRadius, pos: c.position, z: c.zIndex,
             rounded: rad >= 16, chain: chain(e) };
  }).filter(Boolean);
});

console.log('\ndark elements >=150px wide on the page (' + out.length + '):');
out.forEach(i => console.log((i.rounded ? '### ROUNDED ' : '    ') + i.tag + (i.id ? '#' + i.id : '') + ' [' + i.cls + '] "' + i.txt + '"  ' + i.box + '  bg=' + i.bg + ' color=' + i.color + ' r=' + i.radius + ' pos=' + i.pos + ' z=' + i.z + '\n        in: ' + i.chain));
await b.close();
