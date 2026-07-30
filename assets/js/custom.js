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
      el.classList.add('af-dragging');
      // Deliberately NO setPointerCapture here. Capturing on pointerdown
      // retargets the follow-up click to this container, so a plain click on
      // a subcategory circle never reached its link and products stopped
      // loading. Capture only once real dragging starts (below).
    });

    el.addEventListener('pointermove', function(e) {
      if (!isDown) return;
      var dx = e.clientX - startX;
      if (!moved && Math.abs(dx) > 3) {
        moved = true;
        // now it is a drag, not a click — safe to capture so the drag
        // survives the cursor leaving the row
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
  // position — no repositioning. The label reappears as a hover/focus
  // tooltip (see [data-tooltip] CSS). Skips the homepage carousel, which
  // already shows its own always-visible "Add to Cart" text button.
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
      cart.setAttribute('data-tooltip', label);
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

  // ---- Pre-set USD currency cookie so plugin initialises with USD ----
  (function() {
    var opts = '; path=/; max-age=' + (86400 * 365);
    document.cookie = 'woocs_session_currency=USD' + opts;
    document.cookie = 'wmc_current_currency=USD' + opts;
    document.cookie = 'wmc-currency=USD' + opts;
    document.cookie = 'currency=USD' + opts;
    document.cookie = 'chosen_currency=USD' + opts;
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
