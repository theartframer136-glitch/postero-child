/**
 * Read the homepage's Shop by Collection strip WITHOUT clicking anything.
 *
 * The first version clicked the embossed tab and then found neither the tab
 * strip nor the circle row, because the tab is an ordinary link and the click
 * navigated the browser off the homepage. Everything needed is on the page
 * already:
 *   - the tab labels, to confirm the rename reached the strip;
 *   - the GF payload the module ships, to confirm the three sub-collections
 *     are now in it;
 *   - whether the module's own circle-strip selectors match anything at all,
 *     which is the question that decides whether the row can ever be replaced;
 *   - and the row as it really is, located by its contents.
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

const out = await p.evaluate(() => {
  const res = {};
  res.tabs = [...document.querySelectorAll('#topCatSlider .top-cat-btn, .top-category-slider .top-cat-btn')]
    .map(a => ({ t: (a.innerText||'').trim(), gf: a.hasAttribute('data-af-gf'), val: a.getAttribute('data-val') || '' }));

  // The payload the module ships, read out of its own inline script.
  const sc = document.getElementById('af-gf-collection-js');
  if (sc) {
    const m = sc.textContent.match(/var GF = (\{[\s\S]*?\});/);
    if (m) { try { res.gf = JSON.parse(m[1]); } catch (e) { res.gfErr = String(e); } }
  } else res.gfErr = 'no af-gf-collection-js on the page';

  // Do the module's own selectors match anything?
  res.sel = {};
  ['#subcategorySlider', '.subcategory-slider', 'ul.postero-scroll-content'].forEach(s => {
    res.sel[s] = document.querySelectorAll(s).length;
  });
  res.selItem = {};
  ['li.cat-item', '.sub-cat'].forEach(s => { res.selItem[s] = document.querySelectorAll(s).length; });

  // The row as it really is: the smallest element under the tab strip that
  // holds several short captions.
  const tabStrip = document.querySelector('#topCatSlider, .top-category-slider');
  res.tabStripFound = !!tabStrip;
  if (tabStrip) {
    const tabBottom = tabStrip.getBoundingClientRect().bottom;
    let best = null;
    for (const el of document.querySelectorAll('ul, div, nav')) {
      const r = el.getBoundingClientRect();
      if (r.top < tabBottom - 5 || r.top > tabBottom + 260) continue;
      if (r.height < 30 || r.height > 220 || r.width < 300) continue;
      const kids = [...el.children];
      if (kids.length < 3) continue;
      if (kids.filter(k => { const t = (k.innerText||'').trim(); return t && t.length < 40; }).length < 3) continue;
      const n = el.querySelectorAll('*').length;
      if (!best || n < best.n) best = { el, n };
    }
    if (best) {
      const el = best.el, kid = el.children[0];
      res.row = {
        tag: el.tagName.toLowerCase(), id: el.id || '(none)', cls: String(el.className).slice(0,100),
        itemTag: kid ? kid.tagName.toLowerCase() : '-', itemCls: kid ? String(kid.className).slice(0,100) : '-',
        itemHtml: kid ? kid.outerHTML.slice(0,420) : '-',
        captions: [...el.children].map(k => (k.innerText||'').trim().split('\n')[0]).slice(0,16),
      };
    }
  }
  return res;
});

console.log('TABS (' + out.tabs.length + '):');
out.tabs.forEach(t => console.log('   ' + (t.gf ? '* ' : '  ') + JSON.stringify(t.t) + '  slug=' + t.val));
console.log(out.tabs.some(t => /gold\s*foil/i.test(t.t)) ? 'FAIL: a tab still reads Gold Foil' : 'OK: no tab reads Gold Foil');
console.log(out.tabs.some(t => /emboss/i.test(t.t)) ? 'OK: a tab reads Embossed' : 'FAIL: no Embossed tab');

console.log('\nGF PAYLOAD:');
if (!out.gf) console.log('   unreadable: ' + (out.gfErr || '?'));
else {
  console.log('   label   : ' + JSON.stringify(out.gf.label));
  console.log('   circles : ' + (out.gf.circles || []).length);
  (out.gf.circles || []).forEach(c => console.log('       ' + JSON.stringify(c.n) + ' slug=' + c.s + ' img=' + (c.i ? 'yes' : 'NONE')));
  const names = (out.gf.circles || []).map(c => (c.n||'').toLowerCase());
  const missing = ['gold foil prints','uv prints','mixed prints'].filter(w => !names.some(n => n.includes(w)));
  console.log(missing.length ? '   FAIL: payload missing ' + missing.join(', ') : '   OK: all three sub-collections in the payload');
}

console.log("\nDOES THE MODULE'S STRIP SELECTOR MATCH ANYTHING?");
Object.entries(out.sel).forEach(([k,v]) => console.log('   ' + k.padEnd(28) + v + ' match(es)'));
Object.entries(out.selItem).forEach(([k,v]) => console.log('   ' + k.padEnd(28) + v + ' match(es)'));
const anyStrip = Object.values(out.sel).some(v => v > 0);
console.log(anyStrip ? '   OK: the module can find the row' : '   FAIL: none of the module\'s selectors match - the row can never be replaced');

console.log('\nTHE ROW AS IT REALLY IS:');
if (!out.row) console.log('   not found (tabStripFound=' + out.tabStripFound + ')');
else {
  console.log('   <' + out.row.tag + ' id=' + out.row.id + ' class="' + out.row.cls + '">');
  console.log('   item: <' + out.row.itemTag + ' class="' + out.row.itemCls + '">');
  console.log('   itemHtml: ' + out.row.itemHtml.replace(/\s+/g,' '));
  console.log('   captions: ' + JSON.stringify(out.row.captions));
}

// ---- and now the click, without letting the browser leave the page --------
// The tab is an ordinary link, so an earlier run navigated away and could
// measure nothing. Suppressing only the DEFAULT action leaves every handler
// the theme and the module registered free to run, which is exactly what is
// being tested here.
await p.evaluate(() => { document.addEventListener('click', e => e.preventDefault(), true); });
await p.evaluate(() => {
  const a = [...document.querySelectorAll('#topCatSlider .top-cat-btn, .top-category-slider .top-cat-btn')]
    .find(x => /emboss/i.test(x.innerText || ''));
  if (a) a.click();
});
await new Promise(r => setTimeout(r, 5000));

const where = await p.evaluate(() => location.href);
console.log('\nURL after the click: ' + where);

const after = await p.evaluate(() => {
  const strip = document.querySelector('#subcategorySlider, .subcategory-slider');
  // "vanished" was ambiguous: the row is gone either because the theme wiped
  // it or because the browser left the homepage entirely. Say which.
  if (!strip) return { err: /theartframer\.us\/?($|\?|#)/.test(location.href)
      ? 'the row was removed while still on the homepage'
      : 'the browser navigated to ' + location.href };
  return [...strip.children].map(el => {
    const img = el.querySelector('img');
    const r = el.getBoundingClientRect();
    const src = img ? (img.getAttribute('src') || '') : '';
    return { t: (el.innerText||'').trim().split('\n')[0],
             gf: el.classList.contains('af-gf-circle'),
             blank: el.classList.contains('af-gf-circle--blank'),
             subcat: el.getAttribute('data-subcat') || '(none)',
             img: src.startsWith('data:') ? '(drawn placeholder)' : (src ? 'photo' : 'NONE'),
             box: Math.round(r.width) + 'x' + Math.round(r.height) };
  });
});

console.log('\nAFTER CLICKING THE EMBOSSED TAB:');
if (after.err) console.log('   FAIL: ' + after.err);
else {
  after.forEach(c => console.log('   ' + JSON.stringify(c.t).padEnd(22) + ' gf=' + c.gf + ' blank=' + c.blank +
    ' subcat=' + c.subcat.padEnd(18) + ' img=' + c.img.padEnd(22) + ' box=' + c.box));
  const names = after.map(c => c.t.toLowerCase());
  const missing = ['gold foil prints','uv prints','mixed prints'].filter(w => !names.some(n => n.includes(w)));
  console.log(missing.length ? '   FAIL: row missing ' + missing.join(', ') : '   OK: the row shows all three sub-collections');
  const dead = after.filter(c => c.gf && c.subcat === '(none)');
  console.log(dead.length ? '   FAIL: ' + dead.length + ' circle(s) carry no data-subcat' : '   OK: every circle carries its slug');
  const flat = after.filter(c => c.box.startsWith('0x') || c.box.endsWith('x0'));
  console.log(flat.length ? '   FAIL: ' + flat.length + ' circle(s) have collapsed to no size' : '   OK: every circle has a size');
}

await b.close();
