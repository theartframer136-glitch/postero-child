/**
 * Open the live product page in a real headless Chrome, scroll it, and answer
 * the one question the server-side checks cannot: does the gallery actually
 * pin? Runs on the deploy runner (which has Chrome and open internet), not on
 * the shared host.
 *
 * Prints a JSON report: sticky wrapper state, measured positions before and
 * after scrolling, and every gallery ancestor's overflow/transform/filter
 * state — so a failure names its blocker instead of inviting another theory.
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2]
  || 'https://theartframer.us/product/lord-vishnu-golden-halo-canvas-wall-art/';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1366, height: 900 });
    // unique param defeats the page cache so we test THIS deploy's output
    await page.goto(URL + '?afheadless=' + Date.now(), {
      waitUntil: 'networkidle2', timeout: 120000,
    });
    await sleep(3500); // let the footer scripts (wrap + size) finish

    const before = await page.evaluate(() => {
      const g = document.querySelector('div.product .woocommerce-product-gallery');
      const inner = g && g.querySelector('.af-sg-inner');
      const s = document.querySelector('div.product .summary');
      const anc = [];
      if (g) {
        let n = 0;
        for (let a = g.parentElement; a && a !== document.body && n < 12; a = a.parentElement, n++) {
          const cs = getComputedStyle(a);
          anc.push({
            el: (a.className || a.tagName).toString().slice(0, 64),
            overflow: cs.overflow,
            transform: cs.transform === 'none' ? '-' : cs.transform.slice(0, 40),
            filter: (!cs.filter || cs.filter === 'none') ? '-' : cs.filter.slice(0, 30),
            contain: cs.contain && cs.contain !== 'none' ? cs.contain : '-',
            willChange: cs.willChange && cs.willChange !== 'auto' ? cs.willChange : '-',
            unclipped: a.dataset.afSgUnclipped || '',
            unblocked: a.dataset.afSgUnblocked || '',
          });
        }
      }
      return {
        galleryFound: !!g,
        innerFound: !!inner,
        innerPosition: inner ? getComputedStyle(inner).position : null,
        innerTopCss: inner ? getComputedStyle(inner).top : null,
        galleryHeightStyle: g ? g.style.height : null,
        galleryNaturalTop: g ? Math.round(g.getBoundingClientRect().top + window.pageYOffset) : null,
        summaryHeight: s ? s.offsetHeight : null,
        ancestors: anc,
      };
    });

    // scroll well past the gallery top and measure where the images sit now
    await page.evaluate(() => window.scrollTo(0, 1200));
    await sleep(900);
    const after = await page.evaluate(() => {
      const inner = document.querySelector('.af-sg-inner');
      const g = document.querySelector('div.product .woocommerce-product-gallery');
      const el = inner || g;
      return {
        scrollY: window.pageYOffset,
        innerViewportTop: el ? Math.round(el.getBoundingClientRect().top) : null,
      };
    });

    // pinned = the wrapper is holding near its sticky offset (96px) instead of
    // having scrolled away (which would put its top far negative)
    const pinned = after.innerViewportTop !== null
      && after.innerViewportTop > 40 && after.innerViewportTop < 200;

    console.log('=== HEADLESS STICKY CHECK ===');
    console.log(JSON.stringify({ url: URL, before, after, PINNED: pinned }, null, 2));
    console.log(pinned
      ? 'VERDICT: PINNED — the gallery holds while the page scrolls'
      : 'VERDICT: NOT PINNED — see ancestors above for the blocker');
    console.log('=== DONE ===');
  } catch (e) {
    console.log('=== HEADLESS STICKY CHECK ===');
    console.log('HEADLESS ERROR: ' + e.message);
    console.log('=== DONE ===');
  } finally {
    await browser.close();
  }
})();
