/**
 * Read the homepage's Shop by Collection strip as a visitor gets it: the tab
 * labels, and what the circle row holds once the embossed tab is selected.
 * The label and the circles were fixed from two different places, so both are
 * checked here rather than inferred from the PHP.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 1280, height: 900 });
await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 90000 });
for (let i = 0; i < 8; i++) {
  if (!(await p.evaluate(() => document.body.innerText.includes('Checking your browser')))) break;
  await new Promise(r => setTimeout(r, 2500));
  try { await p.reload({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {}
}
if (await p.evaluate(() => document.body.innerText.includes('Checking your browser'))) {
  console.log('BLOCKED by bot check - no measurement'); await b.close(); process.exit(0);
}
await new Promise(r => setTimeout(r, 4000));

const tabs = await p.evaluate(() => [...document.querySelectorAll('#topCatSlider .top-cat-btn, .top-category-slider .top-cat-btn')]
  .map(a => ({ t: (a.innerText||'').trim(), gf: a.hasAttribute('data-af-gf'), val: a.getAttribute('data-val') || '' })));
console.log('TABS (' + tabs.length + '):');
tabs.forEach(t => console.log('   ' + (t.gf ? '* ' : '  ') + JSON.stringify(t.t) + '  slug=' + t.val));

const stale = tabs.filter(t => /gold\s*foil/i.test(t.t));
console.log(stale.length ? 'FAIL: a tab still reads Gold Foil' : 'OK: no tab reads Gold Foil');
const emb = tabs.find(t => /emboss/i.test(t.t));
console.log(emb ? 'OK: a tab reads ' + JSON.stringify(emb.t) : 'FAIL: no Embossed tab found');

if (emb) {
  await p.evaluate(() => {
    const a = [...document.querySelectorAll('#topCatSlider .top-cat-btn, .top-category-slider .top-cat-btn')]
      .find(x => /emboss/i.test(x.innerText || ''));
    if (a) a.click();
  });
  await new Promise(r => setTimeout(r, 4500));
  const circles = await p.evaluate(() => {
    const strips = [...document.querySelectorAll('#subcategorySlider, .subcategory-slider, ul.postero-scroll-content')];
    const strip = strips.find(s => s.offsetParent) || strips[0];
    if (!strip) return null;
    return [...strip.querySelectorAll('li.cat-item, .sub-cat')].map(li => {
      const img = li.querySelector('img');
      const r = li.getBoundingClientRect();
      return { t: (li.innerText||'').trim().split('\n')[0],
               blank: li.classList.contains('af-gf-circle--blank'),
               initial: li.getAttribute('data-af-initial') || '',
               img: img ? (img.getAttribute('src') || '(none)') : '(no img el)',
               imgShown: img ? getComputedStyle(img).display : '-',
               box: Math.round(r.width) + 'x' + Math.round(r.height) };
    });
  });
  if (!circles) console.log('\nFAIL: no circle strip on the page');
  else {
    console.log('\nCIRCLES under that tab (' + circles.length + '):');
    circles.forEach(c => console.log('   ' + JSON.stringify(c.t).padEnd(24) + ' blank=' + c.blank +
      ' initial=' + JSON.stringify(c.initial) + ' box=' + c.box + ' imgDisplay=' + c.imgShown +
      ' src=' + String(c.img).slice(-42)));
    const names = circles.map(c => c.t.toLowerCase());
    const want = ['gold foil prints', 'uv prints', 'mixed prints'];
    const missing = want.filter(w => !names.some(n => n.includes(w)));
    console.log(missing.length ? 'FAIL: missing ' + missing.join(', ') : 'OK: all three sub-collections present');
    const zero = circles.filter(c => c.box.startsWith('0x') || c.box.endsWith('x0'));
    console.log(zero.length ? 'FAIL: ' + zero.length + ' circle(s) have no size' : 'OK: every circle has a size');
  }
}
await b.close();
