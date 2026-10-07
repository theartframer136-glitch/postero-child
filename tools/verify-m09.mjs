// M-09: does the homepage send what it shows, and no more?
//
// Test Run 03, M-09 (went backwards), homepage:
//                          run 02   run 03
//   DOM nodes               8,060    9,713
//   links and buttons       1,250    1,538
//   href="#" dead links        83      119
//   tap targets < 44 px       347      516
//
// tools/probe-home-weight.mjs found where it comes from: Shop by Collection
// loaded every piece of its first collection (74 Radha Krishna cards, 46
// nodes, 8 links, one href="#" and five small targets each), and ten sections
// of the homepage hidden at every width were still sent (1,645 nodes).
// inc/home-weight.php: the homepage asks load_products for 12 and links to the
// rest; sections hidden at every width are not sent.
//
// As a first-time visitor, at 1440 × 900 (how run 03 counted), checks:
//   1  the four counts are back at or under run 02
//   2  Shop by Collection opens on at most 12 pieces
//   3  under it, "See all 74 in Radha Krishna", visible, 44 px or taller,
//      linking to the collection page
//   4  another collection circle still loads its pieces, at most 12, and the
//      link follows it (or goes, when every piece already shows)
//   5  a category page still gets the whole collection from the same endpoint
//   6  no section of the homepage's own content is hidden at every width
//   7  every section that was on show before still is
// And, for the record, the same counts on a phone.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-m09.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });
const results = [];
const say = (ok, what, seen) => { results.push(ok); console.log('  ' + (ok ? 'RIGHT ' : 'WRONG ') + what.padEnd(66) + seen); };
const RUN02 = { nodes: 8060, links: 1250, hash: 83, tiny: 347 };
const RUN03 = { nodes: 9713, links: 1538, hash: 119, tiny: 516 };

// The homepage's containers on show at 1440 px before this change (probe run
// 109): every one must still be there and on show after it.
const SHOWN_BEFORE = ['b32d9a1', '7aa7f44', 'b73f1b1', 'ab616f8', '48b4f36', '2596335', '9bf0335', '85e906b', '609aca5'];

const counts = () => {
  const tiny = el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && (r.width < 44 || r.height < 44); };
  return {
    nodes: document.getElementsByTagName('*').length,
    links: document.querySelectorAll('a,button').length,
    hash: document.querySelectorAll('a[href="#"]').length,
    tiny: [...document.querySelectorAll('a,button,[role="button"]')].filter(tiny).length,
  };
};
const collection = () => {
  const t = s => String(s || '').replace(/\s+/g, ' ').trim();
  const grid = document.querySelector('#productGrid');
  const shell = grid && grid.nextElementSibling && grid.nextElementSibling.classList.contains('af-shell') ? grid.nextElementSibling : null;
  const cards = (shell || grid) ? (shell || grid).querySelectorAll('.product-card').length + (shell && grid ? grid.querySelectorAll('.product-card').length : 0) : -1;
  const a = document.querySelector('.af-coll-all');
  const r = a ? a.getBoundingClientRect() : null;
  const m = grid ? grid.querySelector('.af-coll-more') : null;
  return {
    cards, slider: !!shell,
    link: a ? { text: t(a.textContent), href: a.getAttribute('href'), h: Math.round(r.height), shown: r.width > 0 && r.height > 0 } : null,
    marker: m ? { n: +m.getAttribute('data-count'), name: m.getAttribute('data-name'), url: m.getAttribute('data-url') } : null,
    active: t((document.querySelector('.subcategory-container .active, #subcategorySlider .active, li.cat-item.active') || {}).textContent).slice(0, 30),
  };
};
const sections = (ids) => {
  const cfg = (window.elementorFrontendConfig && elementorFrontendConfig.responsive) || {};
  const active = ['desktop'].concat(Object.keys(cfg.activeBreakpoints || {}));
  const page = document.querySelector('[data-elementor-type="wp-page"]');
  const everywhere = page ? [...page.querySelectorAll('[class*="elementor-hidden-"]')].filter(el => active.every(d => el.classList.contains('elementor-hidden-' + d))) : [];
  const shown = ids.map(id => {
    const el = document.querySelector('.elementor-element-' + id);
    const r = el ? el.getBoundingClientRect() : null;
    return { id, there: !!el, shown: !!r && r.width > 0 && r.height > 0 };
  });
  return { active, everywhere: everywhere.filter(el => !everywhere.some(o => o !== el && o.contains(el))).map(el => el.getAttribute('data-id')), shown };
};

const open = async (viewport) => {
  const ctx = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const ajax = [];
  page.on('response', async res => {
    const body = res.request().postData() || '';
    if (!/admin-ajax\.php/.test(res.url()) || !/(^|&)action=load_products(&|$)/.test(body)) return;
    try { const txt = await res.text(); ajax.push({ body, cards: (txt.match(/class="product-card/g) || []).length, marker: /af-coll-more/.test(txt) }); } catch {}
  });
  const r = await page.goto(SITE + '/', { waitUntil: 'load', timeout: 90000 }).catch(() => null);
  const html = r ? await r.text().catch(() => '') : '';
  await page.waitForTimeout(5000);
  return { ctx, page, ajax, html, status: r ? r.status() : 0 };
};

console.log('verify-m09: ' + SITE + '   ' + new Date().toISOString() + '\n');

const d = await open({ width: 1440, height: 900 });
if (!d.status) { console.log('  the homepage did not answer'); process.exit(1); }
const c = await d.page.evaluate(counts);
const col = await d.page.evaluate(collection);
const sec = await d.page.evaluate(sections, SHOWN_BEFORE);
const note = (d.html.match(/<!-- af-home-weight: ([^>]*) -->/) || [])[1] || '(no note)';
console.log('  homepage at 1440 × 900        run 02   run 03   now');
for (const [k, label] of [['nodes', 'DOM nodes'], ['links', 'links and buttons'], ['hash', 'href="#"'], ['tiny', 'tap targets < 44 px']]) {
  console.log('    ' + label.padEnd(26) + String(RUN02[k]).padStart(7) + String(RUN03[k]).padStart(9) + String(c[k]).padStart(8));
}
console.log('  load_products on load: ' + (d.ajax.length ? d.ajax.map(a => decodeURIComponent(a.body).slice(0, 50) + ' → ' + a.cards + ' cards' + (a.marker ? ' + see-all' : '')).join(' | ') : 'none'));
console.log('  Shop by Collection: ' + col.cards + ' cards' + (col.slider ? ' in the slider' : '') + ' · open tab "' + col.active + '" · link ' + (col.link ? '"' + col.link.text + '" → ' + col.link.href + ' (' + col.link.h + ' px)' : 'none'));
console.log('  page note: ' + note);
console.log('');

say(c.nodes <= RUN02.nodes && c.links <= RUN02.links && c.hash <= RUN02.hash && c.tiny <= RUN02.tiny,
  '1  nodes, links, href="#", small targets at or under run 02',
  `${c.nodes} · ${c.links} · ${c.hash} · ${c.tiny}`);
say(col.cards > 0 && col.cards <= 12 && d.ajax.length > 0 && d.ajax.every(a => a.cards <= 12),
  '2  Shop by Collection opens on at most 12 pieces',
  col.cards + ' cards · load_products answered ' + d.ajax.map(a => a.cards).join(', '));
say(!!col.link && col.link.shown && col.link.h >= 44 && !!col.marker && col.link.href === col.marker.url && col.marker.n > col.cards
    && /\/product-category\//.test(col.link.href) && col.link.text === 'See all ' + col.marker.n + ' in ' + col.marker.name + ' →',
  '3  under it, "See all N in <collection>", shown, 44 px+, to its page',
  col.link ? '"' + col.link.text + '" · ' + col.link.h + ' px · ' + col.link.href : 'no link');

// 4: another circle
const before = d.ajax.length;
const clicked = await d.page.evaluate(() => {
  // One element per circle, whichever markup the strip uses.
  const pick = sel => [...document.querySelectorAll(sel)]
    .filter(el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && !el.classList.contains('active'); });
  let items = pick('.subcategory-container li.cat-item, #subcategorySlider li.cat-item');
  if (!items.length) items = pick('.subcategory-container .sub-cat');
  if (!items.length) items = pick('.subcategory-container a.pf-value');
  const it = items[1] || items[0];
  if (!it) return null;
  const label = (it.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30);
  (it.querySelector('a') || it).click();
  return label;
});
await d.page.waitForTimeout(6000);
const col2 = await d.page.evaluate(collection).catch(() => ({ cards: -1, link: null, marker: null, gone: d.page.url() }));
if (col2.gone) console.log('  the circle click left the homepage for ' + col2.gone);
const tab = d.ajax.slice(before);
const linkFollows = col2.marker ? (!!col2.link && col2.link.text === 'See all ' + col2.marker.n + ' in ' + col2.marker.name + ' →' && col2.link.shown) : !col2.link;
say(!!clicked && tab.length > 0 && tab.every(a => a.cards <= 12) && col2.cards > 0 && col2.cards <= 12 && linkFollows,
  '4  another circle: its pieces, at most 12, and the link follows',
  clicked ? `"${clicked}" → ${col2.cards} cards · link ${col2.link ? '"' + col2.link.text + '"' : 'none'}${col2.marker ? '' : ' (every piece shown)'}` : 'no circle to click');
await d.ctx.close();

// 5: a category page asks the same endpoint and must get everything
const ctx5 = await browser.newContext({ ignoreHTTPSErrors: true });
const p5 = await ctx5.newPage();
await p5.goto(SITE + '/product-category/radha-krishna/', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => null);
const cat = await p5.evaluate(async () => {
  const body = new URLSearchParams({ action: 'load_products', subcategory: 'radha-krishna' });
  const r = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() });
  const txt = await r.text();
  return { cards: (txt.match(/class="product-card/g) || []).length, marker: /af-coll-more/.test(txt) };
}).catch(() => null);
await ctx5.close();
const want = col.marker ? col.marker.n : 74;
say(!!cat && cat.cards >= want && !cat.marker, '5  from a category page, load_products still sends the whole collection',
  cat ? cat.cards + ' Radha Krishna cards' + (cat.marker ? ' + a see-all marker' : '') + ' (the homepage says ' + want + ')' : 'no answer');

say(sec.everywhere.length === 0, '6  no homepage section is hidden at every width',
  sec.everywhere.length ? sec.everywhere.join(' ') : 'none (widths: ' + sec.active.join(', ') + ')');
const gone = sec.shown.filter(s => !s.there || !s.shown);
say(gone.length === 0, '7  every section on show before still is', gone.length ? 'missing: ' + gone.map(s => s.id + (s.there ? ' (hidden)' : ' (gone)')).join(' ') : sec.shown.length + ' of ' + sec.shown.length);

const ph = await open({ width: 375, height: 812 });
const cp = await ph.page.evaluate(counts);
await ph.ctx.close();
console.log('\n  for the record, on a phone (375 × 812): ' + cp.nodes + ' nodes · ' + cp.links + ' links and buttons · href="#" ' + cp.hash + ' · under 44 px ' + cp.tiny);

const right = results.filter(Boolean).length;
console.log('\n' + (right === results.length ? 'M-09 FIXED' : 'M-09 NOT FIXED') + ': ' + right + ' of ' + results.length + ' checks right');
console.log('done ' + new Date().toISOString());
await browser.close();
