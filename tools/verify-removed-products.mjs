// Can a visitor still reach the products taken off the website?
//
// inc/placeholder-products.php made them private: #11491 "test" (N-01), and
// from 25 Sep the five theme demo posters the owner asked to remove (art
// codes TMP-1000 to TMP-1004); on 26 Sep it deleted all six permanently, and
// a deleted product must be unreachable the same ten ways. On 1 Oct the owner
// asked to remove TMP-1246, #25240 "Vaishnava Symbols Trio", made private the
// same way, and then six more: TMP-1233, TMP-1229, TMP-1134, TMP-1120,
// TMP-1078 and TMP-1071, "don't delete them, make them private"; then 45 more,
// TMP-1124 to TMP-1310, "make them private too"; then five from the
// temporary-code sheet (TMP-1216, 1218, 1256, 1295, 1309). This checks, for
// each, every way a shopper or
// a search engine could reach it, as a first-time visitor with no cookies:
//   - its own address, and ?p=<id>
//   - the Store API, by id and by searching its name
//   - a product search for its name and for its art code
//   - the shop, the shop sorted by price, and the home page
//   - the product sitemaps
// tools/verify-n01.mjs is the same check for #11491 alone.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-removed-products.mjs [url]
//
// AF_QA_ONLY (the "only" input of qa-personas.yml) narrows it to some of them:
// art codes or product ids, comma-separated, e.g. "TMP-1310,TMP-1304" or
// "27325". Blank checks all of them, about fifteen seconds each.

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCTS = [
  { id: 115, slug: 'the-penguin-show-poster', name: 'The Penguin Show Poster', code: 'TMP-1000' },
  { id: 123, slug: 'il-lemone-poster', name: 'IL Lemone Poster', code: 'TMP-1001' },
  { id: 177, slug: 'geometric-shapes-poster', name: 'Geometric Shapes poster', code: 'TMP-1002' },
  { id: 199, slug: 'balance-poster', name: 'Balance Poster', code: 'TMP-1003' },
  { id: 211, slug: 'japanese-butterfly-ii-poster', name: 'Japanese Butterfly II Poster', code: 'TMP-1004' },
  { id: 11491, slug: 'test-canvas-wall-art', name: 'test', code: 'TMP-1055' },
  // A search for one of these whole long names finds nothing even while the
  // product is up, so the searches use the words a shopper would type.
  { id: 25240, slug: 'vaishnava-symbols-trio-canvas-wall-art', code: 'TMP-1246', search: 'Vaishnava Symbols Trio',
    name: 'Vaishnava Symbols Trio Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 22747, slug: 'ganesh-pop-art-canvas-wall-art', code: 'TMP-1233', search: 'Ganesh Pop Art',
    name: 'Ganesh Pop Art Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 22016, slug: 'horses-in-color-field-canvas-wall-art', code: 'TMP-1229', search: 'Horses in Color Field',
    name: 'Horses in Color Field Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 29342, slug: 'kashi-vishwanath-gold-spire-canvas-wall-art', code: 'TMP-1134', search: 'Kashi Vishwanath Gold Spire',
    name: 'Kashi Vishwanath Gold Spire Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 28422, slug: 'raas-leela-miniature-canvas-wall-art', code: 'TMP-1120', search: 'Raas Leela Miniature',
    name: 'Raas Leela Miniature Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 23789, slug: 'radha-krishna-color-duet-canvas-wall-art', code: 'TMP-1078', search: 'Radha Krishna Color Duet',
    name: 'Radha Krishna Color Duet Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  { id: 22383, slug: 'krishna-rainbow-splash-canvas-wall-art', code: 'TMP-1071', search: 'Krishna Rainbow Splash',
    name: 'Krishna Rainbow Splash Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor' },
  // 1 Oct, the 45, in the order the owner listed them
  { id: 27325, slug: "radha-krishna-on-the-branch-canvas-wall-art", code: "TMP-1310", search: "Radha Krishna on the Branch",
    name: "Radha Krishna on the Branch Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31527, slug: "krishna-cowherd-modern-art-canvas-wall-art", code: "TMP-1304", search: "Krishna Cowherd Modern Art",
    name: "Krishna Cowherd Modern Art Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31456, slug: "balaji-abstract-gold-canvas-wall-art", code: "TMP-1303", search: "Balaji Abstract Gold",
    name: "Balaji Abstract Gold Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31395, slug: "shiva-smoke-and-trident-canvas-wall-art", code: "TMP-1302", search: "Shiva Smoke and Trident",
    name: "Shiva Smoke and Trident Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31273, slug: "palace-in-the-grove-canvas-wall-art", code: "TMP-1301", search: "Palace in the Grove",
    name: "Palace in the Grove Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31150, slug: "shiva-family-harmony-canvas-wall-art", code: "TMP-1299", search: "Shiva Family Harmony",
    name: "Shiva Family Harmony Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31088, slug: "vaikuntha-celestial-court-canvas-wall-art", code: "TMP-1298", search: "Vaikuntha Celestial Court",
    name: "Vaikuntha Celestial Court Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30966, slug: "vishnu-on-shesha-canvas-wall-art", code: "TMP-1297", search: "Vishnu on Shesha",
    name: "Vishnu on Shesha Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29751, slug: "sacred-cow-relief-art-canvas-wall-art", code: "TMP-1289", search: "Sacred Cow Relief Art",
    name: "Sacred Cow Relief Art Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30093, slug: "horses-of-the-dust-plains-canvas-wall-art", code: "TMP-1290", search: "Horses of the Dust Plains",
    name: "Horses of the Dust Plains Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30154, slug: "ganesha-dawn-silhouette-canvas-wall-art", code: "TMP-1291", search: "Ganesha Dawn Silhouette",
    name: "Ganesha Dawn Silhouette Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30276, slug: "red-sun-winter-tree-canvas-wall-art", code: "TMP-1292", search: "Red Sun Winter Tree",
    name: "Red Sun Winter Tree Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30338, slug: "shiva-parivar-in-clouds-canvas-wall-art", code: "TMP-1293", search: "Shiva Parivar in Clouds",
    name: "Shiva Parivar in Clouds Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30775, slug: "marigold-dreams-portrait-canvas-wall-art", code: "TMP-1294", search: "Marigold Dreams Portrait",
    name: "Marigold Dreams Portrait Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29159, slug: "horse-studies-collage-canvas-wall-art", code: "TMP-1280", search: "Horse Studies Collage",
    name: "Horse Studies Collage Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29220, slug: "vishnu-cosmic-lotus-canvas-wall-art", code: "TMP-1281", search: "Vishnu Cosmic Lotus",
    name: "Vishnu Cosmic Lotus Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29281, slug: "balaji-divine-collage-canvas-wall-art", code: "TMP-1282", search: "Balaji Divine Collage",
    name: "Balaji Divine Collage Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29395, slug: "pichwai-ganesha-fountains-canvas-wall-art", code: "TMP-1283", search: "Pichwai Ganesha Fountains",
    name: "Pichwai Ganesha Fountains Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29456, slug: "maratha-pride-with-lion-canvas-wall-art", code: "TMP-1284", search: "Maratha Pride with Lion",
    name: "Maratha Pride with Lion Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29517, slug: "lone-tree-between-worlds-canvas-wall-art", code: "TMP-1285", search: "Lone Tree Between Worlds",
    name: "Lone Tree Between Worlds Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29578, slug: "twin-faces-of-serenity-canvas-wall-art", code: "TMP-1286", search: "Twin Faces of Serenity",
    name: "Twin Faces of Serenity Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28473, slug: "quiet-harbor-minimal-canvas-wall-art", code: "TMP-1273", search: "Quiet Harbor Minimal",
    name: "Quiet Harbor Minimal Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28534, slug: "kirtan-celebration-canvas-wall-art", code: "TMP-1274", search: "Kirtan Celebration",
    name: "Kirtan Celebration Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28717, slug: "vishnu-in-golden-garlands-canvas-wall-art", code: "TMP-1275", search: "Vishnu in Golden Garlands",
    name: "Vishnu in Golden Garlands Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28962, slug: "savanna-golden-hour-canvas-wall-art", code: "TMP-1278", search: "Savanna Golden Hour",
    name: "Savanna Golden Hour Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27133, slug: "geometric-falls-sunrise-canvas-wall-art", code: "TMP-1257", search: "Geometric Falls Sunrise",
    name: "Geometric Falls Sunrise Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27194, slug: "murmuration-at-dusk-canvas-wall-art", code: "TMP-1258", search: "Murmuration at Dusk",
    name: "Murmuration at Dusk Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27264, slug: "nataraja-bronze-glory-canvas-wall-art", code: "TMP-1259", search: "Nataraja Bronze Glory",
    name: "Nataraja Bronze Glory Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27388, slug: "krishna-minimal-splash-canvas-wall-art", code: "TMP-1260", search: "Krishna Minimal Splash",
    name: "Krishna Minimal Splash Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27449, slug: "buddha-offering-lotus-canvas-wall-art", code: "TMP-1261", search: "Buddha Offering Lotus",
    name: "Buddha Offering Lotus Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27510, slug: "cubist-buddha-visage-canvas-wall-art", code: "TMP-1262", search: "Cubist Buddha Visage",
    name: "Cubist Buddha Visage Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27572, slug: "two-horses-cubist-canvas-wall-art", code: "TMP-1263", search: "Two Horses Cubist",
    name: "Two Horses Cubist Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27633, slug: "flight-path-reverie-canvas-wall-art", code: "TMP-1264", search: "Flight Path Reverie",
    name: "Flight Path Reverie Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27750, slug: "krishna-and-the-monkeys-folk-canvas-wall-art", code: "TMP-1266", search: "Krishna and the Monkeys Folk",
    name: "Krishna and the Monkeys Folk Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27811, slug: "buddha-among-pink-lotuses-canvas-wall-art", code: "TMP-1267", search: "Buddha Among Pink Lotuses",
    name: "Buddha Among Pink Lotuses Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 27981, slug: "crimson-veil-portrait-canvas-wall-art", code: "TMP-1268", search: "Crimson Veil Portrait",
    name: "Crimson Veil Portrait Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28103, slug: "krishna-serene-face-canvas-wall-art", code: "TMP-1269", search: "Krishna Serene Face",
    name: "Krishna Serene Face Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28164, slug: "temple-bells-and-cows-canvas-wall-art", code: "TMP-1270", search: "Temple Bells and Cows",
    name: "Temple Bells and Cows Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28225, slug: "nandi-and-the-jyotirlingas-canvas-wall-art", code: "TMP-1271", search: "Nandi and the Jyotirlingas",
    name: "Nandi and the Jyotirlingas Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30032, slug: "devotion-in-color-mist-canvas-wall-art", code: "TMP-1142", search: "Devotion in Color Mist",
    name: "Devotion in Color Mist Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30409, slug: "krishna-s-temple-gardens-canvas-wall-art", code: "TMP-1147", search: "Krishna's Temple Gardens",
    name: "Krishna's Temple Gardens Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30714, slug: "krishna-sudama-friendship-canvas-wall-art", code: "TMP-1148", search: "Krishna Sudama Friendship",
    name: "Krishna Sudama Friendship Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 31027, slug: "radha-krishna-graphite-duet-canvas-wall-art", code: "TMP-1153", search: "Radha Krishna Graphite Duet",
    name: "Radha Krishna Graphite Duet Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 28656, slug: "radha-s-mirror-of-krishna-canvas-wall-art", code: "TMP-1124", search: "Radha's Mirror of Krishna",
    name: "Radha's Mirror of Krishna Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 29084, slug: "shiva-of-the-ghats-canvas-wall-art", code: "TMP-1130", search: "Shiva of the Ghats",
    name: "Shiva of the Ghats Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  // 1 Oct, five more from the temporary-code sheet, "remove this ... from the website"
  { id: 8711, slug: "stunning-personalized-family-photo-collage-canvas-print-36-x-60-inches", code: "TMP-1216", search: "Personalized Family Photo Collage",
    name: "Stunning Personalized Family Photo Collage Canvas Print (36 x 60 Inches) – Custom Wall Art for Living Room" },
  { id: 8869, slug: "modern-living-room-wall-decor-canvas-set-3-panel-landscape-wall-art-stylish", code: "TMP-1218", search: "Modern Living Room Wall Decor Canvas Set",
    name: "Modern Living Room Wall Decor Canvas Set – 3 Panel Landscape Wall Art for Stylish & Luxurious Interiors" },
  { id: 26628, slug: "floral-arch-wall-art-canvas-wall-art", code: "TMP-1256", search: "Floral Arch Wall Art",
    name: "Floral Arch Wall Art Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 30836, slug: "veena-player-with-peacock-canvas-wall-art", code: "TMP-1295", search: "Veena Player with Peacock",
    name: "Veena Player with Peacock Canvas Wall Art 3x4 Feet – Floating Frame – Premium Digital Canvas Print – Living Room & Home Spiritual Wall Décor" },
  { id: 8805, slug: "modern-cafe-decor-wall-art-set-72x24-inch-stunning-contemporary-abstract-panel", code: "TMP-1309", search: "Modern Cafe Decor Wall Art Set",
    name: "Modern Cafe Decor Wall Art Set – 72x24 Inch Stunning Contemporary Abstract Panel Digital Canvas Print" },
];
const ONLY = (process.env.AF_QA_ONLY || '').split(',').map(s => s.trim().toUpperCase()).filter(Boolean);
const CHECK = ONLY.length ? PRODUCTS.filter(p => ONLY.includes(p.code.toUpperCase()) || ONLY.includes(String(p.id))) : PRODUCTS;

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async path => page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
// WordPress prints "3x4" in a title as "3×4", and "Krishna's" as "Krishna’s":
// each pair reads the same here.
const norm = s => (s || '').replace(/×/g, 'x').replace(/[‘’]/g, "'").replace(/\s+/g, ' ').trim().toLowerCase();
const same = (a, b) => norm(a) === norm(b);
// The H1 read on a product's own address is cut to 60 characters, so it is
// held against the name cut the same way; a long name (#25240's is 137)
// otherwise never matches, and a page still showing the product reads GONE.
const head60 = s => (s || '').replace(/\s+/g, ' ').trim().slice(0, 60);

console.log('verify-removed-products: ' + SITE + '   ' + new Date().toISOString() + '\n');

// Listings shared by every product: which of them does each link to?
const LISTINGS = ['/shop/', '/shop/?orderby=price', '/'];
const linkedFrom = {};
for (const path of LISTINGS) {
  const r = await go(path);
  await page.waitForTimeout(2500);
  const hrefs = r ? await page.evaluate(() => [...document.querySelectorAll('a[href]')].map(a => a.href)).catch(() => null) : null;
  linkedFrom[path] = hrefs;
}

// The product sitemaps, read once.
await go('/');
const sitemaps = await page.evaluate(async () => {
  const idx = await fetch('/sitemap_index.xml').then(r => r.ok ? r.text() : '').catch(() => '');
  const out = [];
  for (const m of [...idx.matchAll(/<loc>([^<]*product-sitemap[^<]*)<\/loc>/g)].map(m => m[1])) {
    const path = new URL(m).pathname;
    out.push({ path, text: await fetch(path).then(r => r.ok ? r.text() : '').catch(() => '') });
  }
  return out;
}).catch(() => []);

let removed = 0;
for (const p of CHECK) {
  console.log('#' + p.id + ' ' + p.code + ' "' + p.name + '"');
  const ways = [];
  const say = (gone, what, measured) => { ways.push(gone); console.log('  ' + (gone ? 'GONE   ' : 'STILL  ') + what.padEnd(44) + ' ' + measured); };

  // Its own address: a 404, or a page that is not this product.
  for (const path of ['/product/' + p.slug + '/', '/?p=' + p.id]) {
    const r = await go(path);
    if (!r) { say(false, path, 'no answer'); continue; }
    await page.waitForTimeout(1200);
    const d = await page.evaluate(() => ({
      h1: ((document.querySelector('h1.product_title, h1') || {}).textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60),
      atc: !!document.querySelector('.single_add_to_cart_button'),
    })).catch(() => ({ h1: '', atc: false }));
    const shown = r.status() === 200 && same(d.h1, head60(p.name));
    say(!shown, path, 'HTTP ' + r.status() + ' · H1 "' + d.h1 + '" · Add to Cart ' + (d.atc ? 'yes' : 'no')
      + ' · x-litespeed-cache ' + (r.headers()['x-litespeed-cache'] || '-'));
  }

  // The Store API: what every widget and app reads.
  await go('/');
  const words = p.search || p.name;
  const api = await page.evaluate(async ({ id, words }) => {
    const one = await fetch('/wp-json/wc/store/v1/products/' + id).then(r => r.status).catch(() => 0);
    const hits = await fetch('/wp-json/wc/store/v1/products?per_page=50&search=' + encodeURIComponent(words)).then(r => r.ok ? r.json() : []).catch(() => []);
    return { one, found: hits.some(h => h.id === id) };
  }, { id: p.id, words }).catch(() => ({ one: 0, found: true }));
  say(api.one !== 200, 'Store API /products/' + p.id, 'HTTP ' + api.one);
  say(!api.found, 'Store API search "' + words + '"', api.found ? 'listed' : 'not listed');

  // Product search by name and by art code.
  for (const q of [words, p.code]) {
    const path = '/?s=' + encodeURIComponent(q) + '&post_type=product';
    const r = await go(path);
    if (!r) { say(false, 'search "' + q + '"', 'no answer'); continue; }
    await page.waitForTimeout(1500);
    const d = await page.evaluate(({ slug, name }) => ({
      linked: [...document.querySelectorAll('a[href]')].some(a => a.href.includes('/product/' + slug)),
      h1: ((document.querySelector('h1.product_title') || {}).textContent || '').trim(),
    }), p).catch(() => null);
    // A search with one hit can redirect straight to the product page.
    const shown = !d || d.linked || same(d.h1, p.name);
    say(!shown, 'search "' + q + '"', 'HTTP ' + r.status() + ' · ' + (d ? (d.linked ? 'links to it' : same(d.h1, p.name) ? 'went to its page' : 'no link') : 'unreadable'));
  }

  // The listings read above.
  for (const path of LISTINGS) {
    const hrefs = linkedFrom[path];
    if (!hrefs) { say(false, path, 'page unreadable'); continue; }
    const linked = hrefs.some(h => h.includes('/product/' + p.slug + '/'));
    say(!linked, path, linked ? 'links to it' : 'no link');
  }

  if (!sitemaps.length) console.log('  NO DATA product sitemaps                             the sitemap index could not be read');
  else {
    const listed = sitemaps.filter(s => s.text.includes('/product/' + p.slug + '/')).map(s => s.path);
    say(!listed.length, 'product sitemaps', sitemaps.length + ' read · listed in: ' + (listed.join(', ') || 'none'));
  }

  const gone = ways.filter(Boolean).length;
  if (gone === ways.length) removed++;
  console.log('  ' + (gone === ways.length ? 'REMOVED' : 'STILL REACHABLE') + ': ' + gone + ' of ' + ways.length + ' ways in are closed\n');
}

console.log('removed from the website: ' + removed + ' of ' + CHECK.length + (ONLY.length ? ' (AF_QA_ONLY: ' + ONLY.join(',') + ')' : ''));
console.log('done ' + new Date().toISOString());
await browser.close();
