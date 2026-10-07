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
    // The theme has THREE quick views. #af-qv is the iframe panel the homepage
    // opens — wide, with the product page's own gallery and sticky add-to-cart
    // bar, and a round back arrow. That is the one the owner wants everywhere.
    // #quickViewModal is a separate, older one. .mfp-woosq is the plugin's.
    const qv = document.getElementById('af-qv');
    const qvs = qv ? getComputedStyle(qv) : null;
    const frame = qv ? qv.querySelector('.af-qv-frame') : null;
    window.__afqv = {
      exists: !!qv,
      hidden: qv ? qv.hidden : null,
      display: qvs ? qvs.display : null,
      loadedClass: qv ? qv.classList.contains('loaded') : null,
      box: qv ? (()=>{const r=qv.getBoundingClientRect();return Math.round(r.width)+'x'+Math.round(r.height);})() : null,
      frameSrc: frame ? (frame.getAttribute('src') || frame.src || '(empty)').slice(0,90) : null,
      htmlOpen: document.documentElement.classList.contains('af-qv-open'),
    };
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
      afqv: window.__afqv,
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
  console.log('\n=== THE IFRAME PANEL (#af-qv), the one the homepage uses ===');
  const q = post.afqv;
  console.log('  exists                :', q.exists);
  console.log('  hidden attribute      :', q.hidden, q.hidden === false ? '  <-- OPEN' : '');
  console.log('  computed display      :', q.display);
  console.log('  has .loaded           :', q.loadedClass);
  console.log('  rendered box          :', q.box);
  console.log('  <html> has af-qv-open :', q.htmlOpen);
  console.log('  iframe src            :', q.frameSrc);
  console.log('\n=== JS ERRORS ===');
  console.log(errs.length ? errs.slice(0,8).join('\n  ') : '  none');
  await b.close();
})();
