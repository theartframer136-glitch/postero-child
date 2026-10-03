// Prints the latest WordPress and what given plugins' newest versions need
// (WordPress, PHP), from api.wordpress.org. Read-only; the site is not touched.
//
// For each plugin it also reads the newest version's own header from
// plugins.svn.wordpress.org: "WC requires at least" (a WooCommerce extension
// newer than the shop's WooCommerce switches itself off; Square going quiet
// would take the card payment off the checkout), "Elementor requires at
// least", and "Requires Plugins".
//
// Run: node tools/print-wp-versions.mjs [slug ...]
// qa-personas.yml passes the site address as the first argument: only plugin
// slugs are taken from the command line.
const args = process.argv.slice(2).filter(a => !a.includes('://'));
const slugs = args.length ? args : (process.env.AF_QA_ONLY || 'woocommerce,elementor,elementor-pro').split(',');
const core = await fetch('https://api.wordpress.org/core/version-check/1.7/').then(r => r.json()).catch(() => null);
if (core) console.log('WordPress offers: ' + core.offers.map(o => o.current + ' (' + o.response + ', needs PHP ' + o.php_version + ')').join('; '));
const HEADERS = ['Requires at least', 'Requires PHP', 'WC requires at least', 'WC tested up to', 'Elementor requires at least', 'Elementor tested up to', 'Requires Plugins'];
const text = u => fetch(u).then(r => r.ok ? r.text() : '').catch(() => '');
async function header(slug, version) {
  for (const base of ['https://plugins.svn.wordpress.org/' + slug + '/tags/' + version + '/', 'https://plugins.svn.wordpress.org/' + slug + '/trunk/']) {
    const list = await text(base);
    const php = [...list.matchAll(/href="([^"/?]+\.php)"/g)].map(m => m[1]);
    php.sort((a, b) => (b.startsWith(slug) ? 1 : 0) - (a.startsWith(slug) ? 1 : 0));
    for (const f of php.slice(0, 12)) {
      const top = (await text(base + f)).slice(0, 8192);
      if (!/Plugin Name:/i.test(top)) continue;
      const got = HEADERS.map(h => { const m = top.match(new RegExp('^[\\s*#@]*' + h + '\\s*:\\s*(.+)$', 'im')); return m ? h + ' ' + m[1].trim() : ''; }).filter(Boolean);
      return f + (base.endsWith('/trunk/') ? ' (trunk)' : '') + ': ' + (got.join('; ') || '(none of the headers)');
    }
  }
  return '(main file not found)';
}
for (const s of slugs) {
  const u = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' + encodeURIComponent(s.trim());
  const d = await fetch(u).then(r => r.json()).catch(e => ({ error: String(e) }));
  console.log(s.trim() + ': ' + (d.error ? d.error : 'version ' + d.version + ', requires WordPress ' + d.requires + ', requires PHP ' + d.requires_php + ', tested up to ' + d.tested + ', updated ' + d.last_updated));
  if (!d.error && d.version) console.log('    ' + await header(s.trim(), d.version));
}
