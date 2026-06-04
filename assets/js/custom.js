// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {

  // ---- Universal product card slider ----
  // Works on any ul.products, whether or not .product-container exists
  function initProductSliders() {
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
