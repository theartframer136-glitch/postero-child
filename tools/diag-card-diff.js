/**
 * Compare a product card on a category archive (the design the owner wants)
 * with the same card in the wishlist's "You may also like" row (the one that
 * looks wrong). Same markup, so the difference is entirely CSS — report the
 * properties that actually differ rather than guessing which ones.
 */
const puppeteer = require('puppeteer-core');

const PAGES = [
  ['WANTED  (category)', 'https://theartframer.us/product-category/digital-canvas-prints/radha-krishna/', 'li.product'],
  ['ACTUAL  (wishlist)', 'https://theartframer.us/wishlist/', '.af-wl-related li.product'],
];

const PROPS = {
  card: ['background-color','border-top-width','border-top-color','border-top-style','border-radius',
         'box-shadow','padding','margin','overflow'],
  imgBox: ['height','aspect-ratio','overflow','background-color','border-radius'],
  img: ['height','object-fit','object-position'],
  ribbon: ['position','top','left','width','height','transform','font-size','font-weight','padding','background-image','border-radius'],
  title: ['font-size','line-height','font-weight','margin','padding','color','text-transform','-webkit-line-clamp'],
  price: ['font-size','font-weight','color','margin'],
  btn: ['font-size','padding','border-top-width','border-radius','letter-spacing','margin'],
};

const PICK = {
  card:   'SELF',
  imgBox: '.product-transition',
  img:    '.product-transition img',
  ribbon: '.onsale',
  title:  '.woocommerce-loop-product__title, h2, h3',
  price:  '.price',
  btn:    '[class*="brochure"], .af-brochure-btn, a[href*="brochure"]',
};

async function grab(browser, url, sel) {
  const p = await browser.newPage();
  await p.setViewport({ width: 1400, height: 1000 });
  await p.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
  for (let i = 0; i < 10; i++) {
    if (!(await p.evaluate(() => document.body.innerText.includes('Checking your browser')))) break;
    await new Promise(r => setTimeout(r, 2500));
    try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
  }
  await new Promise(r => setTimeout(r, 2500));
  const out = await p.evaluate((sel, PROPS, PICK) => {
    const card = document.querySelector(sel);
    if (!card) return { missing: true };
    const res = { box: (() => { const r = card.getBoundingClientRect(); return Math.round(r.width) + 'x' + Math.round(r.height); })() };
    for (const key of Object.keys(PROPS)) {
      const el = PICK[key] === 'SELF' ? card : card.querySelector(PICK[key]);
      if (!el) { res[key] = null; continue; }
      const cs = getComputedStyle(el);
      const o = {};
      for (const prop of PROPS[key]) o[prop] = cs.getPropertyValue(prop);
      const r = el.getBoundingClientRect();
      o['__box'] = Math.round(r.width) + 'x' + Math.round(r.height);
      res[key] = o;
    }
    return res;
  }, sel, PROPS, PICK);
  await p.close();
  return out;
}

(async () => {
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const [want, have] = await Promise.all([
    grab(b, PAGES[0][1], PAGES[0][2]),
    grab(b, PAGES[1][1], PAGES[1][2]),
  ]);
  if (want.missing) { console.log('category card not found'); await b.close(); return; }
  if (have.missing)  { console.log('wishlist related card not found'); await b.close(); return; }

  console.log(`card box   WANTED ${want.box}    ACTUAL ${have.box}\n`);
  for (const key of Object.keys(PROPS)) {
    const a = want[key], c = have[key];
    if (!a && !c) continue;
    if (!a || !c) { console.log(`--- ${key}: present on ${a ? 'WANTED' : 'ACTUAL'} only\n`); continue; }
    const diffs = Object.keys(a).filter(k => a[k] !== c[k]);
    if (!diffs.length) { console.log(`--- ${key}: identical`); continue; }
    console.log(`--- ${key}  (${diffs.length} differ)`);
    for (const k of diffs) {
      console.log(`      ${k.padEnd(22)} WANT ${String(a[k]).slice(0,44).padEnd(46)} HAVE ${String(c[k]).slice(0,44)}`);
    }
    console.log('');
  }
  await b.close();
})();
