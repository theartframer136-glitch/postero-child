/**
 * The mobile bottom bar: Shop / Account / Search / Wishlist.
 * For each cell, where is the icon's centre against the label's centre, and
 * against the cell's own centre? Three different faults look identical on a
 * phone screenshot: the icon off-centre in its cell, the label off-centre, or
 * the pair centred as a unit but padded unevenly. Measure all three.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 420, height: 720, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 60000 });
for (let i = 0; i < 8; i++) {
  if (!(await p.evaluate(() => document.body.innerText.includes('Checking your browser')))) break;
  await new Promise(r => setTimeout(r, 2500));
  try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
}
await new Promise(r => setTimeout(r, 2500));

const out = await p.evaluate(() => {
  const LABELS = ['Shop', 'Account', 'Search', 'Wishlist'];
  // the bar is the element that contains all four labels and is pinned to the bottom
  const all = [...document.querySelectorAll('div,nav,section')];
  const bar = all.filter(el => {
    const t = (el.innerText || '').trim();
    if (!LABELS.every(l => t.includes(l))) return false;
    const cs = getComputedStyle(el);
    if (cs.position !== 'fixed') return false;
    const r = el.getBoundingClientRect();
    return r.height > 30 && r.height < 160 && r.bottom > window.innerHeight - 20;
  }).sort((a, c) => a.getBoundingClientRect().height - c.getBoundingClientRect().height)[0];
  if (!bar) return { found: false };

  const br = bar.getBoundingClientRect();
  const cells = LABELS.map(label => {
    // the smallest element that holds exactly this label
    const cand = [...bar.querySelectorAll('*')].filter(el => (el.innerText || '').trim() === label);
    const cell = cand.length ? cand[cand.length - 1].closest('[class*="icon-box"],[class*="elementor-element"],li,a,div') : null;
    if (!cell) return { label, missing: true };
    const icon = cell.querySelector('svg, i, img, [class*="icon"]:not([class*="icon-box"])');
    const text = [...cell.querySelectorAll('*')].filter(e => (e.innerText||'').trim() === label).pop() || cell;
    const cr = cell.getBoundingClientRect(), ir = icon ? icon.getBoundingClientRect() : null, tr = text.getBoundingClientRect();
    const cs = getComputedStyle(cell);
    const iw = icon ? getComputedStyle(icon) : null;
    return {
      label,
      cellBox: `${Math.round(cr.width)}x${Math.round(cr.height)}`,
      cellCentre: +(cr.left + cr.width / 2).toFixed(1),
      iconCentre: ir ? +(ir.left + ir.width / 2).toFixed(1) : null,
      textCentre: +(tr.left + tr.width / 2).toFixed(1),
      iconVsText: ir ? +((ir.left + ir.width/2) - (tr.left + tr.width/2)).toFixed(1) : null,
      iconVsCell: ir ? +((ir.left + ir.width/2) - (cr.left + cr.width/2)).toFixed(1) : null,
      textVsCell: +((tr.left + tr.width/2) - (cr.left + cr.width/2)).toFixed(1),
      iconBox: ir ? `${Math.round(ir.width)}x${Math.round(ir.height)}` : null,
      iconTag: icon ? icon.tagName.toLowerCase() + '.' + String(icon.className.baseVal ?? icon.className).split(/\s+/)[0] : null,
      cellDisplay: cs.display, cellAlign: cs.alignItems, cellJustify: cs.justifyContent,
      cellTextAlign: cs.textAlign, cellPadding: cs.padding,
      iconDisplay: iw ? iw.display : null, iconMargin: iw ? iw.margin : null,
      iconParentTag: icon && icon.parentElement ? icon.parentElement.tagName.toLowerCase() + '.' + String(icon.parentElement.className).split(/\s+/).slice(0,2).join('.') : null,
      iconParentBox: icon && icon.parentElement ? (() => { const r = icon.parentElement.getBoundingClientRect(); return `${Math.round(r.width)}x${Math.round(r.height)}`; })() : null,
      iconParentCentre: icon && icon.parentElement ? +(() => { const r = icon.parentElement.getBoundingClientRect(); return r.left + r.width/2; })().toFixed(1) : null,
      iconParentTextAlign: icon && icon.parentElement ? getComputedStyle(icon.parentElement).textAlign : null,
    };
  });
  return { found: true, barBox: `${Math.round(br.width)}x${Math.round(br.height)}`, barClass: String(bar.className).slice(0,80), cells };
});

if (!out.found) { console.log('bottom bar not found'); await b.close(); }
else {
  console.log(`bar ${out.barBox}   class="${out.barClass}"\n`);
  console.log('LABEL      CELL       ICON vs TEXT   ICON vs CELL   TEXT vs CELL   ICONBOX');
  console.log('-'.repeat(82));
  for (const c of out.cells) {
    if (c.missing) { console.log(`${c.label.padEnd(10)} (not found)`); continue; }
    console.log(
      c.label.padEnd(10) + c.cellBox.padEnd(11) +
      String(c.iconVsText).padStart(10) + 'px' +
      String(c.iconVsCell).padStart(13) + 'px' +
      String(c.textVsCell).padStart(13) + 'px   ' + (c.iconBox || '-'));
  }
  const f = out.cells.find(c => !c.missing);
  if (f) {
    console.log('\n--- first cell in detail ---');
    console.log(`  cell   : display=${f.cellDisplay} align-items=${f.cellAlign} justify=${f.cellJustify} text-align=${f.cellTextAlign} padding=${f.cellPadding}`);
    console.log(`  icon   : ${f.iconTag}  display=${f.iconDisplay}  margin=${f.iconMargin}`);
    console.log(`  icon's parent: ${f.iconParentTag}  box=${f.iconParentBox}  text-align=${f.iconParentTextAlign}  centre=${f.iconParentCentre}`);
  }
  await b.close();
}
