/** Does the good modal's container exist on a category page, or only on the homepage? */
const puppeteer = require('puppeteer-core');
const PAGES = [
  ['homepage', 'https://theartframer.us/'],
  ['category', 'https://theartframer.us/product-category/digital-canvas-prints/'],
  ['shop',     'https://theartframer.us/shop/'],
];
(async () => {
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  for (const [label, url] of PAGES) {
    const p = await b.newPage();
    await p.setViewport({ width: 1400, height: 950 });
    await p.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
    for (let i = 0; i < 10; i++) {
      const blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser'));
      if (!blocked) break;
      await new Promise(r => setTimeout(r, 2500));
      try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
    }
    const r = await p.evaluate(() => {
      const m = document.getElementById('quickViewModal');
      const c = document.getElementById('quickViewContent');
      return {
        modalExists: !!m,
        modalHTML: m ? m.outerHTML.replace(/\s+/g,' ').slice(0, 260) : null,
        contentExists: !!c,
        qvBtns: document.querySelectorAll('.quick-view-btn').length,
        woosqBtns: document.querySelectorAll('.woosq-btn').length,
        cards: document.querySelectorAll('li.product, .product-card').length,
        // what a card's quick-view control looks like here
        sample: (() => {
          const el = document.querySelector('.quick-view-btn, .woosq-btn');
          return el ? el.outerHTML.replace(/\s+/g,' ').slice(0, 200) : null;
        })(),
      };
    });
    console.log(`\n=== ${label} ===`);
    console.log(`  #quickViewModal   : ${r.modalExists}${r.modalExists ? '' : '   <-- the good modal has nowhere to render'}`);
    console.log(`  #quickViewContent : ${r.contentExists}`);
    if (r.modalHTML) console.log(`  its markup        : ${r.modalHTML}`);
    console.log(`  .quick-view-btn   : ${r.qvBtns}`);
    console.log(`  .woosq-btn        : ${r.woosqBtns}`);
    console.log(`  product cards     : ${r.cards}`);
    console.log(`  a card's control  : ${r.sample}`);
    await p.close();
  }
  await b.close();
})();
