/**
 * Click a Products In Motion tile and measure the popup that opens.
 *
 * The owner's 14:20 recording shows the popup opening — dark backdrop, close
 * button, page dimmed — with nothing inside it. The ids are not the problem:
 * every data-yt on the page is a well-formed YouTube id (measured 14:24). So
 * the fault is between "iframe created with a good URL" and "video visible",
 * and that gap has only a few possible shapes:
 *
 *   the box has no size            aspect-ratio not resolving in that flex
 *                                  context, so a 0px-tall iframe
 *   the iframe never loads         blocked, rewritten by a cache plugin's
 *                                  lazy-loader, or the src never got set
 *   something else is on top       another overlay covering the player
 *
 * Guessing between them costs a deploy each. This clicks the tile in a real
 * browser and prints the answer.
 *
 * Read-only. Runs on the deploy runner.
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
  const errors = [];
  const requests = [];
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' +
      'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');
    await page.setViewport({ width: 1440, height: 900 });
    page.on('pageerror', (e) => errors.push('pageerror: ' + String(e.message || e).slice(0, 200)));
    page.on('console', (m) => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 200)); });
    // Every request to YouTube, so "did the embed even get asked for" is a
    // fact rather than an inference.
    page.on('requestfinished', (r) => {
      const u = r.url();
      if (/youtube|ytimg/.test(u)) requests.push(`${r.response() ? r.response().status() : '?'} ${u.slice(0, 110)}`);
    });
    page.on('requestfailed', (r) => {
      const u = r.url();
      if (/youtube|ytimg/.test(u)) requests.push(`FAILED ${r.failure() ? r.failure().errorText : ''} ${u.slice(0, 90)}`);
    });

    let loaded = false;
    for (let attempt = 1; attempt <= 3 && !loaded; attempt++) {
      try {
        await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'mp=' + Date.now() + attempt,
          { waitUntil: 'domcontentloaded', timeout: 90000 });
        loaded = true;
      } catch (e) { await sleep(6000); }
    }
    console.log('=== HEADLESS MOTION POPUP CHECK ===');
    if (!loaded) { console.log('the host did not answer three times — nothing measured'); await browser.close(); return; }
    await sleep(6000);

    const before = await page.evaluate(() => ({
      tiles: document.querySelectorAll('.af-motion-item').length,
      withId: document.querySelectorAll('.af-motion-item[data-yt]').length,
      lbExists: !!document.querySelector('.af-motion-lb'),
      handlerFile: !!document.querySelector('script') && document.documentElement.innerHTML.includes('afMotionOpenLb'),
      // Which version of the popup code this page is actually running. The
      // owner saw an empty popup eight minutes AFTER the pixel-sizing fix
      // deployed, so "is the fix even on the page the browser got" has to be
      // a measurement too — a page cache can serve yesterday's HTML long
      // after the file on disk has changed.
      pixelSizingLive: document.documentElement.innerHTML.includes('afMotionSizeLb'),
    }));
    console.log('  before the click: ' + JSON.stringify(before));
    if (!before.withId) {
      console.log('VERDICT: no tile carries a video id in the live DOM — the popup cannot open a video');
      await browser.close();
      return;
    }

    await page.evaluate(() => {
      const t = document.querySelector('.af-motion-item[data-yt]');
      if (t) { t.scrollIntoView({ block: 'center' }); t.click(); }
    });
    await sleep(6000);

    const after = await page.evaluate(() => {
      const lb = document.querySelector('.af-motion-lb');
      const fr = lb ? lb.querySelector('iframe') : null;
      const box = lb ? lb.querySelector('.af-motion-lb-box') : null;
      const rect = (el) => {
        if (!el) return null;
        const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
        return { w: Math.round(r.width), h: Math.round(r.height), display: cs.display,
                 opacity: cs.opacity, visibility: cs.visibility, z: cs.zIndex };
      };
      // What is painted at the centre of the popup — if it is not the iframe,
      // something is covering the player.
      let onTop = '';
      if (fr) {
        const r = fr.getBoundingClientRect();
        const el = document.elementFromPoint(Math.round(r.left + r.width / 2), Math.round(r.top + r.height / 2));
        if (el) onTop = el.tagName + '.' + String(el.className).slice(0, 60);
      }
      return {
        popupOpen: !!(lb && lb.classList.contains('open')),
        lb: rect(lb),
        box: rect(box),
        iframe: rect(fr),
        iframeSrc: fr ? (fr.getAttribute('src') || '(no src attribute)') : '(no iframe)',
        iframeSrcProp: fr ? (fr.src || '') : '',
        lazyAttrs: fr ? Array.from(fr.attributes).map((a) => a.name).join(',') : '',
        elementAtCentre: onTop,
      };
    });
    console.log('  after the click:');
    console.log(JSON.stringify(after, null, 2));

    console.log('  --- requests to YouTube ---');
    if (requests.length) requests.slice(0, 12).forEach((r) => console.log('    ' + r));
    else console.log('    (none — the embed was never fetched)');

    console.log('  --- script errors ---');
    if (errors.length) errors.slice(0, 10).forEach((e) => console.log('    ' + e));
    else console.log('    (none)');

    if (!before.pixelSizingLive) console.log('NOTE: this page is running the OLD popup code — the pixel-sizing fix is not in the HTML the browser received (page cache).');
    if (!after.popupOpen) console.log('VERDICT: the click did not open the popup');
    else if (!after.iframe) console.log('VERDICT: the popup opened with no iframe in it');
    else if (after.iframe.h < 40 || after.iframe.w < 40) console.log(`VERDICT: the iframe has no size (${after.iframe.w}x${after.iframe.h}) — a layout fault, not a video fault`);
    else if (!requests.length) console.log('VERDICT: the iframe is sized but nothing was ever fetched from YouTube — the src is not reaching the browser');
    else if (after.elementAtCentre && !/IFRAME/.test(after.elementAtCentre)) console.log(`VERDICT: something covers the player: ${after.elementAtCentre}`);
    else console.log(`VERDICT: iframe ${after.iframe.w}x${after.iframe.h}, YouTube answered — the popup is working`);
    console.log('=== DONE ===');
  } catch (e) {
    console.log('HEADLESS ERROR: ' + e.message);
  } finally {
    await browser.close();
  }
})();
