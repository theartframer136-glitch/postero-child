/**
 * Two candidate fixes for the wall preview on a phone, rendered so they can be
 * looked at rather than argued about.
 *
 * The fault: a blanket `height:auto !important` on every image at phone widths
 * cancels the stage's fill, so the room photo keeps its own 16:9 proportions
 * inside a 286x420 box and the bottom 62% is bare background.
 *
 *   A. restore the fill only  - the photo covers the box, but a 16:9 room in a
 *      portrait box means only about a third of its width survives the crop.
 *   B. restore the fill AND give the box a shape closer to the photo's, so the
 *      room stays recognisable and the box stays tall enough to drag artwork in.
 *
 * Each is emitted as a small JPEG in base64 between markers.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const FILL = `.af-tow-stage .af-tow-wallimg, .af-tow-stage .af-tow-cam{
  width:100% !important; height:100% !important; max-width:none !important;
  object-fit:cover !important; }`;

const VARIANTS = [
  { id: 'A-fill-only', css: FILL },
  { id: 'B-fill-and-shape', css: FILL + `
    @media (max-width:781px){
      .af-tow-stage{ height:auto !important; aspect-ratio: 4 / 3 !important;
                     min-height:0 !important; max-height:none !important; }
    }` },
];

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });

for (const v of VARIANTS) {
  const p = await b.newPage();
  await p.setViewport({ width: 390, height: 800, isMobile: true, hasTouch: true });
  let ok = false;
  for (let i = 1; i <= 3 && !ok; i++) {
    try { await p.goto('https://theartframer.us/try-on-wall/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
    catch { await new Promise(r => setTimeout(r, 4000)); }
  }
  if (!ok) { console.log(v.id + ': load failed'); await p.close(); continue; }
  await new Promise(r => setTimeout(r, 8000));
  await p.addStyleTag({ content: v.css });
  await new Promise(r => setTimeout(r, 1500));

  const m = await p.evaluate(() => {
    const s = document.querySelector('#tow-stage, .af-tow-stage');
    const i = document.querySelector('#tow-wallimg, .af-tow-wallimg');
    if (!s || !i) return null;
    const sr = s.getBoundingClientRect(), ir = i.getBoundingClientRect();
    const nat = i.naturalWidth / i.naturalHeight, boxR = sr.width / sr.height;
    return {
      stage: Math.round(sr.width) + 'x' + Math.round(sr.height),
      coverage: Math.round(100 * ir.height / sr.height),
      visibleWidthOfPhoto: Math.round(100 * Math.min(1, boxR / nat)),
    };
  });
  console.log('\n### ' + v.id + '  ' + JSON.stringify(m));

  const el = await p.$('#tow-stage, .af-tow-stage');
  await el.scrollIntoView();
  await new Promise(r => setTimeout(r, 600));
  const buf = await el.screenshot({ type: 'jpeg', quality: 45 });
  console.log('@@IMG:' + v.id + '@@' + buf.toString('base64') + '@@END@@');
  await p.close();
}
await b.close();
