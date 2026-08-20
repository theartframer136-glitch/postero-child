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
    // The host returns 508 while a deploy is still busy, and a 508 page has no
    // product markup on it at all. Loading it once and reporting "not pinned"
    // is a FALSE VERDICT about the gallery -- run 32343465049 did exactly that:
    // galleryFound false, scrollY 0, and a confident NOT PINNED underneath.
    // So: load until the product markup is actually there, and if it never is,
    // say INCONCLUSIVE rather than passing judgement on a page we never saw.
    let httpStatus = null;
    let loaded = false;
    for (let attempt = 1; attempt <= 3 && !loaded; attempt++) {
      // unique param defeats the page cache so we test THIS deploy's output
      const resp = await page.goto(URL + '?afheadless=' + Date.now(), {
        waitUntil: 'networkidle2', timeout: 120000,
      }).catch((e) => { console.log('load attempt ' + attempt + ' failed: ' + e.message); return null; });
      httpStatus = resp ? resp.status() : null;
      await sleep(3500); // let the footer scripts (wrap + size) finish
      loaded = await page.evaluate(
        () => !!document.querySelector('div.product .woocommerce-product-gallery')
      ).catch(() => false);
      if (!loaded) {
        console.log('load attempt ' + attempt + ': HTTP ' + httpStatus + ', no product gallery in the page');
        if (attempt < 3) await sleep(15000);
      }
    }

    // The site performs one self-navigation shortly after load (a currency
    // cookie reload), which destroys the evaluation context mid-measure. So:
    // every evaluate retries after the navigation settles.
    const evalRetry = async (fn, arg) => {
      for (let i = 0; i < 4; i++) {
        try { return await page.evaluate(fn, arg); }
        catch (e) {
          if (!/context was destroyed|navigation/i.test(e.message)) throw e;
          await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => {});
          await sleep(2500);
        }
      }
      throw new Error('page kept navigating during measurement');
    };

    const before = await evalRetry(() => {
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
    await evalRetry(() => window.scrollTo(0, 1200));
    await sleep(900);
    const after = await evalRetry(() => {
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
    // Nothing measured means nothing proved, in either direction.
    const measured = before.galleryFound && before.innerFound && after.scrollY > 0;

    console.log('=== HEADLESS STICKY CHECK ===');
    console.log(JSON.stringify({
      url: URL, httpStatus, before, after,
      PINNED: measured ? pinned : null,
    }, null, 2));
    console.log(!measured
      ? 'VERDICT: INCONCLUSIVE — the product page never rendered here (HTTP '
        + httpStatus + (before.galleryFound ? ', gallery found but not measured' : ', no gallery in the page')
        + '). This says nothing about the gallery; re-run when the host is idle.'
      : pinned
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
