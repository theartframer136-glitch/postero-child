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
  // There are two hero widgets and the site hides one per breakpoint. The
  // first version of this measured the first one in the DOM - the desktop
  // hero, display:none on a phone - and reported the phone hero blank when it
  // was not. Measure the one that is laid out.
  const all = [...document.querySelectorAll('.elementor-widget-slides')];
  const host = all.find(w => w.getBoundingClientRect().width > 0) || all[0];
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
    measured: host.getAttribute('data-id') || '?',
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
    bgChain: abg ? (() => { const out = []; let n = abg; while (n && n !== host.parentElement && out.length < 10) { const c = getComputedStyle(n), q = r(n);
        out.push(n.tagName.toLowerCase() + '.' + String(n.className).split(/\s+/).slice(0,3).join('.') + ' ' + Math.round(q.width) + 'x' + Math.round(q.height) + ' d=' + c.display + ' op=' + c.opacity + ' vis=' + c.visibility + ' pos=' + c.position + ' ov=' + c.overflow + (c.transform !== 'none' ? ' tf=' + c.transform : '') + (n === abg ? ' bgi=' + c.backgroundImage.slice(0, 90) + ' bgsz=' + c.backgroundSize + ' bgpos=' + c.backgroundPosition + ' attrs=' + [...n.attributes].map(a => a.name + '=' + a.value.slice(0, 40)).join(' ') : ''));
        n = n.parentElement; } return out; })() : null,
    hostClasses: host.className,
  };
});

const report = (label, m) => {
  console.log('\n=== ' + label + ' ===');
  if (m.err) { console.log('  ' + m.err); return; }
  console.log('  viewport            : ' + m.viewport + '   widget measured: ' + m.measured);
  console.log('  hero box            : ' + JSON.stringify(m.host));
  console.log('  wrapper transform   : ' + m.wrapperTransform);
  console.log('  slides              : ' + m.slideCount + '  inline widths ' + JSON.stringify(m.inlineWidths));
  console.log('  slide boxes (l..r)  : ' + JSON.stringify(m.slideBoxes));
  console.log('  active slide        : #' + m.activeIndex + '  in view=' + m.activeInView + '  bg=' + JSON.stringify(m.activeBg));
  console.log('  any slide in view   : ' + m.anySlideInView);
  console.log('  our mobile fix ran  : ' + m.ourFixRan);
  console.log('  host classes        : ' + m.hostClasses);
  (m.bgChain || []).forEach(l => console.log('    ' + l));
  console.log('  ' + (m.anySlideInView && m.activeBg && m.activeBg.hasImage && m.activeBg.h > 60 ? 'PASS - a banner is on screen' : 'FAIL - hero is blank'));
};

// Does the banner actually PAINT? A computed background-image url is not a
// picture on screen: the file could 404, or a white layer could sit on top.
// Screenshot the hero box and count near-white pixels.
async function paint(p, label) {
  try {
    const box = await p.evaluate(() => {
      const all = [...document.querySelectorAll('.elementor-widget-slides')];
      const host = all.find(w => w.getBoundingClientRect().width > 0); if (!host) return null;
      host.scrollIntoView({ block: 'center' });
      const r = host.getBoundingClientRect(); return { x: Math.max(0, r.left), y: Math.max(0, r.top), w: r.width, h: r.height };
    });
    if (!box || box.w < 10 || box.h < 10) { console.log('  paint check: no hero box'); return; }
    await new Promise(r => setTimeout(r, 1500));
    const b64 = await p.screenshot({ clip: { x: box.x, y: box.y, width: box.w, height: box.h }, encoding: 'base64' });
    const stats = await p.evaluate(async (b64) => {
      const im = new Image(); im.src = 'data:image/png;base64,' + b64; await im.decode();
      const c = document.createElement('canvas'); c.width = im.width; c.height = im.height;
      const g = c.getContext('2d'); g.drawImage(im, 0, 0);
      const d = g.getImageData(0, 0, c.width, c.height).data; let white = 0, n = 0;
      for (let i = 0; i < d.length; i += 16) { n++; if (d[i] > 245 && d[i+1] > 245 && d[i+2] > 245) white++; }
      return { w: im.width, h: im.height, whitePct: Math.round(100 * white / n) };
    }, b64);
    console.log('  paint check (' + label + '): hero pixels ' + stats.w + 'x' + stats.h + ', near-white ' + stats.whitePct + '%  -> ' + (stats.whitePct > 90 ? 'BLANK' : 'picture painted'));
    // and the image files themselves
    const urls = await p.evaluate(() => [...new Set([...document.querySelectorAll('.elementor-widget-slides .swiper-slide-bg')].map(e => (getComputedStyle(e).backgroundImage.match(/url\("?([^")]+)/) || [])[1]).filter(Boolean))]);
    const codes = await p.evaluate(async (urls) => { const out = []; for (const u of urls.slice(0, 8)) { try { const r = await fetch(u, { cache: 'no-store' }); out.push(r.status + ' ' + (r.headers.get('content-type') || '') + ' ' + u.split('/').pop()); } catch (e) { out.push('ERR ' + u.split('/').pop()); } } return out; }, urls);
    codes.forEach(c => console.log('    img: ' + c));
  } catch (e) { console.log('  paint check failed: ' + e.message); }
}

// A. fresh at phone width
{
  const p = await b.newPage();
  await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true });
  if (await load(p)) { report('A: loaded at 423px (a real phone)', await measure(p)); await paint(p, 'A'); }
  else console.log('A: could not load');
  await p.close();
}
// C. the owner's DevTools frame: 423x642
{
  const p = await b.newPage();
  await p.setViewport({ width: 423, height: 642, isMobile: true, hasTouch: true });
  if (await load(p)) { report('C: loaded at 423x642 (the screenshot)', await measure(p)); await paint(p, 'C'); }
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
    report('B: resized 1280 -> 423 (the recording)', await measure(p)); await paint(p, 'B');
    // and back, since a fix must not wreck the desktop it returns to
    await p.setViewport({ width: 1280, height: 900 });
    await new Promise(r => setTimeout(r, 5000));
    report('B2: resized back 423 -> 1280', await measure(p));
  } else console.log('B: could not load');
  await p.close();
}
await b.close();
