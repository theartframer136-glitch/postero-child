/**
 * The empty hrefs are NOT in post_content and NOT in _elementor_data — both
 * read zero on the server. Yet the live pages serve them. So something renders
 * them at output time. Show the surrounding markup so the owner is named.
 */
const PAGES = ['https://theartframer.us/login/', 'https://theartframer.us/sign-up/'];
for (const url of PAGES) {
  const html = await (await fetch(url, { headers: { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/124 Safari/537.36' } })).text();
  if (html.includes('Checking your browser')) { console.log(`${url}: BLOCKED by bot check`); continue; }
  const re = /<a\b[^>]*href=(["'])\1[^>]*>[\s\S]{0,120}?<\/a>/gi;
  const hits = html.match(re) || [];
  console.log(`\n=== ${url}  (${html.length} bytes, ${hits.length} anchors with an empty href) ===`);
  hits.slice(0, 6).forEach((h, i) => {
    const idx = html.indexOf(h);
    const before = html.slice(Math.max(0, idx - 300), idx).replace(/\s+/g, ' ');
    // nearest enclosing element with a class, to name the renderer
    const cls = [...before.matchAll(/class="([^"]{4,90})"/g)].map(m => m[1]).slice(-2);
    console.log(`\n  [${i + 1}] ${h.replace(/\s+/g, ' ').slice(0, 190)}`);
    console.log(`      nearest classes before it: ${cls.join('  |  ') || '(none)'}`);
  });
}
