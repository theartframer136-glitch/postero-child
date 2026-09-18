/**
 * Where does the empty band above every product image come from?
 *
 * Candidates that all look identical on screen: padding on the image box, a
 * margin on the img, an element sitting in normal flow before the img (the
 * discount ribbon is the obvious suspect), or an inline img whose line box
 * adds leading. Each needs a different fix, so measure rather than guess.
 */
const puppeteer = require('puppeteer-core');
const URL = process.argv[2] || 'https://theartframer.us/product-category/digital-canvas-prints/radha-krishna/';

(async () => {
  const b = await puppeteer.launch({ channel:'chrome', headless:'new', args:['--no-sandbox','--disable-dev-shm-usage'] });
  const p = await b.newPage();
  await p.setViewport({ width:1400, height:1000 });
  await p.goto(URL, { waitUntil:'networkidle2', timeout:60000 });
  for (let i=0;i<10;i++){ if(!(await p.evaluate(()=>document.body.innerText.includes('Checking your browser')))) break;
    await new Promise(r=>setTimeout(r,2500)); try{await p.reload({waitUntil:'networkidle2',timeout:45000});}catch{} }
  await new Promise(r=>setTimeout(r,2500));

  const out = await p.evaluate(() => {
    const cards = [...document.querySelectorAll('li.product')].slice(0, 8);
    return cards.map(card => {
      // the box that holds the picture
      const box = card.querySelector('.product-image, .product-transition, .woocommerce-loop-product__link, a');
      const img = card.querySelector('img');
      if (!box || !img) return null;
      const br = box.getBoundingClientRect(), ir = img.getBoundingClientRect();
      const bs = getComputedStyle(box), is = getComputedStyle(img);

      // everything inside the box that renders ABOVE the image
      const above = [...box.querySelectorAll('*')].filter(el => {
        if (el === img || el.contains(img)) return false;
        const r = el.getBoundingClientRect();
        return r.height > 0 && r.top < ir.top + 1 && r.bottom > br.top - 1;
      }).slice(0, 5).map(el => {
        const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
        return `${el.tagName.toLowerCase()}.${(el.className||'').toString().split(/\s+/).slice(0,2).join('.')}`
             + ` ${Math.round(r.width)}x${Math.round(r.height)} pos:${cs.position}`;
      });

      return {
        title: (card.querySelector('.woocommerce-loop-product__title, h2, h3')||{textContent:''}).textContent.trim().slice(0,28),
        boxTag: box.tagName.toLowerCase() + '.' + (box.className||'').toString().split(/\s+/).slice(0,2).join('.'),
        gapTop: +(ir.top - br.top).toFixed(1),
        gapBottom: +(br.bottom - ir.bottom).toFixed(1),
        boxH: Math.round(br.height), imgH: Math.round(ir.height),
        boxPad: bs.padding, boxDisplay: bs.display, boxAR: bs.aspectRatio, boxOverflow: bs.overflow,
        imgDisplay: is.display, imgMargin: is.margin, imgVAlign: is.verticalAlign,
        imgObjectFit: is.objectFit, imgH_css: is.height, imgAR: is.aspectRatio,
        above,
      };
    }).filter(Boolean);
  });

  console.log(`=== ${URL}\n`);
  console.log('TITLE                        BOX                       GAP-TOP  GAP-BOT   BOX-H  IMG-H');
  console.log('-'.repeat(96));
  for (const r of out) {
    console.log(r.title.padEnd(28) + ' ' + r.boxTag.padEnd(25) + ' ' +
      String(r.gapTop).padStart(7) + ' ' + String(r.gapBottom).padStart(8) + ' ' +
      String(r.boxH).padStart(7) + ' ' + String(r.imgH).padStart(6));
  }
  const f = out[0];
  if (f) {
    console.log('\n--- the first card in detail ---');
    console.log('  box     : display=' + f.boxDisplay + '  padding=' + f.boxPad +
                '  aspect-ratio=' + f.boxAR + '  overflow=' + f.boxOverflow);
    console.log('  img     : display=' + f.imgDisplay + '  margin=' + f.imgMargin +
                '  vertical-align=' + f.imgVAlign);
    console.log('            object-fit=' + f.imgObjectFit + '  height=' + f.imgH_css + '  aspect-ratio=' + f.imgAR);
    console.log('  drawn ABOVE the image inside the box:');
    if (!f.above.length) console.log('      nothing — so the gap is padding, margin or leading, not an element');
    for (const a of f.above) console.log('      ' + a);
  }
  await b.close();
})();
