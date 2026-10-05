// The computed look of the home page hero slideshow as Elementor Pro draws it
// (slides #80f8de4 on a desktop, #0971963 on a phone), so the theme's own
// slides widget can match it exactly once Elementor Pro is switched off
// (owner, 5 Oct: "keep everything free"). Prints the arrows' and dots' HTML and
// the computed styles that place and size every part.
//
// Read-only.
//
// Run: node tools/probe-slides-style.mjs [site]
import { chromium } from 'playwright';

const SITE = (process.argv.slice(2).find(a => a.includes('://')) || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const PROPS = ['display', 'position', 'top', 'right', 'bottom', 'left', 'width', 'height', 'transform', 'z-index', 'color', 'fill',
  'font-size', 'background-color', 'background-size', 'background-position', 'background-repeat', 'opacity', 'margin', 'padding',
  'align-items', 'justify-content', 'cursor', 'border-radius', 'overflow', 'min-width', 'min-height', 'text-align', 'inset'];
for (const [label, w, h, id] of [['desktop', 1440, 900, '80f8de4'], ['phone', 390, 844, '0971963']]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, isMobile: label === 'phone', hasTouch: label === 'phone' });
  const page = await ctx.newPage();
  await page.goto(SITE + '/', { waitUntil: 'load', timeout: 60000 }).catch(() => null);
  await page.waitForTimeout(3000);
  const out = await page.evaluate(({ id, PROPS }) => {
    const root = document.querySelector('.elementor-element-' + id);
    if (!root) return { missing: true };
    const pick = (sel) => {
      const el = sel ? root.querySelector(sel) : root;
      if (!el) return null;
      const cs = getComputedStyle(el), b = el.getBoundingClientRect();
      const o = { box: Math.round(b.x) + ',' + Math.round(b.y) + ' ' + Math.round(b.width) + 'x' + Math.round(b.height) };
      for (const p of PROPS) o[p] = cs.getPropertyValue(p);
      return o;
    };
    const wrap = root.querySelector('.elementor-slides-wrapper');
    const tail = wrap ? [...wrap.children].filter(c => !c.classList.contains('swiper-wrapper')).map(c => c.outerHTML.replace(/\s+/g, ' ').slice(0, 700)) : [];
    return {
      tail,
      bullets: root.querySelectorAll('.swiper-pagination-bullet').length,
      parts: {
        widget: pick(''), wrapper: pick('.elementor-slides-wrapper'), slide: pick('.swiper-slide-active') || pick('.swiper-slide'),
        bg: pick('.swiper-slide-active .swiper-slide-bg') || pick('.swiper-slide-bg'), inner: pick('.swiper-slide-active .swiper-slide-inner') || pick('.swiper-slide-inner'),
        contents: pick('.swiper-slide-active .swiper-slide-contents'), prev: pick('.elementor-swiper-button-prev'), next: pick('.elementor-swiper-button-next'),
        prevIcon: pick('.elementor-swiper-button-prev > *'), pagination: pick('.swiper-pagination'), bullet: pick('.swiper-pagination-bullet:not(.swiper-pagination-bullet-active)'),
        bulletActive: pick('.swiper-pagination-bullet-active'),
      },
    };
  }, { id, PROPS });
  console.log('\n######## ' + label + ' #' + id);
  if (out.missing) { console.log('  not on the page'); await ctx.close(); continue; }
  console.log('  bullets: ' + out.bullets);
  for (const t of out.tail) console.log('  tail html: ' + t);
  for (const [k, v] of Object.entries(out.parts)) {
    if (!v) { console.log('  ' + k + ': (none)'); continue; }
    console.log('  ' + k.padEnd(13) + Object.entries(v).filter(([p, x]) => x && x !== 'auto' && x !== 'normal' && x !== 'none' && x !== '0px' && x !== 'rgba(0, 0, 0, 0)').map(([p, x]) => p + '=' + x).join('; '));
  }
  await ctx.close();
}
console.log('\ndone');
await browser.close();
