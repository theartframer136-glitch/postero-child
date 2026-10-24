/**
 * Which Elementor widgets on a page are never visible at any screen width?
 *
 * Loads the page at one width inside each of Elementor's device ranges on this
 * site (mobile ≤767, mobile_extra ≤880, tablet ≤1024, tablet_extra ≤1200,
 * laptop ≤1366, desktop above) and records, for every widget under the root
 * selector, whether it takes up any space. A widget that is drawn at none of
 * them is sent to every visitor and seen by nobody. Used to check, in a real
 * browser, what the server will leave out before it does.
 *
 * Read-only. Usage: node tools/diag-never-visible.js <url> [root selector]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/cart/';
const ROOT = process.argv[3] || '#masthead, #colophon';
const WIDTHS = [390, 820, 1000, 1100, 1300, 1600];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const seen = {};
  try {
    for (const w of WIDTHS) {
      const page = await browser.newPage();
      await page.setViewport({ width: w, height: 900, isMobile: w < 768, hasTouch: w < 768, deviceScaleFactor: 1 });
      await page.goto(URL + (URL.includes('?') ? '&' : '?') + 'afvis=' + Date.now(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {});
      await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 12000 }).catch(() => {});
      await sleep(2500);
      const rows = await page.evaluate((root) => {
        const out = [];
        document.querySelectorAll(root).forEach((r) => {
          r.querySelectorAll('[data-widget_type]').forEach((el) => {
            const b = el.getBoundingClientRect();
            out.push({ id: el.getAttribute('data-id'), type: el.getAttribute('data-widget_type'), kb: Math.round(el.outerHTML.length / 102.4) / 10, vis: b.width > 0 && b.height > 0 && getComputedStyle(el).visibility !== 'hidden' });
          });
        });
        return out;
      }, ROOT);
      rows.forEach((r) => {
        const k = r.id + ' ' + r.type;
        seen[k] = seen[k] || { kb: r.kb, at: [] };
        if (r.vis) seen[k].at.push(w);
      });
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log(`=== ${URL}  (${ROOT})  widths ${WIDTHS.join(', ')}`);
  const never = [];
  Object.keys(seen).sort().forEach((k) => {
    const s = seen[k];
    console.log(`  ${s.at.length ? 'shown at ' + s.at.join(',') : 'NEVER SHOWN'}`.padEnd(40) + `${String(s.kb).padStart(6)} KB  ${k}`);
    if (!s.at.length) never.push(k);
  });
  console.log(`\n  never shown: ${never.length} widgets, ${Math.round(never.reduce((a, k) => a + seen[k].kb, 0))} KB`);
  console.log('@@NEVER@@' + JSON.stringify(never.map((k) => k.split(' ')[0])) + '@@END@@');
})();
