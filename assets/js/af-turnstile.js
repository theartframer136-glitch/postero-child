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
    try { return window.turnstile.getResponse(t.id) || t.tok || ''; } catch (e) { return t.tok || ''; }
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
        theme: 'light',
        size: compact ? 'compact' : 'flexible',
        language: C.lang || 'auto',
        retry: 'auto',
        'refresh-expired': 'auto',
        callback: function (tok) { t.tok = tok; t.interactive = false; note(box, ''); release(box, false); },
        // refresh-expired: auto fetches the next one by itself
        'expired-callback': function () { t.tok = ''; },
        'before-interactive-callback': function () { t.interactive = true; if (t.hold) note(box, C.msg.tick); },
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
    note(box, t.interactive ? C.msg.tick : C.msg.wait);
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

  // When to draw: a box on screen, or any field of its form used. The
  // popup's box only on use: the popup sits in every page's header, and an
  // unopened popup must not fetch Cloudflare's script on every page view.
  var boxes = Array.prototype.filter.call(document.querySelectorAll('.af-ts'), function (b) { return !b.closest(POPUP); });
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

  window.afTsState = function () {
    return {
      script: state + (slow ? ' (slow)' : ''),
      boxes: Array.prototype.map.call(document.querySelectorAll('.af-ts'), function (b) {
        var t = b.__afTs || {};
        return {
          action: b.getAttribute('data-action'),
          inPopup: !!b.closest(POPUP),
          drawn: t.id !== null && t.id !== undefined,
          compact: b.classList.contains('af-ts-compact'),
          token: !!token(b),
          iframe: !!b.querySelector('iframe')
        };
      }),
      errors: errors.slice()
    };
  };
})();
