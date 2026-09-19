/**
 * Try On Wall, phone width: are the camera controls off the video?
 *
 * Chrome can supply a fake camera, so this drives the real thing - it presses
 * the page's own "use live camera" button and lets startCam/calStart run -
 * rather than faking the state by hand and proving only that CSS exists.
 *
 * Checked: the wall-height pill is gone; the instruction and the X are above
 * the picture, not on it; the X is at the right of that strip; nothing of the
 * three overlaps the stage; and a desktop is untouched.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({
  channel: 'chrome', headless: 'new',
  args: ['--no-sandbox','--disable-dev-shm-usage',
         '--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream'],
});

async function run(W, label) {
  const p = await b.newPage();
  await p.setViewport({ width: W, height: 820, isMobile: W < 700, hasTouch: W < 700 });
  const ctx = b.defaultBrowserContext();
  try { await ctx.overridePermissions('https://theartframer.us', ['camera']); } catch {}
  let ok = false;
  for (let i = 1; i <= 3 && !ok; i++) {
    try { await p.goto('https://theartframer.us/try-on-wall/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
    catch { await new Promise(r => setTimeout(r, 4000)); }
  }
  if (!ok) { console.log(label + ': could not load'); await p.close(); return; }
  await new Promise(r => setTimeout(r, 7000));
  for (let i = 0; i < 6; i++) {
    let blocked = false;
    try { blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser')); } catch {}
    const has = await p.evaluate(() => !!document.getElementById('tow-stage')).catch(() => false);
    if (!blocked && has) break;
    await new Promise(r => setTimeout(r, 3000));
  }

  const started = await p.evaluate(() => {
    const btn = document.getElementById('tow-cambtn');
    if (!btn) return false;
    btn.click();
    return true;
  });
  await new Promise(r => setTimeout(r, 6000));

  const out = await p.evaluate(() => {
    const id = x => document.getElementById(x);
    const r = el => { if (!el) return null; const q = el.getBoundingClientRect();
      return { x: Math.round(q.left), y: Math.round(q.top), w: Math.round(q.width), h: Math.round(q.height),
               right: Math.round(q.right), bottom: Math.round(q.bottom) }; };
    const vis = el => { if (!el) return false; const c = getComputedStyle(el);
      const q = el.getBoundingClientRect();
      return c.display !== 'none' && c.visibility !== 'hidden' && q.width > 0 && q.height > 0; };
    const stage = id('tow-stage'), head = id('tow-camhead');
    const overlaps = el => {
      if (!vis(el) || !stage) return false;
      const a = el.getBoundingClientRect(), s = stage.getBoundingClientRect();
      return !(a.right <= s.left || a.left >= s.right || a.bottom <= s.top || a.top >= s.bottom);
    };
    return {
      camOn: vis(id('tow-cam')),
      stage: r(stage),
      strip: { box: r(head), visible: vis(head) },
      stripMsg: { text: (head && head.querySelector('.af-tow-camhead-msg') || {}).textContent || '',
                  visible: vis(head && head.querySelector('.af-tow-camhead-msg')),
                  box: r(head && head.querySelector('.af-tow-camhead-msg')) },
      stripX: { visible: vis(head && head.querySelector('.af-tow-camhead-x')),
                box: r(head && head.querySelector('.af-tow-camhead-x')) },
      onVideo: {
        calmsg: vis(id('tow-calmsg')), camstop: vis(id('tow-camstop')),
        calh: vis(id('tow-calh')), recal: vis(id('tow-recal')),
      },
      overlapsStage: {
        strip: overlaps(head), calmsg: overlaps(id('tow-calmsg')),
        camstop: overlaps(id('tow-camstop')), calh: overlaps(id('tow-calh')),
      },
      calboxStillThere: vis(id('tow-calbox')),
    };
  });

  console.log('\n=== ' + label + ' (' + W + 'px) ===  camera button pressed: ' + started);
  console.log('  camera feed visible : ' + out.camOn);
  console.log('  stage               : ' + JSON.stringify(out.stage));
  console.log('  strip               : visible=' + out.strip.visible + ' ' + JSON.stringify(out.strip.box));
  console.log('  strip message       : visible=' + out.stripMsg.visible + '  "' + out.stripMsg.text.trim().slice(0, 60) + '"');
  console.log('  strip X             : visible=' + out.stripX.visible + ' ' + JSON.stringify(out.stripX.box));
  console.log('  still ON the video  : ' + JSON.stringify(out.onVideo));
  console.log('  overlapping stage   : ' + JSON.stringify(out.overlapsStage));
  console.log('  red rectangle kept  : ' + out.calboxStillThere);

  if (W < 700) {
    const pass = [];
    pass.push(['wall-height pill gone', out.onVideo.calh === false]);
    pass.push(['instruction not on the video', out.onVideo.calmsg === false && !out.overlapsStage.calmsg]);
    pass.push(['X not on the video', out.onVideo.camstop === false && !out.overlapsStage.camstop]);
    pass.push(['strip is above the stage', !!out.strip.box && !!out.stage && out.strip.box.bottom <= out.stage.y]);
    pass.push(['strip does not overlap the stage', out.overlapsStage.strip === false]);
    pass.push(['X sits at the right of the strip', !!out.stripX.box && !!out.strip.box && (out.strip.box.right - out.stripX.box.right) <= 14]);
    pass.push(['red rectangle still drawn', out.calboxStillThere === true]);
    pass.forEach(x => console.log('  ' + (x[1] ? 'PASS' : 'FAIL') + '  ' + x[0]));
    console.log('  ' + (pass.every(x => x[1]) ? 'ALL PASS' : 'SOME FAILED') + ' at ' + W + 'px');
  } else {
    const untouched = out.strip.visible === false && out.onVideo.camstop === true;
    console.log('  desktop untouched (strip hidden, X still on the video): ' + (untouched ? 'PASS' : 'FAIL'));
  }
  await p.close();
}

await run(423, 'phone, the width in the recording');
await run(1280, 'desktop');
await b.close();
