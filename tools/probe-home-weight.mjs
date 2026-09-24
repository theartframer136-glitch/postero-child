// M-09: where does the homepage's weight come from?
//
// Test Run 03, M-09 (went backwards), homepage:
//   DOM nodes             8,060 → 9,713
//   links and buttons     1,250 → 1,538
//   href="#" dead links      83 → 119
//   tap targets <44px       347 → 516
//   scripts                 205 → 205
//   stylesheets             162 → 162
//
// The totals are measured the way tools/verify-audit.mjs measures them
// (1440 × 900, a first-time visitor). They are then split by section, so each
// count has an owner: the header, each band of the page, the footer, and
// whatever sits hidden at the end of <body> (menus, pop-ups, modals).
//
// For each section: nodes, links and buttons, href="#", targets under 44 px,
// nodes that are not displayed at all, product cards, and the first heading.
// Then the href="#" links and the small targets grouped by what they are, and
// the scripts and stylesheets grouped by plugin.
//
// Read-only. Nothing goes in a cart.
//
// Run: node tools/probe-home-weight.mjs [url]

import { chromium } from 'playwright';

const SITE = (process.argv[2] || process.env.AF_QA_URL || 'https://theartframer.us').replace(/\/$/, '');
const browser = await chromium.launch({ headless: true });

const measure = () => {
  const t = s => String(s || '').replace(/\s+/g, ' ').trim();
  const all = [...document.getElementsByTagName('*')];
  const shown = el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
  const tiny = el => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0 && (r.width < 44 || r.height < 44); };
  const label = el => {
    const id = el.id ? '#' + el.id : '';
    const cls = [...el.classList].filter(c => !/^elementor-(element|widget|column|section|top-section|inner-section|col-|repeater)|^e-(con|flex|parent|child)$|^e-lazyloaded$/.test(c)).slice(0, 3).join('.');
    const di = el.getAttribute('data-id') ? '[' + el.getAttribute('data-id') + ']' : '';
    const wt = el.getAttribute('data-widget_type') ? '{' + el.getAttribute('data-widget_type') + '}' : '';
    return (el.tagName.toLowerCase() + id + (cls ? '.' + cls : '') + di + wt).slice(0, 90);
  };
  const size = el => el.getElementsByTagName('*').length + 1;

  // Split the page into sections: go down while one child holds most of the
  // nodes, then take the children of the element where no child does.
  const blocks = [];
  const split = (el, depth) => {
    const kids = [...el.children].filter(k => !/^(SCRIPT|STYLE|NOSCRIPT|LINK|META|TEMPLATE)$/.test(k.tagName));
    const n = size(el);
    const big = kids.find(k => size(k) > n * 0.6);
    if (big && depth < 14) {
      for (const k of kids) if (k !== big && size(k) >= 30) blocks.push(k);
      return split(big, depth + 1);
    }
    for (const k of kids) {
      if (size(k) > 1500 && depth < 14) split(k, depth + 1);
      else if (size(k) >= 30) blocks.push(k);
    }
  };
  split(document.body, 0);

  const hiddenRoots = all.filter(el => el.nodeType === 1 && getComputedStyle(el).display === 'none' && !/^(SCRIPT|STYLE|NOSCRIPT|LINK|META|TEMPLATE|HEAD|TITLE)$/.test(el.tagName));
  const hiddenSet = new Set();
  for (const h of hiddenRoots) { hiddenSet.add(h); for (const d of h.getElementsByTagName('*')) hiddenSet.add(d); }

  const rows = blocks.map(b => {
    const els = [b, ...b.getElementsByTagName('*')];
    const r = b.getBoundingClientRect();
    return {
      label: label(b),
      top: Math.round(r.top + scrollY), height: Math.round(r.height),
      nodes: els.length,
      links: els.filter(e => e.matches('a,button')).length,
      hash: els.filter(e => e.matches('a[href="#"]')).length,
      tiny: els.filter(e => e.matches('a,button,[role="button"]') && tiny(e)).length,
      hidden: els.filter(e => hiddenSet.has(e)).length,
      cards: b.querySelectorAll('li.product, .product-card, .af-card, [data-product_id]').length,
      imgs: b.querySelectorAll('img').length,
      head: t((b.querySelector('h1,h2,h3,h4,.elementor-heading-title') || {}).textContent).slice(0, 40),
      hiddenOn: [...b.classList].filter(c => c.startsWith('elementor-hidden-')).map(c => c.slice(17)).join(' '),
    };
  });
  const covered = new Set(); for (const b of blocks) { covered.add(b); for (const d of b.getElementsByTagName('*')) covered.add(d); }

  const group = (els, key, ex) => {
    const m = new Map();
    for (const e of els) { const k = key(e); if (!m.has(k)) m.set(k, [0, ex ? ex(e) : '']); m.get(k)[0]++; }
    return [...m.entries()].sort((a, b) => b[1][0] - a[1][0]).slice(0, 25).map(([k, [n, x]]) => [k + (x ? '   e.g. ' + x : ''), n]);
  };
  const owner = e => { const b = blocks.find(x => x.contains(e)); return b ? label(b).slice(0, 44) : '(outside)'; };
  const hashEls = [...document.querySelectorAll('a[href="#"]')];
  const tinyEls = [...document.querySelectorAll('a,button,[role="button"]')].filter(tiny);
  const what = e => {
    const cls = [...e.classList].slice(0, 2).join('.') || (e.parentElement ? 'in ' + [...e.parentElement.classList].slice(0, 2).join('.') : '');
    return e.tagName.toLowerCase() + '.' + cls;
  };
  const eg = e => { const r = e.getBoundingClientRect(); return '"' + t(e.textContent || e.getAttribute('aria-label') || e.title).slice(0, 22) + '" ' + Math.round(r.width) + '×' + Math.round(r.height); };
  const src = s => {
    try {
      const u = new URL(s, location.href);
      const m = u.pathname.match(/\/wp-content\/(plugins|themes)\/([^/]+)/) || u.pathname.match(/\/wp-(includes)\//);
      return u.host === location.host ? (m ? m[1] + '/' + (m[2] || '') : u.pathname.split('/').slice(0, 3).join('/')) : u.host;
    } catch { return '?'; }
  };
  // Elementor elements hidden at every active width: the desktop plus each
  // breakpoint this site has switched on. Outermost ones only.
  const cfg = (window.elementorFrontendConfig && elementorFrontendConfig.responsive) || {};
  const active = ['desktop'].concat(Object.keys(cfg.activeBreakpoints || {}));
  const everywhere = [...document.querySelectorAll('[class*="elementor-hidden-"]')].filter(el =>
    active.every(d => el.classList.contains('elementor-hidden-' + d)));
  const outer = everywhere.filter(el => !everywhere.some(o => o !== el && o.contains(el)));
  const hiddenAll = outer.map(el => {
    const doc = el.closest('[data-elementor-type]');
    return {
      id: el.getAttribute('data-id') || el.id || '?',
      type: el.getAttribute('data-element_type') || el.tagName.toLowerCase(),
      widget: el.getAttribute('data-widget_type') || '',
      in: doc ? doc.getAttribute('data-elementor-type') + ' ' + doc.getAttribute('data-elementor-id') : '(no document)',
      nodes: size(el), links: el.querySelectorAll('a,button').length, imgs: el.querySelectorAll('img').length,
      head: t((el.querySelector('h1,h2,h3,h4,.elementor-heading-title') || {}).textContent || el.textContent).slice(0, 40),
    };
  });

  return {
    active, hiddenAll,
    totals: {
      html: document.documentElement.outerHTML.length,
      nodes: all.length,
      links: document.querySelectorAll('a,button').length,
      hash: hashEls.length,
      tiny: tinyEls.length,
      scripts: document.querySelectorAll('script').length,
      scriptsExt: document.querySelectorAll('script[src]').length,
      sheets: document.querySelectorAll('link[rel="stylesheet"]').length,
      styles: document.querySelectorAll('style').length,
      imgs: document.images.length,
      hidden: hiddenSet.size,
      shownLinks: [...document.querySelectorAll('a,button')].filter(shown).length,
      uncovered: all.filter(e => !covered.has(e) && document.body.contains(e)).length,
      pageHeight: document.documentElement.scrollHeight,
    },
    rows,
    hashBy: group(hashEls, e => owner(e) + '  ' + what(e), eg),
    tinyBy: group(tinyEls, e => owner(e) + '  ' + what(e), eg),
    hiddenBy: group(hiddenRoots.filter(h => !hiddenRoots.some(o => o !== h && o.contains(h))), h => owner(h) + '  ' + label(h) + ' (' + size(h) + ' nodes)'),
    scriptsBy: group([...document.querySelectorAll('script')], s => s.src ? src(s.src) : 'inline ' + (s.id || s.type || '')),
    sheetsBy: group([...document.querySelectorAll('link[rel="stylesheet"]')], l => src(l.href)),
  };
};

const run = async (name, viewport, scroll) => {
  const ctx = await browser.newContext({ viewport, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  const ajax = [];
  page.on('response', async res => {
    const req = res.request();
    if (req.method() !== 'POST' || !/admin-ajax\.php/.test(res.url())) return;
    const body = req.postData() || '';
    const action = (body.match(/(?:^|&)action=([^&]+)/) || [])[1] || '?';
    let cards = '?';
    try { const txt = await res.text(); cards = (txt.match(/class="product-card/g) || []).length; } catch {}
    ajax.push(action + ' ' + decodeURIComponent(body.replace(/(^|&)action=[^&]*/, '')).slice(0, 60) + ' → ' + cards + ' cards');
  });
  const r = await page.goto(SITE + '/', { waitUntil: 'load', timeout: 90000 }).catch(e => { console.log('  no answer: ' + e.message); return null; });
  if (!r) { await ctx.close(); return null; }
  const raw = await r.text().catch(() => '');
  await page.waitForTimeout(4000);
  if (scroll) {
    await page.evaluate(async () => { for (let y = 0; y < document.documentElement.scrollHeight; y += 700) { scrollTo(0, y); await new Promise(r => setTimeout(r, 180)); } scrollTo(0, 0); });
    await page.waitForTimeout(3000);
  }
  const d = await page.evaluate(measure);
  await ctx.close();
  const T = d.totals;
  T.rawCards = (raw.match(/class="product-card/g) || []).length;
  console.log(`\n=== ${name} · HTTP ${r.status()} · ${Math.round(T.html / 1024)} KB of DOM as HTML · page ${T.pageHeight}px tall ===`);
  console.log(`  nodes ${T.nodes} · links+buttons ${T.links} (${T.shownLinks} shown) · href="#" ${T.hash} · under 44px ${T.tiny} · scripts ${T.scripts} (${T.scriptsExt} files) · stylesheets ${T.sheets} + ${T.styles} <style> · images ${T.imgs} · nodes not displayed ${T.hidden} · not in any section ${T.uncovered}`);
  console.log(`  product cards in the HTML as served ${T.rawCards} · admin-ajax calls: ${ajax.length ? ajax.join(' | ') : 'none'}`);
  console.log(`  Elementor widths switched on: ${d.active.join(', ')}`);
  console.log('  hidden at every one of them:');
  for (const h of d.hiddenAll) console.log('    ' + (h.type + (h.widget ? ' ' + h.widget : '')).padEnd(34) + (' id ' + h.id).padEnd(13) + ' in ' + h.in.padEnd(22) + String(h.nodes).padStart(6) + ' nodes' + String(h.links).padStart(5) + ' links' + String(h.imgs).padStart(4) + ' imgs  ' + h.head);
  console.log('\n  section'.padEnd(64) + '   top  nodes links  #  <44 hidden cards imgs  heading');
  const merged = [];
  for (const x of d.rows) {
    const last = merged[merged.length - 1];
    if (last && last.label === x.label && x.label.indexOf('[') < 0) {
      last.n++; for (const k of ['nodes', 'links', 'hash', 'tiny', 'hidden', 'cards', 'imgs']) last[k] += x[k];
    } else merged.push({ ...x, n: 1 });
  }
  for (const x of merged) x.label = (x.n > 1 ? x.n + ' × ' : '') + x.label;
  for (const x of merged) console.log('  ' + x.label.padEnd(60).slice(0, 60) + String(x.top).padStart(7) + String(x.nodes).padStart(7) + String(x.links).padStart(6) + String(x.hash).padStart(4) + String(x.tiny).padStart(5) + String(x.hidden).padStart(7) + String(x.cards).padStart(6) + String(x.imgs).padStart(5) + '  ' + x.head + (x.hiddenOn ? '   [hidden on: ' + x.hiddenOn + ']' : ''));
  if (name.startsWith('desktop, as loaded') || name.startsWith('phone')) {
    console.log('\n  href="#" by owner and kind:');
    for (const [k, n] of d.hashBy) console.log('    ' + String(n).padStart(4) + '  ' + k);
    console.log('\n  under 44px by owner and kind:');
    for (const [k, n] of d.tinyBy) console.log('    ' + String(n).padStart(4) + '  ' + k);
    console.log('\n  subtrees not displayed (display:none), largest owners:');
    for (const [k, n] of d.hiddenBy) console.log('    ' + String(n).padStart(4) + '  ' + k);
  }
  if (name.startsWith('desktop, as loaded')) {
    console.log('\n  scripts by source:');
    for (const [k, n] of d.scriptsBy) console.log('    ' + String(n).padStart(4) + '  ' + k);
    console.log('\n  stylesheets by source:');
    for (const [k, n] of d.sheetsBy) console.log('    ' + String(n).padStart(4) + '  ' + k);
  }
  return d;
};

console.log('probe-home-weight: ' + SITE + '   ' + new Date().toISOString());
await run('desktop, as loaded (1440×900, how run 03 counted)', { width: 1440, height: 900 }, false);
await run('desktop, after scrolling to the end', { width: 1440, height: 900 }, true);
await run('tablet, as loaded (1024×768)', { width: 1024, height: 768 }, false);
await run('phone, as loaded (375×812)', { width: 375, height: 812 }, false);
console.log('\ndone ' + new Date().toISOString());
await browser.close();
