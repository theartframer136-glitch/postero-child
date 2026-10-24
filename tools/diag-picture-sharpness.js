/**
 * Is every picture on a page sharp enough for the screen, and what did it cost?
 *
 * Speed work that changes which picture file a browser downloads must never
 * leave a picture with fewer pixels than the screen shows (the owner's rule:
 * picture quality must not go down). This measures it rather than arguing it.
 * For every picture drawn on the page it prints the file the browser chose,
 * that file's width, the width it is drawn at times the screen's density, and
 * the ratio of the two: 1.00 or more means every screen pixel has a picture
 * pixel behind it. Then the bytes the page's pictures took. Run before and
 * after a change at the same width and density and compare.
 *
 * It also saves screenshots of the top of the page and the first product rows
 * to $SHOT_DIR (when set), so the pictures can be looked at side by side.
 *
 * Read-only. Usage: node tools/diag-picture-sharpness.js <url> <width> <density> [label]
 */
const fs = require('fs');
const puppeteer = require('puppeteer-core');

const URL = process.argv[2] || 'https://theartframer.us/';
const WIDTH = parseInt(process.argv[3] || '390', 10);
const DPR = parseFloat(process.argv[4] || '2');
const LABEL = (process.argv[5] || 'shot').replace(/[^a-z0-9-]/gi, '');
const SHOT_DIR = process.env.SHOT_DIR || '';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const phone = WIDTH < 768;

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    if (phone) {
      await page.setUserAgent('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
        + '(KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36');
    }
    await page.setViewport({ width: WIDTH, height: 900, isMobile: phone, hasTouch: phone, deviceScaleFactor: DPR });
    const sep = URL.includes('?') ? '&' : '?';
    await page.goto(URL + sep + 'afsharp=' + Date.now(), { waitUntil: 'networkidle2', timeout: 120000 }).catch(() => {});
    // The site reloads itself once on a first visit; wait for that to be over.
    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }).catch(() => {});
    await sleep(4000);

    if (SHOT_DIR) {
      fs.mkdirSync(SHOT_DIR, { recursive: true });
      await page.screenshot({ path: `${SHOT_DIR}/${LABEL}-${WIDTH}px-${DPR}x-top.png` });
    }

    // Walk down the page so lazy pictures load, as a shopper scrolling would.
    const h = await page.evaluate(() => document.body.scrollHeight);
    for (let y = 0; y < Math.min(h, 12000); y += 700) {
      await page.evaluate((yy) => window.scrollTo(0, yy), y);
      await sleep(350);
    }
    await sleep(3000);

    if (SHOT_DIR) {
      // The first row of the home page's product slider, or a listing's first cards.
      const box = await page.evaluate(() => {
        const img = document.querySelector('img.main-img, ul.products li.product img');
        if (!img) return null;
        img.scrollIntoView({ block: 'center' });
        const card = img.closest('.product-card, .product-slide, li.product, .product-content') || img;
        const row = card.parentElement && card.parentElement.parentElement ? card.parentElement.parentElement : card;
        const r = row.getBoundingClientRect();
        return { x: 0, y: Math.max(0, r.top + window.scrollY - 10), width: window.innerWidth, height: Math.min(900, r.height + 20) };
      });
      if (box) {
        await sleep(1500);
        await page.screenshot({ path: `${SHOT_DIR}/${LABEL}-${WIDTH}px-${DPR}x-cards.png`, clip: box, captureBeyondViewport: true });
      }
    }

    const r = await page.evaluate(async (dpr) => {
      // naturalWidth of a srcset picture is divided by the density the browser
      // picked, so the file's real width is read from a plain copy of it.
      const real = {};
      const fileWidth = (src) => real[src] || (real[src] = new Promise((res) => {
        const i = new Image();
        i.onload = () => res([i.naturalWidth, i.naturalHeight]);
        i.onerror = () => res([0, 0]);
        i.src = src;
      }));
      const bytes = {};
      performance.getEntriesByType('resource').forEach((e) => {
        if (e.initiatorType === 'img' || e.initiatorType === 'css' || /\.(jpe?g|png|webp|avif)(\?|$)/i.test(e.name)) {
          bytes[e.name.split('?')[0]] = e.transferSize || e.encodedBodySize || 0;
        }
      });
      const rows = [];
      for (const im of document.querySelectorAll('img')) {
        const b = im.getBoundingClientRect();
        if (b.width < 60 || !im.currentSrc || !im.naturalWidth) continue;
        const st = getComputedStyle(im);
        if (st.visibility === 'hidden' || st.display === 'none') continue;
        const [fw, fh] = await fileWidth(im.currentSrc);
        if (!fw) continue;
        const need = b.width * dpr;
        // A picture drawn "cover" is cropped, so its height can be the side that binds.
        const fit = st.objectFit === 'cover' && b.height
          ? Math.max(fw / need, Math.min(fw / need, fh / (b.height * dpr)))
          : fw / need;
        rows.push({
          file: im.currentSrc.split('/').pop().split('?')[0],
          cls: (im.className || '').toString().split(/\s+/).filter((c) => /main-img|hover|wp-post-image|attachment/.test(c)).slice(0, 1).join('')
            + (im.getAttribute('srcset') ? ' +srcset' : ''),
          nat: fw, drawn: Math.round(b.width), ratio: fit,
          kb: Math.round((bytes[im.currentSrc.split('?')[0]] || 0) / 1024),
        });
      }
      const totalKb = Math.round(Object.values(bytes).reduce((a, b) => a + b, 0) / 1024);
      return { rows, totalKb, files: Object.keys(bytes).length };
    }, DPR);

    console.log(`=== ${URL} at ${WIDTH}px, ${DPR}x density ===`);
    console.log('  ratio = picture pixels per screen pixel (1.00 or more is full sharpness)');
    const seen = new Set();
    let under = 0;
    r.rows.forEach((x) => {
      const key = x.file + x.drawn;
      if (seen.has(key)) return;
      seen.add(key);
      if (x.ratio < 0.995) under++;
      if (seen.size <= 60) {
        console.log(`  ${x.ratio.toFixed(2).padStart(5)}  ${String(x.nat).padStart(5)}px file  drawn ${String(x.drawn).padStart(4)}px  ${String(x.kb).padStart(4)} KB  ${x.cls.padEnd(22)} ${x.file}`);
      }
    });
    console.log(`\n  pictures drawn: ${seen.size}   below full sharpness: ${under}`);
    console.log(`  picture files downloaded: ${r.files}, ${r.totalKb} KB`);
    console.log(`@@SHARP@@${JSON.stringify({ url: URL, width: WIDTH, dpr: DPR, drawn: seen.size, under, kb: r.totalKb, files: r.files })}@@END@@`);
  } finally {
    await browser.close();
  }
})();
