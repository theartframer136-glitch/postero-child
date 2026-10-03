/**
 * Read-only QA of the Login and Sign-up pages (Essential Addons Login |
 * Register widget). Logs in with a username that does not exist and reports
 * what the form answers; the Sign-up page is only looked at, never submitted.
 * Nothing is created or changed on the site.
 */
import { createRequire } from 'module';
const req = createRequire(import.meta.url);
const puppeteer = req('puppeteer-core');
const S = 'https://theartframer.us';
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const p = await b.newPage();
await p.setViewport({ width: 1366, height: 900 });
const errs = [];
p.on('pageerror', e => errs.push('pageerror: ' + e.message));
p.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 160)); });
for (const path of ['/login/', '/sign-up/']) {
  const r = await p.goto(S + path + '?afqa=' + Date.now(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(e => null);
  const info = await p.evaluate(() => ({
    forms: document.querySelectorAll('.eael-login-registration-wrapper form, form.eael-login-form, form.eael-register-form, #eael-login-form, #eael-register-form').length,
    fields: [...document.querySelectorAll('.eael-login-registration-wrapper input')].map(i => i.name || i.type).filter(Boolean).slice(0, 14),
    eaelCss: [...document.styleSheets].filter(s => (s.href || '').includes('eael')).map(s => s.href.replace(location.origin, '')).slice(0, 4),
    eaelJs: [...document.scripts].filter(s => (s.src || '').includes('eael')).map(s => s.src.replace(location.origin, '')).slice(0, 4),
  }));
  console.log(`=== ${path} status ${r ? r.status() : 'n/a'}  forms ${info.forms}`);
  console.log('  inputs: ' + info.fields.join(', '));
  console.log('  eael css: ' + info.eaelCss.join(' ')); console.log('  eael js: ' + info.eaelJs.join(' '));
  if (path === '/login/') {
    const user = await p.$('input[name="eael-user-login"]'), pass = await p.$('input[name="eael-user-password"]');
    if (!user || !pass) { console.log('  login fields not found'); continue; }
    await user.type('qa-no-such-user-' + Date.now()); await pass.type('not-a-password');
    await Promise.all([
      p.waitForNavigation({ timeout: 20000 }).catch(() => null),
      p.evaluate(() => { const s = document.querySelector('[name="eael-login-submit"], .eael-lr-btn, .eael-login-form [type="submit"], #eael-login-submit'); if (s) s.click(); }),
    ]);
    await sleep(4000);
    const msg = await p.evaluate(() => {
      const el = document.querySelector('.eael-form-msg, .eael-form-validation-container, .eael-lr-form-validation-container, .eael-login-form .error, .woocommerce-error');
      return el ? el.innerText.trim().replace(/\s+/g, ' ').slice(0, 200) : '(no message element)';
    });
    console.log('  after wrong login: ' + msg + '   url ' + p.url().replace(S, ''));
  }
}
console.log('=== errors: ' + (errs.length ? errs.slice(0, 8).join(' | ') : 'none'));
await b.close();
