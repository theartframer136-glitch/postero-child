// Prints Cloudflare's published IP ranges (https://www.cloudflare.com/ips-v4
// and ips-v6), the addresses its proxy connects to a site from. Read-only;
// nothing on the site is touched.
//
// Run: node tools/print-cloudflare-ips.mjs
for (const v of ['ips-v4', 'ips-v6']) {
  const r = await fetch('https://www.cloudflare.com/' + v).catch(e => ({ ok: false, status: String(e) }));
  const t = r.ok ? (await r.text()).trim() : '';
  console.log('== ' + v + ' (HTTP ' + r.status + ')');
  console.log(t);
}
const api = await fetch('https://api.cloudflare.com/client/v4/ips').then(r => r.json()).catch(() => null);
if (api && api.result) {
  console.log('== api etag ' + api.result.etag);
  console.log('v4 ' + api.result.ipv4_cidrs.join(' '));
  console.log('v6 ' + api.result.ipv6_cidrs.join(' '));
}
