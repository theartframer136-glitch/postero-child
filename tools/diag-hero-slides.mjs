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
  const box = await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); h.scrollIntoView({ block: 'center' }); const r = h.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, h: r.height }; });
  const b64 = await p.screenshot({ clip: { x: Math.max(0, box.x), y: Math.max(0, box.y), width: box.w, height: box.h }, encoding: 'base64' });
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
for (let i = 0; i < 9; i++) {
  const a = await active(); const w = await pixels();
  console.log('slide #' + a.idx + '  ' + a.file + '  bg ' + a.bgBox + ' kb=' + a.kb + ' tf=' + a.bgTransform + ' op=' + a.bgOpacity + '  slide ' + a.slideBox + '  wrap ' + a.wrapT + '  innerBg=' + a.innerBg + ' overlay=' + a.overlay + '  -> white ' + w + '% ' + (w > 92 ? 'BLANK' : 'ok'));
  await p.evaluate(() => { const h = document.querySelector('.elementor-element-0971963'); const n = h.querySelector('.elementor-swiper-button-next'); if (n) n.click(); });
  await new Promise(r => setTimeout(r, 1800));
}
const urls = await p.evaluate(() => [...new Set([...document.querySelectorAll('.elementor-element-0971963 .swiper-slide-bg')].map(e => (getComputedStyle(e).backgroundImage.match(/url\("?([^")]+)/) || [])[1]).filter(Boolean))]);
const info = await p.evaluate(async (urls) => { const out = []; for (const u of urls) { const r = await fetch(u, { cache: 'force-cache' }); const buf = await r.arrayBuffer(); const im = new Image(); im.src = u; let dec = 'ok'; try { await im.decode(); } catch (e) { dec = 'DECODE FAIL ' + e.message; } out.push(u.split('/').pop() + '  ' + Math.round(buf.byteLength / 1024) + 'KB  ' + im.naturalWidth + 'x' + im.naturalHeight + '  ' + dec); } return out; }, urls);
console.log('\nbanner files:'); info.forEach(l => console.log('  ' + l));
await b.close();
