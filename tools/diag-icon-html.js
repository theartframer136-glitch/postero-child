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
    const find = (name) => {
      const as = [...document.querySelectorAll('a')];
      const a = as.find(x => x.textContent.trim().replace(/\s+/g,' ') === name ||
                             x.textContent.trim().startsWith(name));
      if (!a) return { name, found: false };
      const li = a.closest('li');
      return { name, found: true,
        liClass: li ? li.className : '',
        html: (li ? li.innerHTML : a.outerHTML).replace(/\s+/g,' ').slice(0, 340) };
    };
    return ['Banners & Signage', 'Corporate Printing', 'Gold Foiled & UV'].map(find);
  });
  for (const r of out) {
    console.log(`\n=== ${r.name} ===`);
    if (!r.found) { console.log('  NOT FOUND in the page'); continue; }
    console.log(`  li class: ${r.liClass}`);
    console.log(`  html    : ${r.html}`);
  }
  await b.close();
})();
