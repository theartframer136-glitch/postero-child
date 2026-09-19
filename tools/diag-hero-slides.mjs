/**
 * Loaded at 423x642, one banner paints (banner-1, 60% white pixels) and the
 * next does not (banner-2, 100% white) although both files are 200 and both
 * are the computed background. So: walk every slide with the arrow, measure
 * the painted pixels for each, and decode every banner file to learn its
 * real dimensions - a picture Chrome will not decode paints as nothing.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 423, height: 642, isMobile: true, hasTouch: true });
let ok = false;
for (let i = 1; i <= 3 && !ok; i++) {
  try { await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); ok = true; }
  catch { await new Promise(r => setTimeout(r, 4000)); }
}
if (!ok) { console.log('could not load'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 8000));
await p.evaluate(() => { for (const s of ['#afOverlay', '#af-consent']) document.querySelectorAll(s).forEach(e => e.remove()); });

const host = () => document.querySelector('.elementor-element-0971963');
const pixels = async () => {
  const box = await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); h.scrollIntoView({ block: 'start' }); const r = h.getBoundingClientRect(); const y = Math.max(0, r.top); return { x: Math.max(0, r.left), y, w: r.width, h: Math.min(r.bottom, window.innerHeight) - y }; });
  const b64 = await p.screenshot({ clip: { x: box.x, y: box.y, width: box.w, height: box.h }, captureBeyondViewport: false, encoding: 'base64' });
  return p.evaluate(async (b64) => {
    const im = new Image(); im.src = 'data:image/png;base64,' + b64; await im.decode();
    const c = document.createElement('canvas'); c.width = im.width; c.height = im.height;
    const g = c.getContext('2d'); g.drawImage(im, 0, 0);
    const d = g.getImageData(0, 0, c.width, c.height).data; let white = 0, n = 0;
    for (let i = 0; i < d.length; i += 16) { n++; if (d[i] > 245 && d[i+1] > 245 && d[i+2] > 245) white++; }
    return Math.round(100 * white / n);
  }, b64);
};
const active = () => p.evaluate(() => {
  const h = document.querySelector('.elementor-element-0971963');
  const a = h.querySelector('.swiper-slide-active'); const bg = a && a.querySelector('.swiper-slide-bg');
  const c = bg && getComputedStyle(bg);
  const wr = h.querySelector('.swiper-wrapper');
  return { idx: [...h.querySelectorAll('.swiper-slide')].indexOf(a), file: c ? (c.backgroundImage.match(/([^/]+)"?\)$/) || [])[1] : null,
    bgBox: bg ? (r => Math.round(r.width) + 'x' + Math.round(r.height) + '@' + Math.round(r.left) + ',' + Math.round(r.top))(bg.getBoundingClientRect()) : null,
    kb: bg ? bg.className.replace('swiper-slide-bg', '').trim() : null, bgTransform: c ? c.transform : null, bgOpacity: c ? c.opacity : null,
    slideBox: a ? (r => Math.round(r.width) + 'x' + Math.round(r.height) + '@' + Math.round(r.left))(a.getBoundingClientRect()) : null,
    wrapT: wr ? getComputedStyle(wr).transform : null,
    innerBg: a ? getComputedStyle(a.querySelector('.swiper-slide-inner') || a).backgroundColor : null,
    overlay: a && a.querySelector('.elementor-background-overlay') ? getComputedStyle(a.querySelector('.elementor-background-overlay')).backgroundColor + '/' + getComputedStyle(a.querySelector('.elementor-background-overlay')).opacity : 'none' };
});
// stop autoplay so the walk is deterministic
await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); const sw = h.querySelector('.swiper'); try { sw.swiper.autoplay.stop(); } catch (e) {} });
// What is actually stacked at the middle of the hero, top to bottom?
const stack = await p.evaluate(() => {
  const h = document.querySelector('.elementor-element-0971963'); h.scrollIntoView({ block: 'center' });
  const r = h.getBoundingClientRect(); const pts = [[r.left + r.width / 2, r.top + r.height / 2], [r.left + 40, r.top + 40], [r.left + r.width / 2, r.top + 20]];
  return pts.map(([x, y]) => 'at ' + Math.round(x) + ',' + Math.round(y) + ':\n' + document.elementsFromPoint(x, y).slice(0, 14).map(e => { const c = getComputedStyle(e); const q = e.getBoundingClientRect();
    return '    ' + e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className).split(/\s+/).slice(0, 4).join('.') + ' ' + Math.round(q.width) + 'x' + Math.round(q.height) + ' bg=' + c.backgroundColor + (c.backgroundImage !== 'none' ? ' bgi=yes' : '') + ' op=' + c.opacity + ' z=' + c.zIndex + ' pos=' + c.position + ' mix=' + c.mixBlendMode + ' filt=' + c.filter + ' cv=' + c.contentVisibility; }).join('\n')).join('\n');
});
console.log('STACK\n' + stack);
// full-page shot of the hero region, as the eye sees it (base64 length only + a coarse 12x8 luminance map)
const map = await (async () => {
  const box = await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); h.scrollIntoView({ block: 'start' }); const r = h.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: Math.min(r.height, window.innerHeight - r.top) }; });
  const b64 = await p.screenshot({ clip: { x: box.x, y: Math.max(0, box.y), width: box.w, height: box.h }, captureBeyondViewport: false, encoding: 'base64' });
  return p.evaluate(async (b64) => { const im = new Image(); im.src = 'data:image/png;base64,' + b64; await im.decode();
    const c = document.createElement('canvas'); c.width = im.width; c.height = im.height; const g = c.getContext('2d'); g.drawImage(im, 0, 0);
    const rows = []; for (let ry = 0; ry < 8; ry++) { let row = ''; for (let cx = 0; cx < 12; cx++) { const d = g.getImageData(Math.floor(cx * c.width / 12), Math.floor(ry * c.height / 8), 1, 1).data; const l = (d[0] + d[1] + d[2]) / 3; row += l > 245 ? '.' : l > 180 ? '-' : l > 100 ? '+' : '#'; } rows.push(row); } return rows.join('\n'); }, b64);
})();
console.log('LUMA MAP (. white, - light, + mid, # dark)\n' + map);
// Style facts the earlier dumps did not ask for, then experiments: change
// one thing in the page, re-measure the pixels, put it back.
console.log('FACTS ' + await p.evaluate(() => {
  const h = document.querySelector('.elementor-element-0971963');
  const a = h.querySelector('.swiper-slide-active'), bg = a.querySelector('.swiper-slide-bg'), wr = h.querySelector('.swiper-wrapper'), sw = h.querySelector('.swiper');
  const f = (e, ps) => ps.map(p => p + '=' + getComputedStyle(e)[p]).join(' ');
  return '\n  bg:     ' + f(bg, ['backgroundAttachment','backgroundClip','backgroundOrigin','backgroundBlendMode','pointerEvents','animationName','animationDuration','clipPath','maskImage','webkitMaskImage','isolation','filter','backfaceVisibility','transformStyle','visibility','display','zIndex','height','width'])
    + '\n  slide:  ' + f(a, ['pointerEvents','visibility','opacity','overflow','clipPath','backfaceVisibility','transform','height','width','display'])
    + '\n  wrap:   ' + f(wr, ['pointerEvents','visibility','opacity','overflow','height','width','display','transform','transitionDuration'])
    + '\n  swiper: ' + f(sw, ['pointerEvents','visibility','opacity','overflow','height','width','display','clipPath'])
    + '\n  inner:  ' + f(a.querySelector('.swiper-slide-inner'), ['backgroundColor','backgroundImage','height','width','pointerEvents'])
    + '\n  bg inline: ' + bg.getAttribute('style');
}));
for (let i = 0; i < 3; i++) {
  const a = await active(); const w = await pixels();
  console.log('slide #' + a.idx + '  ' + a.file + '  bg ' + a.bgBox + ' kb=' + a.kb + ' tf=' + a.bgTransform + ' op=' + a.bgOpacity + '  slide ' + a.slideBox + '  wrap ' + a.wrapT + '  innerBg=' + a.innerBg + ' overlay=' + a.overlay + '  -> white ' + w + '% ' + (w > 92 ? 'BLANK' : 'ok'));
  await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); const n = h.querySelector('.elementor-swiper-button-next'); if (n) n.click(); });
  await new Promise(r => setTimeout(r, 1800));
}
const urls = await p.evaluate(() => [...new Set([...document.querySelectorAll('.elementor-element-0971963 .swiper-slide-bg')].map(e => (getComputedStyle(e).backgroundImage.match(/url\("?([^")]+)/) || [])[1]).filter(Boolean))]);
const info = await p.evaluate(async (urls) => { const out = []; for (const u of urls) { const r = await fetch(u, { cache: 'force-cache' }); const buf = await r.arrayBuffer(); const im = new Image(); im.src = u; let dec = 'ok'; try { await im.decode(); } catch (e) { dec = 'DECODE FAIL ' + e.message; } out.push(u.split('/').pop() + '  ' + Math.round(buf.byteLength / 1024) + 'KB  ' + im.naturalWidth + 'x' + im.naturalHeight + '  ' + dec); } return out; }, urls);
console.log('\nbanner files:'); info.forEach(l => console.log('  ' + l));
await b.close();
