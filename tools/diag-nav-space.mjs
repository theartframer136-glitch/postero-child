// Measure the real header nav: how much free space is left on the row, and how
// wide the Admin Console item is at nav font size. Admin item is logged-in only,
// so measure the nav as served and simulate the item's width.
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
  const p = await (await b.newContext({ viewport: { width: 1920, height: 1080 } })).newPage();
  await p.goto('https://theartframer.us/?nm=' + Date.now(), { waitUntil: 'load', timeout: 120000 });
  await p.waitForTimeout(7000);
  const r = await p.evaluate(() => {
    // the nav list holding BLOG
    const links = Array.from(document.querySelectorAll('ul > li > a'));
    const blog = links.find(a => /^\s*blog\s*$/i.test(a.textContent || ''));
    if (!blog) return { error: 'no BLOG link found' };
    const ul = blog.closest('ul');
    const li = blog.closest('li');
    const cs = getComputedStyle(ul);
    const lics = getComputedStyle(li);
    const ulr = ul.getBoundingClientRect();
    const items = Array.from(ul.children).map(c => { const q = c.getBoundingClientRect(); return { t: (c.innerText||'').trim().slice(0,22), x: Math.round(q.x), r: Math.round(q.right), w: Math.round(q.width) }; });
    const last = items[items.length - 1];
    // simulate the injected item at the SAME styling the theme gives its lis
    const probe = document.createElement('li');
    probe.className = li.className;
    probe.innerHTML = '<a href="#">Admin Console</a>';
    ul.appendChild(probe);
    const pw = Math.round(probe.getBoundingClientRect().width);
    probe.remove();
    return {
      ulDisplay: cs.display, ulFlexWrap: cs.flexWrap, ulWidth: Math.round(ulr.width),
      ulRight: Math.round(ulr.right), ulGap: cs.gap, liDisplay: lics.display, liClass: li.className.slice(0,60),
      parentOfUl: ul.parentElement ? ul.parentElement.tagName + '.' + (ul.parentElement.className||'').toString().slice(0,50) : '',
      parentWidth: ul.parentElement ? Math.round(ul.parentElement.getBoundingClientRect().width) : 0,
      items, lastRight: last.r, freeSpaceOnRow: Math.round(ulr.right - last.r),
      injectedItemWidth: pw,
    };
  });
  console.log(JSON.stringify(r, null, 1));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
