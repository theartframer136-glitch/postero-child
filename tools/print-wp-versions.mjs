// Prints the latest WordPress and what given plugins' newest versions need
// (WordPress, PHP), from api.wordpress.org. Read-only; the site is not touched.
//
// Run: node tools/print-wp-versions.mjs [slug ...]
const slugs = process.argv.slice(2).length ? process.argv.slice(2) : (process.env.AF_QA_ONLY || 'woocommerce,elementor,elementor-pro').split(',');
const core = await fetch('https://api.wordpress.org/core/version-check/1.7/').then(r => r.json()).catch(() => null);
if (core) console.log('WordPress offers: ' + core.offers.map(o => o.current + ' (' + o.response + ', needs PHP ' + o.php_version + ')').join('; '));
for (const s of slugs) {
  const u = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' + encodeURIComponent(s.trim());
  const d = await fetch(u).then(r => r.json()).catch(e => ({ error: String(e) }));
  console.log(s.trim() + ': ' + (d.error ? d.error : 'version ' + d.version + ', requires WordPress ' + d.requires + ', requires PHP ' + d.requires_php + ', tested up to ' + d.tested + ', updated ' + d.last_updated));
}
