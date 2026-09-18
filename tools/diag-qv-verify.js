/** Why did the theme's modal not open after the click? */
const puppeteer = require('puppeteer-core');
(async () => {
  const b = await puppeteer.launch({ channel:'chrome', headless:'new', args:['--no-sandbox','--disable-dev-shm-usage'] });
  const p = await b.newPage();
  const errs = [];
  p.on('pageerror', e => errs.push('pageerror: ' + String(e.message).slice(0,120)));
  p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0,120)); });
  await p.setViewport({ width:1400, height:950 });
  await p.goto('https://theartframer.us/product-category/digital-canvas-prints/', { waitUntil:'networkidle2', timeout:60000 });
  for (let i=0;i<10;i++){ if(!(await p.evaluate(()=>document.body.innerText.includes('Checking your browser')))) break;
    await new Promise(r=>setTimeout(r,2500)); try{await p.reload({waitUntil:'networkidle2',timeout:45000});}catch{} }
  await new Promise(r=>setTimeout(r,3000));

  const pre = await p.evaluate(()=>{
    const m = document.getElementById('quickViewModal');
    const cs = m ? getComputedStyle(m) : null;
    return {
      jq: typeof window.jQuery,
      dual: document.querySelectorAll('.woosq-btn.quick-view-btn').length,
      modalDisplay: cs ? cs.display : null,
      modalInline: m ? (m.getAttribute('style') || '(none)') : null,
      // does any rule force it hidden with !important?
      hiddenImportant: (() => {
        if (!m) return null;
        for (const sheet of document.styleSheets) {
          let rules; try { rules = sheet.cssRules; } catch { continue; }
          for (const r of rules || []) {
            if (!r.selectorText || !r.style) continue;
            if (!/quickViewModal|quick-view-modal/.test(r.selectorText)) continue;
            if (r.style.getPropertyPriority('display') === 'important')
              return r.selectorText + ' { display:' + r.style.display + ' !important }';
          }
        }
        return false;
      })(),
    };
  });

  // Click and watch what the handler does to the element.
  await p.evaluate(()=>{ const btn = document.querySelector('.woosq-btn.quick-view-btn'); if (btn) btn.click(); });
  await new Promise(r=>setTimeout(r,9000));

  const post = await p.evaluate(()=>{
    const m = document.getElementById('quickViewModal');
    const c = document.getElementById('quickViewContent');
    const cs = m ? getComputedStyle(m) : null;
    const plugin = document.querySelector('.mfp-woosq');
    return {
      modalDisplay: cs ? cs.display : null,
      modalOpacity: cs ? cs.opacity : null,
      modalZ: cs ? cs.zIndex : null,
      modalInline: m ? (m.getAttribute('style') || '(none)') : null,
      modalBox: m ? (() => { const r=m.getBoundingClientRect(); return Math.round(r.width)+'x'+Math.round(r.height); })() : null,
      bodyClass: /modal-open/.test(document.body.className) ? 'modal-open present' : 'modal-open ABSENT',
      contentChars: c ? c.textContent.trim().length : 0,
      contentStarts: c ? c.textContent.trim().slice(0,70) : null,
      pluginPresent: !!plugin,
    };
  });

  console.log('=== BEFORE CLICK ===');
  console.log('  jQuery                :', pre.jq);
  console.log('  buttons with both     :', pre.dual);
  console.log('  #quickViewModal display:', pre.modalDisplay, ' inline:', pre.modalInline);
  console.log('  forced hidden by CSS  :', pre.hiddenImportant);
  console.log('\n=== AFTER CLICK ===');
  console.log('  display               :', post.modalDisplay, ' opacity:', post.modalOpacity, ' z:', post.modalZ);
  console.log('  inline style          :', post.modalInline);
  console.log('  rendered box          :', post.modalBox);
  console.log('  body                  :', post.bodyClass);
  console.log('  content chars         :', post.contentChars);
  console.log('  content begins        :', post.contentStarts);
  console.log('  plugin popup present  :', post.pluginPresent);
  console.log('\n=== JS ERRORS ===');
  console.log(errs.length ? errs.slice(0,8).join('\n  ') : '  none');
  await b.close();
})();
