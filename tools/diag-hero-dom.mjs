/**
 * Which hero is actually on a phone, and what collapses it?
 *
 * The first probe measured the first slides widget on the page and found it
 * 0x0 at 423px. This site swaps whole containers between desktop and phone
 * with Elementor's visibility classes (the header does exactly that), so the
 * widget it measured may simply be the desktop one. Enumerate every slides
 * widget, its visibility classes, its box, and - walking up to <body> - the
 * first ancestor that is display:none or has no height, with that
 * ancestor's classes. Then the same for the mobile-visible one's slides.
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
for (let i = 0; i < 6; i++) {
  let blocked = false;
  try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {}
  if (!blocked) break;
  await new Promise(r => setTimeout(r, 3000));
  try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {}
  await new Promise(r => setTimeout(r, 5000));
}

const out = await p.evaluate(() => {
  const vw = window.innerWidth;
  const r = el => { const q = el.getBoundingClientRect(); return { l: Math.round(q.left), t: Math.round(q.top), w: Math.round(q.width), h: Math.round(q.height) }; };
  const cls = el => [...el.classList].filter(c => /hidden|elementor-element-|e-con|swiper|slides|af-/.test(c)).slice(0, 8).join(' ');
  const widgets = [...document.querySelectorAll('.elementor-widget-slides, .elementor-slides-wrapper, [data-widget_type^="slides"]')];
  // dedupe nested matches: keep outermost
  const outer = widgets.filter(w => !widgets.some(o => o !== w && o.contains(w)));
  return {
    viewport: vw,
    count: outer.length,
    widgets: outer.map((w, i) => {
      const cs = getComputedStyle(w);
      // first collapsing ancestor
      let n = w, collapsed = null;
      while (n && n !== document.body) {
        const c = getComputedStyle(n), q = n.getBoundingClientRect();
        if (c.display === 'none' || c.visibility === 'hidden' || (q.height === 0 && q.width === 0)) {
          collapsed = { tag: n.tagName.toLowerCase(), id: n.id || '', cls: cls(n), display: c.display, visibility: c.visibility, box: r(n) };
        }
        n = n.parentElement;
      }
      const slides = [...w.querySelectorAll('.swiper-slide')];
      const active = slides.find(s => s.classList.contains('swiper-slide-active')) || slides[0];
      const bg = active && active.querySelector('.swiper-slide-bg');
      const wrap = w.querySelector('.swiper-wrapper');
      return {
        i, id: w.getAttribute('data-id') || w.id || '', cls: cls(w),
        display: cs.display, visibility: cs.visibility, box: r(w),
        highestCollapsedAncestor: collapsed,
        slides: slides.length,
        wrapperTransform: wrap ? getComputedStyle(wrap).transform : null,
        activeSlide: active ? { box: r(active), inlineW: active.style.width || '' } : null,
        activeBg: bg ? { box: r(bg), img: /url\(/.test(getComputedStyle(bg).backgroundImage), size: getComputedStyle(bg).backgroundSize } : null,
        inView: !!active && (function(){ const q = active.getBoundingClientRect(); return q.right > 0 && q.left < vw && q.height > 0; })(),
      };
    }),
    // anything else that looks like a hero in the first screen?
    firstScreenBigBoxes: [...document.querySelectorAll('section, .e-con, .elementor-section')].filter(e => {
      const q = e.getBoundingClientRect(); return q.top < 700 && q.height > 120 && q.width > 300; })
      .slice(0, 6).map(e => ({ cls: cls(e), box: r(e) })),
  };
});

console.log('viewport ' + out.viewport + '  slides widgets: ' + out.count);
out.widgets.forEach(w => {
  console.log('\n--- widget #' + w.i + '  data-id=' + w.id + '  [' + w.cls + ']');
  console.log('    display=' + w.display + ' visibility=' + w.visibility + ' box=' + JSON.stringify(w.box) + ' slides=' + w.slides);
  console.log('    collapsed by   : ' + (w.highestCollapsedAncestor ? JSON.stringify(w.highestCollapsedAncestor) : 'nothing - it is laid out'));
  console.log('    wrapper transform: ' + w.wrapperTransform);
  console.log('    active slide   : ' + JSON.stringify(w.activeSlide) + '  in view=' + w.inView);
  console.log('    active bg      : ' + JSON.stringify(w.activeBg));
});
console.log('\nbig boxes in the first screen: ' + JSON.stringify(out.firstScreenBigBoxes));
await b.close();
