/**
 * The homepage hero on a phone, two ways a phone can arrive at it:
 *   A. loaded at 423px            - what a real phone does
 *   B. loaded at 1280px, resized  - what the owner's DevTools recording does
 *
 * The child theme's mobile hero fix is gated on window.innerWidth at LOAD, so
 * the two may behave differently. For each: the slide widths Swiper left
 * inline, the wrapper's translate, whether the active slide's box actually
 * intersects the viewport, and whether its background image is present. A
 * hero whose slides are 1521px wide and translated four slides left inside a
 * 423px screen shows nothing - which is what the recording shows.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });

async function load(p) {
  let ok = false;
  for (let i = 1; i <= 3 && !ok; i++) {
    try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
    catch { await new Promise(r => setTimeout(r, 4000)); }
  }
  if (!ok) return false;
  await new Promise(r => setTimeout(r, 7000));
  for (let i = 0; i < 6; i++) {
    let blocked = false;
    try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {}
    if (!blocked) break;
    await new Promise(r => setTimeout(r, 3000));
    try { await p.reload({ waitUntil: 'domcontentloaded', timeout: 60000 }); } catch {}
    await new Promise(r => setTimeout(r, 5000));
  }
  return true;
}

const measure = (p) => p.evaluate(() => {
  const vw = window.innerWidth;
  const host = document.querySelector('.elementor-widget-slides, .elementor-slides-wrapper, .elementor-slides');
  if (!host) return { err: 'no slides widget on the page' };
  const wrap = host.querySelector('.swiper-wrapper');
  const slides = [...host.querySelectorAll('.swiper-slide')];
  const r = el => el.getBoundingClientRect();
  const hb = r(host);
  const active = slides.find(s => s.classList.contains('swiper-slide-active')) || slides[0];
  const abg = active && active.querySelector('.swiper-slide-bg');
  const inView = el => { const q = r(el); return q.right > 0 && q.left < vw && q.width > 0 && q.height > 0; };
  return {
    viewport: vw,
    host: { w: Math.round(hb.width), h: Math.round(hb.height), top: Math.round(hb.top) },
    wrapperTransform: wrap ? getComputedStyle(wrap).transform : null,
    slideCount: slides.length,
    inlineWidths: slides.slice(0, 6).map(s => s.style.width || '(none)'),
    slideBoxes: slides.slice(0, 6).map(s => { const q = r(s); return Math.round(q.left) + '..' + Math.round(q.right) + ' x' + Math.round(q.height); }),
    anySlideInView: slides.some(inView),
    activeIndex: slides.indexOf(active),
    activeInView: !!active && inView(active),
    activeBg: abg ? { hasImage: /url\(/.test(getComputedStyle(abg).backgroundImage), h: Math.round(r(abg).height), size: getComputedStyle(abg).backgroundSize } : null,
    ourFixRan: slides.some(s => (s.style.width || '') === '100vw'),
  };
});

const report = (label, m) => {
  console.log('\n=== ' + label + ' ===');
  if (m.err) { console.log('  ' + m.err); return; }
  console.log('  viewport            : ' + m.viewport);
  console.log('  hero box            : ' + JSON.stringify(m.host));
  console.log('  wrapper transform   : ' + m.wrapperTransform);
  console.log('  slides              : ' + m.slideCount + '  inline widths ' + JSON.stringify(m.inlineWidths));
  console.log('  slide boxes (l..r)  : ' + JSON.stringify(m.slideBoxes));
  console.log('  active slide        : #' + m.activeIndex + '  in view=' + m.activeInView + '  bg=' + JSON.stringify(m.activeBg));
  console.log('  any slide in view   : ' + m.anySlideInView);
  console.log('  our mobile fix ran  : ' + m.ourFixRan);
  console.log('  ' + (m.anySlideInView && m.activeBg && m.activeBg.hasImage && m.activeBg.h > 60 ? 'PASS - a banner is on screen' : 'FAIL - hero is blank'));
};

// A. fresh at phone width
{
  const p = await b.newPage();
  await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true });
  if (await load(p)) report('A: loaded at 423px (a real phone)', await measure(p));
  else console.log('A: could not load');
  await p.close();
}
// B. desktop, then resized down - the recording
{
  const p = await b.newPage();
  await p.setViewport({ width: 1280, height: 900 });
  if (await load(p)) {
    report('B0: desktop before resize', await measure(p));
    await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true });
    await new Promise(r => setTimeout(r, 5000));
    report('B: resized 1280 -> 423 (the recording)', await measure(p));
    // and back, since a fix must not wreck the desktop it returns to
    await p.setViewport({ width: 1280, height: 900 });
    await new Promise(r => setTimeout(r, 5000));
    report('B2: resized back 423 -> 1280', await measure(p));
  } else console.log('B: could not load');
  await p.close();
}
await b.close();
