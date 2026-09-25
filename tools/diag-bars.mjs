/** Stretcher bar products: categories, type, price, as the Store API reports them. Read-only. */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage();
const get = async (u) => { await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 90000 }); await new Promise(r => setTimeout(r, 2500)); const t = await p.evaluate(() => document.body.innerText); try { return JSON.parse(t); } catch { return { raw: t.slice(0, 300) }; } };
const cats = await get('https://theartframer.us/wp-json/wc/store/v1/products/categories?per_page=100');
const acc = Array.isArray(cats) ? cats.find(c => c.slug === 'art-accessories') : null;
const kids = Array.isArray(cats) ? cats.filter(c => acc && c.parent === acc.id) : [];
console.log('ACCESSORY CATEGORIES ' + JSON.stringify(kids.map(c => [c.id, c.slug, c.name, c.count])));
for (const c of kids.filter(c => /stretch/i.test(c.name))) {
  const prods = await get('https://theartframer.us/wp-json/wc/store/v1/products?per_page=50&category=' + c.id);
  console.log('\n== ' + c.slug);
  if (!Array.isArray(prods)) { console.log(JSON.stringify(prods)); continue; }
  for (const x of prods) console.log(JSON.stringify({ id: x.id, name: x.name.slice(0, 60), type: x.type, price: x.prices.price, regular: x.prices.regular_price, purch: x.is_purchasable, cats: x.categories.map(k => k.slug).join(',') }));
}
await b.close();
