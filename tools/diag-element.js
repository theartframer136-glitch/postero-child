/**
 * Look inside one element on a real phone-sized page.
 *
 * The mobile audit tells you a page is wrong. It does not tell you WHY a
 * particular widget is wrong, and guessing at a plugin's markup from a
 * screenshot is how a stylesheet ends up written against selectors that do
 * not exist. This prints the subtree under a selector — every node's box, the
 * background image and how it is sized, and the images inside it — so a fix
 * can be written against what is actually there.
 *
 * Read-only. Usage: node tools/diag-element.js <url> <selector> [width]
 */
const puppeteer = require('puppeteer-core');

const URL = process.argv[2];
const SEL = process.argv[3];
const WIDTH = parseInt(process.argv[4] || '420', 10);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function dump(sel, vw) {
  const root = document.querySelector(sel);
  if (!root) return { error: 'no element matches ' + sel };
  const name = (el) => {
    const id = el.id ? '#' + el.id : '';
    const cls = (typeof el.className === 'string' && el.className)
      ? '.' + el.className.trim().split(/\s+/).slice(0, 4).join('.') : '';
    return el.tagName.toLowerCase() + id + cls;
  };
  const out = { root: name(root), vw, nodes: [] };
  const bgUrls = [];
  const walk = (el, depth) => {
    if (depth > 6) return;
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    const bg = cs.backgroundImage && cs.backgroundImage !== 'none' ? cs.backgroundImage : '';
    // A background image's own proportions decide whether "cover" crops it to
    // nothing. Without them you cannot choose a height that shows the picture
    // instead of a fragment of it.
    const m = bg && bg.match(/url\("?([^")]+)"?\)/);
    if (m && bgUrls.indexOf(m[1]) === -1) bgUrls.push(m[1]);
    out.nodes.push({
      d: depth, el: name(el),
      box: [Math.round(r.width), Math.round(r.height)],
      minH: cs.minHeight, h: cs.height,
      bg: bg ? bg.slice(0, 90) : '',
      bgSize: bg ? cs.backgroundSize : '',
      tag: el.tagName.toLowerCase(),
      src: el.tagName === 'IMG' ? (el.getAttribute('src') || '').slice(-60) : '',
      natural: el.tagName === 'IMG' ? el.naturalWidth + 'x' + el.naturalHeight : '',
      fs: cs.fontSize,
      txt: (el.children.length === 0 ? (el.textContent || '').trim().slice(0, 40) : ''),
    });
    for (const c of el.children) walk(c, depth + 1);
  };
  walk(root, 0);
  out.nodes = out.nodes.slice(0, 120);
  out.bgSizes = await Promise.all(bgUrls.slice(0, 8).map((u) => new Promise((res) => {
    const im = new Image();
    im.onload = () => res({ url: u.slice(-46), w: im.naturalWidth, h: im.naturalHeight,
      ratio: Math.round((im.naturalWidth / im.naturalHeight) * 100) / 100 });
    im.onerror = () => res({ url: u.slice(-46), w: 0, h: 0, ratio: 0 });
    im.src = u;
  })));
  return out;
}

(async () => {
  const browser = await puppeteer.launch({
    channel: 'chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 '
      + '(KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36');
    await page.setViewport({ width: WIDTH, height: 900, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
    await page.goto(URL, { waitUntil: 'domcontentloaded', timeout: 90000 });
    // The site reloads itself once on first load; settle, then measure, and
    // measure again if the context went away underneath us.
    let r = null;
    for (let go = 1; go <= 2 && !r; go++) {
      try {
        await sleep(go === 1 ? 6000 : 4000);
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));
        await sleep(2000);
        r = await page.evaluate(dump, SEL, WIDTH);
      } catch (e) { if (go === 2) console.log('measure failed: ' + e.message); }
    }
    if (!r) return;
    if (r.error) { console.log(r.error); return; }
    console.log(`=== ${r.root} at ${r.vw}px ===`);
    for (const n of r.nodes) {
      const pad = '  '.repeat(n.d);
      let line = `${pad}${n.el}  ${n.box[0]}x${n.box[1]}`;
      if (n.minH && n.minH !== '0px') line += `  min-height:${n.minH}`;
      if (n.fs) line += `  ${n.fs}`;
      console.log(line);
      if (n.bg) console.log(`${pad}   background: ${n.bg}  size:${n.bgSize}`);
      if (n.src) console.log(`${pad}   img src …${n.src}  natural ${n.natural}`);
      if (n.txt) console.log(`${pad}   "${n.txt}"`);
    }
    if (r.bgSizes && r.bgSizes.length) {
      console.log('\nbackground images, at their own size:');
      r.bgSizes.forEach((b) => console.log(`  …${b.url}  ${b.w}x${b.h}  ratio ${b.ratio}`));
    }
  } catch (e) {
    console.log('DIAG ERROR: ' + e.message);
  } finally { await browser.close(); }
})();
