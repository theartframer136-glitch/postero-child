/**
 * The camera strip with the black panel gone: render it and read its colours.
 *
 * Dropping a background is never only a background - the text and the two
 * controls on it were all chosen to read against black. So this reports the
 * computed colour of every part against the colour actually behind it, and
 * emits a small JPEG of the strip and the video beneath it.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({
  channel: 'chrome', headless: 'new',
  args: ['--no-sandbox','--disable-dev-shm-usage',
         '--use-fake-ui-for-media-stream','--use-fake-device-for-media-stream'],
});
const p = await b.newPage();
await p.setViewport({ width: 423, height: 820, isMobile: true, hasTouch: true });
try { await b.defaultBrowserContext().overridePermissions('https://theartframer.us', ['camera']); } catch {}
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/try-on-wall/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 8000));
await p.evaluate(() => { const c = document.getElementById('tow-cambtn'); if (c) c.click(); });
await new Promise(r => setTimeout(r, 6000));

const out = await p.evaluate(() => {
  const head = document.getElementById('tow-camhead');
  if (!head) return null;
  const cs = el => el ? getComputedStyle(el) : null;
  const behind = (function(){          // the first ancestor that paints something
    let n = head.parentElement;
    while (n) { const bg = getComputedStyle(n).backgroundColor;
      if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
      n = n.parentElement; }
    return 'none found';
  })();
  const q = el => { if (!el) return null; const r = el.getBoundingClientRect();
    return { w: Math.round(r.width), h: Math.round(r.height) }; };
  const msg = head.querySelector('.af-tow-camhead-msg');
  const strong = head.querySelector('.af-tow-camhead-msg strong');
  return {
    stripBg: cs(head).backgroundColor,
    behind,
    stripBox: q(head),
    msgColor: msg ? cs(msg).color : null,
    strongColor: strong ? cs(strong).color : null,
    x: { bg: cs(head.querySelector('.af-tow-camhead-x')).backgroundColor,
         color: cs(head.querySelector('.af-tow-camhead-x')).color,
         border: cs(head.querySelector('.af-tow-camhead-x')).borderColor },
    ft: { bg: cs(head.querySelector('.af-tow-camhead-ft')).backgroundColor,
          color: cs(head.querySelector('.af-tow-camhead-ft')).color,
          text: (head.querySelector('.af-tow-camhead-ft')||{}).textContent },
  };
});
console.log(JSON.stringify(out, null, 1));
const black = out && /rgba?\(\s*(?:26|0)\s*,\s*(?:26|0)\s*,\s*(?:26|0)/.test(out.stripBg || '');
console.log('\nstrip has its own background: ' + (out && out.stripBg !== 'rgba(0, 0, 0, 0)' ? 'YES -> ' + out.stripBg : 'no (transparent)'));
console.log('still a black panel: ' + (black ? 'FAIL' : 'PASS'));

// a picture of the strip plus the video under it
const shot = await p.evaluate(() => {
  const h = document.getElementById('tow-camhead'), s = document.getElementById('tow-stage');
  if (!h || !s) return null;
  const a = h.getBoundingClientRect(), c = s.getBoundingClientRect();
  return { x: Math.max(0, a.left - 6), y: Math.max(0, a.top - 6),
           width: Math.min(420, a.width + 12), height: (c.bottom - a.top) + 12 };
});
if (shot) {
  const buf = await p.screenshot({ type: 'jpeg', quality: 48, clip: shot });
  console.log('@@IMG@@' + buf.toString('base64') + '@@END@@');
}
await b.close();
