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


// close popup + cookie card so the burger is clickable, then a REAL click
await p.evaluate(() => { for (const s of ['#afOverlay', '#af-consent', '.af-overlay']) document.querySelectorAll(s).forEach(e => e.remove()); });
const tg = await p.$('.hfe-nav-menu__toggle');
console.log('toggle found: ' + !!tg);
if (tg) { await tg.click(); await new Promise(r => setTimeout(r, 3000)); }
const out = await p.evaluate(() => {
  const lines = [];
  const desc = (e, pseudo) => { const c = getComputedStyle(e, pseudo || null); const r = e.getBoundingClientRect();
    return (pseudo || '') + e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className).split(/\s+/).slice(0,5).join('.') +
      ' ' + Math.round(r.left)+','+Math.round(r.top)+' '+Math.round(r.width)+'x'+Math.round(r.height) +
      ' d=' + c.display + ' vis=' + c.visibility + ' bg=' + c.backgroundColor + (c.backgroundImage !== 'none' ? ' bgi=' + c.backgroundImage.slice(0,50) : '') +
      ' col=' + c.color + ' r=' + c.borderTopLeftRadius + ' bd=' + c.borderTopWidth + ' ' + c.borderTopColor + ' p=' + c.padding + ' m=' + c.margin +
      (pseudo ? ' content=' + c.content : ' txt="' + [...e.childNodes].filter(n=>n.nodeType===3).map(n=>n.textContent.trim()).filter(Boolean).join('|').slice(0,40) + '"'); };
  const roots = document.querySelectorAll('.hfe-nav-menu, nav[class*="hfe-nav-menu"], .hfe-nav-menu__layout-horizontal, .hfe-nav-menu__layout-vertical');
  lines.push('nav roots: ' + roots.length + '  html.classes=' + document.documentElement.className.slice(0,80) + ' body.menu-open=' + /open/i.test(document.body.className));
  const walk = (e, depth) => {
    if (depth > 10) return;
    for (const ch of e.children) {
      const c = getComputedStyle(ch);
      lines.push('  '.repeat(depth) + desc(ch));
      for (const ps of ['::before', '::after']) { const pc = getComputedStyle(ch, ps); if (pc.content !== 'none' && pc.content !== 'normal' && pc.display !== 'none') lines.push('  '.repeat(depth) + '  ' + desc(ch, ps)); }
      if (c.display !== 'none') walk(ch, depth + 1);
    }
  };
  roots.forEach((r, i) => { lines.push('ROOT ' + i + ': ' + desc(r)); walk(r, 1); });
  // also: any wide dark visible element in the first 900px
  [...document.querySelectorAll('*')].forEach(e => { const c = getComputedStyle(e), r = e.getBoundingClientRect();
    if (c.display === 'none' || c.visibility === 'hidden' || r.width < 150 || r.height < 30 || r.top > 900 || r.top < -50) return;
    const m = (c.backgroundColor||'').match(/\d+(\.\d+)?/g); if (!m) return; const a = m[3] !== undefined ? +m[3] : 1; if (a < .3) return;
    const l = 0.2126*m[0]+0.7152*m[1]+0.0722*m[2]; if (l > 60) return;
    lines.push('DARK-IN-VIEW ' + desc(e)); });
  return lines.join('\n');
});
console.log(out.split('\n').filter(l => /elementor-button|DARK-IN-VIEW|Deals|nav roots/.test(l)).join('\n'));
await b.close();
