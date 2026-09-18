/**
 * Is the Instagram feed in the server-sent HTML, or injected by script later?
 * The output-buffer fix only reaches the former. Compare raw HTML with the
 * rendered DOM, and check whether any <video> carries preload="none".
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const URL = 'https://theartframer.us/';
const raw = await (await fetch(URL, { headers: { 'User-Agent': 'Mozilla/5.0 AF-Audit', 'Cache-Control': 'no-cache' } })).text();
const rawVideos = (raw.match(/<video\b/gi) || []).length;
const rawPreloadNone = (raw.match(/<video\b[^>]*preload="none"/gi) || []).length;
const rawInsta = (raw.match(/cdninstagram/gi) || []).length;
console.log(`RAW HTML   : <video> tags=${rawVideos}  with preload="none"=${rawPreloadNone}  cdninstagram refs=${rawInsta}  bytes=${raw.length}`);
console.log(`           : LiteSpeed header? ${/x-litespeed-cache/i.test(raw) ? '(in body?)' : 'n/a'}`);
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const p = await b.newPage(); await p.setViewport({ width: 1400, height: 1000 });
const media = [];
p.on('response', r => { try { const u = r.url(); if (/cdninstagram/.test(u)) media.push({ u: u.slice(0, 70), type: r.request().resourceType(), init: r.request().initiator && r.request().initiator.type }); } catch {} });
const resp = await p.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });
console.log(`HTTP ${resp.status()}  x-litespeed-cache: ${resp.headers()['x-litespeed-cache'] || '(none)'}`);
await new Promise(r => setTimeout(r, 4000));
const dom = await p.evaluate(() => {
  const vids = [...document.querySelectorAll('video')];
  const feed = document.querySelector('[class*="qligg"],[class*="insta-gallery"],[id*="qligg"],[class*="instagram"]');
  return {
    videos: vids.length,
    preloadNone: vids.filter(v => v.getAttribute('preload') === 'none').length,
    autoplay: vids.filter(v => v.hasAttribute('autoplay')).length,
    withSrc: vids.filter(v => v.src || v.querySelector('source')).length,
    feedTag: feed ? feed.tagName + '.' + (feed.className || '').toString().slice(0, 60) : '(no feed container found)',
    feedTop: feed ? Math.round(feed.getBoundingClientRect().top + window.scrollY) : null,
    firstVideoHTML: vids[0] ? vids[0].outerHTML.slice(0, 220) : null,
  };
});
console.log(`RENDERED   : <video>=${dom.videos}  preload=none=${dom.preloadNone}  autoplay=${dom.autoplay}  withSrc=${dom.withSrc}`);
console.log(`FEED       : ${dom.feedTag}  top=${dom.feedTop}px from page top`);
console.log(`FIRST VIDEO: ${dom.firstVideoHTML}`);
const byType = {}; for (const m of media) byType[m.type] = (byType[m.type] || 0) + 1;
console.log(`INSTAGRAM requests during load: ${media.length}  by type: ${JSON.stringify(byType)}  initiators: ${[...new Set(media.map(m => m.init))].join(',')}`);
await b.close();
