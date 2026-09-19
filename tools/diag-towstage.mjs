/**
 * Try On Wall at phone width: the room photo fills only the top of its box and
 * the rest shows the stage's own cream background.
 *
 * .af-tow-wallimg is written as position:absolute; inset:0; width:100%;
 * height:100%; object-fit:cover - which would fill it. So something is winning
 * against that, or the stage is taller than the rule thinks. Report what the
 * browser actually computed for both, at the width in the video and at two
 * others, rather than reading the stylesheet and guessing which rule applies.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });

for (const W of [423, 390, 768]) {
  const p = await b.newPage();
  await p.setViewport({ width: W, height: 720, isMobile: W < 700, hasTouch: W < 700 });
  let ok = false;
  for (let i = 1; i <= 3 && !ok; i++) {
    try { await p.goto('https://theartframer.us/try-on-wall/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
    catch { await new Promise(r => setTimeout(r, 4000)); }
  }
  if (!ok) { console.log(W + 'px: could not load'); await p.close(); continue; }
  await new Promise(r => setTimeout(r, 7000));

  const out = await p.evaluate(() => {
    const stage = document.querySelector('#tow-stage, .af-tow-stage');
    const img = document.querySelector('#tow-wallimg, .af-tow-wallimg');
    const box = el => { if (!el) return null; const r = el.getBoundingClientRect();
      return { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height) }; };
    const cs = el => { if (!el) return null; const c = getComputedStyle(el);
      return { pos: c.position, top: c.top, left: c.left, right: c.right, bottom: c.bottom,
               w: c.width, h: c.height, minH: c.minHeight, maxH: c.maxHeight,
               fit: c.objectFit, disp: c.display, aspect: c.aspectRatio, flex: c.flex }; };
    return {
      stageBox: box(stage), stageCs: cs(stage),
      imgBox: box(img), imgCs: cs(img),
      imgSrc: img ? (img.getAttribute('src') || '').slice(-60) : null,
      natural: img ? img.naturalWidth + 'x' + img.naturalHeight : null,
      // how much of the stage the image actually covers
      coverage: (stage && img) ? Math.round(100 * img.getBoundingClientRect().height / stage.getBoundingClientRect().height) : null,
    };
  });

  console.log('\n=== ' + W + 'px ===');
  console.log('  stage  box=' + JSON.stringify(out.stageBox));
  console.log('         ' + JSON.stringify(out.stageCs));
  console.log('  img    box=' + JSON.stringify(out.imgBox) + '  natural=' + out.natural);
  console.log('         ' + JSON.stringify(out.imgCs));
  console.log('  src    ...' + out.imgSrc);
  console.log('  the image covers ' + out.coverage + '% of the stage height'
    + (out.coverage !== null && out.coverage < 98 ? '   <-- the rest is bare background' : ''));
  await p.close();
}
await b.close();
