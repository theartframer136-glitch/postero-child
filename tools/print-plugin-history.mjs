// Recent releases of given plugins and what each needs (WordPress, PHP,
// WooCommerce), from wordpress.org. Read-only; the site is not touched.
//
// Owner, 3 Oct: "update all". WooCommerce 11.1.2 needs WordPress 7.0 and the
// host keeps the site on 6.9.9 for now; an earlier release may still run on
// 6.9.9 and bring WooCommerce far enough for Square 5.5.1 ("WC requires at
// least: 10.9"). This lists the newest releases with their requirements.
//
// Run: node tools/print-plugin-history.mjs [slug ...]   (or AF_QA_ONLY=slug,slug)
const args = process.argv.slice(2).filter(a => !a.includes('://'));
const slugs = args.length ? args : (process.env.AF_QA_ONLY || 'woocommerce').split(',');
const HEADERS = ['Requires at least', 'Requires PHP', 'WC requires at least', 'WC tested up to'];
const text = u => fetch(u).then(r => r.ok ? r.text() : '').catch(() => '');
const cmp = (a, b) => { const x = a.split('.').map(Number), y = b.split('.').map(Number); for (let i = 0; i < Math.max(x.length, y.length); i++) { const d = (x[i] || 0) - (y[i] || 0); if (d) return d; } return 0; };
for (const raw of slugs) {
  const slug = raw.trim();
  const d = await fetch('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' + encodeURIComponent(slug) + '&request[fields][versions]=1')
    .then(r => r.json()).catch(e => ({ error: String(e) }));
  if (d.error) { console.log(slug + ': ' + d.error); continue; }
  const stable = Object.keys(d.versions || {}).filter(v => /^\d+(\.\d+)+$/.test(v)).sort(cmp).reverse();
  console.log('\n' + slug + ': latest ' + d.version + '; newest releases and what they need:');
  // The newest release of each minor line, then the rest, up to 14.
  const seen = new Set(), pick = [];
  for (const v of stable) { const line = v.split('.').slice(0, 2).join('.'); if (!seen.has(line)) { seen.add(line); pick.push(v); } if (pick.length >= 14) break; }
  const main = slug === 'woocommerce' ? 'woocommerce.php' : null;
  for (const v of pick) {
    let head = '';
    const files = main ? [main] : [...(await text('https://plugins.svn.wordpress.org/' + slug + '/tags/' + v + '/')).matchAll(/href="([^"/?]+\.php)"/g)].map(m => m[1]);
    for (const f of files.slice(0, 10)) { const t = (await text('https://plugins.svn.wordpress.org/' + slug + '/tags/' + v + '/' + f)).slice(0, 8192); if (/Plugin Name:/i.test(t)) { head = t; break; } }
    const got = HEADERS.map(h => { const m = head.match(new RegExp('^[\\s*#@]*' + h + '\\s*:\\s*(.+)$', 'im')); return m ? h.replace('Requires at least', 'WP').replace('Requires PHP', 'PHP').replace('WC requires at least', 'needs WC').replace('WC tested up to', 'WC tested') + ' ' + m[1].trim() : ''; }).filter(Boolean);
    console.log('  ' + v.padEnd(10) + (got.join('; ') || '(header not read)'));
  }
}
