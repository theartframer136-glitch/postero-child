/**
 * Is the Quick View on the category page the SAME modal as the one on the
 * homepage, or a different one?
 *
 * The recordings show two very different things from the same eye icon: on
 * the homepage a wide two-column panel with a thumbnail strip and a sticky
 * add-to-cart bar, on a category page a cramped one with a dot carousel, a
 * title wrapping a word per line, and a VIEW BROCHURE button floating outside
 * the panel. Either two plugins are answering the same click on different
 * pages, or one modal is being restyled by the page under it. Those need
 * opposite fixes, so identify it rather than guess.
 */
const puppeteer = require('puppeteer-core');

const PAGES = [
  ['homepage', 'https://theartframer.us/'],
  ['category', 'https://theartframer.us/product-category/digital-canvas-prints/'],
];

async function look(browser, label, url) {
  const p = await browser.newPage();
  await p.setViewport({ width: 1400, height: 950 });
  await p.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
  for (let i = 0; i < 10; i++) {
    const blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser'));
    if (!blocked) break;
    await new Promise(r => setTimeout(r, 2500));
    try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
  }

  // What quick-view triggers even exist on this page?
  const triggers = await p.evaluate(() => {
    const sel = '.woosq-btn, .woosq-open, a[class*="quick"], button[class*="quick"], [data-quick], .eael-product-quick-view, .open-quick-view';
    return [...document.querySelectorAll(sel)].slice(0, 6).map(e => ({
      tag: e.tagName.toLowerCase(), cls: (e.className || '').toString().slice(0, 70),
      id: e.getAttribute('data-id') || e.getAttribute('data-product_id') || '',
    }));
  });

  let opened = false;
  try {
    await p.evaluate(() => {
      const sel = '.woosq-btn, .woosq-open, a[class*="quick"], button[class*="quick"], [data-quick], .eael-product-quick-view';
      const b = document.querySelector(sel);
      if (b) b.click();
    });
    await new Promise(r => setTimeout(r, 7000));
    opened = true;
  } catch (e) { /* fall through */ }

  const modal = await p.evaluate(() => {
    // Find the biggest visible fixed/absolute panel that appeared.
    const cands = [...document.querySelectorAll('div,aside,section')].filter(el => {
      const cs = getComputedStyle(el);
      if (cs.display === 'none' || cs.visibility === 'hidden' || +cs.opacity === 0) return false;
      if (!/fixed|absolute/.test(cs.position)) return false;
      const r = el.getBoundingClientRect();
      return r.width > 500 && r.height > 300 && r.top < window.innerHeight;
    });
    if (!cands.length) return { found: false };
    cands.sort((a, b) => (b.getBoundingClientRect().width * b.getBoundingClientRect().height)
                       - (a.getBoundingClientRect().width * a.getBoundingClientRect().height));
    const m = cands[0];
    const r = m.getBoundingClientRect();

    const q = (s) => m.querySelector(s);
    const title = q('h1,h2,.product_title,.entry-title');
    const tr = title ? title.getBoundingClientRect() : null;
    const img = q('img');
    const ir = img ? img.getBoundingClientRect() : null;

    return {
      found: true,
      cls: (m.className || '').toString().slice(0, 110),
      id: m.id || '',
      box: `${Math.round(r.width)}x${Math.round(r.height)}`,
      left: Math.round(r.left), top: Math.round(r.top),
      // The tells that separate the two designs seen in the recordings
      hasThumbStrip: !!q('.flex-control-thumbs, .woosq-thumbs, .thumbnails, [class*="thumb"] img'),
      hasDotCarousel: !!q('.swiper-pagination, .slick-dots, .flickity-page-dots, [class*="dots"]'),
      hasStickyBar: !!q('[class*="sticky"], [class*="footer-bar"], [class*="bottom-bar"]'),
      titleWidth: tr ? Math.round(tr.width) : null,
      titleHeight: tr ? Math.round(tr.height) : null,
      titleText: title ? title.textContent.trim().replace(/\s+/g, ' ').slice(0, 44) : null,
      imgWidth: ir ? Math.round(ir.width) : null,
      // Anything drawn outside the panel's own box is escaping it
      escaping: [...m.querySelectorAll('*')].filter(el => {
        const b = el.getBoundingClientRect();
        return b.width > 40 && b.height > 14 && (b.top < r.top - 2 || b.right > r.right + 2);
      }).slice(0, 4).map(el => (el.className || el.tagName).toString().slice(0, 50)),
    };
  });

  await p.close();
  return { label, url, triggers, opened, modal };
}

(async () => {
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  for (const [label, url] of PAGES) {
    const r = await look(b, label, url);
    console.log(`\n================ ${r.label.toUpperCase()} ================`);
    console.log(`  ${r.url}`);
    console.log(`  quick-view triggers on the page:`);
    if (!r.triggers.length) console.log('      none matched');
    for (const t of r.triggers) console.log(`      <${t.tag} class="${t.cls}"> id=${t.id}`);
    if (!r.modal.found) { console.log('  NO MODAL opened'); continue; }
    const m = r.modal;
    console.log(`  panel      : ${m.box} at ${m.left},${m.top}`);
    console.log(`  its classes: ${m.cls}${m.id ? '  #' + m.id : ''}`);
    console.log(`  thumb strip: ${m.hasThumbStrip}   dot carousel: ${m.hasDotCarousel}   sticky bar: ${m.hasStickyBar}`);
    console.log(`  title      : ${m.titleWidth}px wide, ${m.titleHeight}px tall  "${m.titleText}"`);
    console.log(`  main image : ${m.imgWidth}px wide`);
    if (m.escaping.length) console.log(`  DRAWN OUTSIDE THE PANEL: ${m.escaping.join(' | ')}`);
  }
  await b.close();
})();
