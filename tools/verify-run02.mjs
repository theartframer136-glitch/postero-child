// Re-measure "Art Framer Test Run 02" against the live site.
//
// 85 cases and 17 new defects, handed in as fact. Run 01 taught me the report
// is usually right about WHAT is wrong and sometimes wrong about WHY, and that
// the difference only shows up if you measure it yourself. So: every claim
// here is re-taken, the measured value is printed next to the claimed one, and
// the verdict is decided from the number rather than asserted.
//
// This pass covers everything answerable over plain HTTP — response headers,
// cache behaviour, H1 counts, robots directives, JSON-LD, duplicate pages,
// script shims. No browser, no cart, no writes of any kind. The interaction
// claims (pop-up, focus ring, contrast, checkout widths, CAD payment methods,
// per-piece shipping) need a real browser and are a separate pass.
//
// Run: node tools/verify-run02.mjs [url]

import https from 'node:https';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');

const YES = 'CONFIRMED', NO = 'CONTRADICTED', PART = 'PARTLY', NA = 'NO DATA';
const rows = [];
const say = (id, verdict, claim, measured) => {
  rows.push({ id, verdict });
  console.log('  ' + verdict.padEnd(13) + id.padEnd(9) + String(measured).replace(/\s+/g, ' ').slice(0, 150));
};

function get(path, opts = {}) {
  return new Promise(resolve => {
    const url = path.startsWith('http') ? path : SITE + path;
    const req = https.get(url, {
      agent: false, headers: { 'User-Agent': 'af-run02-verify', 'Accept-Encoding': opts.raw ? 'identity' : 'br, gzip' },
    }, res => {
      let body = '';
      res.setEncoding('latin1');
      res.on('data', c => { body += c; });
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body, url }));
    });
    req.on('error', e => resolve({ status: 0, headers: {}, body: '', err: e.message }));
    req.setTimeout(30000, () => req.destroy(new Error('timeout')));
  });
}

const h1s   = html => (html.match(/<h1[\s>]/gi) || []).length;
const robots = html => (html.match(/<meta[^>]+name=["']robots["'][^>]+content=["']([^"']+)/i) || [])[1] || '(none)';
const desc  = html => (html.match(/<meta[^>]+name=["']description["'][^>]+content=["']([^"']*)/i) || [])[1];

console.log('verify run 02: ' + SITE + '   ' + new Date().toISOString());

// ── INF: the security/transport baseline the report says is clean ──────────
console.log('\n— infrastructure & HTTP —');
{
  const r = await get('/');
  const H = r.headers;
  const want = {
    'INF-01': ['strict-transport-security', /max-age=31536000/i, 'HSTS max-age=31536000; includeSubDomains'],
    'INF-02': ['x-content-type-options', /nosniff/i, 'nosniff'],
    'INF-03': ['x-frame-options', /sameorigin|deny/i, 'SAMEORIGIN'],
    'INF-04': ['referrer-policy', /strict-origin-when-cross-origin/i, 'strict-origin-when-cross-origin'],
    'INF-05': ['permissions-policy', /geolocation/i, 'geolocation=(), camera=(self), microphone=()'],
    'INF-07': ['content-encoding', /br|gzip/i, 'content-encoding: br'],
  };
  for (const [id, [name, re, claimed]] of Object.entries(want)) {
    const got = H[name];
    say(id, got && re.test(got) ? YES : (got ? PART : NO), claimed, `claimed "${claimed}" · header ${name}: ${got || '(absent)'}`);
  }
  say('INF-06', H['content-security-policy'] ? NO : YES, 'no CSP on payment pages',
      'front page CSP: ' + (H['content-security-policy'] || '(absent — as claimed)'));
  say('DEF-14', /jquery-migrate/i.test(r.body) ? YES : NO, 'jQuery Migrate shipped to production',
      'jquery-migrate in the front page html: ' + /jquery-migrate/i.test(r.body));
}

// CSP specifically on checkout, which is the claim that matters
{
  const c = await get('/checkout/');
  say('DEF-06', c.headers['content-security-policy'] ? NO : YES, 'no CSP on /checkout/',
      '/checkout/ HTTP ' + c.status + ' · CSP: ' + (c.headers['content-security-policy'] || '(absent)')
      + ' · cache-control: ' + (c.headers['cache-control'] || '-'));
  say('INF-10', /no-store/i.test(c.headers['cache-control'] || '') ? YES : NO,
      'cart/checkout not publicly cached', 'checkout cache-control: ' + (c.headers['cache-control'] || '(none)'));
}

// ── DEF-04: the product template never enters page cache ──────────────────
console.log('\n— DEF-04 · product template caching (the 2.5s claim) —');
{
  // Find a real product rather than trusting a URL from the report.
  const shop = await get('/shop/');
  const prod = (shop.body.match(/https?:\/\/[^"']*\/product\/[a-z0-9-]+\//i) || [])[0];
  if (!prod) {
    say('DEF-04', NA, 'product pages never cache', 'could not find a product URL on /shop/');
  } else {
    const seen = [];
    for (let i = 0; i < 4; i++) {
      const t0 = Date.now();
      const r = await get(prod);
      seen.push({ ls: r.headers['x-litespeed-cache'] || '-', up: r.headers['x-hcdn-upstream-rt'] || '-', ms: Date.now() - t0, status: r.status });
      console.log('    ' + (i + 1) + '  HTTP ' + r.status + '  ls-cache=' + String(seen[i].ls).padEnd(6)
        + ' upstream=' + String(seen[i].up).padEnd(8) + ' ' + seen[i].ms + 'ms');
    }
    const hits = seen.filter(s => /hit/i.test(s.ls)).length;
    // For comparison, the two templates the report says DO cache.
    const home = await get('/'); const shop2 = await get('/shop/');
    console.log('    /        ls-cache=' + (home.headers['x-litespeed-cache'] || '-')
      + '   /shop/  ls-cache=' + (shop2.headers['x-litespeed-cache'] || '-'));
    say('DEF-04', hits === 0 ? YES : (hits < 3 ? PART : NO),
        'product pages miss every time while / and /shop/ hit',
        prod.replace(SITE, '') + ' → ' + seen.map(s => s.ls).join(',') + ' · hits ' + hits + '/4'
        + ' · home=' + (home.headers['x-litespeed-cache'] || '-'));
  }
}

// ── SEO: H1s, robots, descriptions, duplicates, JSON-LD ───────────────────
console.log('\n— DEF-09 · templates with no H1 —');
{
  const claimed = ['/product-category/home-decor/', '/login/', '/sign-up/', '/wishlist/', '/?s=ganesh&post_type=product', '/cart/'];
  const found = [];
  for (const p of claimed) {
    const r = await get(p);
    const n = h1s(r.body);
    found.push({ p, n, status: r.status });
    console.log('    ' + String(r.status).padEnd(5) + 'h1=' + n + '   ' + p);
  }
  const zero = found.filter(f => f.n === 0 && f.status === 200).length;
  const live = found.filter(f => f.status === 200).length;
  say('DEF-09', zero === live && live > 0 ? YES : (zero > 0 ? PART : NO),
      'six templates render with no H1', zero + ' of ' + live + ' reachable templates have zero h1');
}

console.log('\n— DEF-10 / DEF-15 · robots and meta descriptions —');
{
  for (const p of ['/wishlist/', '/login/', '/sign-up/']) {
    const r = await get(p);
    const rb = robots(r.body);
    say('DEF-10' + p.replace(/\//g, ''), r.status !== 200 ? NA : (/noindex/i.test(rb) ? NO : YES),
        p + ' is indexable', 'HTTP ' + r.status + ' · robots: ' + rb);
  }
  for (const p of ['/blog/', '/wishlist/']) {
    const r = await get(p);
    const d = desc(r.body);
    say('DEF-15' + p.replace(/\//g, ''), r.status !== 200 ? NA : (d === undefined ? YES : NO),
        p + ' has no meta description', 'HTTP ' + r.status + ' · description: ' + (d === undefined ? '(absent)' : '"' + d.slice(0, 60) + '"'));
  }
}

console.log('\n— SEO-11 · duplicate pages still 200 —');
{
  const dupes = ['/all-artists/', '/all-artists-2/', '/cart-2/', '/icons/', '/dashboard/'];
  const live = [];
  for (const p of dupes) {
    const r = await get(p);
    live.push(r.status);
    console.log('    HTTP ' + String(r.status).padEnd(5) + p + '   robots: ' + robots(r.body));
  }
  const n200 = live.filter(s => s === 200).length;
  say('SEO-11', n200 === dupes.length ? YES : (n200 ? PART : NO),
      'all five duplicates return 200', n200 + ' of ' + dupes.length + ' return 200 · ' + live.join(','));
}

console.log('\n— DEF-07 / DEF-08 · product JSON-LD —');
{
  const shop = await get('/shop/');
  const prod = (shop.body.match(/https?:\/\/[^"']*\/product\/[a-z0-9-]+\//i) || [])[0];
  if (!prod) { say('DEF-07', NA, 'schema price', 'no product found'); }
  else {
    const r = await get(prod);
    const blocks = [...r.body.matchAll(/<script[^>]+application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
    let product = null;
    for (const b of blocks) {
      try {
        const j = JSON.parse(b);
        const graph = j['@graph'] || (Array.isArray(j) ? j : [j]);
        for (const node of graph) if (node && /product/i.test(node['@type'] || '')) product = node;
      } catch (e) {}
    }
    if (!product) say('DEF-07', NA, 'schema price', 'no Product node parsed from ' + blocks.length + ' JSON-LD blocks');
    else {
      const offers = product.offers || {};
      const price = offers.price !== undefined ? String(offers.price) : '(none)';
      const isAgg = /AggregateOffer/i.test(offers['@type'] || '');
      // what the page itself offers, from the size map in the html
      const sizePrices = [...r.body.matchAll(/\$\s?(\d{2,4})(?:\.00)?\b/g)].map(m => Number(m[1]));
      const lo = Math.min(...sizePrices), hi = Math.max(...sizePrices);
      say('DEF-07', isAgg ? NO : YES, 'schema publishes one price for a range',
          prod.replace(SITE, '') + ' · offers.@type=' + (offers['@type'] || '-') + ' price=' + price
          + ' · prices seen on page: ' + (sizePrices.length ? lo + '–' + hi : 'none'));
      say('DEF-08', / \| /.test(product.name || '') ? YES : NO, 'schema name carries the site suffix',
          'Product.name = "' + String(product.name || '(none)').slice(0, 80) + '"');
    }
  }
}

// ── DEF-17 · inverted price filter ────────────────────────────────────────
console.log('\n— DEF-17 · inverted price filter —');
{
  const r = await get('/shop/?min_price=500&max_price=10');
  const cards = (r.body.match(/class="[^"]*\bproduct\b[^"]*type-product/gi) || []).length;
  const msg = /no products were found|nothing (matched|hanging)|sorry/i.test(r.body);
  say('DEF-17', r.status === 200 && cards === 0 && !msg ? YES : (cards === 0 ? PART : NO),
      'inverted range gives a blank page with no message',
      'HTTP ' + r.status + ' · product cards: ' + cards + ' · empty-state message: ' + msg);
}

// ── regression rows that are answerable over HTTP ─────────────────────────
console.log('\n— regression spot-checks —');
{
  const s1 = await get('/?s=krishan&post_type=product');
  const c1 = (s1.body.match(/class="[^"]*\bproduct\b[^"]*type-product/gi) || []).length;
  say('M-03', c1 === 0 ? YES : NO, 'krishan returns 0 results', 'krishan → ' + c1 + ' product cards');

  const s2 = await get('/?s=ganesh&post_type=product');
  const c2 = (s2.body.match(/class="[^"]*\bproduct\b[^"]*type-product/gi) || []).length;
  console.log('    (ganesh → ' + c2 + ' cards, claimed 14)');

  const p37 = await get('/shop/page/999/');
  say('PAGE-02', p37.status === 404 ? YES : NO, 'beyond-last page returns 404', '/shop/page/999/ → HTTP ' + p37.status);

  const nf = await get('/this-page-does-not-exist-af-qa/');
  say('INF-15', nf.status === 404 ? YES : NO, 'real 404 with a custom page',
      'HTTP ' + nf.status + ' · "This wall is empty." present: ' + /this wall is empty/i.test(nf.body));

  const users = await get('/wp-json/wp/v2/users');
  say('INF-16', users.status === 404 || users.status === 401 ? YES : NO, 'REST user enumeration blocked',
      '/wp-json/wp/v2/users → HTTP ' + users.status);

  const author = await get('/?author=1');
  say('INF-17', author.status === 404 ? YES : PART, 'author archive blocked', '/?author=1 → HTTP ' + author.status);
}

// ── verdict table ─────────────────────────────────────────────────────────
const tally = rows.reduce((a, r) => (a[r.verdict] = (a[r.verdict] || 0) + 1, a), {});
console.log('\n— tally —');
for (const [k, v] of Object.entries(tally)) console.log('  ' + k.padEnd(14) + v);
console.log('\ndone ' + new Date().toISOString());
