/** Owner's recording, 29 Sep: pick Digital download, reload, and the page must open on Painting only at the canvas price. */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const U = 'https://theartframer.us/product/krishna-bells-and-lamps-aarti-canvas-wall-art-3x4-feet-floating-frame-premium-digital-canvas-print-living-room-home-spiritual-wall-decor/';
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage(); await p.setViewport({ width: 1280, height: 900 });
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const st = () => p.evaluate(() => ({ kit: (document.querySelector('input[name="af_kit"]:checked') || {}).value,
  live: (document.getElementById('af-live-price') || {}).textContent, head: ((document.querySelector('.summary .price, p.price') || {}).innerText || '').replace(/\s+/g, ' '),
  sizeShown: !!document.querySelector('.af-opts .af-opt-group') && document.querySelector('.af-opts .af-opt-group').getBoundingClientRect().height > 0 }));
await p.goto(U, { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {}); await sleep(6000);
console.log('open     ' + JSON.stringify(await st()));
await p.evaluate(() => document.querySelector('input[name="af_kit"][value="digital"]').closest('label').click()); await sleep(1500);
console.log('digital  ' + JSON.stringify(await st()));
await p.reload({ waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {}); await sleep(6000);
const r = await st(); console.log('reloaded ' + JSON.stringify(r));
await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => {}); await sleep(3000);
await p.goBack({ waitUntil: 'networkidle2', timeout: 90000 }).catch(() => {}); await sleep(6000);
const bk = await st(); console.log('back     ' + JSON.stringify(bk));
const ok = [r, bk].every(x => x.kit === 'painting' && !/9\.\d\d/.test(x.live || '') && x.sizeShown);
console.log('VERDICT ' + (ok ? 'PASS' : 'FAIL'));
await b.close();
