/**
 * Do the WALL HEIGHT buttons still control anything on a phone, now that the
 * row that used to sit over the video is hidden and only the panel row is left?
 *
 * Presses 8 / 9 / 10 ft with the camera running and records, for each:
 *   - which button carries the .on class
 *   - the size of the drawn artwork (#tow-framebox) - this is what wall height
 *     is FOR: px-per-foot, hence how big the piece is shown
 *   - the size of the red calibration rectangle (#tow-calbox)
 *
 * The rectangle is expected NOT to move: it is a fixed target that the visitor
 * lines the ceiling and floor up to, and the wall height says how many feet
 * that span is worth. But that is a claim, so it is measured rather than
 * asserted - and if the artwork does not move either, the buttons are dead.
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

// a product must be on the stage or there is no artwork to resize
const picked = await p.evaluate(() => {
  const sel = document.getElementById('tow-prod');
  if (sel && sel.options.length > 1 && !sel.value) { sel.selectedIndex = 1; sel.dispatchEvent(new Event('change', {bubbles:true})); }
  return sel ? (sel.value || '(none)') : 'no selector';
});
await new Promise(r => setTimeout(r, 3000));
await p.evaluate(() => { const b = document.getElementById('tow-cambtn'); if (b) b.click(); });
await new Promise(r => setTimeout(r, 6000));

const snap = () => p.evaluate(() => {
  const r = el => { if (!el) return null; const q = el.getBoundingClientRect();
    return { w: Math.round(q.width), h: Math.round(q.height) }; };
  const row = document.getElementById('tow-wallh');
  const onBtn = row ? [...row.querySelectorAll('button[data-ft]')].filter(x => x.classList.contains('on')).map(x => x.getAttribute('data-ft')) : [];
  const calhRow = document.getElementById('tow-calh');
  const onHidden = calhRow ? [...calhRow.querySelectorAll('button[data-ft]')].filter(x => x.classList.contains('on')).map(x => x.getAttribute('data-ft')) : [];
  return {
    onInPanel: onBtn, onInHiddenRow: onHidden,
    artwork: r(document.getElementById('tow-framebox')),
    redBox: r(document.getElementById('tow-calbox')),
    price: (document.getElementById('tow-price') || {}).textContent || '',
  };
});

console.log('product selected: ' + picked);
console.log('camera on: ' + await p.evaluate(() => {
  const v = document.getElementById('tow-cam'); return !!v && getComputedStyle(v).display !== 'none'; }));

const rows = [];
for (const ft of ['10', '8', '9', '10']) {
  const clicked = await p.evaluate((ft) => {
    const row = document.getElementById('tow-wallh');
    if (!row) return false;
    const btn = row.querySelector('button[data-ft="' + ft + '"]');
    if (!btn) return false;
    btn.click();
    return true;
  }, ft);
  await new Promise(r => setTimeout(r, 2500));
  const s = await snap();
  rows.push({ ft, clicked, ...s });
  console.log('\npressed ' + ft + ' ft  (clicked=' + clicked + ')');
  console.log('   .on in panel row   : ' + JSON.stringify(s.onInPanel));
  console.log('   .on in hidden row  : ' + JSON.stringify(s.onInHiddenRow));
  console.log('   artwork drawn size : ' + JSON.stringify(s.artwork));
  console.log('   red rectangle      : ' + JSON.stringify(s.redBox));
  console.log('   price              : ' + s.price.trim());
}

const art = rows.map(x => x.artwork && x.artwork.h).filter(x => x != null);
const box = rows.map(x => x.redBox && x.redBox.h).filter(x => x != null);
const varies = a => a.length > 1 && Math.max(...a) - Math.min(...a) > 1;
console.log('\n--- what the buttons actually move ---');
console.log('  artwork heights   : ' + JSON.stringify(art) + '  -> ' + (varies(art) ? 'CHANGES with wall height (buttons are live)' : 'DOES NOT CHANGE (buttons are dead)'));
console.log('  red box heights   : ' + JSON.stringify(box) + '  -> ' + (varies(box) ? 'changes' : 'fixed'));
console.log('  .on tracked correctly: ' + (rows.every(x => x.onInPanel.length === 1 && x.onInPanel[0] === x.ft) ? 'yes' : 'NO'));
await b.close();
