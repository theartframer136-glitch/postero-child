/**
 * Why is the New Arrivals row empty on the page?
 *
 * Deploy 960 answered what the row IS: the theme renders it as a Swiper
 * carousel — <div class="products swiper-wrapper" data-items="3"
 * data-autoplay="2000" data-loop="1"> with a <div class="product swiper-slide">
 * per card. Thirty-six of those slides are in the DOM while nothing is visible.
 * Every earlier check counted ul.products / li.product, which this theme does
 * not emit, and so reported "no cards" about a row that was full.
 *
 * A Swiper that is never initialised is invisible in most themes: the slides
 * carry no width, the container is hidden until the library marks it
 * swiper-initialized, or both. So this measures the carousel itself — its box,
 * its container's box and computed style, whether it was initialised, whether
 * the page's own self-heal fired — and records every uncaught script error,
 * because a single exception in an earlier ready handler is enough to stop the
 * theme's carousel setup from ever running.
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
  const errors = [];
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    page.on('pageerror', (e) => errors.push('pageerror: ' + String(e.message || e).slice(0, 220)));
    page.on('console', (m) => {
      if (m.type() === 'error') errors.push('console.error: ' + m.text().slice(0, 220));
    });
    await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'na=' + Date.now(),
      { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(2500);

    // The site navigates to itself once after load (a currency cookie reload),
    // which destroys the evaluation context mid-measure. Ride it out.
    const evalRetry = async (fn) => {
      for (let i = 0; i < 4; i++) {
        try { return await page.evaluate(fn); }
        catch (e) {
          if (!/context was destroyed|navigation|detached/i.test(e.message)) throw e;
          await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }).catch(() => {});
          await sleep(2500);
        }
      }
      throw new Error('the page kept navigating during measurement');
    };

    // The measurement, run twice: once early (before any self-heal could
    // fire) and once after scrolling the row into view and waiting, so the
    // log shows both the broken state and whether anything repaired it.
    const measure = () => {
      const box = (el) => {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top) };
      };
      const style = (el) => {
        if (!el) return null;
        const cs = getComputedStyle(el);
        return { display: cs.display, opacity: cs.opacity, visibility: cs.visibility,
                 height: cs.height, overflow: cs.overflow, position: cs.position };
      };
      const hs = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = hs.find((x) => /new\s*arrivals/i.test(x.textContent || ''));
      const out = { headingFound: !!h, headingTop: h ? Math.round(h.getBoundingClientRect().top) : null };

      // The row: the products wrapper nearest AFTER the heading in document
      // order — there is one on this page, but do not assume that forever.
      const wraps = Array.from(document.querySelectorAll('.products'));
      let wrap = null;
      if (h) {
        wrap = wraps.find((w) => h.compareDocumentPosition(w) & Node.DOCUMENT_POSITION_FOLLOWING) || wraps[0] || null;
      } else {
        wrap = wraps[0] || null;
      }
      out.productsWrappers = wraps.length;
      if (!wrap) return out;

      const container = wrap.parentElement;
      const outer = container ? container.parentElement : null;
      const slides = Array.from(wrap.children);
      out.wrap = { tag: wrap.tagName, cls: String(wrap.className).slice(0, 80), box: box(wrap), style: style(wrap),
                   slides: slides.length, healed: wrap.getAttribute('data-af-healed') || '' };
      out.container = container ? {
        tag: container.tagName, cls: String(container.className).slice(0, 120), box: box(container), style: style(container),
        initialised: container.classList.contains('swiper-initialized') || container.classList.contains('swiper-container-initialized'),
        data: Array.from(container.attributes).filter((a) => a.name.startsWith('data-')).map((a) => a.name + '=' + a.value).slice(0, 12).join(' '),
      } : null;
      out.outer = outer ? { tag: outer.tagName, cls: String(outer.className).slice(0, 120), box: box(outer), style: style(outer) } : null;
      out.firstSlide = slides[0] ? { cls: String(slides[0].className).slice(0, 60), box: box(slides[0]), style: style(slides[0]),
                                     img: !!slides[0].querySelector('img'), imgBox: box(slides[0].querySelector('img')) } : null;

      // Which ancestor, if any, collapses or hides the row.
      let n = wrap, chain = [];
      for (let i = 0; i < 12 && n && n !== document.body; i++, n = n.parentElement) {
        const cs = getComputedStyle(n), b = n.getBoundingClientRect();
        chain.push(`${n.tagName}.${String(n.className).slice(0, 34)} h=${Math.round(b.height)} d=${cs.display} o=${cs.opacity} v=${cs.visibility} ov=${cs.overflow}`);
      }
      out.ancestors = chain;
      out.swiperLib = typeof window.Swiper;
      return out;
    };

    const early = await evalRetry(measure);
    await evalRetry(() => {
      const hs = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = hs.find((x) => /new\s*arrivals/i.test(x.textContent || ''));
      if (h) h.scrollIntoView({ block: 'center' });
    });
    await sleep(6000);
    const late = await evalRetry(measure);

    console.log('=== HEADLESS NEW ARRIVALS CHECK ===');
    console.log('--- early (2.5s after load) ---');
    console.log(JSON.stringify(early, null, 2));
    console.log('--- late (after scroll + 6s) ---');
    console.log(JSON.stringify(late, null, 2));
    console.log('--- uncaught script errors on the page ---');
    if (errors.length) errors.slice(0, 20).forEach((e) => console.log('  ' + e));
    else console.log('  (none)');

    const w = late.wrap, c = late.container;
    if (!late.headingFound) console.log('VERDICT: no New Arrivals heading on the page');
    else if (!w) console.log('VERDICT: no products wrapper on the page at all');
    else if (w.slides > 0 && w.box.h > 40 && c && c.box.h > 40) console.log(`VERDICT: ${w.slides} slides rendered and visible (${w.box.w}x${w.box.h}) — the row is working${w.healed ? ' (after the self-heal)' : ''}`);
    else if (w.slides > 0 && c && !c.initialised) console.log(`VERDICT: ${w.slides} slides in the DOM but the carousel was NEVER INITIALISED — container ${c.box.w}x${c.box.h}, Swiper lib: ${late.swiperLib}`);
    else if (w.slides > 0) console.log(`VERDICT: ${w.slides} slides in the DOM, carousel initialised, but collapsed — see ancestors`);
    else console.log('VERDICT: the products wrapper is empty — the query returned nothing on this request');
    console.log('=== DONE ===');
  } catch (e) {
    console.log('=== HEADLESS NEW ARRIVALS CHECK ===');
    console.log('HEADLESS ERROR: ' + e.message);
    if (errors.length) errors.slice(0, 10).forEach((x) => console.log('  ' + x));
  } finally {
    await browser.close();
  }
})();
