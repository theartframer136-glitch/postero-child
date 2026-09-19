/**
 * The phone's off-canvas menu shows a solid black pill under Deals & Discounts.
 * Open the menu the way a thumb does - the hamburger - then list every item:
 * text, box, background, colour, and the rule sources. Flag anything painted
 * near-black whose text is also near-black (invisible), and anything with no
 * text at all.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 8000));

// open the menu
const opened = await p.evaluate(() => {
  const cands = [...document.querySelectorAll('a,button,div,span')].filter(e => {
    const c = (e.className || '') + ' ' + (e.getAttribute('aria-label') || '');
    return /hamburger|menu-toggle|menu-btn|mobile-menu|offcanvas|off-canvas|burger|menu-icon|toggle-menu/i.test(String(c)) &&
           e.getBoundingClientRect().width > 0;
  });
  if (!cands.length) return null;
  cands[0].click();
  return String(cands[0].className).slice(0, 80);
});
console.log('opened via: ' + opened);
await new Promise(r => setTimeout(r, 5000));   // let the panel finish sliding in

const out = await p.evaluate(() => {
  const vw = window.innerWidth;
  // the panel: a fixed/absolute element, visible, occupying most of the height, left side
  const panels = [...document.querySelectorAll('*')].filter(e => {
    const c = getComputedStyle(e), r = e.getBoundingClientRect();
    return (c.position === 'fixed' || c.position === 'absolute') && c.display !== 'none' && c.visibility !== 'hidden'
      && r.height > 400 && r.width > 200 && r.width < vw && r.left <= 2;
  }).sort((a, b) => b.getBoundingClientRect().height - a.getBoundingClientRect().height);
  const panel = panels[0];
  if (!panel) return { err: 'no open menu panel found' };
  const lum = css => { const m = (css||'').match(/\d+(\.\d+)?/g); if (!m || m.length < 3) return null;
    const a = m[3] !== undefined ? parseFloat(m[3]) : 1; if (a === 0) return null;
    return (0.2126*+m[0] + 0.7152*+m[1] + 0.0722*+m[2]); };
  // Everything inside the panel that has a painted background or text - no
  // position filter this time, the panel was caught mid-slide last run.
  const items = [...panel.querySelectorAll('*')]
    .filter(e => { const r = e.getBoundingClientRect(); return r.height > 20 && r.width > 40; })
    .map(e => {
      const c = getComputedStyle(e), r = e.getBoundingClientRect();
      const own = [...e.childNodes].filter(n => n.nodeType === 3).map(n => n.textContent.trim()).join(' ').slice(0, 40);
      const txt = (e.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 40);
      const bgL = lum(c.backgroundColor), fgL = lum(c.color);
      return { tag: e.tagName.toLowerCase(), id: e.id, cls: String(e.className).slice(0, 60), txt, own,
               box: Math.round(r.left)+','+Math.round(r.top)+' '+Math.round(r.width)+'x'+Math.round(r.height),
               bg: c.backgroundColor, color: c.color, radius: c.borderRadius,
               dark: bgL !== null && bgL < 60,
               invisibleText: bgL !== null && fgL !== null && Math.abs(bgL - fgL) < 40 && bgL < 60 };
    })
    .filter(i => i.dark || i.txt);
  return { panelCls: String(panel.className).slice(0, 80), panelBox: Math.round(panel.getBoundingClientRect().width)+'x'+Math.round(panel.getBoundingClientRect().height), items };
});

if (out.err) { console.log(out.err); await b.close(); process.exit(0); }
console.log('panel: ' + out.panelCls + '  ' + out.panelBox + '\n');
out.items.forEach(i => console.log((i.dark ? '### DARK ' : '    ') + i.tag + (i.id ? '#' + i.id : '') + ' [' + i.cls + '] "' + i.txt + '"' + (i.own && i.own !== i.txt ? ' own="' + i.own + '"' : '') + '  ' + i.box + '  bg=' + i.bg + ' color=' + i.color + ' radius=' + i.radius + (i.invisibleText ? '   <-- TEXT INVISIBLE ON ITS BACKGROUND' : '') + (!i.txt ? '   <-- NO TEXT' : '')));
await b.close();
