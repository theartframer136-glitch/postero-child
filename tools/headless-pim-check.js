/**
 * Why do the "Products In Motion" cards show black above and below the video?
 *
 * The child theme's CSS already scales the player to cover the 9:16 card
 * (width:177.78%, height:100%, scale(1.8)), so if bands are visible then either
 * that CSS is not reaching the element, or the element on the page is not the
 * one the CSS names, or the thing being letterboxed is INSIDE the player where
 * page CSS cannot reach. Those need different fixes, and only a real browser
 * can tell them apart.
 *
 * So this reports, for each card: what the media element actually is, its
 * computed box against the card's box, and every property that decides whether
 * it covers. A card whose media rect is shorter than the card rect is being
 * letterboxed by the page; one whose media rect matches but still shows bands
 * is being letterboxed by YouTube inside the frame.
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
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--autoplay-policy=no-user-gesture-required'],
  });
  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'afpim=' + Date.now(),
      { waitUntil: 'networkidle2', timeout: 120000 });
    await sleep(3000);

    // The site performs one self-navigation after load (a currency cookie
    // reload) which destroys the evaluation context mid-measure; retry past it.
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

    // scroll the section into view so lazy players actually start
    await evalRetry(() => {
      const heads = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = heads.find((e) => /products\s*in\s*motion/i.test(e.textContent || ''));
      if (h) h.scrollIntoView({ block: 'center' });
      return !!h;
    });
    await sleep(6000);

    const report = await evalRetry(() => {
      const out = { headingFound: false, containers: [], cards: [] };

      const heads = Array.from(document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title'));
      const h = heads.find((e) => /products\s*in\s*motion/i.test(e.textContent || ''));
      out.headingFound = !!h;
      if (h) out.headingClass = (h.className || '').toString().slice(0, 80);

      // Which implementations are on the page at all
      ['.af-pim-wrap', '.af-pim-track', '.af-pim-card', '.af-reel-stage',
       '.elementor-widget-video', 'video', 'iframe'].forEach((sel) => {
        out.containers.push({ sel, count: document.querySelectorAll(sel).length });
      });

      const box = (el) => {
        const r = el.getBoundingClientRect();
        return { w: Math.round(r.width), h: Math.round(r.height),
                 t: Math.round(r.top), l: Math.round(r.left) };
      };

      // Prefer the theme's own cards; fall back to whatever sits under the heading
      let cards = Array.from(document.querySelectorAll('.af-pim-card'));
      if (!cards.length && h) {
        let sec = h.closest('section, .e-con, .elementor-section') || h.parentElement;
        for (let hop = 0; sec && hop < 4 && !cards.length; hop++) {
          cards = Array.from(sec.querySelectorAll('video, iframe')).map((m) => m.parentElement);
          sec = sec.nextElementSibling;
        }
      }

      cards.slice(0, 4).forEach((card, i) => {
        const cs = getComputedStyle(card);
        const entry = {
          i,
          cardClass: (card.className || '').toString().slice(0, 70),
          cardBox: box(card),
          cardOverflow: cs.overflow,
          cardAspect: cs.aspectRatio,
          cardBg: cs.backgroundColor,
          media: [],
        };
        card.querySelectorAll('iframe, video, img').forEach((m) => {
          const ms = getComputedStyle(m);
          entry.media.push({
            tag: m.tagName,
            cls: (m.className || '').toString().slice(0, 50),
            src: (m.getAttribute('src') || '').slice(0, 90),
            box: box(m),
            width: ms.width, height: ms.height,
            objectFit: ms.objectFit,
            position: ms.position,
            transform: ms.transform === 'none' ? '-' : ms.transform.slice(0, 46),
            opacity: ms.opacity,
            zIndex: ms.zIndex,
            display: ms.display,
            // for <video>, the file's own shape — a 16:9 file in a 9:16 box is
            // the whole question
            natural: m.tagName === 'VIDEO'
              ? (m.videoWidth + 'x' + m.videoHeight)
              : (m.tagName === 'IMG' ? (m.naturalWidth + 'x' + m.naturalHeight) : '-'),
          });
        });
        out.cards.push(entry);
      });
      return out;
    });

    console.log('=== HEADLESS PRODUCTS-IN-MOTION CHECK ===');
    console.log(JSON.stringify(report, null, 2));

    // The verdict names WHICH kind of letterboxing, because the fixes differ
    const c = report.cards[0];
    if (!c) {
      console.log('VERDICT: no cards found — the section did not render');
    } else {
      const m = c.media.find((x) => x.tag === 'VIDEO' || x.tag === 'IFRAME') || c.media[0];
      if (!m) {
        console.log('VERDICT: card has no media element');
      } else if (m.box.h < c.cardBox.h - 4 || m.box.w < c.cardBox.w - 4) {
        console.log('VERDICT: PAGE letterboxing — the media element is smaller than its card '
          + `(${m.box.w}x${m.box.h} inside ${c.cardBox.w}x${c.cardBox.h}); CSS on the page fixes it`);
      } else {
        console.log('VERDICT: media FILLS the card — any bands are inside the player itself '
          + '(a 16:9 source in a 9:16 frame), so the fix is scale/crop, not sizing');
      }
    }
    console.log('=== DONE ===');
  } catch (e) {
    console.log('=== HEADLESS PRODUCTS-IN-MOTION CHECK ===');
    console.log('HEADLESS ERROR: ' + e.message);
    console.log('=== DONE ===');
  } finally {
    await browser.close();
  }
})();
