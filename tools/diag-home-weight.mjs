/**
 * A clean homepage weight measurement, with every request grouped by host, so
 * "30 MB" is attributed rather than asserted. Run twice: cold then warm.
 */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
for (const pass of ['cold', 'warm']) {
  const p = await b.newPage();
  await p.setViewport({ width: 1400, height: 1000 });
  await p.setCacheEnabled(pass === 'warm');
  const reqs = [];
  p.on('response', async r => {
    try {
      const len = parseInt(r.headers()['content-length'] || '0', 10);
      reqs.push({ host: new URL(r.url()).host, type: r.request().resourceType(), bytes: len, url: r.url() });
    } catch {}
  });
  const resp = await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 90000 });
  await new Promise(r => setTimeout(r, 5000));
  const blocked = await p.evaluate(() => document.body.innerText.includes('Checking your browser'));
  const total = reqs.reduce((a, r) => a + r.bytes, 0);
  console.log(`\n=== ${pass.toUpperCase()}  HTTP ${resp.status()}  ${blocked ? 'BOT-CHECK PAGE — figures meaningless' : 'real page'} ===`);
  console.log(`  ${reqs.length} requests, ${(total / 1048576).toFixed(2)} MB with a declared length`);
  const byHost = {};
  for (const r of reqs) { byHost[r.host] = byHost[r.host] || { n: 0, b: 0 }; byHost[r.host].n++; byHost[r.host].b += r.bytes; }
  Object.entries(byHost).sort((a, c) => c[1].b - a[1].b).slice(0, 8)
    .forEach(([h, v]) => console.log(`     ${(v.b / 1048576).toFixed(2).padStart(7)} MB  ${String(v.n).padStart(4)} req  ${h}`));
  const vids = await p.evaluate(() => {
    const v = [...document.querySelectorAll('video')];
    return { total: v.length, none: v.filter(x => x.getAttribute('preload') === 'none').length,
             other: v.filter(x => x.getAttribute('preload') !== 'none').map(x => (x.className || x.id || 'unnamed') + ':' + (x.getAttribute('preload') || 'unset')).slice(0, 6) };
  });
  console.log(`  <video>: ${vids.total} total, ${vids.none} preload=none`);
  if (vids.other.length) console.log(`     not covered: ${vids.other.join(', ')}`);
  await p.close();
}
await b.close();
