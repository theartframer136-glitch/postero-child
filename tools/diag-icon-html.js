/** Compare the rendered markup of a working category row and the new one. */
const puppeteer = require('puppeteer-core');
(async () => {
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage'] });
  const p = await b.newPage();
  await p.setViewport({ width: 1400, height: 900 });
  await p.goto('https://theartframer.us/', { waitUntil: 'networkidle2', timeout: 60000 });
  for (let i = 0; i < 10; i++) {
    const ch = await p.evaluate(() => document.body.innerText.includes('Checking your browser'));
    if (!ch) break;
    await new Promise(r => setTimeout(r, 2500));
    try { await p.reload({ waitUntil: 'networkidle2', timeout: 45000 }); } catch {}
  }
  const out = await p.evaluate(() => {
    const all = (name) => {
      const as = [...document.querySelectorAll('a')].filter(x =>
        x.textContent.trim().replace(/\s+/g,' ').startsWith(name));
      return as.map(a => {
        const li = a.closest('li');
        const box = li || a;
        const r = box.getBoundingClientRect();
        return {
          liClass: li ? li.className : '(no li)',
          visible: r.width > 0 && r.height > 0,
          ancestors: (() => { const out = []; let n = box.parentElement;
            for (let i = 0; i < 4 && n; i++, n = n.parentElement)
              out.push(n.tagName.toLowerCase() + (n.className ? '.' + String(n.className).split(/\s+/).slice(0,3).join('.') : ''));
            return out.join(' < '); })(),
          html: box.outerHTML.replace(/\s+/g,' ').slice(0, 400),
        };
      });
    };
    const o = {};
    for (const n of ['Banners & Signage', 'Corporate Printing', 'Gold Foiled & UV']) o[n] = all(n);
    return o;
  });
  for (const name of Object.keys(out)) {
    console.log(`\n================ ${name}  (${out[name].length} matches) ================`);
    out[name].forEach((r, i) => {
      console.log(`  --- match ${i+1}  visible=${r.visible}`);
      console.log(`      li class : ${r.liClass}`);
      console.log(`      in       : ${r.ancestors}`);
      console.log(`      html     : ${r.html}`);
    });
  }
  await b.close();
})();
