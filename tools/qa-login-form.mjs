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
      // the Login | Register widget prints its three forms but shows one; a hidden form's box is drawn when it is shown
      check(`browser: ${label}: ${want} box(es) drawn by Cloudflare`, mine.filter(x => x.drawn).length >= want && frames >= want, { boxes: mine, frames, script: s && s.script });
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

/* ---------- the other forms a guest can send: contact, newsletter, comments and reviews ---------- */
async function otherFormChecks() {
  const puppeteer = req('puppeteer-core');
  const b = await puppeteer.launch({ channel: 'chrome', headless: 'new', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] });
  const p = await b.newPage();
  await p.setViewport({ width: 1366, height: 900 }); await p.setUserAgent(UA);
  const own = []; let cf = 0;
  p.on('pageerror', e => { const s = String(e && (e.stack || e.message)); if (/af-turnstile|afTs/.test(s)) own.push(s.slice(0, 200)); });
  p.on('request', r => { if (r.url().includes('challenges.cloudflare.com')) cf++; });
  const state = () => p.evaluate(() => window.afTsState ? window.afTsState() : null);
  const drawnBox = async (action, prep) => {
    if (prep) await prep();
    await p.evaluate(a => { const bx = [...document.querySelectorAll('.af-ts')].find(x => x.getAttribute('data-action') === a); if (!bx) return; bx.scrollIntoView({ block: 'center' }); const f = bx.closest('form'); const i = f && f.querySelector('input:not([type=hidden]), textarea'); if (i) i.dispatchEvent(new FocusEvent('focusin', { bubbles: true })); }, action);
    let s = null;
    for (let i = 0; i < 30; i++) { s = await state(); if (s && s.boxes.some(x => x.action === action && x.drawn)) break; await sleep(500); }
    await sleep(2000);
    s = await state();
    return { box: s && s.boxes.find(x => x.action === action), errors: s ? s.errors.filter(e => CONFIG_ERR.test(e)) : ['no afTsState'] };
  };

  // the contact page
  await p.goto(S + '/contact/?' + bust(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
  const contactNonce = await p.evaluate(() => (document.getElementById('afContactForm') || {}).dataset?.nonce || '');
  if (MODE === 'on') {
    const r = await drawnBox('contact');
    check('browser: contact form: box drawn by Cloudflare', !!(r.box && r.box.drawn), r);
    check('browser: contact form: no configuration error', r.errors.length === 0, r.errors);
  } else {
    const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
    check('browser: contact page: no box, no Cloudflare script', n.boxes === 0 && n.cfg === 'undefined' && cf === 0, { ...n, cf });
  }
  // the newsletter: the live site prints no footer newsletter form (the
  // child theme's footer is off; the Elementor footer has none). The only
  // newsletter field is the home popup overlay, which custom.js wires to
  // af_nl_subscribe with the page's af_ajax.nl_nonce. It shows ~9 s after load.
  const nlNonce = await p.evaluate(() => (window.af_ajax && window.af_ajax.nl_nonce) || '');
  await p.goto(S + '/?' + bust(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
  const overlay = await p.waitForSelector('.af-input-group input[type="email"]', { timeout: 16000 }).catch(() => null);
  if (!overlay) {
    console.log('INFO  browser: home popup newsletter: the overlay did not appear within 16 s (shown once per visitor?) - not checked in the browser');
  } else if (MODE === 'on') {
    await p.evaluate(() => { const i = document.querySelector('.af-input-group input[type="email"]'); i.focus(); });
    await p.type('.af-input-group input[type="email"]', 'qa-turnstile@example..com'); // passes the script's own check, refused by the server, so nothing is stored
    await p.evaluate(() => { const g = document.querySelector('.af-input-group'); const b = g && g.querySelector('button, [role="button"]'); if (b) b.click(); });
    let s = null;
    for (let i = 0; i < 30; i++) { s = await state(); if (s && s.boxes.some(x => x.action === 'newsletter' && x.drawn)) break; await sleep(500); }
    await sleep(2500);
    s = await state();
    const nb = s && s.boxes.find(x => x.action === 'newsletter');
    check('browser: home popup newsletter: quiet box created on use and drawn by Cloudflare', !!(nb && nb.onUse && nb.quiet && nb.drawn), nb || s);
    check('browser: home popup newsletter: no configuration error', !!(s && !s.errors.some(e => CONFIG_ERR.test(e))), s && s.errors);
  } else {
    const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
    check('browser: home page with popup: no box, no Cloudflare script', n.boxes === 0 && n.cfg === 'undefined', n);
  }

  // a blog post's comment form and a product's review form
  const links = await p.evaluate(async () => {
    const out = { post: null, postId: 0, product: null };
    try { const r = await fetch('/wp-json/wp/v2/posts?per_page=1&_fields=id,link', { headers: { Accept: 'application/json' } }).then(x => x.json()); if (r && r[0]) { out.post = r[0].link; out.postId = r[0].id; } } catch (e) {}
    try { const r = await fetch('/wp-json/wc/store/v1/products?per_page=1', { headers: { Accept: 'application/json' } }).then(x => x.json()); if (r && r[0]) out.product = r[0].permalink; } catch (e) {}
    return out;
  });
  for (const [label, url, prep] of [
    ['blog comment form', links.post, null],
    ['product review form', links.product, () => p.evaluate(() => { const t = document.querySelector('a[href="#tab-reviews"], .reviews_tab a, [data-tab="reviews"], a[href*="#reviews"]'); if (t) t.click(); })],
  ]) {
    if (!url) { check(`browser: ${label}: a page to test on`, false, links); continue; }
    cf = 0;
    await p.goto(url + (url.includes('?') ? '&' : '?') + bust(), { waitUntil: 'networkidle2', timeout: 90000 }).catch(() => null);
    const hasForm = await p.evaluate(() => !!document.querySelector('#commentform, form.comment-form'));
    if (!hasForm) { check(`browser: ${label}: form found on ${url}`, false); continue; }
    if (MODE === 'on') {
      const r = await drawnBox('comment', prep);
      const why = (r.box && r.box.drawn) ? null : await p.evaluate(() => {
        const bx = [...document.querySelectorAll('.af-ts')].find(x => x.getAttribute('data-action') === 'comment');
        const chain = []; for (let el = bx; el && el !== document.body && chain.length < 12; el = el.parentElement) { const cs = getComputedStyle(el); if (cs.display === 'none' || cs.visibility === 'hidden' || el.hidden) chain.push((el.tagName + '.' + (el.className || '') + '#' + (el.id || '')).slice(0, 80) + ' display=' + cs.display + ' visibility=' + cs.visibility); }
        const cs = bx ? getComputedStyle(bx) : null;
        return { boxWidth: bx ? bx.clientWidth : -1, boxStyle: cs ? { display: cs.display, float: cs.float, width: cs.width, visibility: cs.visibility, parent: bx.parentElement.tagName + '.' + bx.parentElement.className, parentDisplay: getComputedStyle(bx.parentElement).display } : null, hiddenAncestors: chain, tabs: [...document.querySelectorAll('a[href^="#tab-"], .wc-tabs a, .tabs a, [role="tab"]')].map(a => (a.getAttribute('href') || a.textContent.trim()).slice(0, 40)).slice(0, 10), formShown: !!(document.querySelector('#commentform') && document.querySelector('#commentform').getClientRects().length) };
      });
      check(`browser: ${label}: box drawn by Cloudflare, no configuration error`, !!(r.box && r.box.drawn) && r.errors.length === 0, why || r);
    } else {
      const n = await p.evaluate(() => ({ boxes: document.querySelectorAll('.af-ts').length, cfg: typeof window.afTs }));
      check(`browser: ${label}: no box, no Cloudflare script`, n.boxes === 0 && n.cfg === 'undefined' && cf === 0, { ...n, cf });
    }
  }
  check('browser: other forms: no error from our script', own.length === 0, own);
  await b.close();

  // the server side, without a token. With the check off, the honeypot field is
  // filled so the endpoints answer without storing anything; a comment is never posted then.
  const jsonOf = (t) => { try { return JSON.parse(t); } catch (e) { return null; } };
  const c = await post('/wp-admin/admin-ajax.php', { action: 'af_contact_submit', nonce: contactNonce, af_name: 'QA Turnstile', af_email: 'qa@example.com', af_subject: 'General Question', af_message: 'Automated security-check test, nothing to answer.', af_hp: MODE === 'on' ? '' : 'x' });
  const cj = jsonOf(c.html);
  if (MODE === 'on') check('server: contact form refuses a post without a token', !!(cj && cj.success === false && MSG.test(cj.data?.message || '')), c.html.slice(0, 200));
  else check('server: contact form answers as before', !!(cj && cj.success === true), c.html.slice(0, 200));
  const n = await post('/wp-admin/admin-ajax.php', { action: 'af_nl_subscribe', nonce: nlNonce, af_nl_email: 'qa-turnstile@example.com', af_nl_hp: MODE === 'on' ? '' : 'x' });
  const nj = jsonOf(n.html);
  if (MODE === 'on') check('server: newsletter form refuses a post without a token', !!(nj && nj.success === false && MSG.test(nj.data?.message || '')), n.html.slice(0, 200));
  else check('server: newsletter form answers as before', !!(nj && nj.success === true), n.html.slice(0, 200));
  if (MODE === 'on' && links.postId) {
    const r = await post('/wp-comments-post.php', { comment: 'Automated security-check test', author: 'QA Turnstile', email: 'qa@example.com', comment_post_ID: String(links.postId), comment_parent: '0', submit: 'Post Comment' }, false);
    check('server: comment form refuses a post without a token (403)', r.status === 403 && MSG.test(text(r.html)), { status: r.status, text: text(r.html).slice(0, 160) });
  }
}

console.log(`=== Turnstile ${MODE.toUpperCase()}: forms checked on ${S}`);
await serverChecks().catch(e => check('server checks ran', false, String(e && e.stack || e)));
if (process.env.QA_ONLY !== 'server') await browserChecks().catch(e => check('browser checks ran', false, String(e && e.stack || e)));
if (process.env.QA_ONLY !== 'server') await otherFormChecks().catch(e => check('other form checks ran', false, String(e && e.stack || e)));
console.log(`=== ${fails ? fails + ' FAILED' : 'all passed'}`);
process.exit(fails ? 1 : 0);
