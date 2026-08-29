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

    // A blank document reads exactly like a broken section — deploy 895
    // reported "the section did not render" with ZERO iframes anywhere on a
    // page the server was serving at 928KB, which is impossible for a page
    // that loaded. So: if nothing at all is there, load once more before
    // believing it.
    const bodyLen = async () => evalRetry(() => document.body ? document.body.innerHTML.length : 0);
    if ((await bodyLen()) < 5000) {
      console.log('(page came back essentially empty — reloading once)');
      await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'afpim=' + Date.now(),
        { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => {});
      await sleep(4000);
    }

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

      // Cards that are actually ON SCREEN. The row is a marquee 32 cards long,
      // so the first four in DOM order sit thousands of pixels off to the left
      // — and the player is only created for a card the IntersectionObserver
      // sees, so sampling those reports "no iframe" for a section whose visible
      // cards are playing perfectly well. Sort by how central each card is.
      let cards = Array.from(document.querySelectorAll('.af-pim-card'))
        .filter((c) => {
          const r = c.getBoundingClientRect();
          return r.right > 0 && r.left < innerWidth && r.width > 0;
        })
        .sort((a, b) => {
          const ca = Math.abs(a.getBoundingClientRect().left + a.getBoundingClientRect().width / 2 - innerWidth / 2);
          const cb = Math.abs(b.getBoundingClientRect().left + b.getBoundingClientRect().width / 2 - innerWidth / 2);
          return ca - cb;
        });
      out.onScreenCards = cards.length;
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
            maxWidth: ms.maxWidth,
            maxHeight: ms.maxHeight,
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

    // Every media element on every sampled card has to cover its card. Report
    // each one, so a pass is a list of measurements rather than one word.
    let bad = 0, checked = 0;
    report.cards.forEach((c) => {
      c.media.forEach((m) => {
        if (m.display === 'none' || m.box.w === 0 && m.box.h === 0 && m.tag === 'IMG') return;
        checked++;

        // For an IFRAME the element's own box is the wrong thing to judge.
        // YouTube fits a 16:9 video inside it, so a 290x526 frame — which
        // "covers" a 290x516 card by any box test — still paints its video
        // only 163px tall, and that is the band the owner sees. Measure the
        // fitted video instead. (Deploy 890 passed the box test and was still
        // visibly wrong; this is that lesson.)
        let vw = m.box.w, vh = m.box.h, note = '';
        if (m.tag === 'IFRAME') {
          vh = Math.min(m.box.h, Math.round(m.box.w * 9 / 16));
          vw = Math.min(m.box.w, Math.round(m.box.h * 16 / 9));
          note = `  [frame ${m.box.w}x${m.box.h}, max-width ${m.maxWidth}]`;
        }
        const coversW = vw >= c.cardBox.w - 1;
        const coversH = vh >= c.cardBox.h - 1;
        if (!coversW || !coversH) bad++;
        console.log(`  card ${c.i} ${m.tag}.${m.cls || '-'}  ${vw}x${vh}`
          + ` in card ${c.cardBox.w}x${c.cardBox.h}`
          + `  -> ${coversW && coversH ? 'COVERS' : 'LETTERBOXED'}`
          + (coversH ? '' : ` (short by ${c.cardBox.h - vh}px)`) + note);
      });
    });
    if (!report.cards.length) {
      console.log('VERDICT: no cards on screen — the section did not render');
    } else if (!checked) {
      console.log('VERDICT: cards found but no media in them yet');
    } else {
      console.log(bad
        ? `VERDICT: ${bad} of ${checked} media element(s) LETTERBOXED — see the short-by figures`
        : `VERDICT: all ${checked} media element(s) COVER their card`);
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
