/**
 * Prove the refund policy page reads on a phone.
 *
 * Measures the two things the video showed wrong, at the width it was filmed
 * at: how wide the step description column actually is, and whether the
 * comparison table still forces its headings one letter to a line.
 *
 * Usage: node tools/diag-refund-mobile.js https://theartframer.us/refund-policy/
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/refund-policy/';

(async () => {
  const browser = await puppeteer.launch({ channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 420, height: 720, isMobile: true, deviceScaleFactor: 2 });
  await page.goto(URL, { waitUntil: 'networkidle2', timeout: 60000 });

  const out = await page.evaluate(() => {
    const r = { steps: [], table: null, overflow: null };

    document.querySelectorAll('.taf-track li').forEach(li => {
      const span = li.querySelector('span');
      const b = li.querySelector('b');
      if (!span) return;
      const sr = span.getBoundingClientRect(), br = b ? b.getBoundingClientRect() : null;
      // Words per line is the real tell: a 40px column shows one.
      const words = span.textContent.trim().split(/\s+/).length;
      const lines = Math.round(sr.height / parseFloat(getComputedStyle(span).lineHeight || 20));
      r.steps.push({
        title: b ? b.textContent.trim().slice(0, 22) : '(none)',
        descWidth: Math.round(sr.width),
        titleWidth: br ? Math.round(br.width) : 0,
        lines, words,
        wordsPerLine: +(words / Math.max(1, lines)).toFixed(1),
      });
    });

    const t = document.querySelector('.taf-table');
    if (t) {
      const firstRow = t.querySelector('tbody tr');
      const cells = firstRow ? [...firstRow.querySelectorAll('td')] : [];
      const cs = getComputedStyle(t);
      r.table = {
        display: cs.display,
        tableWidth: Math.round(t.getBoundingClientRect().width),
        theadHidden: (() => { const h = t.querySelector('thead');
          return h ? getComputedStyle(h).position === 'absolute' || getComputedStyle(h).display === 'none' : null; })(),
        rowIsCard: firstRow ? getComputedStyle(firstRow).display : null,
        cells: cells.map(td => ({
          label: td.getAttribute('data-taf-label'),
          width: Math.round(td.getBoundingClientRect().width),
          labelShown: getComputedStyle(td, '::before').content,
        })),
      };
    }

    r.overflow = {
      docWidth: document.documentElement.scrollWidth,
      viewport: window.innerWidth,
      horizontalScroll: document.documentElement.scrollWidth > window.innerWidth + 1,
    };
    return r;
  });

  console.log('=== REFUND POLICY AT 420px ===\n');
  console.log('--- the four tracker steps ---');
  for (const s of out.steps) {
    const ok = s.descWidth > 200 ? 'OK ' : 'BAD';
    console.log(`  ${ok}  "${s.title}"  description ${s.descWidth}px wide, ${s.lines} lines, ${s.wordsPerLine} words per line`);
  }
  console.log('\n--- the comparison table ---');
  if (!out.table) console.log('  no .taf-table found');
  else {
    console.log(`  table display   : ${out.table.display}  (${out.table.tableWidth}px wide)`);
    console.log(`  header row hidden: ${out.table.theadHidden}`);
    console.log(`  a row renders as: ${out.table.rowIsCard}`);
    for (const c of out.table.cells) {
      console.log(`    cell ${String(c.width).padStart(4)}px  label=${c.label || '(none)'}  drawn=${c.labelShown}`);
    }
  }
  console.log('\n--- page overflow ---');
  console.log(`  document ${out.overflow.docWidth}px vs viewport ${out.overflow.viewport}px` +
              (out.overflow.horizontalScroll ? '   <-- SIDEWAYS SCROLL' : '   (no sideways scroll)'));

  await browser.close();
})();
