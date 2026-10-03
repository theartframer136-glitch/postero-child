// The home page, section by section, on a phone and on a desktop. After the
// 3 Oct Elementor update (4.1.5 -> 4.3.3, with Essential Addons and Premium
// Addons) the home page on a 390px phone came out 3,500px shorter with the same
// number of widgets. This lists each top-level section: its height on each
// screen, the kinds of widget in it, and any widget that takes no room on the
// phone though it is not set to hide there (content that stopped showing), so
// a section that collapsed can be told from one that got tidier.
//
// Read-only.
//
// Run: node tools/diag-home-sections.mjs [site]
import { chromium } from 'playwright';

const SITE = (process.argv.slice(2).find(a => a.includes('://')) || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
console.log('diag-home-sections: ' + SITE + '   ' + new Date().toISOString());
const out = {};
for (const [label, w, h] of [['phone', 390, 844], ['desktop', 1440, 900]]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, isMobile: label === 'phone', hasTouch: label === 'phone' });
  const page = await ctx.newPage();
  await page.goto(SITE + '/', { waitUntil: 'load', timeout: 60000 }).catch(() => null);
  await page.waitForTimeout(2500);
  const h1 = await page.evaluate(() => document.documentElement.scrollHeight);
  for (let y = 0; y < 40; y++) { await page.mouse.wheel(0, 800); await page.waitForTimeout(150); }
  await page.waitForTimeout(2000);
  const h2 = await page.evaluate(() => document.documentElement.scrollHeight);
  out[label] = await page.evaluate(() => {
    const doc = document.querySelector('[data-elementor-type="wp-page"]');
    if (!doc) return { secs: [] };
    const secs = [...doc.children].filter(e => e.classList.contains('elementor-element') || e.classList.contains('elementor-section'));
    return { secs: secs.map(s => {
      const b = s.getBoundingClientRect();
      const ws = [...s.querySelectorAll('.elementor-widget')];
      const types = [...new Set(ws.map(x => (x.getAttribute('data-widget_type') || '?').replace('.default', '')))];
      const hiddenHere = el => /elementor-hidden-(mobile|phone)/.test(el.className) || !!el.closest('[class*="elementor-hidden-mobile"]');
      const empty = ws.filter(x => { const r = x.getBoundingClientRect(); return r.height < 2 && !hiddenHere(x); })
        .map(x => (x.getAttribute('data-widget_type') || '?').replace('.default', '') + '#' + x.getAttribute('data-id'));
      const text = (s.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 60);
      return { id: s.getAttribute('data-id'), h: Math.round(b.height), types: types.join(','), widgets: ws.length,
               hiddenMobile: /elementor-hidden-mobile/.test(s.className), empty, text,
               imgs: [...s.querySelectorAll('img')].filter(i => i.getBoundingClientRect().height > 2).length };
    }) };
  });
  out[label].h1 = h1; out[label].h2 = h2;
  await ctx.close();
}
console.log('\nhome height: phone ' + out.phone.h1 + ' on load, ' + out.phone.h2 + ' after scrolling; desktop ' + out.desktop.h1 + ' / ' + out.desktop.h2);
const D = Object.fromEntries((out.desktop.secs || []).map(s => [s.id, s]));
let sum = 0;
for (const s of out.phone.secs || []) {
  sum += s.h;
  const d = D[s.id] || {};
  console.log('\nsection ' + s.id + '  phone ' + s.h + 'px' + (s.hiddenMobile ? ' (set to hide on phones)' : '') + '  desktop ' + (d.h ?? '?') + 'px  widgets ' + s.widgets + '  pictures shown ' + s.imgs + ' / ' + (d.imgs ?? '?'));
  console.log('   kinds: ' + s.types);
  console.log('   text:  ' + s.text);
  if (s.empty.length) console.log('   TAKES NO ROOM ON THE PHONE (not set to hide): ' + s.empty.join(' '));
}
console.log('\nsections add up to ' + sum + 'px on the phone');
console.log('done ' + new Date().toISOString());
await browser.close();
