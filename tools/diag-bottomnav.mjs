/**
 * Mobile bottom bar: Shop / Account / Search / Wishlist.
 * The previous pass resolved each "cell" by walking up from the label, which
 * landed on a text-only wrapper that excludes the icon — so every icon read as
 * missing. Measure the bar's own direct children instead (they are the real
 * cells) and dump the markup so the structure is not guessed at.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');

const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 420, height: 720, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
// The server is slow enough at times that a single attempt times out and the
// run reports nothing; that is not a finding about the page.
let loaded = false;
for (let attempt = 1; attempt <= 3 && !loaded; attempt++) {
  try {
    await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 });
    loaded = true;
  } catch (e) {
    console.log('load attempt ' + attempt + ' failed: ' + e.message);
    await new Promise(r => setTimeout(r, 4000));
  }
}
if (!loaded) { console.log('could not load the page - no measurement'); await b.close(); process.exit(0); }
await new Promise(r => setTimeout(r, 4000));
for (let i = 0; i < 8; i++) {
  if (!(await p.evaluate(() => document.body.innerText.includes('Checking your browser')))) break;
  await new Promise(r => setTimeout(r, 2500));
  try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
}
if (await p.evaluate(() => document.body.innerText.includes('Checking your browser'))) {
  console.log('BLOCKED by bot check - no measurement'); await b.close(); process.exit(0);
}
await new Promise(r => setTimeout(r, 2500));

const out = await p.evaluate(() => {
  const LABELS = ['Shop', 'Account', 'Search', 'Wishlist'];
  const bar = [...document.querySelectorAll('div,nav,section')].filter(el => {
    const t = (el.innerText || '').trim();
    if (!LABELS.every(l => t.includes(l))) return false;
    if (getComputedStyle(el).position !== 'fixed') return false;
    const r = el.getBoundingClientRect();
    return r.height > 30 && r.height < 160 && r.bottom > window.innerHeight - 20;
  }).sort((a, c) => a.getBoundingClientRect().height - c.getBoundingClientRect().height)[0];
  if (!bar) return { found: false };

  const box = el => { const r = el.getBoundingClientRect();
    return { l:+r.left.toFixed(1), t:+r.top.toFixed(1), w:+r.width.toFixed(1), h:+r.height.toFixed(1), cx:+(r.left+r.width/2).toFixed(1), cy:+(r.top+r.height/2).toFixed(1) }; };

  // The bar's single child is a grid wrapper; the real cells are the four
  // Elementor widgets inside it. Measure each widget's own icon against its
  // own label - the previous pass compared every icon to the LAST label in
  // the bar, which made the numbers meaningless.
  const widgets = [...bar.querySelectorAll('.elementor-widget')];
  const cells = widgets.map(cell => {
    const cs = getComputedStyle(cell);
    const icon = cell.querySelector('svg, i[class], img');
    const textNode = [...cell.querySelectorAll('h3, span, a')].filter(e => {
      const t = (e.innerText||'').trim();
      return LABELS.includes(t) && !e.querySelector('svg,i[class],img'); }).pop();
    return {
      label: (cell.innerText||'').trim().split('\n')[0],
      cls: String(cell.className).slice(0,60),
      cell: box(cell),
      icon: icon ? box(icon) : null,
      iconTag: icon ? icon.tagName.toLowerCase()+'|'+String(icon.className.baseVal ?? icon.className).slice(0,34) : null,
      iconWrap: icon && icon.parentElement !== cell ? { cls:String(icon.parentElement.className).slice(0,40), box:box(icon.parentElement), ta:getComputedStyle(icon.parentElement).textAlign, d:getComputedStyle(icon.parentElement).display } : null,
      text: textNode ? { cls:String(textNode.className).slice(0,34), box:box(textNode), ta:getComputedStyle(textNode).textAlign } : null,
      cs: { d:cs.display, ai:cs.alignItems, jc:cs.justifyContent, ta:cs.textAlign, pad:cs.padding },
    };
  });
  return { found: true, barBox: box(bar), barCls: String(bar.className).slice(0,90),
           barCs: (c=>({d:c.display,gtc:c.gridTemplateColumns,ai:c.alignItems,jc:c.justifyContent,gap:c.gap,pad:c.padding}))(getComputedStyle(bar)),
           html: bar.outerHTML.slice(0, 6000), cells };
});

if (!out.found) { console.log('bottom bar not found'); }
else {
  console.log('BAR', JSON.stringify(out.barBox), out.barCls);
  console.log('BARCS', JSON.stringify(out.barCs), '\n');
  for (const c of out.cells) {
    console.log('== ' + c.label + '  [' + c.cls + ']');
    console.log('   cell ', JSON.stringify(c.cell), JSON.stringify(c.cs));
    console.log('   icon ', c.iconTag, JSON.stringify(c.icon));
    console.log('   wrap ', JSON.stringify(c.iconWrap));
    console.log('   text ', JSON.stringify(c.text));
    if (c.icon && c.text) console.log('   >> iconCx-textCx = ' + (c.icon.cx - c.text.box.cx).toFixed(1) + '   iconCx-cellCx = ' + (c.icon.cx - c.cell.cx).toFixed(1) + '   textCx-cellCx = ' + (c.text.box.cx - c.cell.cx).toFixed(1));
    console.log('');
  }
  // VERTICAL: the fault in the screenshot is that the four do not share a
  // baseline - one sits a little low, another a little high. Compare each
  // icon's top and each label's top against the others.
  const withBoth = out.cells.filter(c => c.icon && c.text);
  if (withBoth.length) {
    const iconTops = withBoth.map(c => c.icon.t);
    const textTops = withBoth.map(c => c.text.box.t);
    const spread = a => +(Math.max(...a) - Math.min(...a)).toFixed(1);
    console.log('\nVERTICAL ALIGNMENT');
    withBoth.forEach(c => console.log('   ' + c.label.padEnd(10) + ' iconTop=' + c.icon.t + '  labelTop=' + c.text.box.t + '  iconH=' + c.icon.h));
    console.log('   icon tops differ by  : ' + spread(iconTops) + 'px');
    console.log('   label tops differ by : ' + spread(textTops) + 'px');
    console.log(spread(iconTops) <= 1 && spread(textTops) <= 1
      ? '   OK: all four share one baseline'
      : '   FAIL: the four do not share a baseline');
  }
  const bad = out.cells.filter(c => c.icon && c.text && Math.abs(c.icon.cx - c.text.box.cx) > 1.5);
  console.log(bad.length ? 'RESULT: ' + bad.length + ' cell(s) still off-centre' : 'RESULT: all cells centred (icon within 1.5px of its label)');
}
await b.close();
