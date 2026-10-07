// DEF-10: personalised and thin pages are indexable.
//
// Reported: /wishlist/, /login/ and /sign-up/ send "follow, index", while cart
// and checkout are correctly noindex — "so the config exists, it just was not
// applied to these". The report's fix adds compare and dashboard.
//
// Four things are read per page, because each is a different way to get this
// wrong:
//
//   1. the robots meta, and HOW MANY robots metas there are. search.php already
//      works around Rank Math by echoing a second tag; two tags that disagree
//      are resolved by Google taking the stricter, but they are still a mess,
//      and a fix that adds a third would be worse, not better.
//   2. any X-Robots-Tag header, which overrides the meta.
//   3. whether the page is in the XML sitemap. A noindexed URL that is still
//      submitted in the sitemap is a Search Console error of its own —
//      "Submitted URL marked noindex" — so fixing the meta alone would trade
//      one warning for another.
//   4. whether the page exists at all. /compare/ and /dashboard/ are in the
//      report's fix line but not in its measurements.
//
// Controls: cart and checkout (should already be noindex), home and shop
// (must stay indexable — noindexing the shop by accident is the worst
// outcome this fix could have).
//
// Read-only.
//
// Run: node tools/probe-robots.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const PAGES = [
  ['/wishlist/',  'wishlist',  'noindex'],
  ['/login/',     'login',     'noindex'],
  ['/sign-up/',   'sign-up',   'noindex'],
  ['/compare/',   'compare',   'noindex'],
  ['/dashboard/', 'dashboard', 'noindex'],
  ['/cart/',      'cart',      'noindex'],   // control, already right
  ['/checkout/',  'checkout',  'noindex'],   // control, already right
  ['/',           'home',      'index'],     // control, must stay
  ['/shop/',      'shop',      'index'],     // control, must stay
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

console.log('probe-robots: ' + SITE + '   ' + new Date().toISOString() + '\n');

// ── the sitemap first, so each page can be checked against it ─────────────
const inSitemap = new Set();
let sitemapRead = false;
try {
  const idx = await page.goto(SITE + '/sitemap_index.xml', { waitUntil: 'domcontentloaded', timeout: 20000 });
  const idxText = idx ? await idx.text() : '';
  const maps = [...idxText.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim());
  const pageMaps = maps.filter(u => /page-sitemap/i.test(u));
  console.log('sitemap index: ' + maps.length + ' sitemaps, ' + pageMaps.length + ' for pages');
  for (const u of pageMaps) {
    const r = await page.goto(u, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const t = r ? await r.text() : '';
    [...t.matchAll(/<loc>([^<]+)<\/loc>/g)].forEach(m => inSitemap.add(m[1].trim().replace(/\/?$/, '/')));
    sitemapRead = true;
  }
  console.log('page URLs in the sitemap: ' + inSitemap.size + '\n');
} catch (e) {
  console.log('sitemap could not be read: ' + String(e.message).slice(0, 80) + ' — sitemap column will say NO DATA\n');
}

console.log('page        want      HTTP  robots meta (count)                         x-robots-tag   sitemap');
const rows = [];
for (const [path, label, want] of PAGES) {
  let status = 0, xrt = '', final = '';
  try {
    const r = await page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 20000 });
    status = r ? r.status() : 0;
    if (r) { try { const h = await r.allHeaders(); xrt = h['x-robots-tag'] || ''; } catch (e) {} }
    await page.waitForTimeout(1200);
    final = page.url();
  } catch (e) { status = 0; }

  const m = status ? await page.evaluate(() => {
    const tags = [...document.querySelectorAll('meta[name="robots" i]')].map(t => (t.getAttribute('content') || '').trim());
    return { tags };
  }).catch(() => ({ tags: [] })) : { tags: [] };

  const redirected = final && final.replace(/\/?(\?.*)?$/, '/') !== (SITE + path).replace(/\/?$/, '/');
  const all = (m.tags.join(' ') + ' ' + xrt).toLowerCase();
  const effective = /noindex/.test(all) ? 'noindex' : (status ? 'index' : '?');
  const listed = sitemapRead ? (inSitemap.has((SITE + path).replace(/\/?$/, '/')) ? 'LISTED' : '—') : 'NO DATA';

  console.log(label.padEnd(12) + want.padEnd(10) + String(status).padEnd(6)
    + ((m.tags.join(' | ') || '(none)') + '  (' + m.tags.length + ')').padEnd(44)
    + (xrt || '—').padEnd(15) + listed
    + (redirected ? '   → redirected to ' + final.replace(SITE, '') : ''));
  rows.push({ label, want, status, effective, count: m.tags.length, listed, redirected });
}

console.log('\n— verdict —');
for (const r of rows) {
  let v;
  if (!r.status) v = 'NO DATA — no response';
  else if (r.status === 404) v = 'does not exist (404) — nothing to noindex';
  else if (r.effective !== r.want) v = (r.want === 'noindex' ? 'INDEXABLE — should be noindex' : 'NOINDEXED — must stay indexable!');
  else v = 'correct (' + r.effective + ')';
  if (r.count > 1) v += '   · ' + r.count + ' robots tags on one page';
  if (r.want === 'noindex' && r.listed === 'LISTED') v += '   · still submitted in the sitemap';
  console.log('  ' + r.label.padEnd(12) + v);
}

await browser.close();
console.log('\ndone ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
