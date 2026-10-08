/**
 * Live QA of every login / sign-up / password-reset form and its Cloudflare
 * Turnstile check (inc/turnstile.php), as a guest. Read-only: nothing is
 * created and no email is sent: every attempt uses a username that does not
 * exist or an address that is not one, so even a check that let it through
 * would change nothing.
 *
 *   node tools/qa-login-form.mjs on    Turnstile switched on: every form shows its box, Cloudflare
 *                                      draws it with no configuration error (wrong site key,
 *                                      hostname not allowed, key disabled), and every form
 *                                      refuses a post that carries no token, with the
 *                                      security-check message.
 *   node tools/qa-login-form.mjs off   switched off: no box and no Cloudflare script anywhere,
 *                                      and the forms answer as they always did.
 *
 * Exit 1 when a check fails. Bot-detected codes from Cloudflare (300*, 600*)
 * are expected in a headless browser and do not fail a check.
 */
import { createRequire } from 'module';
const req = createRequire(import.meta.url);
const S = process.env.QA_BASE || 'https://theartframer.us'; // QA_BASE: a test copy of the site
const MODE = process.argv[2] === 'on' ? 'on' : 'off';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
const MSG = /complete the security check above the button/i;
const CONFIG_ERR = /^(1101|1102|4000)/; // 110100 110110 110200 400020 400021 400070
const sleep = (ms) => new Promise(r => setTimeout(r, ms));
const bust = () => 'afqa=' + Date.now() + Math.floor(Math.random() * 1000);
let fails = 0;
function check(name, ok, info) {
  if (!ok) fails++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${info !== undefined ? '  ' + (typeof info === 'string' ? info : JSON.stringify(info)).slice(0, 400) : ''}`);
}

/* ---------- server side: a post without a token ---------- */
const jar = {};
function cookies() { return Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '); }
function keep(res) {
  for (const c of (res.headers.getSetCookie ? res.headers.getSetCookie() : [])) {
    const [kv] = c.split(';'); const i = kv.indexOf('=');
    if (i > 0) jar[kv.slice(0, i).trim()] = kv.slice(i + 1).trim();
  }
}
async function get(path) {
  const res = await fetch(S + path, { headers: { 'User-Agent': UA, Cookie: cookies(), Accept: 'text/html' }, redirect: 'follow' });
  keep(res);
  return { status: res.status, html: await res.text() };
}
async function post(path, fields, follow = true) {
  const res = await fetch(S + path, {
    method: 'POST', redirect: follow ? 'follow' : 'manual',
    headers: { 'User-Agent': UA, Cookie: cookies(), 'Content-Type': 'application/x-www-form-urlencoded', Referer: S + path.split('?')[0] },
    body: new URLSearchParams(fields).toString(),
  });
  keep(res);
  return { status: res.status, html: await res.text(), cookies: res.headers.getSetCookie ? res.headers.getSetCookie() : [] };
}
// the hidden fields of the first <form> whose opening tag matches re
function hidden(html, re) {
  const m = html.match(new RegExp('<form[^>]*(?:' + re.source + ')[\\s\\S]*?</form>', 'i'));
  if (!m) return null;
  const out = {};
  for (const tag of m[0].match(/<input\b[^>]*>/gi) || []) {
    if (!/type=["']?hidden/i.test(tag)) continue;
    const n = tag.match(/name=["']([^"']+)["']/i), v = tag.match(/value=["']([^"']*)["']/i);
    if (n) out[n[1]] = v ? v[1] : '';
  }
  return out;
}
const text = (html) => html.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ');
const user = 'qa-no-such-user-' + Date.now();

async function serverChecks() {
  const expect = (name, body) => MODE === 'on'
    ? check(`server: ${name} refuses a post without a token`, MSG.test(text(body)), text(body).match(/.{0,80}(security check|Invalid credentials|Error)[^.]{0,80}/i)?.[0] || '(no message found)')
    : check(`server: ${name} answers as before (no security-check message)`, !MSG.test(text(body)), text(body).match(/.{0,80}security check.{0,60}/i)?.[0]);

  const ma = await get('/my-account/');
  const lf = hidden(ma.html, /woocommerce-form-login/);
  if (!lf) check('server: My Account login form found', false);
  else expect('My Account login', (await post('/my-account/', { ...lf, username: user, password: 'not-a-password', login: 'Log in' })).html);

  const ma2 = await get('/my-account/');
  const rf = hidden(ma2.html, /woocommerce-form-register/);
  if (!rf) check('server: My Account register form found', false);
  else expect('My Account register', (await post('/my-account/', { ...rf, email: 'qa-not-an-email', username: user, password: 'Qa-not-used-1!', register: 'Register' })).html);

  const lp = await get('/my-account/lost-password/');
  const pf = hidden(lp.html, /lost_reset_password|woocommerce-ResetPassword/);
  if (!pf) check('server: My Account lost-password form found', false);
  else expect('My Account lost password', (await post('/my-account/lost-password/', { ...pf, user_login: user })).html);

  // the header popup (AJAX): its own answer shape
  const nonce = (ma.html.match(/name="security-login" value="([0-9a-f]+)"/) || [])[1] || '';
  const pr = await post('/wp-admin/admin-ajax.php', { action: 'postero_login', username: user, password: 'not-a-password', 'security-login': nonce });
  let pj = null; try { pj = JSON.parse(pr.html); } catch (e) {}
  if (MODE === 'on') check('server: header popup refuses a post without a token', pj && pj.status === false && MSG.test(pj.msg || ''), pr.html.slice(0, 200));
  else check('server: header popup answers as before', !(pj && MSG.test(pj.msg || '')), pr.html.slice(0, 200));

  expect('wp-login.php login', (await post('/wp-login.php', { log: user, pwd: 'not-a-password', 'wp-submit': 'Log In' })).html);
  expect('wp-login.php register', (await post('/wp-login.php?action=register', { user_login: user, user_email: 'qa-not-an-email', 'wp-submit': 'Register' })).html);
  expect('wp-login.php lost password', (await post('/wp-login.php?action=lostpassword', { user_login: user, 'wp-submit': 'Get New Password' })).html);

  // the Login | Register widget on /login/: it answers with a redirect and a cookie holding the message
  const lg = await get('/login/?' + bust());
  const ef = hidden(lg.html, /eael-login-form|id=["']eael-login-form/);
  if (!ef) check('server: /login/ form found', false);
  else {
    const r = await post('/login/', { ...ef, 'eael-user-login': user, 'eael-user-password': 'not-a-password', 'eael-login-submit': 'Log In' }, false);
    const msg = decodeURIComponent(((r.cookies.find(c => /^eael_login_error_/.test(c)) || '').split(';')[0].split('=')[1] || '').replace(/\+/g, ' '));
    if (MODE === 'on') check('server: /login/ refuses a post without a token', MSG.test(msg), msg || r.cookies.join(' | ').slice(0, 200));
    else check('server: /login/ answers as before', !MSG.test(msg), msg);
  }
}

/* ---------- in a browser: the boxes ---------- */
async function browserChecks() {
  const puppeteer = req('puppeteer-core');
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
  async function open(path, prep) {
    const p = await b.newPage();
    await p.setViewport({ width: 1366, height: 900 });
    await p.setUserAgent(UA);
    const own = []; let cf = 0;
    p.on('pageerror', e => { const s = String(e && (e.stack || e.message)); if (/af-turnstile|afTs/.test(s)) own.push(s.slice(0, 200)); });
    p.on('request', r => { if (r.url().includes('challenges.cloudflare.com')) cf++; });
    await p.goto(S + path + (path.includes('?') ? '&' : '?') + bust(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
    if (prep) await prep(p);
    return { p, own, cf: () => cf };
  }
  async function boxes(p, want) {
    // bring every box into view and use its form, as a visitor would
    await p.evaluate(() => document.querySelectorAll('.af-ts').forEach(bx => {
      bx.scrollIntoView({ block: 'center' });
      const f = bx.closest('form'); const i = f && f.querySelector('input:not([type=hidden])');
      if (i) i.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    }));
    let s = null;
    for (let i = 0; i < 30; i++) {
      s = await p.evaluate(() => window.afTsState ? window.afTsState() : null);
      if (s && s.boxes.filter(x => !x.inPopup).length >= want && s.boxes.filter(x => !x.inPopup).every(x => x.drawn)) break;
      await sleep(500);
    }
    await sleep(2500); // let Cloudflare report a configuration error, if it has one
    s = await p.evaluate(() => window.afTsState ? window.afTsState() : null);
    const frames = p.frames().filter(f => f.url().includes('challenges.cloudflare.com')).length;
    return { s, frames };
  }
  const pages = [
    ['/login/', 1, 'Login page (Login | Register widget)'],
    ['/sign-up/', 1, 'Sign-up page (Login | Register widget)'],
    ['/my-account/', 2, 'My Account login + register'],
    ['/my-account/lost-password/', 1, 'My Account lost password'],
    ['/wp-login.php', 1, 'wp-login.php login'],
    ['/wp-login.php?action=register', 1, 'wp-login.php register'],
    ['/wp-login.php?action=lostpassword', 1, 'wp-login.php lost password'],
  ];
  for (const [path, want, label] of pages) {
    const { p, own, cf } = await open(path);
    if (MODE === 'on') {
      const { s, frames } = await boxes(p, want);
      const mine = s ? s.boxes.filter(x => !x.inPopup) : [];
      const cfgErr = s ? s.errors.filter(e => CONFIG_ERR.test(e)) : ['no afTsState'];
      check(`browser: ${label}: ${want} box(es) drawn by Cloudflare`, mine.length >= want && mine.every(x => x.drawn) && frames >= want, { boxes: mine, frames, script: s && s.script });
      check(`browser: ${label}: no configuration error from Cloudflare`, cfgErr.length === 0, s && s.errors);
      check(`browser: ${label}: no error from our script`, own.length === 0, own);
    } else {
      await sleep(1500);
      const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
      check(`browser: ${label}: no box, no Cloudflare script`, n.boxes === 0 && n.cfg === 'undefined' && cf() === 0, { ...n, cf: cf() });
    }
    await p.close();
  }

  // the header popup: shown the way its trigger shows it, then used
  {
    const { p, own, cf } = await open('/shop/');
    const before = cf();
    if (MODE === 'on') {
      await p.evaluate(() => {
        const f = document.querySelector('.postero-login-form-ajax'); if (!f) return;
        for (let el = f; el && el !== document.body; el = el.parentElement) {
          const cs = getComputedStyle(el);
          if (cs.display === 'none') el.style.display = 'block';
          if (cs.visibility === 'hidden') el.style.visibility = 'visible';
          if (cs.opacity === '0') el.style.opacity = '1';
        }
        f.querySelector('input[name="username"]').dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
      });
      let s = null;
      for (let i = 0; i < 30; i++) { s = await p.evaluate(() => window.afTsState && window.afTsState()); if (s && s.boxes.some(x => x.inPopup && x.drawn)) break; await sleep(500); }
      await sleep(2500);
      s = await p.evaluate(() => window.afTsState && window.afTsState());
      check('browser: header popup: Cloudflare not fetched until the popup is used', before === 0, { before });
      check('browser: header popup: box drawn on use', !!(s && s.boxes.some(x => x.inPopup && x.drawn)), s);
      check('browser: header popup: no configuration error, no error from our script', s && !s.errors.some(e => CONFIG_ERR.test(e)) && own.length === 0, { errors: s && s.errors, own });
    } else {
      await sleep(1500);
      const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
      check('browser: header popup: no box, no Cloudflare script', n.boxes === 0 && n.cfg === 'undefined' && cf() === 0, { ...n, cf: cf() });
    }
    await p.close();
  }

  // checkout: "Returning customer? Click here to login" (a basket is needed for the checkout page)
  {
    const p = await b.newPage();
    await p.setViewport({ width: 1366, height: 900 }); await p.setUserAgent(UA);
    let cf = 0; p.on('request', r => { if (r.url().includes('challenges.cloudflare.com')) cf++; });
    await p.goto(S + '/shop/?' + bust(), { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
    const id = await p.evaluate(async () => {
      const r = await fetch('/wp-json/wc/store/v1/products?per_page=20&type=simple', { headers: { Accept: 'application/json' } }).then(x => x.json()).catch(() => []);
      const ok = (r || []).find(x => x.is_purchasable && x.is_in_stock);
      return ok ? ok.id : 0;
    });
    if (!id) check('browser: checkout login: a product to put in the basket', false);
    else {
      await p.goto(S + '/?add-to-cart=' + id, { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => null);
      await p.goto(S + '/checkout/?' + bust(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
      const before = cf;
      const has = await p.$('a.showlogin');
      if (MODE === 'on') {
        check('browser: checkout: Cloudflare not fetched while the login form is folded', before === 0, { before });
        if (!has) check('browser: checkout: "Click here to login" link found', false);
        else {
          await p.click('a.showlogin');
          let s = null;
          for (let i = 0; i < 30; i++) { s = await p.evaluate(() => window.afTsState && window.afTsState()); if (s && s.boxes.some(x => x.action === 'login' && x.drawn)) break; await sleep(500); }
          await sleep(2500);
          s = await p.evaluate(() => window.afTsState && window.afTsState());
          check('browser: checkout: box drawn when the login form opens', !!(s && s.boxes.some(x => x.action === 'login' && x.drawn)), s);
          check('browser: checkout: no configuration error from Cloudflare', s && !s.errors.some(e => CONFIG_ERR.test(e)), s && s.errors);
        }
      } else {
        const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
        check('browser: checkout: no box, no Cloudflare script', n.boxes === 0 && n.cfg === 'undefined' && cf === 0, { ...n, cf });
      }
    }
    await p.close();
  }
  await b.close();
}

console.log(`=== Turnstile ${MODE.toUpperCase()}: forms checked on ${S}`);
await serverChecks().catch(e => check('server checks ran', false, String(e && e.stack || e)));
if (process.env.QA_ONLY !== 'server') await browserChecks().catch(e => check('browser checks ran', false, String(e && e.stack || e)));
console.log(`=== ${fails ? fails + ' FAILED' : 'all passed'}`);
process.exit(fails ? 1 : 0);
