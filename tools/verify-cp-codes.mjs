// Do the Corporate Printing products carry their CP codes?
//
// The owner's CP CODE FILE (26 Sep) names 33 pictures by CP code, CP-220001 to
// CP-220033. Each picture is the main image of one Corporate Printing product,
// which until now carried a temporary code (TMP-1171 to TMP-1203).
// tools/artcode-corrections.csv writes the CP codes on deploy. This checks each
// product as a first-time visitor:
//   - its page opens (HTTP 200)
//   - its own "Art Code:" line reads exactly "Art Code: CP-…" (not a card's)
//   - its SKU, from the Store API, is the CP code
//   - a product search for the CP code finds it
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/verify-cp-codes.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const PRODUCTS = [
  { id: 33440, code: 'CP-220001-3520', was: 'TMP-1183', slug: 'gold-foil-business-cards-heavyweight-cards-with-metallic-foil-stamping' },
  { id: 33434, code: 'CP-220002-3520', was: 'TMP-1179', slug: 'membership-loyalty-cards-premium-printed-cards-with-foil-numbering-and-barcodes' },
  { id: 33462, code: 'CP-220003-3520', was: 'TMP-1201', slug: 'round-business-cards-custom-die-cut-circular-cards-with-qr-and-foil-options' },
  { id: 33450, code: 'CP-220004-3520', was: 'TMP-1192', slug: 'raised-foil-loyalty-cards-soft-touch-black-cards-with-metallic-raised-print' },
  { id: 33449, code: 'CP-220005-083117', was: 'TMP-1191', slug: 'foil-stamped-training-certificates-personalised-award-and-completion-certificates' },
  { id: 33436, code: 'CP-220006-083117', was: 'TMP-1180', slug: 'presentation-folders-envelopes-branded-corporate-document-folders-with-foil-detail' },
  { id: 33463, code: 'CP-220007-083117', was: 'TMP-1202', slug: 'custom-certificate-printing-foil-and-embossed-certificates-on-premium-stock' },
  { id: 33420, code: 'CP-220008-5883', was: 'TMP-1171', slug: 'a5-flyer-printing-full-colour-double-sided-leaflets-for-promotions-and-product-launches' },
  { id: 33444, code: 'CP-220009', was: 'TMP-1186', slug: 'full-colour-commercial-printing-brochures-folders-cards-and-corporate-collateral' },
  { id: 33447, code: 'CP-220010', was: 'TMP-1189', slug: 'marketing-print-pack-complete-business-collateral-set-for-campaigns-and-mailings' },
  { id: 33422, code: 'CP-220011-2565', was: 'TMP-1172', slug: 'birthday-banner-standee-with-stand-personalised-celebration-roll-up-display' },
  { id: 33423, code: 'CP-220012-2565', was: 'TMP-1173', slug: 'grand-opening-roll-up-banner-branded-pull-up-display-for-store-launches-and-openings' },
  { id: 33451, code: 'CP-220013-2565', was: 'TMP-1193', slug: 'roll-up-banner-display-set-multiple-coordinated-pull-up-banners-for-corridors-and-foyers' },
  { id: 33459, code: 'CP-220014-2565', was: 'TMP-1199', slug: 'weatherproof-outdoor-poster-stand-lockable-forecourt-display-for-retail-promotions' },
  { id: 33461, code: 'CP-220015-2565', was: 'TMP-1200', slug: 'personalised-welcome-standee-printed-event-sign-for-birthdays-weddings-and-receptions' },
  { id: 33454, code: 'CP-220016-6030', was: 'TMP-1195', slug: 'projecting-shop-sign-double-sided-bracket-sign-for-storefronts-and-walkways' },
  { id: 33455, code: 'CP-220017-6030', was: 'TMP-1196', slug: 'custom-vinyl-store-banner-heavy-duty-weatherproof-pvc-advertising-banner' },
  { id: 33426, code: 'CP-220018-2060', was: 'TMP-1175', slug: 'hanging-event-banners-large-format-ceiling-and-pole-banners-for-arenas-and-venues' },
  { id: 33437, code: 'CP-220019-8080', was: 'TMP-1181', slug: 'bridal-shower-backdrop-tapestry-printed-fabric-banner-with-calligraphy-and-botanical-art' },
  { id: 33428, code: 'CP-220020-8080', was: 'TMP-1176', slug: 'step-and-repeat-media-wall-branded-photo-backdrop-for-press-events-and-exhibitions' },
  { id: 33430, code: 'CP-220021-8080', was: 'TMP-1177', slug: 'personalised-wedding-backdrop-printed-fabric-photo-wall-for-receptions-and-showers' },
  { id: 33442, code: 'CP-220022-3040', was: 'TMP-1184', slug: 'outdoor-pylon-business-sign-freestanding-post-mounted-signage-for-premises-and-forecourts' },
  { id: 33458, code: 'CP-220023-3040', was: 'TMP-1198', slug: 'wayfinding-directional-signs-interior-and-exterior-navigation-signage-systems' },
  { id: 33445, code: 'CP-220024-6030', was: 'TMP-1187', slug: 'led-light-box-sign-illuminated-shopfront-and-reception-display' },
  { id: 33432, code: 'CP-220025-8060', was: 'TMP-1178', slug: 'acrylic-qr-wi-fi-display-stand-printed-counter-sign-for-reviews-socials-and-guest-wi-fi' },
  { id: 33446, code: 'CP-220026-6025', was: 'TMP-1188', slug: 'printed-table-cloths-full-colour-branded-covers-for-events-and-trade-stands' },
  { id: 33443, code: 'CP-220027', was: 'TMP-1185', slug: 'exhibition-table-cover-banner-set-coordinated-trade-stand-package-for-conferences' },
  { id: 33456, code: 'CP-220028', was: 'TMP-1197', slug: 'exhibition-booth-table-backdrop-complete-branded-stand-package-for-conferences' },
  { id: 33424, code: 'CP-220029-3030', was: 'TMP-1174', slug: 'waterproof-vinyl-stickers-kiss-cut-labels-custom-brand-decals-in-any-shape' },
  { id: 33439, code: 'CP-220030-140160', was: 'TMP-1182', slug: 'ethnic-print-jute-tote-bag-reusable-branded-shopper-with-contrast-handles' },
  { id: 33448, code: 'CP-220031-140160', was: 'TMP-1190', slug: 'personalised-logo-jute-tote-bag-branded-corporate-gift-and-event-giveaway' },
  { id: 33452, code: 'CP-220032-140160', was: 'TMP-1194', slug: 'line-art-canvas-tote-bags-minimal-printed-cotton-shoppers-for-retail-and-gifting' },
  { id: 33464, code: 'CP-220033-140160', was: 'TMP-1203', slug: 'heat-transfer-jute-tote-bags-named-favour-bags-with-bamboo-handles-for-events' },
];

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const go = async path => page.goto(SITE + path, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);

console.log('verify-cp-codes: ' + SITE + '   ' + new Date().toISOString() + '\n');

// SKUs from the Store API, 100 at a time, by id.
await go('/');
const skus = await page.evaluate(async ids => {
  const out = {};
  const r = await fetch('/wp-json/wc/store/v1/products?per_page=100&include=' + ids.join(',')).then(r => r.ok ? r.json() : []).catch(() => []);
  for (const p of r) out[p.id] = p.sku || '';
  return out;
}, PRODUCTS.map(p => p.id)).catch(() => ({}));

let pageOk = 0, lineOk = 0, skuOk = 0, searchOk = 0, allOk = 0;
for (const p of PRODUCTS) {
  const r = await go('/product/' + p.slug + '/');
  await page.waitForTimeout(1200);
  const line = r ? await page.evaluate(() => ((document.querySelector('.af-art-code--single') || {}).textContent || '').replace(/\s+/g, ' ').trim()).catch(() => '') : '';
  const status = r ? r.status() : 0;
  const exact = line === 'Art Code: ' + p.code;
  const sku = skus[p.id] === undefined ? '(not in the Store API)' : skus[p.id];
  const skuMatch = sku === p.code;

  const s = await go('/?s=' + encodeURIComponent(p.code) + '&post_type=product');
  await page.waitForTimeout(1200);
  const found = s ? await page.evaluate(({ slug }) => [...document.querySelectorAll('a[href]')].some(a => a.href.includes('/product/' + slug))
    || location.pathname.includes('/product/' + slug), p).catch(() => false) : false;

  if (status === 200) pageOk++;
  if (exact) lineOk++;
  if (skuMatch) skuOk++;
  if (found) searchOk++;
  const ok = status === 200 && exact && skuMatch && found;
  if (ok) allOk++;
  console.log((ok ? '  OK   ' : '  FAIL ') + ('#' + p.id).padEnd(8) + p.code.padEnd(18) + 'HTTP ' + status
    + ' · "' + (line || '(no Art Code line)') + '" · SKU ' + sku + ' · search ' + (found ? 'finds it' : 'does NOT find it')
    + (line === 'Art Code: ' + p.was ? '   (still the temporary ' + p.was + ')' : ''));
}

console.log('\n— Corporate Printing products: ' + PRODUCTS.length + ' —');
console.log('  page opens (HTTP 200)          : ' + pageOk);
console.log('  "Art Code: CP-…" exactly       : ' + lineOk);
console.log('  SKU = the CP code              : ' + skuOk);
console.log('  search for the CP code finds it: ' + searchOk);
console.log('  all four                       : ' + allOk + ' of ' + PRODUCTS.length);
console.log('done ' + new Date().toISOString());
await browser.close();
