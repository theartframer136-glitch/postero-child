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
await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 60000 });
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

  const cells = [...bar.children].map(cell => {
    const cs = getComputedStyle(cell);
    const icon = cell.querySelector('svg, i[class], img');
    const textNode = [...cell.querySelectorAll('*')].filter(e => {
      const t = (e.innerText||'').trim(); return LABELS.includes(t) && !e.querySelector('svg,i[class],img'); }).pop();
    return {
      label: (cell.innerText||'').trim().split('\n')[0],
      cls: String(cell.className).slice(0,70),
      cell: box(cell),
      icon: icon ? box(icon) : null,
      iconTag: icon ? icon.tagName.toLowerCase()+'|'+String(icon.className.baseVal ?? icon.className).slice(0,40) : null,
      iconWrap: icon && icon.parentElement !== cell ? { cls:String(icon.parentElement.className).slice(0,50), box:box(icon.parentElement), ta:getComputedStyle(icon.parentElement).textAlign, d:getComputedStyle(icon.parentElement).display, pad:getComputedStyle(icon.parentElement).padding, m:getComputedStyle(icon.parentElement).margin } : null,
      text: textNode ? { cls:String(textNode.className).slice(0,40), box:box(textNode), ta:getComputedStyle(textNode).textAlign } : null,
      cs: { d:cs.display, ai:cs.alignItems, jc:cs.justifyContent, ta:cs.textAlign, pad:cs.padding, m:cs.margin, fd:cs.flexDirection },
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
  console.log('---- HTML ----\n' + out.html);
}
await b.close();
