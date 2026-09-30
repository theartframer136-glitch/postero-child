/** Cart totals box at the owner's 1918px: why "Price before discount" breaks mid-word. */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const S = 'https://theartframer.us';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
for (const [w, h] of [[1918, 1078], [1366, 768], [390, 844]]) {
  const ctx = await b.createBrowserContext(); const p = await ctx.newPage(); await p.setViewport({ width: w, height: h });
  const go = async (u) => { for (let i = 0; i < 3; i++) { try { await p.goto(u, { waitUntil: 'networkidle2', timeout: 90000 }); break; } catch { await sleep(3000); } } await sleep(2500); };
  await go(S + '/product/radha-krishna-moonlit-melody-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/');
  await Promise.all([p.waitForNavigation({ timeout: 45000 }).catch(() => {}), p.evaluate(() => document.querySelector('form.cart .single_add_to_cart_button').click())]);
  await sleep(2500); await go(S + '/cart/');
  console.log('\n=== ' + w + ' ===');
  console.log(await p.evaluate(() => {
    const box = document.querySelector('.cart_totals'); if (!box) return 'no cart_totals';
    const t = box.querySelector('table'); const out = ['box ' + Math.round(box.getBoundingClientRect().width) + ' table ' + Math.round(t.getBoundingClientRect().width) + ' display=' + getComputedStyle(t).display + ' layout=' + getComputedStyle(t).tableLayout];
    box.querySelectorAll('tr').forEach(tr => { const th = tr.querySelector('th'), td = tr.querySelector('td'); if (!th) return;
      const cs = getComputedStyle(th); const r = th.getBoundingClientRect();
      const lines = Math.round(r.height / parseFloat(cs.lineHeight || 20));
      out.push('  ' + th.innerText.trim().slice(0, 24).padEnd(24) + ' th ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' wb=' + cs.wordBreak + ' ow=' + cs.overflowWrap + ' ws=' + cs.whiteSpace + ' fs=' + cs.fontSize + ' w=' + cs.width + (td ? ' | td ' + Math.round(td.getBoundingClientRect().width) + ' ta=' + getComputedStyle(td).textAlign : ''));
    });
    return out.join('\n');
  }));
  console.log('rules on the th: ' + await p.evaluate(() => {
    const th = document.querySelector('.cart_totals .af-ct-was th, .cart_totals th'); const out = [];
    for (const ss of document.styleSheets) { let rs; try { rs = ss.cssRules; } catch { continue; }
      const scan = (list, media) => { for (const r of list) { if (r.cssRules && !r.selectorText) { scan(r.cssRules, r.conditionText || media); continue; }
        if (!r.selectorText || !r.style) continue; let m = false; try { m = th.matches(r.selectorText); } catch {}
        if (m && (r.style.width || r.style.wordBreak || r.style.overflowWrap || r.style.maxWidth || r.style.minWidth || r.style.whiteSpace || r.style.fontSize)) out.push((ss.href || 'inline#' + (ss.ownerNode && ss.ownerNode.id)).split('/').pop().slice(0, 30) + ' [' + (media || '') + '] ' + r.selectorText.slice(0, 90) + ' {w=' + r.style.width + ' maxw=' + r.style.maxWidth + ' wb=' + r.style.wordBreak + ' ow=' + r.style.overflowWrap + ' ws=' + r.style.whiteSpace + ' fs=' + r.style.fontSize + '}'); } };
      try { scan(rs, ''); } catch {} }
    return '\n  ' + out.join('\n  ');
  }));
  await ctx.close();
}
await b.close();
