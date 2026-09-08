/**
 * Why is the New Arrivals section empty on the page?
 *
 * The server-side report says the query returns twelve products, and the owner
 * still sees a heading with nothing under it. Those two facts can only both be
 * true somewhere between the query and the paint: the row renders no cards, or
 * renders them into a box with no height, or renders them and something hides
 * them. Each needs a different fix and only a real browser can tell them apart
 * — which is the same lesson the Products In Motion row taught three times.
 *
 * So this finds the heading the way the page's own script does (by its text),
 * walks up to the section that should contain the cards, and reports what is
 * actually there: the card count, the container's computed box, whether an
 * ancestor is hidden or zero-height, and WooCommerce's own "no products" text
 * if it was printed.
 *
 * Read-only. Runs on the deploy runner, which has Chrome and open internet.
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome',
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'na=' + Date.now(),
      { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(2500);

    // The row may be built after first paint, and it may be lazy — scroll it
    // into view before judging it empty, exactly as a visitor would.
    await page.evaluate(() => {
      const hs = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = hs.find((x) => /new\s*arrivals/i.test(x.textContent || ''));
      if (h) h.scrollIntoView({ block: 'center' });
    });
    await sleep(3500);

    const out = await page.evaluate(() => {
      const box = (el) => {
        const r = el.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height) };
      };
      const hs = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = hs.find((x) => /new\s*arrivals/i.test(x.textContent || ''));
      if (!h) return { error: 'no New Arrivals heading on the page' };

      // The section is the nearest ancestor that a products row would live in.
      let sec = h.closest('section, .e-con, .elementor-section, .elementor-widget-wrap') || h.parentElement;
      for (let i = 0; i < 5 && sec && !sec.querySelector('li.product, .product, .woocommerce'); i++) {
        sec = sec.parentElement;
      }
      const r = {
        headingText: (h.textContent || '').trim().slice(0, 60),
        sectionTag: sec ? sec.tagName + '.' + String(sec.className).slice(0, 60) : '(none)',
        sectionBox: sec ? box(sec) : null,
        cards: sec ? sec.querySelectorAll('li.product').length : 0,
        anyProductClass: sec ? sec.querySelectorAll('.product').length : 0,
        ulCount: sec ? sec.querySelectorAll('ul.products').length : 0,
        wooBlocks: sec ? sec.querySelectorAll('.woocommerce').length : 0,
        infoText: '',
        hiddenAncestor: '',
        firstCard: null,
        htmlSample: '',
      };
      if (!sec) return r;

      // WooCommerce prints this when a query returns nothing.
      const info = sec.querySelector('.woocommerce-info, .woocommerce-no-products-found, p.woocommerce-info');
      if (info) r.infoText = (info.textContent || '').trim().slice(0, 120);

      // A row that rendered but cannot be seen: find the ancestor responsible.
      let n = sec;
      while (n && n !== document.body) {
        const cs = getComputedStyle(n);
        const bb = n.getBoundingClientRect();
        if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) === 0 ||
            (bb.height < 4 && n.children.length > 0)) {
          r.hiddenAncestor = n.tagName + '.' + String(n.className).slice(0, 50) +
            ` display=${cs.display} vis=${cs.visibility} opacity=${cs.opacity} h=${Math.round(bb.height)}`;
          break;
        }
        n = n.parentElement;
      }

      const card = sec.querySelector('li.product');
      if (card) {
        const cs = getComputedStyle(card);
        r.firstCard = {
          box: box(card), display: cs.display, visibility: cs.visibility,
          opacity: cs.opacity, height: cs.height,
          title: (card.querySelector('.woocommerce-loop-product__title, h2, h3') || {}).textContent || '',
        };
      } else {
        // No cards at all: show what IS inside, which says whether the widget
        // rendered an empty shell or never ran.
        r.htmlSample = sec.innerHTML.replace(/\s+/g, ' ').slice(0, 700);
      }
      return r;
    });

    console.log('=== HEADLESS NEW ARRIVALS CHECK ===');
    console.log(JSON.stringify(out, null, 2));
    if (out.error) {
      console.log('VERDICT: ' + out.error);
    } else if (out.cards > 0 && out.firstCard && out.firstCard.box.h > 20) {
      console.log(`VERDICT: ${out.cards} card(s) rendered and visible — the row is working`);
    } else if (out.cards > 0) {
      console.log(`VERDICT: ${out.cards} card(s) rendered but NOT visible — ${out.hiddenAncestor || 'zero-height cards'}`);
    } else if (out.infoText) {
      console.log(`VERDICT: the query returned nothing on the page — "${out.infoText}"`);
    } else {
      console.log('VERDICT: no product cards in the section at all — see htmlSample for what rendered');
    }
    console.log('=== DONE ===');
  } catch (e) {
    console.log('=== HEADLESS NEW ARRIVALS CHECK ===');
    console.log('HEADLESS ERROR: ' + e.message);
  } finally {
    await browser.close();
  }
})();
