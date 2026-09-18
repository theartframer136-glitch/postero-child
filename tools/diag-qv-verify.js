const puppeteer = require('puppeteer-core');
(async () => {
  const b = await puppeteer.launch({ channel:'chrome', headless:'new', args:['--no-sandbox','--disable-dev-shm-usage'] });
  const p = await b.newPage();
  await p.setViewport({ width:1400, height:950 });
  await p.goto('https://theartframer.us/product-category/digital-canvas-prints/', { waitUntil:'networkidle2', timeout:60000 });
  for (let i=0;i<10;i++){ if(!(await p.evaluate(()=>document.body.innerText.includes('Checking your browser')))) break;
    await new Promise(r=>setTimeout(r,2500)); try{await p.reload({waitUntil:'networkidle2',timeout:45000});}catch{} }
  await new Promise(r=>setTimeout(r,3000));
  const before = await p.evaluate(()=>({
    dual: document.querySelectorAll('.woosq-btn.quick-view-btn').length,
    woosqOnly: document.querySelectorAll('.woosq-btn:not(.quick-view-btn)').length,
  }));
  await p.evaluate(()=>{ const b=document.querySelector('.woosq-btn'); if(b) b.click(); });
  await new Promise(r=>setTimeout(r,8000));
  const after = await p.evaluate(()=>{
    const good = document.getElementById('quickViewModal');
    const gs = good ? getComputedStyle(good) : null;
    const plugin = document.querySelector('.mfp-woosq, .mfp-bg');
    const ps = plugin ? getComputedStyle(plugin) : null;
    const inner = good ? good.querySelector('#quickViewContent') : null;
    return {
      goodOpen: !!(gs && gs.display !== 'none' && +gs.opacity > 0),
      pluginOpen: !!(ps && ps.display !== 'none'),
      contentChars: inner ? inner.textContent.trim().length : 0,
      contentStarts: inner ? inner.textContent.trim().slice(0,60) : null,
    };
  });
  console.log('buttons carrying BOTH classes :', before.dual);
  console.log('buttons still plugin-only     :', before.woosqOnly);
  console.log('theme modal opened            :', after.goodOpen);
  console.log('plugin popup opened           :', after.pluginOpen, after.pluginOpen ? '  <-- still hijacking' : '');
  console.log('content loaded (chars)        :', after.contentChars);
  console.log('content begins                :', after.contentStarts);
  await b.close();
})();
