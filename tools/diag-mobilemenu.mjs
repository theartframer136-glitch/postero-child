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
  const vw = window.innerWidth;
  const panel = [...document.querySelectorAll('*')].find(e => {
    const c = getComputedStyle(e), r = e.getBoundingClientRect();
    return (c.position === 'fixed' || c.position === 'absolute') && c.display !== 'none' && c.visibility !== 'hidden'
      && r.height > 400 && r.width > 200 && r.width < vw && r.left >= -2 && r.left < 40 && c.opacity !== '0';
  });
  if (!panel) return 'no panel';
  const lines = [];
  const desc = (e, pseudo) => { const c = getComputedStyle(e, pseudo || null); const r = e.getBoundingClientRect();
    return (pseudo || '') + e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className).split(/\s+/).slice(0,4).join('.') +
      ' ' + Math.round(r.left)+','+Math.round(r.top)+' '+Math.round(r.width)+'x'+Math.round(r.height) +
      ' d=' + c.display + ' bg=' + c.backgroundColor + (c.backgroundImage !== 'none' ? ' bgi=' + c.backgroundImage.slice(0,60) : '') +
      ' col=' + c.color + ' r=' + c.borderTopLeftRadius + ' bd=' + c.borderTopWidth + ' ' + c.borderTopColor + ' h=' + c.height + ' p=' + c.padding +
      (pseudo ? ' content=' + c.content : ' txt="' + (e.childNodes.length ? [...e.childNodes].filter(n=>n.nodeType===3).map(n=>n.textContent.trim()).join('|').slice(0,40) : '') + '"'); };
  lines.push('PANEL ' + desc(panel));
  lines.push('panel outerHTML head: ' + panel.outerHTML.slice(0, 300));
  const walk = (e, depth) => {
    if (depth > 12) return;
    for (const ch of e.children) {
      const r = ch.getBoundingClientRect(); const c = getComputedStyle(ch);
      if (c.display === 'none') continue;
      if (r.top > 900) continue;
      lines.push('  '.repeat(depth) + desc(ch));
      for (const ps of ['::before', '::after']) { const pc = getComputedStyle(ch, ps); if (pc.content !== 'none' && pc.content !== 'normal' && pc.display !== 'none') lines.push('  '.repeat(depth) + '  ' + desc(ch, ps)); }
      walk(ch, depth + 1);
    }
  };
  walk(panel, 1);
  return lines.join('\n');
});
console.log(out);
await b.close();
