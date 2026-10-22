/*
 * Cloudflare Turnstile boxes (inc/turnstile.php).
 *
 * Cloudflare's script is fetched once, the first time a box is on screen or
 * its form is used, and each box is drawn when it is visible: the checkout's
 * folded login form and the header's closed login popup cost nothing until
 * they are opened.
 *
 * A click on Log in / Register / Reset before the check has finished is held,
 * and sent by itself the moment the check passes. A form is never blocked when
 * Cloudflare's script cannot load: the server then answers with its message.
 *
 * Checkout (22 Oct): a quiet box in form.checkout; it is reset after every
 * refused Place order. The checkout's email capture (inc/abandoned-cart.php)
 * uses a box of its own, off-screen and outside the form, so it can never
 * hold or spend the token Place order needs.
 *
 * window.__afTsErrors and window.afTsState() are read by tools/qa-login-form.mjs.
 */
(function () {
  'use strict';
  var C = window.afTs;
  if (!C || !C.sitekey || !document.querySelector || !window.addEventListener) return;

  var API = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=afTsOnload';
  var POPUP = '.postero-login-form-ajax';
  var state = window.turnstile ? 'ready' : 'idle'; // idle | loading | ready | failed
  var slow = false;                                 // no script after 15 s
  var waiting = [];                                 // boxes waiting for the script
  var errors = window.__afTsErrors = window.__afTsErrors || [];

  function shown(el) {
    if (!el.getClientRects().length) return false;
    if (el.checkVisibility) return el.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true });
    return true;
  }

  // the line under a box, for "one moment" and for problems
  function note(box, text, bad) {
    var p = box.nextElementSibling;
    if (!p || !p.classList.contains('af-ts-note')) {
      if (!text) return;
      p = document.createElement('p');
      p.className = 'af-ts-note';
      p.setAttribute('role', 'status');
      p.setAttribute('aria-live', 'polite');
      box.parentNode.insertBefore(p, box.nextSibling);
    }
    p.textContent = text || '';
    p.classList.toggle('af-ts-bad', !!bad);
  }

  function blocked() { return state === 'failed' || (state === 'loading' && slow); }

  function load() {
    if (state !== 'idle') return;
    state = 'loading';
    window.afTsOnload = function () {
      state = 'ready';
      var q = waiting; waiting = [];
      q.forEach(function (b) { note(b, ''); draw(b); });
    };
    var s = document.createElement('script');
    s.src = API;
    s.async = true;
    s.onerror = function () {
      state = 'failed';
      errors.push('script');
      waiting.forEach(function (b) { note(b, C.msg.blocked, true); release(b, true); });
    };
    document.head.appendChild(s);
    setTimeout(function () {
      if (state !== 'loading') return;
      slow = true;
      errors.push('script-timeout');
      waiting.forEach(function (b) { note(b, C.msg.blocked, true); release(b, true); });
    }, 15000);
  }

  function want(box) {
    if (!box.__afTs) box.__afTs = { id: null, hold: null, timer: 0, interactive: false };
    if (box.__afTs.id !== null) return;
    if (state === 'ready') { draw(box); return; }
    if (waiting.indexOf(box) < 0) waiting.push(box);
    load();
  }

  function token(box) {
    var t = box.__afTs;
    if (!t || t.id === null || !window.turnstile) return '';
    // a token left over from a long idle (a phone locked, a tab in the
    // background, where the timers that renew it run late) is no token
    try { if (window.turnstile.isExpired && window.turnstile.isExpired(t.id)) return ''; } catch (e) {}
    try { return window.turnstile.getResponse(t.id) || t.tok || ''; } catch (e) { return t.tok || ''; }
  }
  function expired(box) {
    var t = box.__afTs;
    try { return !!(t && t.id !== null && window.turnstile && window.turnstile.isExpired && window.turnstile.isExpired(t.id)); } catch (e) { return false; }
  }
  // a box the visitor has to tick, brought to where they can see it
  function bring(box) {
    if (box.closest('.af-ts-offscreen')) return; // the email capture's own box: never scrolled to
    try { box.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { try { box.scrollIntoView(); } catch (e2) {} }
  }

  function draw(box) {
    var t = box.__afTs;
    if (!window.turnstile || t.id !== null) return;
    // not laid out yet (folded form, closed popup): drawn when it shows
    if (!box.clientWidth) { if (waiting.indexOf(box) < 0) waiting.push(box); return; }
    var compact = box.clientWidth < 300;
    box.classList.toggle('af-ts-compact', compact);
    try {
      t.id = window.turnstile.render(box, {
        sitekey: C.sitekey,
        action: box.getAttribute('data-action') || undefined,
        // newsletter boxes stay invisible unless Cloudflare needs a tick
        appearance: box.getAttribute('data-appearance') || 'always',
        theme: 'light',
        size: compact ? 'compact' : 'flexible',
        language: C.lang || 'auto',
        retry: 'auto',
        'refresh-expired': 'auto',
        callback: function (tok) { t.tok = tok; t.interactive = false; note(box, ''); release(box, false); },
        // refresh-expired: auto fetches the next one by itself
        'expired-callback': function () { t.tok = ''; },
        // a quiet box takes room only once Cloudflare shows its tick box
        'before-interactive-callback': function () { t.interactive = true; box.classList.add('af-ts-shown'); if (t.hold) { note(box, C.msg.tick); bring(box); } },
        'after-interactive-callback': function () { t.interactive = false; },
        'error-callback': function (code) {
          t.tok = '';
          errors.push(String(code));
          note(box, C.msg.error, true);
        }
      });
      if (t.id === undefined) t.id = null;
      if (t.id !== null) box.classList.add('af-ts-ready');
    } catch (e) {
      errors.push('render: ' + (e && e.message ? e.message : e));
    }
  }

  // a held click or submit goes on, once the check passed or cannot run
  function release(box, anyway) {
    var t = box.__afTs;
    if (!t || !t.hold) return;
    if (!anyway && !token(box)) return;
    var go = t.hold; t.hold = null;
    clearTimeout(t.timer);
    go();
  }

  function hold(box, go) {
    var t = box.__afTs;
    t.hold = go;
    if (expired(box)) { t.tok = ''; try { window.turnstile.reset(t.id); } catch (e) {} }
    note(box, t.interactive ? C.msg.tick : C.msg.wait);
    if (t.interactive) bring(box);
    clearTimeout(t.timer);
    // a check still running after 8 s: send anyway and let the server answer,
    // unless the visitor is being asked to tick the box
    t.timer = setTimeout(function () {
      if (!t.hold) return;
      if (t.interactive) { note(box, C.msg.tick); return; }
      release(box, true);
    }, 8000);
  }

  function boxIn(form) { return form ? form.querySelector('.af-ts') : null; }

  // should this click / submit wait for the check?
  function mustWait(box) {
    if (!box || box.__afTsPass || blocked()) return false;
    want(box);
    return !token(box);
  }

  // Forms that post normally (WooCommerce, the Login | Register widget,
  // wp-login.php): the submit event, before any other handler sees it.
  document.addEventListener('submit', function (e) {
    var form = e.target, box = boxIn(form);
    if (!mustWait(box)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    var by = e.submitter || null;
    hold(box, function () {
      box.__afTsPass = true;
      try {
        if (form.requestSubmit) form.requestSubmit(by && by.form === form ? by : undefined);
        else if (by) by.click(); else form.submit();
      } finally { box.__afTsPass = false; }
    });
  }, true);

  // The header popup: its login.js sends on a click of the button (bound on
  // body), so the click is what has to wait.
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest(POPUP + ' button[type="submit"]') : null;
    if (!btn) return;
    var box = boxIn(btn.form || btn.closest(POPUP));
    if (!mustWait(box)) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    hold(box, function () {
      box.__afTsPass = true;
      try { btn.click(); } finally { box.__afTsPass = false; }
    });
  }, true);

  // The popup has no box of its own: one goes right above its button.
  Array.prototype.forEach.call(document.querySelectorAll(POPUP), function (form) {
    if (boxIn(form)) return;
    var btn = form.querySelector('button[type="submit"]');
    var box = document.createElement('div');
    box.className = 'af-ts';
    box.setAttribute('data-action', 'login');
    box.setAttribute('role', 'group');
    box.setAttribute('aria-label', 'Security check');
    if (btn) btn.parentNode.insertBefore(box, btn); else form.appendChild(box);
  });

  // After each popup login answer: the token was spent, so a new one, and the
  // popup shows this answer (its login.js keeps the first message it showed).
  if (window.jQuery) {
    window.jQuery(document).on('ajaxComplete', function (ev, xhr, opts) {
      var data = opts && typeof opts.data === 'string' ? opts.data : '';
      if (data.indexOf('action=postero_login') < 0) return;
      Array.prototype.forEach.call(document.querySelectorAll(POPUP), function (form) {
        var box = boxIn(form), t = box && box.__afTs;
        if (t && t.id !== null && window.turnstile) { t.tok = ''; try { window.turnstile.reset(t.id); } catch (e) {} }
        var r = xhr && xhr.responseJSON, msg = form.querySelector('.result-error');
        if (r && r.status === false && r.msg && msg) msg.textContent = r.msg;
      });
    });
  }

  // Checkout (22 Oct): WooCommerce sends the order from its own jQuery submit
  // handler, and payment methods start it with .trigger('submit') too (Square's
  // Cash App Pay once the buyer approves in Cash App, even on the page it
  // returns to; Square's card form once the card is read). Those never raise
  // the native submit event above, so the wait is also asked on the event
  // WooCommerce raises just before it posts: no token yet, nothing posted,
  // and the order is sent by itself once the check has passed.
  if (window.jQuery) {
    window.jQuery('form.checkout').on('checkout_place_order', function () {
      var form = this, box = form.querySelector('.af-ts[data-action="checkout"]');
      if (!box || box.__afTsPass || blocked()) return true;
      want(box);
      if (token(box)) return true;
      // the payment method may have greyed the form out before it submitted
      // (Square's Cash App Pay and card form do): lifted, so a box Cloudflare
      // asks to be ticked can be ticked; WooCommerce greys it again on posting
      try { window.jQuery(form).unblock(); } catch (e) {}
      if (!box.__afTs.hold) hold(box, function () {
        box.__afTsPass = true;
        try { window.jQuery(form).trigger('submit'); } finally { box.__afTsPass = false; }
      });
      return false;
    });
  }

  // The "pay for this order" page: a normal form post, but Square's Cash App
  // Pay and card form start it with jQuery's .trigger('submit') too, which the
  // native submit listener above never sees. Bound before their handlers.
  if (window.jQuery) {
    window.jQuery('form#order_review').on('submit', function (e) {
      var form = this, box = form.querySelector('.af-ts[data-action="pay"]');
      if (!box || box.__afTsPass || blocked()) return;
      want(box);
      if (token(box)) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      try { window.jQuery(form).unblock(); } catch (e2) {}
      if (!box.__afTs.hold) hold(box, function () {
        box.__afTsPass = true;
        try { window.jQuery(form).trigger('submit'); } finally { box.__afTsPass = false; }
      });
      return false;
    });
    Array.prototype.forEach.call(document.querySelectorAll('form#order_review .af-ts[data-action="pay"]'), function (b) { want(b); });
  }

  // Checkout (22 Oct): after a refused Place order the next attempt needs a
  // fresh token (each is good for one check), so the checkout box starts a
  // new one at once; quiet boxes do this without the shopper seeing it.
  if (window.jQuery) {
    window.jQuery(document.body).on('checkout_error', function () {
      Array.prototype.forEach.call(document.querySelectorAll('form.checkout .af-ts'), function (box) {
        var t = box.__afTs;
        if (t && t.id !== null && window.turnstile) { t.tok = ''; try { window.turnstile.reset(t.id); } catch (e) {} }
      });
    });
  }

  // When to draw: a box on screen, or any field of its form used. The
  // popup's box and the newsletter boxes only on use: they sit in every
  // page's header and footer, and a page view must not fetch Cloudflare's
  // script for a form nobody touches.
  var boxes = Array.prototype.filter.call(document.querySelectorAll('.af-ts'), function (b) { return !b.closest(POPUP) && !b.hasAttribute('data-on-use'); });
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && shown(en.target)) want(en.target);
      });
    }, { rootMargin: '200px 0px' });
    boxes.forEach(function (b) { io.observe(b); });
  } else {
    boxes.forEach(function (b) { if (shown(b)) want(b); });
  }
  function onUse(e) {
    var form = e.target && e.target.closest ? e.target.closest('form') : null;
    var box = boxIn(form);
    if (box) want(box);
  }
  document.addEventListener('focusin', onUse, true);
  document.addEventListener('pointerdown', onUse, true);

  // For forms that send by themselves (the contact form, the newsletter forms):
  //   ready(box, cb)  draws the box if needed and calls cb(token) once the
  //                   check passed, or cb('') when it cannot run (8 s at most);
  //   reset(formOrBox)  after each answer: a token is spent by one post;
  //   box(parent, before, action, quiet)  a box for a form built by script.
  window.afTsApi = {
    want: want,
    token: token,
    ready: function (box, cb) {
      if (!box || blocked()) { cb(''); return; }
      want(box);
      var tok = token(box);
      if (tok) { cb(tok); return; }
      hold(box, function () { cb(token(box)); });
    },
    reset: function (el) {
      var box = el && el.classList && el.classList.contains('af-ts') ? el : boxIn(el);
      var t = box && box.__afTs;
      if (!t || t.id === null || !window.turnstile) return;
      t.tok = '';
      try { window.turnstile.reset(t.id); } catch (e) {}
    },
    box: function (parent, before, action, quiet) {
      var box = document.createElement('div');
      box.className = 'af-ts' + (quiet ? ' af-ts-quiet' : '');
      box.setAttribute('data-action', action || 'form');
      box.setAttribute('role', 'group');
      box.setAttribute('aria-label', 'Security check');
      box.setAttribute('data-on-use', '1');
      if (quiet) box.setAttribute('data-appearance', 'interaction-only');
      parent.insertBefore(box, before || null);
      return box;
    }
  };

  window.afTsState = function () {
    return {
      script: state + (slow ? ' (slow)' : ''),
      boxes: Array.prototype.map.call(document.querySelectorAll('.af-ts'), function (b) {
        var t = b.__afTs || {};
        return {
          action: b.getAttribute('data-action'),
          inPopup: !!b.closest(POPUP),
          onUse: b.hasAttribute('data-on-use'),
          quiet: b.classList.contains('af-ts-quiet'),
          drawn: t.id !== null && t.id !== undefined,
          compact: b.classList.contains('af-ts-compact'),
          token: !!token(b),
          iframe: !!b.querySelector('iframe')
        };
      }),
      errors: errors.slice()
    };
  };

  // Apple Pay / Google Pay (Square's buttons on product, basket and checkout
  // pages): Square's script posts the order itself, with its own list of
  // fields, once the buyer has approved in the wallet. A quiet box of its own
  // sits under the buttons, is drawn when the buyer reaches for them, and
  // Square's post waits for its token (as Place order does) and carries it.
  if (C.checkout && window.jQuery) {
    var walletBox = function () {
      var w = document.getElementById('wc-square-digital-wallet');
      if (!w || !w.parentNode) return null;
      var b = document.getElementById('af-ts-wallet');
      if (b) return b;
      // never inside a form (on product pages the buttons sit in the add-to-
      // cart form, whose submit would then wait for this check) nor inside the
      // basket totals (redrawn on every basket change): just after them
      var at = w.closest('form') || w.closest('.cart-collaterals') || w.closest('.cart_totals') || w;
      b = window.afTsApi.box(at.parentNode, at.nextSibling, 'checkout', true);
      b.id = 'af-ts-wallet';
      return b;
    };
    var reach = function (e) {
      if (e.target && e.target.closest && e.target.closest('#wc-square-digital-wallet')) { var b = walletBox(); if (b) want(b); }
    };
    ['pointerover', 'pointerdown', 'touchstart', 'focusin'].forEach(function (ev) { document.addEventListener(ev, reach, true); });
    var wrap = function (proto) {
      if (!proto || typeof proto.process_digital_wallet_checkout !== 'function' || proto.process_digital_wallet_checkout.__afTs) return;
      var orig = proto.process_digital_wallet_checkout;
      proto.process_digital_wallet_checkout = function (data) {
        var self = this, args = arguments, b = walletBox();
        if (!b || blocked()) return orig.apply(self, args);
        return new Promise(function (ok, no) {
          window.afTsApi.ready(b, function (tok) {
            if (tok && data && typeof data === 'object') data['cf-turnstile-response'] = tok;
            orig.apply(self, args).then(ok, no);
          });
        });
      };
      proto.process_digital_wallet_checkout.__afTs = true;
    };
    // Square defines its class inside its own page-ready callback, before or
    // after this script runs: wrapped the moment it is set, and once more
    // after every page-ready callback has run
    var KEY = 'WC_Square_Digital_Wallet_Handler';
    if (window[KEY]) wrap(window[KEY].prototype);
    else {
      try {
        var held;
        Object.defineProperty(window, KEY, { configurable: true, enumerable: true,
          get: function () { return held; },
          set: function (v) { held = v; try { wrap(v && v.prototype); } catch (e) {} } });
      } catch (e) {}
    }
    window.jQuery(function () { setTimeout(function () { wrap(window[KEY] && window[KEY].prototype); }, 0); });
    // and whatever posts to the route without that method: the token, when there is one
    window.jQuery.ajaxPrefilter(function (opts) {
      if (!opts || typeof opts.url !== 'string' || opts.url.indexOf('square_digital_wallet_process_checkout') < 0) return;
      var b = document.getElementById('af-ts-wallet'), tok = b && token(b);
      if (tok && typeof opts.data === 'string' && opts.data.indexOf('cf-turnstile-response=') < 0) opts.data += (opts.data ? '&' : '') + 'cf-turnstile-response=' + encodeURIComponent(tok);
    });
    // each token is good for one check
    window.jQuery(document).on('ajaxComplete', function (ev, xhr, opts) {
      if (!opts || typeof opts.url !== 'string' || opts.url.indexOf('square_digital_wallet_process_checkout') < 0) return;
      var b = document.getElementById('af-ts-wallet');
      if (b) window.afTsApi.reset(b);
    });
  }

  // Checkout's box is printed after the order summary; a theme template
  // without that hook gets one in the same place from here.
  if (C.checkout && window.jQuery) window.jQuery(function () { if (typeof walletBox === 'function') walletBox(); });
  if (C.checkout) {
    Array.prototype.forEach.call(document.querySelectorAll('form.checkout'), function (form) {
      var box = form.querySelector('.af-ts[data-action="checkout"]');
      if (!box) {
        var rv = form.querySelector('#order_review');
        box = window.afTsApi.box(form, rv ? rv.nextSibling : null, 'checkout', true);
      }
      // started as the checkout page opens, so the token is ready when the
      // buyer is (a page Cash App returns to submits before anyone touches it)
      want(box);
    });
  }
})();
