// How pages LOOK and BEHAVE with some plugins unloaded and their theme ports
// doing the work, in a real browser, next to the same pages as they are now,
// without switching anything off for visitors. Run by preview-look.yml, which
// puts tools/port-preview-dropin.php in place (as wp-content/db.php) for the
// run with a one-time secret (AF_PV): only requests carrying it leave the
// plugins in AF_SKIP unloaded. Every page must come back marked as a preview
// (the meta tag naming what was unloaded; the X-AF-Port-Preview header is
// shown too, but a CDN may strip it);
// otherwise it stops, exit 2, rather than compare the live site with itself.
// Exit 1 when something differs, 0 when all is the same.
//
// Written for Elementor Pro (owner, 5 Oct: "keep everything free"), whose
// visible work on this site is the home page hero slideshow, the WooCommerce
// breadcrumbs and the footer bar pinned to the bottom on phones
// (inc/ports/elementor-pro.php). For each page, at desktop and phone width,
// both ways:
//   - HTTP status, error text, page height, script errors
//   - every slideshow: slides, whether Swiper started and moves on its own,
//     the picture of the slide on screen, arrows and dots (shown, where, size),
//     and an arrow click moving it
//   - breadcrumbs: text and colours
//   - the footer bar container bec7134: position and where it sits
//   - a screenshot of the first screen, and how many pixels differ
// and says SAME or DIFFERS for each. A third load per page keeps Elementor's
// element cache on (af_cache=keep: the stored copy, as visitors get it just
// after a switch-off) and compares its slideshows and breadcrumbs too.
//
// Run (by the workflow): AF_PV=<secret> AF_SKIP=elementor-pro AF_URLS="/ /shop/" node tools/preview-look.mjs
import { chromium } from 'playwright';
import fs from 'fs';
import zlib from 'zlib';

const SITE = (process.env.AF_SITE || 'https://theartframer.us').replace(/\/$/, '');
const PV = process.env.AF_PV || '';
const SKIP = (process.env.AF_SKIP || '').replace(/[^a-z0-9,-]/g, '');
const URLS = (process.env.AF_URLS || '/').split(/\s+/).filter(Boolean);
if (!PV || !SKIP) { console.log('AF_PV and AF_SKIP are needed'); process.exit(2); }

// A tiny PNG reader (8-bit RGBA/RGB, non-interlaced, as Chromium writes them).
function readPng(buf) {
  let p = 8, w = 0, h = 0, ct = 0; const idat = [];
  while (p < buf.length) {
    const len = buf.readUInt32BE(p), type = buf.toString('ascii', p + 4, p + 8), data = buf.subarray(p + 8, p + 8 + len);
    if (type === 'IHDR') { w = data.readUInt32BE(0); h = data.readUInt32BE(4); ct = data[9]; }
    if (type === 'IDAT') idat.push(data);
    p += 12 + len;
  }
  const bpp = ct === 6 ? 4 : 3, raw = zlib.inflateSync(Buffer.concat(idat)), out = Buffer.alloc(w * h * bpp), stride = w * bpp;
  for (let y = 0; y < h; y++) {
    const f = raw[y * (stride + 1)], line = raw.subarray(y * (stride + 1) + 1, (y + 1) * (stride + 1));
    for (let x = 0; x < stride; x++) {
      const a = x >= bpp ? out[y * stride + x - bpp] : 0, b = y ? out[(y - 1) * stride + x] : 0, c = (x >= bpp && y) ? out[(y - 1) * stride + x - bpp] : 0;
      let v = line[x];
      if (f === 1) v += a; else if (f === 2) v += b; else if (f === 3) v += (a + b) >> 1;
      else if (f === 4) { const pa = Math.abs(b - c), pb = Math.abs(a - c), pc = Math.abs(a + b - 2 * c); v += (pa <= pb && pa <= pc) ? a : (pb <= pc ? b : c); }
      out[y * stride + x] = v & 255;
    }
  }
  return { w, h, bpp, px: out };
}
function diffShare(a, b) {
  if (a.w !== b.w || a.h !== b.h) return 1;
  let n = 0;
  for (let i = 0; i < a.w * a.h; i++) {
    const o = i * a.bpp, q = i * b.bpp;
    if (Math.abs(a.px[o] - b.px[q]) + Math.abs(a.px[o + 1] - b.px[q + 1]) + Math.abs(a.px[o + 2] - b.px[q + 2]) > 48) n++;
  }
  return n / (a.w * a.h);
}

async function look(browser, label, w, h, path, skip, cache = '') {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, isMobile: label === 'phone', hasTouch: label === 'phone' });
  const page = await ctx.newPage();
  const errs = [], logs = [], failed = [];
  page.on('pageerror', e => errs.push(String(e.message || e).slice(0, 140)));
  page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') logs.push(m.type() + ': ' + m.text().slice(0, 160)); });
  page.on('requestfailed', q => failed.push(q.url().replace(SITE, '').replace(/[?&]af_pv=[^&]*/, '').slice(0, 140) + ' ' + ((q.failure() || {}).errorText || '')));
  const sep = path.includes('?') ? '&' : '?';
  const url = SITE + path + sep + 'afr=' + Date.now() + Math.random().toString(36).slice(2, 7) + '&af_pv=' + PV + '&af_skip=' + skip + (cache ? '&af_cache=' + cache : '');
  const r = await page.goto(url, { waitUntil: 'load', timeout: 90000 }).catch(() => null);
  await page.waitForTimeout(3500);
  const marker = await page.evaluate(() => (document.querySelector('meta[name="af-port-preview"]') || {}).content || '');
  const pvHeader = r ? (r.headers()['x-af-port-preview'] || '') : '';
  const shot = await page.screenshot({ fullPage: false }).catch(() => null);
  const d = await page.evaluate(async () => {
    const vis = el => { if (!el) return false; const b = el.getBoundingClientRect(), s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && s.display !== 'none' && +s.opacity > 0; };
    const sliders = [];
    for (const el of document.querySelectorAll('.elementor-widget-slides')) {
      const wrap = el.querySelector('.elementor-slides-wrapper');
      const sw = wrap && wrap.swiper;
      const active = el.querySelector('.swiper-slide-active .swiper-slide-bg') || el.querySelector('.swiper-slide-bg');
      const bg = active ? getComputedStyle(active).backgroundImage : '';
      const prev = el.querySelector('.elementor-swiper-button-prev'), next = el.querySelector('.elementor-swiper-button-next');
      const box = x => { if (!x) return 'none'; const b = x.getBoundingClientRect(); return Math.round(b.x) + ',' + Math.round(b.y) + ' ' + Math.round(b.width) + 'x' + Math.round(b.height); };
      sliders.push({
        id: el.getAttribute('data-id'), shown: vis(el), height: Math.round(el.getBoundingClientRect().height),
        slides: el.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)').length,
        started: !!sw, index: sw ? sw.realIndex : -1,
        picture: (bg.match(/[^/"]+\.(webp|jpe?g|png)/i) || ['none'])[0],
        prev: vis(prev) ? box(prev) : 'hidden', next: vis(next) ? box(next) : 'hidden',
        arrowColour: prev ? getComputedStyle(prev).color : '', arrowSize: prev ? getComputedStyle(prev).fontSize : '',
        dots: [...el.querySelectorAll('.swiper-pagination-bullet')].filter(vis).length,
      });
    }
    const crumbs = [...document.querySelectorAll('.elementor-widget-woocommerce-breadcrumb .woocommerce-breadcrumb')].filter(vis).map(n => ({
      text: n.innerText.replace(/\s+/g, ' ').trim(), colour: getComputedStyle(n).color, link: n.querySelector('a') ? getComputedStyle(n.querySelector('a')).color : '' }));
    const bar = document.querySelector('.elementor-element-bec7134');
    const barInfo = bar ? (vis(bar) ? getComputedStyle(bar).position + ' bottom ' + Math.round(window.innerHeight - bar.getBoundingClientRect().bottom) + 'px' : 'hidden') : 'missing';
    // Evidence for when a slideshow does not start or the breadcrumbs differ.
    const short = u => u.replace(location.origin, '').replace(/[?&]ver=[^&]*/, '');
    const f = window.elementorFrontend;
    const evidence = {
      scripts: [...document.querySelectorAll('script[src]')].map(x => short(x.src)).filter(u => /swiper|slides|ports\/epro|elementor(-pro)?\/assets\/js\/(frontend|webpack)|litespeed/.test(u)),
      styles: [...document.querySelectorAll('link[rel=stylesheet]')].map(x => short(x.href)).filter(u => /swiper|slides|ports\/epro/.test(u)),
      delayed: document.querySelectorAll('script[type="litespeed/javascript"], script[data-src]').length,
      elementor: f ? { hooks: !!f.hooks, utilsSwiper: typeof (f.utils && f.utils.swiper), version: (f.config && f.config.version) || '' } : 'none',
      Swiper: typeof window.Swiper,
      slides: [...document.querySelectorAll('.elementor-widget-slides')].map(el => ({ id: el.getAttribute('data-id'), afStarted: !!el.__afSlides, html: el.outerHTML.replace(/\s+/g, ' ').slice(0, 500) })),
      crumbs: [...document.querySelectorAll('.elementor-widget-woocommerce-breadcrumb')].map(el => el.outerHTML.replace(/\s+/g, ' ').slice(0, 700)),
    };
    return { sliders, crumbs, bar: barInfo, evidence, height: Math.round(document.documentElement.scrollHeight / 100) * 100,
      error: /There has been a critical error/.test(document.body ? document.body.innerText : '') };
  }).catch(e => ({ fail: String(e).slice(0, 120) }));
  // does each started slider move on its own, and on an arrow click?
  if (d.sliders) {
    for (const s of d.sliders) {
      if (!s.started || !s.shown) { s.moves = 'n/a'; continue; }
      const before = s.index;
      await page.waitForTimeout(5800);
      const after = await page.evaluate(id => { const w = document.querySelector('.elementor-element-' + id + ' .elementor-slides-wrapper'); return w && w.swiper ? w.swiper.realIndex : -1; }, s.id);
      s.moves = after !== before ? 'yes (' + before + '->' + after + ')' : 'no (' + before + ')';
      const clicked = await page.evaluate(id => {
        const el = document.querySelector('.elementor-element-' + id), w = el && el.querySelector('.elementor-slides-wrapper');
        const n = el && el.querySelector('.elementor-swiper-button-next');
        if (!w || !w.swiper || !n) return 'no arrow';
        const a = w.swiper.realIndex; n.click();
        return new Promise(res => setTimeout(() => res(w.swiper.realIndex !== a ? 'moves' : 'stays'), 900));
      }, s.id);
      s.click = clicked;
    }
  }
  await ctx.close();
  return { status: r ? r.status() : 0, marker, pvHeader, logs: [...new Set(logs)].slice(0, 12), failed: failed.slice(0, 12), errs: [...new Set(errs)], shotBuf: shot, shot: shot ? readPng(shot) : null, ...d };
}

const browser = await chromium.launch({ headless: true });
let differs = 0;
for (const path of URLS) {
  for (const [label, w, h] of [['desktop', 1440, 900], ['phone', 390, 844]]) {
    const a = await look(browser, label, w, h, path, 'none');
    const b = await look(browser, label, w, h, path, SKIP);
    console.log('\n=== ' + path + ' [' + label + ']   now: HTTP ' + a.status + ' (preview ' + a.marker + ')   ' + SKIP + ' off: HTTP ' + b.status + ' (preview ' + b.marker + ')');
    if (a.marker !== 'none' || b.marker !== SKIP) {
      console.log('  NOT A PREVIEW: the pages did not come through the preview file (header ' + JSON.stringify(a.pvHeader) + '/' + JSON.stringify(b.pvHeader)
        + ', marker ' + JSON.stringify(a.marker) + '/' + JSON.stringify(b.marker) + '); stopping rather than compare the live site with itself');
      await browser.close();
      process.exit(2);
    }
    const row = (what, x, y) => { const same = JSON.stringify(x) === JSON.stringify(y); if (!same) differs++; console.log('  ' + (same ? 'SAME   ' : 'DIFFERS') + ' ' + what.padEnd(16) + (same ? JSON.stringify(x) : JSON.stringify(x) + '  ->  ' + JSON.stringify(y))); };
    row('error page', a.error, b.error);
    row('page height', a.height, b.height);
    row('script errors', a.errs, b.errs);
    const ids = [...new Set([...(a.sliders || []).map(s => s.id), ...(b.sliders || []).map(s => s.id)])];
    for (const id of ids) {
      const x = (a.sliders || []).find(s => s.id === id) || {}, y = (b.sliders || []).find(s => s.id === id) || {};
      if (!x.shown && !y.shown) { row('slides #' + id, 'not shown at this width', 'not shown at this width'); continue; }
      for (const k of ['height', 'slides', 'started', 'picture', 'prev', 'next', 'arrowColour', 'arrowSize', 'dots', 'moves', 'click']) {
        const xv = k === 'moves' ? String(x[k] || '').split(' ')[0] : x[k], yv = k === 'moves' ? String(y[k] || '').split(' ')[0] : y[k];
        row('slides ' + k, xv, yv);
      }
    }
    row('breadcrumbs', a.crumbs, b.crumbs);
    const stalled = side => (side.sliders || []).some(x => x.shown && !x.started);
    if (stalled(a) || stalled(b) || JSON.stringify(a.crumbs) !== JSON.stringify(b.crumbs)) {
      for (const [name, side] of [['now', a], [SKIP + ' off', b]]) {
        console.log('    evidence, ' + name + ':');
        for (const [k, v] of Object.entries(side.evidence || {})) console.log('      ' + k + ': ' + JSON.stringify(v));
        console.log('      console: ' + JSON.stringify(side.logs) + '\n      failed requests: ' + JSON.stringify(side.failed));
      }
    }
    row('footer bar', a.bar, b.bar);
    if (a.shot && b.shot) {
      const share = diffShare(a.shot, b.shot);
      console.log('  ' + (share < 0.02 ? 'SAME   ' : 'DIFFERS') + ' first screen     ' + (share * 100).toFixed(2) + '% of pixels differ');
      if (share >= 0.02) differs++;
      // kept by the workflow as an artifact (persona-*.png)
      const name = 'persona-look-' + label + '-' + (path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home');
      fs.writeFileSync(name + '-now.png', a.shotBuf);
      fs.writeFileSync(name + '-' + SKIP.replace(/,/g, '+') + '-off.png', b.shotBuf);
    }
    // The same with Elementor's element cache left on: what visitors get
    // just after a switch-off, from the copy Elementor stored earlier (the
    // preview file reads it, never writes it). Slideshows and breadcrumbs
    // only, against the page as it is now.
    const c = await look(browser, label, w, h, path, SKIP, 'keep');
    if (c.marker !== SKIP) {
      console.log('  NOT A PREVIEW (stored copy): marker ' + JSON.stringify(c.marker) + '; stopping');
      await browser.close();
      process.exit(2);
    }
    console.log('  -- ' + SKIP + ' off, served from Elementor\'s stored copy:');
    row('stored: errors', [a.error, a.errs], [c.error, c.errs]);
    for (const id of ids) {
      const x = (a.sliders || []).find(s => s.id === id) || {}, y = (c.sliders || []).find(s => s.id === id) || {};
      if (!x.shown && !y.shown) continue;
      for (const k of ['started', 'picture', 'next', 'dots', 'moves', 'click']) {
        const xv = k === 'moves' ? String(x[k] || '').split(' ')[0] : x[k], yv = k === 'moves' ? String(y[k] || '').split(' ')[0] : y[k];
        row('stored: ' + k, xv, yv);
      }
    }
    row('stored: crumbs', a.crumbs, c.crumbs);
    if (stalled(c)) {
      console.log('    evidence, stored copy:');
      for (const [k, v] of Object.entries(c.evidence || {})) console.log('      ' + k + ': ' + JSON.stringify(v));
      console.log('      console: ' + JSON.stringify(c.logs) + '\n      failed requests: ' + JSON.stringify(c.failed));
    }
  }
}
console.log('\n' + (differs ? differs + ' difference(s): read them above before switching anything off' : 'no differences'));
await browser.close();
process.exit(differs ? 1 : 0);
