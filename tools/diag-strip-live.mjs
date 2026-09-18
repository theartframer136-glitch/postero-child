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
    // Find the circle row by its CONTENT rather than by a guessed selector:
    // the smallest element holding several short captions, sitting between the
    // tab strip and the product grid. The previous selector list matched
    // nothing, which is the whole reason the row was never replaced.
    const tabStrip = document.querySelector('#topCatSlider, .top-category-slider');
    if (!tabStrip) return { err: 'no tab strip' };
    const tabBottom = tabStrip.getBoundingClientRect().bottom;
    let best = null;
    for (const el of document.querySelectorAll('ul, div, nav')) {
      const r = el.getBoundingClientRect();
      if (r.top < tabBottom - 5 || r.top > tabBottom + 260) continue;
      if (r.height < 30 || r.height > 220 || r.width < 300) continue;
      const kids = [...el.children];
      if (kids.length < 3) continue;
      const captions = kids.filter(k => { const t = (k.innerText||'').trim(); return t && t.length < 40; });
      if (captions.length < 3) continue;
      if (!best || el.querySelectorAll('*').length < best.n) best = { el, n: el.querySelectorAll('*').length };
    }
    if (!best) return { err: 'no row found by content either' };
    const el = best.el;
    const kid = el.children[0];
    return {
      rowTag: el.tagName.toLowerCase(),
      rowId: el.id || '(none)',
      rowCls: String(el.className).slice(0, 90),
      itemTag: kid ? kid.tagName.toLowerCase() : '-',
      itemCls: kid ? String(kid.className).slice(0, 90) : '-',
      itemHtml: kid ? kid.outerHTML.slice(0, 500) : '-',
      items: [...el.children].map(li => {
        const img = li.querySelector('img');
        const r = li.getBoundingClientRect();
        return { t: (li.innerText||'').trim().split('\n')[0],
                 blank: li.classList.contains('af-gf-circle--blank'),
                 gf: li.classList.contains('af-gf-circle'),
                 initial: li.getAttribute('data-af-initial') || '',
                 img: img ? (img.getAttribute('src') || '(none)') : '(no img el)',
                 imgShown: img ? getComputedStyle(img).display : '-',
                 box: Math.round(r.width) + 'x' + Math.round(r.height) };
      }),
    };
  });
  if (!circles || circles.err) console.log('\nFAIL: ' + ((circles && circles.err) || 'no circle strip on the page'));
  else {
    console.log('\nROW: <' + circles.rowTag + ' id=' + circles.rowId + ' class="' + circles.rowCls + '">');
    console.log('ITEM: <' + circles.itemTag + ' class="' + circles.itemCls + '">');
    console.log('ITEM HTML: ' + circles.itemHtml.replace(/\s+/g, ' '));
    console.log('\nCIRCLES under that tab (' + circles.items.length + '):');
    circles.items.forEach(c => console.log('   ' + JSON.stringify(c.t).padEnd(24) + ' gf=' + c.gf + ' blank=' + c.blank +
      ' initial=' + JSON.stringify(c.initial) + ' box=' + c.box + ' imgDisplay=' + c.imgShown +
      ' src=' + String(c.img).slice(-40)));
    const names = circles.items.map(c => c.t.toLowerCase());
    const want = ['gold foil prints', 'uv prints', 'mixed prints'];
    const missing = want.filter(w => !names.some(n => n.includes(w)));
    console.log(missing.length ? 'FAIL: missing ' + missing.join(', ') : 'OK: all three sub-collections present');
  }
}
await b.close();
