/** Where on the rendered homepage does localhost/wordpress/postero appear, and is it inside a <script>? */
const html = await (await fetch('https://theartframer.us/', { headers: { 'User-Agent': 'Mozilla/5.0 AF-Audit' } })).text();
if (html.includes('Checking your browser')) { console.log('BLOCKED by bot check'); process.exit(0); }
const needle = /localhost\\?\/wordpress\\?\/postero/g;
let m, n = 0;
// index script blocks so each hit can be classified
const scripts = []; const sre = /<script\b[^>]*>[\s\S]*?<\/script>/gi; let sm;
while ((sm = sre.exec(html))) scripts.push([sm.index, sm.index + sm[0].length, sm[0].slice(0, 80)]);
while ((m = needle.exec(html))) {
  n++;
  const i = m.index;
  const inScript = scripts.find(([a, b]) => i >= a && i < b);
  const ctx = html.slice(Math.max(0, i - 160), i + 120).replace(/\s+/g, ' ');
  console.log(`\n#${n} at byte ${i}  ${inScript ? 'INSIDE <script> (' + inScript[2].replace(/\s+/g,' ') + ')' : 'in page markup'}`);
  console.log('   ' + ctx);
}
console.log(`\n${n} occurrence(s) in the homepage HTML (${html.length} bytes)`);
