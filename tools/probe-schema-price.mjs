// DEF-07: the Product JSON-LD publishes one price for a range of prices.
//
// Reported: offers.price "80.00" while the page's own size map runs 2×3 $60
// through 4×6 $150. Google then shows $80 when the cheapest option is $60 and
// a common selection is $100, and a landing price that disagrees with the
// rich-result price is a standard Merchant Center disapproval.
//
// The fix is either AggregateOffer with lowPrice/highPrice, or the lowest
// purchasable price. Both need one number this probe exists to establish:
// WHAT IS ACTUALLY PURCHASABLE.
//
// The repository says five sizes are offered — 2×3, 3×2, 2.5×3, 3×4, 3×5, at
// $60/$60/$65/$80/$100 — while af_pricing_config() prices fifteen. The
// report's $60–$150 map includes 3×6, 4×5 and 4×6, which af_sizes_offered()
// does not list. So either the page shows sizes it will not sell, or the
// offered list changed since the report. A highPrice of $150 for something
// nobody can buy would be a new Merchant Center problem in place of the old
// one, so this reads the size chips as rendered — label, price, and whether
// they are marked unavailable — and compares them against the JSON-LD.
//
// Read-only. No cart, no writes.
//
// Run: node tools/probe-schema-price.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1280, height: 950 }, ignoreHTTPSErrors: true });
const page = await ctx.newPage();

const visit = async (path, wait = 2600) => {
  const r = await page.goto(path.startsWith('http') ? path : SITE + path,
    { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => null);
  if (r) await page.waitForTimeout(wait);
  return r ? r.status() : 0;
};

console.log('probe-schema-price: ' + SITE + '   ' + new Date().toISOString() + '\n');

try {
  await visit('/shop/', 2500);
  let picks = [];
  try {
    picks = await page.evaluate(() => [...document.querySelectorAll('a[href*="/product/"]')]
      .map(a => a.href).filter((v, i, a) => a.indexOf(v) === i).slice(0, 4));
  } catch (e) { console.log('  /shop/ read failed: ' + String(e.message).slice(0, 80)); }

  if (!picks.length) { console.log('  no products found — NO DATA'); }

  for (const url of picks) {
    const st = await visit(url, 3200);
    const path = url.replace(SITE, '');
    console.log('── ' + path + '   HTTP ' + st);

    const d = await page.evaluate(() => {
      const out = { product: null, sizes: [], shown: '', title: '' };

      // Every JSON-LD block on the page, flattened, looking for Product.
      const blocks = [...document.querySelectorAll('script[type="application/ld+json"]')];
      const nodes = [];
      const walk = n => {
        if (!n || typeof n !== 'object') return;
        if (Array.isArray(n)) { n.forEach(walk); return; }
        nodes.push(n);
        if (n['@graph']) walk(n['@graph']);
      };
      blocks.forEach(b => { try { walk(JSON.parse(b.textContent || '{}')); } catch (e) {} });
      const isProduct = n => {
        const t = n && n['@type'];
        return t && (Array.isArray(t) ? t.indexOf('Product') >= 0 : t === 'Product');
      };
      const p = nodes.find(isProduct);
      if (p) {
        const o = p.offers && (Array.isArray(p.offers) ? p.offers[0] : p.offers);
        out.product = {
          name: String(p.name || '').slice(0, 120),
          offerType: o ? String(o['@type'] || '') : '(no offers)',
          price: o ? (o.price !== undefined ? String(o.price) : '') : '',
          low: o ? (o.lowPrice !== undefined ? String(o.lowPrice) : '') : '',
          high: o ? (o.highPrice !== undefined ? String(o.highPrice) : '') : '',
          currency: o ? String(o.priceCurrency || '') : '',
          availability: o ? String(o.availability || '') : '',
          validUntil: o ? String(o.priceValidUntil || '') : '',
          offerCount: o ? (o.offerCount !== undefined ? String(o.offerCount) : '') : '',
        };
      }

      out.title = String(document.title || '').slice(0, 120);

      // The size chips as actually rendered: label, price, and whether the
      // page itself says the option cannot be bought.
      const money = s => {
        const m = String(s || '').replace(/\s+/g, ' ').match(/\$\s?([\d,]+(?:\.\d{2})?)/);
        return m ? Number(m[1].replace(/,/g, '')) : null;
      };
      const cand = [...document.querySelectorAll(
        '#af-size-select option, select.af-size-select option, [data-type="size"] option,'
        + ' [data-size], .af-size, .af-size-chip, .af-opt-size li, label')]
        .filter(el => /\d\s*[×x]\s*\d/.test(((el.innerText || '') + '')));
      const seen = {};
      cand.forEach(el => {
        const t = ((el.innerText || '') + '').replace(/\s+/g, ' ').trim();
        if (!t || t.length > 90 || seen[t]) return;
        seen[t] = 1;
        const cls = ((el.className || '') + ' ' + ((el.getAttribute && el.getAttribute('class')) || ''));
        const oos = /out-of-stock|oos|unavailable|disabled|sold-?out/i.test(cls)
                 || /out of stock|unavailable|sold out/i.test(t)
                 || (el.hasAttribute && el.hasAttribute('disabled'));
        out.sizes.push({ label: t.slice(0, 60), price: money(t), oos: !!oos });
      });

      const pr = document.querySelector('.price, .summary .price, p.price');
      out.shown = pr ? ((pr.innerText || '') + '').replace(/\s+/g, ' ').trim().slice(0, 70) : '';
      return out;
    }).catch(e => null);

    if (!d) { console.log('    could not read the page\n'); continue; }
    if (!d.product) { console.log('    no Product JSON-LD found\n'); continue; }

    const p = d.product;
    console.log('    JSON-LD offers @type   : ' + (p.offerType || '(none)'));
    console.log('      price                : ' + (p.price || '—')
      + (p.low || p.high ? '     lowPrice: ' + (p.low || '—') + '   highPrice: ' + (p.high || '—') : ''));
    console.log('      currency/availability: ' + p.currency + '   ' + p.availability.replace('https://schema.org/', ''));
    console.log('      priceValidUntil      : ' + (p.validUntil || '—')
      + (p.offerCount ? '   offerCount: ' + p.offerCount : ''));
    console.log('      schema name          : ' + p.name);
    console.log('      page <title>         : ' + d.title);
    console.log('      price shown on page  : ' + (d.shown || '—'));

    const prices = d.sizes.filter(s => s.price !== null);
    const buyable = prices.filter(s => !s.oos);
    console.log('    size options rendered  : ' + d.sizes.length
      + '   (' + prices.length + ' with a price, ' + buyable.length + ' buyable)');
    prices.slice(0, 20).forEach(s =>
      console.log('        ' + (s.oos ? 'OOS ' : '    ') + ('$' + s.price).padEnd(8) + s.label));

    if (buyable.length) {
      const lo = Math.min(...buyable.map(s => s.price));
      const hi = Math.max(...buyable.map(s => s.price));
      console.log('    purchasable range      : $' + lo + ' – $' + hi);
      const schemaPrice = Number(p.price || p.low || 0);
      if (p.offerType === 'AggregateOffer' && p.low && p.high) {
        const ok = Number(p.low) === lo && Number(p.high) === hi;
        console.log('    → ' + (ok
          ? 'FIXED — AggregateOffer matches the purchasable range exactly.'
          : 'AggregateOffer present but $' + p.low + '–$' + p.high + ' does not match $' + lo + '–$' + hi + '.'));
      } else if (schemaPrice && Math.abs(schemaPrice - lo) < 0.01) {
        console.log('    → acceptable — a single price equal to the cheapest purchasable option.');
      } else if (schemaPrice) {
        console.log('    → DEF-07 CONFIRMED — schema says $' + schemaPrice
          + ' but the cheapest purchasable option is $' + lo + '.');
        console.log('      Google would show $' + schemaPrice + ' against a landing page starting at $' + lo + '.');
      }
    } else {
      console.log('    could not read any priced size chips — the range is UNMEASURED,');
      console.log('    so do not write a highPrice from this run.');
    }

    // ── DEF-08: is Product.name the product, or the SEO title? ──────────
    const nm = (p.name || '').trim();
    const ttl = (d.title || '').trim();
    // Judge on the SITE SUFFIX, taken from the <title>, not on any separator.
    // A live run reported LIKELY because the product's own title contains
    // en-dashes — "… 3x4 Feet – Floating Frame – Premium …" — which says
    // nothing about the shop's name being appended.
    const sufM = ttl.match(/\s[|\u2013\u2014-]\s(.+)$/);
    const suffix = sufM ? sufM[1].trim() : '';
    if (!nm) {
      console.log('    → DEF-08 NO DATA — the Product node carries no name.');
    } else if (nm === ttl) {
      console.log('    → DEF-08 CONFIRMED — the schema name IS the page <title>, verbatim.');
    } else if (suffix && nm.endsWith(suffix)) {
      console.log('    → DEF-08 CONFIRMED — the schema name ends with the site suffix "'
        + suffix + '".');
    } else if (!suffix) {
      console.log('    → DEF-08 NO DATA — the <title> carries no suffix to compare against.');
    } else {
      console.log('    → DEF-08 FIXED — the schema name does not carry the site suffix "'
        + suffix + '".');
    }
    if (/&(amp|quot|#0?39|lt|gt);/i.test(nm)) {
      console.log('      note: the name still contains an HTML entity — it will print literally.');
    }
    console.log('');
  }
} catch (e) {
  console.log('\n  probe stopped early: ' + String(e.message).slice(0, 200));
}

await browser.close();
console.log('done ' + new Date().toISOString());
setTimeout(() => process.exit(0), 3000).unref();
