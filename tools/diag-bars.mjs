/** Stretcher bar products: which sub-collections, and each product's type and price. Read-only. */
import { createRequire } from 'module';
const puppeteer = createRequire(import.meta.url)('puppeteer-core');
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox','--disable-dev-shm-usage','--disable-gpu'] });
const p = await b.newPage();
await p.goto('https://theartframer.us/', { waitUntil: 'domcontentloaded', timeout: 90000 }); await new Promise(r => setTimeout(r, 6000));
const post = (body) => p.evaluate(async (body) => (await fetch('/wp-admin/admin-ajax.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })).text(), body);
const subs = await post('action=load_subcategories&parent=art-accessories');
const slugs = [...subs.matchAll(/data-subcat="([^"]+)"[^>]*>\s*<img[^>]*>\s*<span>\s*([^<]+)/g)].map(m => [m[1], m[2].trim()]);
console.log('SUBS ' + JSON.stringify(slugs));
const urls = new Set();
for (const [slug, name] of slugs.filter(s => /stretch/i.test(s[1] + s[0]))) {
  const html = await post('action=load_products&subcategory=' + encodeURIComponent(slug));
  const links = [...new Set([...html.matchAll(/href="(https:\/\/theartframer\.us\/product\/[^"#?]+)/g)].map(m => m[1]))];
  console.log('\n== ' + slug + ' (' + name + ') ' + links.length + ' products');
  links.forEach(u => urls.add(u + '|' + slug));
}
for (const x of urls) {
  const [u, slug] = x.split('|');
  await p.goto(u, { waitUntil: 'domcontentloaded', timeout: 90000 }); await new Promise(r => setTimeout(r, 3000));
  console.log(slug + ' ' + JSON.stringify(await p.evaluate(() => ({
    title: document.title.slice(0, 60), bodyCls: (document.body.className.match(/product-type-\w+/) || [''])[0],
    cats: [...document.body.classList].filter(c => c.startsWith('product_cat-')).join(' ').slice(0, 200),
    price: ((document.querySelector('.summary .price, p.price') || {}).innerText || '').replace(/\s+/g, ' '),
    atc: !!document.querySelector('form.cart button[name="add-to-cart"], .single_add_to_cart_button'),
    id: (document.querySelector('[name="add-to-cart"]') || {}).value || (document.body.className.match(/postid-(\d+)/) || [])[1] }))));
}
await b.close();
