// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {

  // ---- Universal product card slider (homepage only) ----
  // Only runs on the front page — shop/category pages stay as normal grids
  function initProductSliders() {
    if (!document.body.classList.contains('home')) return;
    document.querySelectorAll('ul.products').forEach(function(track) {
      // Skip if already initialized
      if (track.dataset.sliderInit) return;
      track.dataset.sliderInit = '1';

      // Check if already inside a .product-container with .prod-nav buttons
      var container = track.closest('.product-container');
      var hasNavBtns = container && container.querySelector('.prod-nav');

      if (!hasNavBtns) {
        // Wrap the ul in a slider shell
        var wrapper = document.createElement('div');
        wrapper.className = 'af-slider-wrapper';

        var prevBtn = document.createElement('button');
        prevBtn.className = 'af-nav af-prev';
        prevBtn.innerHTML = '&#8249;';
        prevBtn.setAttribute('aria-label', 'Previous');

        var nextBtn = document.createElement('button');
        nextBtn.className = 'af-nav af-next';
        nextBtn.innerHTML = '&#8250;';
        nextBtn.setAttribute('aria-label', 'Next');

        var viewport = document.createElement('div');
        viewport.className = 'af-slider-viewport';

        track.parentNode.insertBefore(wrapper, track);
        wrapper.appendChild(prevBtn);
        wrapper.appendChild(viewport);
        wrapper.appendChild(nextBtn);
        viewport.appendChild(track);

        bindSlider(track, prevBtn, nextBtn);
      } else {
        var prevBtn2 = container.querySelector('.prev-prod');
        var nextBtn2 = container.querySelector('.next-prod');
        bindSlider(track, prevBtn2, nextBtn2);
      }
    });
  }

  function bindSlider(track, prevBtn, nextBtn) {
    var currentIndex = 0;

    function getVisibleCount() {
      var w = window.innerWidth;
      if (w <= 576)  return 1;
      if (w <= 991)  return 3;
      return 5;
    }

    function getCards() {
      return Array.from(track.querySelectorAll('li.product'));
    }

    function getCardWidth() {
      var cards = getCards();
      if (!cards.length) return 0;
      var gap = parseFloat(getComputedStyle(track).gap) || 16;
      return cards[0].offsetWidth + gap;
    }

    function slideTo(index) {
      var cards = getCards();
      var visible = getVisibleCount();
      var max = Math.max(0, cards.length - visible);
      currentIndex = Math.max(0, Math.min(index, max));
      track.style.transform = 'translateX(' + -(currentIndex * getCardWidth()) + 'px)';
    }

    if (prevBtn) prevBtn.addEventListener('click', function() { slideTo(currentIndex - getVisibleCount()); });
    if (nextBtn) nextBtn.addEventListener('click', function() { slideTo(currentIndex + getVisibleCount()); });
    window.addEventListener('resize', function() { slideTo(0); });
  }

  initProductSliders();

  // ---- Subcategory row: click-and-hold to drag left/right ----
  // Touch devices already scroll natively via overflow-x:auto; this adds
  // the same "click and drag" gesture for mouse users so every subcategory
  // can be reached without relying only on the arrow buttons.
  function initDragScroll(el) {
    if (el.dataset.dragInit) return;
    el.dataset.dragInit = '1';
    var isDown = false, startX = 0, startScroll = 0, moved = false;
    el.classList.add('af-drag-scroll');

    el.addEventListener('pointerdown', function(e) {
      if (e.pointerType !== 'mouse') return; // let touch keep native momentum scroll
      isDown = true;
      moved = false;
      startX = e.clientX;
      startScroll = el.scrollLeft;
      // Deliberately NO setPointerCapture here, and — just as important — NO
      // af-dragging class yet. Both retarget the follow-up click away from
      // the circle: capture does it directly, and af-dragging does it through
      // the stylesheet's `.af-dragging * { pointer-events:none }` rule, which
      // makes the pointerup hit-test miss every child so the click lands on
      // this container instead of the circle's link. That rule armed on
      // pointerdown is why a plain mouse click on a circle did nothing while
      // DevTools' touch emulation (which skips this handler) worked fine.
      // Both engage only once real dragging starts (below).
    });

    el.addEventListener('pointermove', function(e) {
      if (!isDown) return;
      var dx = e.clientX - startX;
      if (!moved && Math.abs(dx) > 3) {
        moved = true;
        // now it is a drag, not a click — safe to capture and to arm the
        // af-dragging class (grabbing cursor + pointer-events:none on the
        // children) so the drag survives the cursor leaving the row and no
        // link gets hovered mid-drag
        el.classList.add('af-dragging');
        if (el.setPointerCapture) { try { el.setPointerCapture(e.pointerId); } catch (err) {} }
      }
      if (moved) el.scrollLeft = startScroll - dx;
    });

    function stopDrag(e) {
      isDown = false;
      el.classList.remove('af-dragging');
      if (e && el.hasPointerCapture && el.hasPointerCapture(e.pointerId)) {
        try { el.releasePointerCapture(e.pointerId); } catch (err) {}
      }
      // A stale moved=true silently swallows the NEXT genuine click (the
      // click suppressor below checks it). Only a pointerup is followed by a
      // click, and that click arrives within the same input sequence — so on
      // any other ending (pointerleave, pointercancel: the drag left the row)
      // clear the flag now, and after a pointerup let the follow-up click be
      // suppressed but expire the flag shortly after in case none arrives.
      if (!e || e.type !== 'pointerup') { moved = false; }
      else if (moved) { setTimeout(function () { moved = false; }, 400); }
    }
    el.addEventListener('pointerup', stopDrag);
    el.addEventListener('pointerleave', stopDrag);
    el.addEventListener('pointercancel', stopDrag);

    // The circles are links, and links are natively draggable: without this the
    // browser starts a link-drag on the first few pixels of movement, fires
    // pointercancel, and kills the scroll gesture. (Capturing on pointerdown
    // used to suppress that as a side effect — but it also swallowed clicks.)
    el.addEventListener('dragstart', function(e) {
      if (isDown || moved) e.preventDefault();
    });

    // A real drag shouldn't also trigger the subcategory link underneath the pointer
    el.addEventListener('click', function(e) {
      if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
    }, true);
  }

  function initSubcategoryDragScroll() {
    // #subcategorySlider / .subcategory-slider is how the parent theme
    // actually renders this row on most pages; ul.postero-scroll-content is
    // a fallback markup seen elsewhere. Cover both.
    document.querySelectorAll('ul.postero-scroll-content, #subcategorySlider, .subcategory-slider').forEach(initDragScroll);
  }
  initSubcategoryDragScroll();
  try {
    new MutationObserver(initSubcategoryDragScroll).observe(document.body, { childList: true, subtree: true });
  } catch (e) {}

  // ---- Archive/category product cards: strip the "Add to Cart" label so it
  // matches the icon-only wishlist/quick-view buttons, in its existing
  // position — no repositioning. The label reappears as a native title
  // tooltip, same as the wishlist/quick-view icons already use. Skips the
  // homepage carousel, which already shows its own always-visible
  // "Add to Cart" text button.
  function initCartIconOnly() {
    document.querySelectorAll('a.add_to_cart_button, button.add_to_cart_button').forEach(function(cart) {
      if (cart.dataset.iconOnlyInit) return;
      if (cart.closest('.product-slider, .af-shell-track, #productGrid')) return;
      cart.dataset.iconOnlyInit = '1';

      var label = (cart.textContent || '').trim() || 'Add to Cart';
      Array.from(cart.childNodes)
        .filter(function(n) { return n.nodeType === 3 && n.textContent.trim() !== ''; })
        .forEach(function(n) { cart.removeChild(n); });
      var labelSpan = document.createElement('span');
      labelSpan.className = 'af-icon-label';
      labelSpan.textContent = label;
      cart.appendChild(labelSpan);
      cart.setAttribute('title', label);
      cart.setAttribute('aria-label', label);
      if (!cart.querySelector('svg, img, i, .dashicons, .af-icon-glyph')) {
        var glyph = document.createElement('span');
        glyph.className = 'af-icon-glyph';
        glyph.setAttribute('aria-hidden', 'true');
        glyph.textContent = '🛍'; // shopping bag
        cart.insertBefore(glyph, cart.firstChild);
      }
      cart.classList.add('af-icon-only');
    });
  }
  initCartIconOnly();
  try {
    new MutationObserver(initCartIconOnly).observe(document.body, { childList: true, subtree: true });
  } catch (e) {}

  // ---- Archive/category product cards: group Add to Cart, Compare,
  // Wishlist and Quick View together, side by side, in the bottom-right
  // corner of the image. Cart/Compare are already icon-only (see above and
  // functions.php); wishlist/quick-view are left as whatever the theme/
  // plugin already renders — only their position moves.
  function initCardIconCorner() {
    document.querySelectorAll('.woocommerce ul.products li.product, .woocommerce-page ul.products li.product').forEach(function(card) {
      if (card.dataset.iconCornerInit) return;
      if (card.closest('.product-slider, .af-shell-track, #productGrid')) return;

      var cart = card.querySelector('a.add_to_cart_button, button.add_to_cart_button');
      var cmp  = card.querySelector('.af-cmp-btn:not(.af-cmp-single)');
      var wish = card.querySelector('a.add_to_wishlist, .yith-wcwl-add-to-wishlist a, .yith-wcwl-add-button a, [class*="wishlist"] a, [class*="wishlist"] button');
      var qv   = card.querySelector('.yith-wcqv-button, [class*="quick-view"], [class*="quickview"], [data-quick-view]');
      if (!cart && !cmp && !wish && !qv) return; // nothing rendered into this card yet — retry on the next pass

      card.dataset.iconCornerInit = '1';

      var imgWrap = card.querySelector('.af-img-wrap') || card.querySelector('.product-img-wrap') || card.querySelector('.woocommerce-loop-product__link') || card;
      if (getComputedStyle(imgWrap).position === 'static') {
        imgWrap.style.setProperty('position', 'relative', 'important');
      }
      var row = document.createElement('div');
      row.className = 'af-icon-corner';
      imgWrap.appendChild(row);

      [cart, cmp, wish, qv].forEach(function(el) {
        if (!el) return;
        row.appendChild(el.closest('a, button') || el);
      });
    });
  }
  initCardIconCorner();
  try {
    new MutationObserver(initCardIconCorner).observe(document.body, { childList: true, subtree: true });
  } catch (e) {}

  // ---- Pre-set USD currency cookie so plugin initialises with USD ----
  (function() {
    var opts = '; path=/; max-age=' + (86400 * 365);
    document.cookie = 'woocs_session_currency=USD' + opts;
    document.cookie = 'wmc_current_currency=USD' + opts;
    document.cookie = 'wmc-currency=USD' + opts;
    document.cookie = 'currency=USD' + opts;
    document.cookie = 'chosen_currency=USD' + opts;
  })();

  // ---- Carry the coupon notice across the cart reload (H-01) ----
  //
  // Measured on the live cart, 22 Sep 2026. Applying a coupon produces:
  //
  //     200  /?wc-ajax=apply_coupon
  //     200  /cart/                   <- a full page load, immediately after
  //
  // and the cart that comes back has two empty notice wrappers and nothing
  // in them. The server is not at fault — POSTing the same form without the
  // AJAX returns the error in the bytes, verbatim:
  //
  //     <ul class="woocommerce-error" role="alert"><li> Coupon "..." cannot
  //     be applied because it does not exist. </li></ul>
  //
  // WooCommerce's apply_coupon AJAX handler ends with wc_print_notices(),
  // which prints AND clears. Core cart.js inserts that HTML into the page,
  // and then something in the parent theme reloads /cart/ and throws it
  // away. By the time the reloaded page is built the queue is empty, so no
  // server-side hook can put it back — which is why five of them on
  // woocommerce_before_cart fired perfectly and rendered nothing. The
  // reload lives in postero's page-cart.min.js, which is not in this
  // repository, so this catches the notice on its way past instead of
  // arguing with whatever triggers the reload.
  (function() {
    var KEY = 'af_cart_notice';
    var store;
    try { store = window.sessionStorage; store.getItem(KEY); } catch (e) { return; }

    // On its way past: keep whatever the coupon call answered with.
    $(document).ajaxSuccess(function(e, xhr, settings) {
      var url = (settings && settings.url) || '';
      if (url.indexOf('apply_coupon') === -1 && url.indexOf('remove_coupon') === -1) return;
      var html = (xhr && xhr.responseText) || '';
      if (!/woocommerce-(error|message|info)/.test(html)) return;
      try { store.setItem(KEY, JSON.stringify({ at: Date.now(), html: html })); } catch (err) {}
    });

    // After the reload: put it back, but only if this page has none of its
    // own, and only if it is seconds old. A stash that outlives its reload
    // would otherwise greet the next visit to the cart with a stale error.
    var raw;
    try { raw = store.getItem(KEY); store.removeItem(KEY); } catch (err) { return; }
    if (!raw) return;
    var saved;
    try { saved = JSON.parse(raw); } catch (err) { return; }
    if (!saved || !saved.html || Date.now() - saved.at > 15000) return;
    // .cart-empty is "Your cart is currently empty", which is the page, not
    // a notice, and must not count as one already being shown.
    if (document.querySelector('.woocommerce-error, .woocommerce-message, .woocommerce-info:not(.cart-empty)')) return;
    var wrap = document.querySelector('.woocommerce-notices-wrapper');
    if (wrap) { wrap.innerHTML = saved.html; return; }
    // Both wrappers on this cart measured empty while a real notice rendered
    // somewhere else entirely, so do not assume one is there to fill.
    var form = document.querySelector('.woocommerce-cart-form, .woocommerce');
    if (form) form.insertAdjacentHTML('beforebegin', saved.html);
  })();

  // ---- DEF-01: wire up the pop-up's email capture ----
  //
  // Measured on the live home page, 22 Sep 2026:
  //
  //   div#afOverlay.af-overlay.active
  //     div.af-popup > div.af-popup-right > div.af-input-group
  //       <input type="email" placeholder="Enter your email">   name=null
  //       <button> "SAVE MORE MONEY*"                            no type
  //
  //   POST requests after pressing the button : 0
  //   input value after the click             : unchanged
  //   overlay still visible                   : true
  //
  // No form, no name, no handler, nothing sent. Every address typed into the
  // site's most prominent call to action was discarded.
  //
  // The overlay belongs to a plugin — none of that markup is in this theme,
  // so it cannot be fixed at the source from here. What can be done is to
  // teach it to speak to the subscribe endpoint this theme already has:
  // af_nl_subscribe (functions.php), which the footer form uses, with a
  // nonce, a honeypot, rate limiting, validation and dedupe already in place.
  // Nothing new is invented; the field is simply connected to the thing that
  // was always there.
  //
  // Delegated from document on purpose. The overlay is injected about nine
  // seconds after load, so a handler bound at ready() to an element that does
  // not exist yet would bind to nothing — which is exactly how the H-01 fix
  // earlier today managed to be deployed and do nothing at all.
  (function () {
    var SENT = 'afNlWired';

    function group(el) { return el && el.closest ? el.closest('.af-input-group') : null; }

    function emailIn(g) {
      return g ? g.querySelector('input[type="email"], input[placeholder*="mail" i]') : null;
    }

    function tell(g, text, ok) {
      var msg = g.querySelector('.af-nl-msg');
      if (!msg) {
        msg = document.createElement('p');
        msg.className = 'af-nl-msg';
        msg.setAttribute('role', 'status');
        msg.setAttribute('aria-live', 'polite');
        msg.style.cssText = 'margin:8px 0 0;font-size:12.5px;line-height:1.4;';
        g.parentNode.insertBefore(msg, g.nextSibling);
      }
      msg.style.color = ok ? '#1a7f46' : '#b3261e';
      msg.textContent = text;
    }

    function submit(g) {
      var input = emailIn(g);
      if (!input) return;
      var email = (input.value || '').trim();

      // Validate here as well as on the server, so the common mistake is
      // answered instantly instead of costing a round trip on a site that
      // takes seven seconds to answer one.
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        tell(g, 'Please enter a valid email address.', false);
        input.focus();
        return;
      }
      if (g.dataset[SENT] === 'busy') return;   // no double submits
      g.dataset[SENT] = 'busy';

      var btn = g.querySelector('button, [role="button"]');
      var label = btn ? btn.textContent : '';
      if (btn) { btn.disabled = true; btn.textContent = '…'; }

      var cfg = window.af_ajax || {};
      var fd = new FormData();
      fd.append('action', 'af_nl_subscribe');
      fd.append('af_nl_email', email);
      fd.append('af_nl_hp', '');                 // honeypot stays empty
      fd.append('nonce', cfg.nl_nonce || '');

      fetch(cfg.url || '/wp-admin/admin-ajax.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          var m = (res && res.data && res.data.message) ? res.data.message : null;
          if (res && res.success) {
            tell(g, m || 'Thanks — you are subscribed!', true);
            input.value = '';
          } else {
            tell(g, m || 'Something went wrong — please try again.', false);
          }
        })
        .catch(function () {
          // A stale nonce answers "-1" rather than JSON and lands here. The
          // page is cached and WordPress nonces expire in a day, so this is a
          // real case, not a theoretical one — say something a person can act
          // on instead of failing silently, which is the bug being fixed.
          tell(g, 'Could not reach the server — please refresh and try again.', false);
        })
        .then(function () {
          g.dataset[SENT] = '';
          if (btn) { btn.disabled = false; btn.textContent = label; }
        });
    }

    // The input carries no name and no required flag. Both can be set from
    // here, which makes it a real form control for autofill and for anything
    // that inspects it, without touching the plugin's markup or layout.
    function adopt(g) {
      var input = emailIn(g);
      if (!input || g.dataset.afNlAdopted) return;
      g.dataset.afNlAdopted = '1';
      if (!input.getAttribute('name')) input.setAttribute('name', 'af_nl_email');
      if (!input.hasAttribute('required')) input.setAttribute('required', '');
      if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', 'Email address');
      var btn = g.querySelector('button');
      if (btn && !btn.getAttribute('type')) btn.setAttribute('type', 'button');
    }

    document.addEventListener('click', function (e) {
      try {
        var g = group(e.target);
        if (!g || !emailIn(g)) return;
        var btn = e.target.closest('button, [role="button"]');
        if (!btn || !g.contains(btn)) return;
        e.preventDefault();
        adopt(g);
        submit(g);
      } catch (err) {}
    }, false);

    document.addEventListener('keydown', function (e) {
      try {
        if (e.key !== 'Enter') return;
        var t = e.target;
        if (!t || t.tagName !== 'INPUT') return;
        var g = group(t);
        if (!g || emailIn(g) !== t) return;
        e.preventDefault();
        adopt(g);
        submit(g);
      } catch (err) {}
    }, false);

    // Adopt whatever is already on the page, and whatever arrives later.
    function sweep() {
      try { document.querySelectorAll('.af-input-group').forEach(adopt); } catch (e) {}
    }
    sweep();
    try { new MutationObserver(sweep).observe(document.body, { childList: true, subtree: true }); } catch (e) {}
  })();

  // ---- Suppress 404 errors from missing video files ----
  window.addEventListener('error', function(e) {
    if (e.target && (e.target.tagName === 'VIDEO' || e.target.tagName === 'SOURCE')) {
      e.preventDefault();
      $(e.target).closest(
        '.elementor-widget-video, .elementor-background-video-container, [class*="video-wrapper"], [class*="video-container"]'
      ).hide();
    }
  }, true);
});
