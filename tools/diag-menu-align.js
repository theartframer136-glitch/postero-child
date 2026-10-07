/**
 * Measure the CATEGORIES dropdown rows: where the icon sits, where the label
 * starts, and what the real gap between them is.
 *
 * The rows are hidden until hover, so the submenu is forced visible first —
 * a hidden element measures as zero and would make any fix a guess.
 */
const puppeteer = require('puppeteer-core');

(async () => {
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const p = await b.newPage();
  await p.setViewport({ width: 1400, height: 950 });
  await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 60000 });

  for (let i = 0; i < 10; i++) {
    const blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser'));
    if (!blocked) break;
    await new Promise(r => setTimeout(r, 2500));
    try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
  }

  const out = await p.evaluate(() => {
    // Find the dropdown that actually holds the category rows.
    const icon = document.querySelector('.primary-navigation i.menu-icon');
    if (!icon) return { error: 'no .menu-icon inside .primary-navigation' };
    const ul = icon.closest('ul');
    // Force the whole chain visible so geometry is real.
    let n = ul;
    while (n && n !== document.body) {
      n.style.setProperty('display', 'block', 'important');
      n.style.setProperty('visibility', 'visible', 'important');
      n.style.setProperty('opacity', '1', 'important');
      n.style.setProperty('height', 'auto', 'important');
      n.style.setProperty('overflow', 'visible', 'important');
      n = n.parentElement;
    }

    const rows = [...ul.children].slice(0, 12).map(li => {
      const a = li.querySelector(':scope > a');
      if (!a) return null;
      const ic = a.querySelector('i.menu-icon');
      const tt = a.querySelector('span.menu-title');
      const ar = a.getBoundingClientRect();
      const ir = ic ? ic.getBoundingClientRect() : null;
      const tr = tt ? tt.getBoundingClientRect() : null;
      const cs = (el) => el ? getComputedStyle(el) : null;
      const ca = cs(a), ci = cs(ic), ct = cs(tt);
      return {
        label: (tt ? tt.textContent : a.textContent).trim().slice(0, 26),
        hasIcon: !!ic,
        iconClass: ic ? ic.className : '',
        gapPx: (ir && tr) ? +(tr.left - ir.right).toFixed(1) : null,
        iconBox: ir ? `${ir.width.toFixed(1)}x${ir.height.toFixed(1)}` : null,
        iconTop: ir ? +ir.top.toFixed(1) : null,
        textTop: tr ? +tr.top.toFixed(1) : null,
        baselineOff: (ir && tr) ? +((ir.top + ir.height / 2) - (tr.top + tr.height / 2)).toFixed(1) : null,
        anchor: ca ? { display: ca.display, padding: ca.padding, align: ca.alignItems, gap: ca.gap, lineHeight: ca.lineHeight } : null,
        iconCss: ci ? { display: ci.display, fontSize: ci.fontSize, width: ci.width, margin: ci.margin,
                        vAlign: ci.verticalAlign, lineHeight: ci.lineHeight } : null,
        titleCss: ct ? { display: ct.display, fontSize: ct.fontSize, vAlign: ct.verticalAlign, lineHeight: ct.lineHeight } : null,
      };
    }).filter(Boolean);

    return { ulClass: ul.className, rows };
  });

  if (out.error) { console.log('ERROR:', out.error); await b.close(); return; }

  console.log(`=== dropdown ul.${out.ulClass} ===\n`);
  console.log('LABEL                      ICON?  GAP    ICONBOX    VCENTRE-OFF');
  console.log('-'.repeat(72));
  for (const r of out.rows) {
    console.log(
      r.label.padEnd(26) + ' ' +
      (r.hasIcon ? 'yes  ' : 'NO   ') + ' ' +
      String(r.gapPx === null ? '-' : r.gapPx + 'px').padStart(6) + ' ' +
      String(r.iconBox || '-').padStart(10) + ' ' +
      String(r.baselineOff === null ? '-' : r.baselineOff + 'px').padStart(12)
    );
  }
  const first = out.rows.find(r => r.hasIcon);
  if (first) {
    console.log('\n--- computed styles on a row that has an icon ---');
    console.log('  anchor :', JSON.stringify(first.anchor));
    console.log('  icon   :', JSON.stringify(first.iconCss));
    console.log('  title  :', JSON.stringify(first.titleCss));
    console.log('  icon class:', first.iconClass);
  }
  const noIcon = out.rows.filter(r => !r.hasIcon).map(r => r.label);
  if (noIcon.length) console.log('\n  rows with NO icon:', noIcon.join(', '));

  await b.close();
})();
