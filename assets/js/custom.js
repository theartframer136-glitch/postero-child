// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {

  // ---- Product card slider ----
  function initProductSlider() {
    var container = document.querySelector('.product-container');
    if (!container) return;

    var viewport = container.querySelector('#productGrid, .product-slider');
    if (!viewport) return;

    var track = viewport.querySelector('ul.products') || viewport;
    var prevBtn = container.querySelector('.prev-prod');
    var nextBtn = container.querySelector('.next-prod');

    var currentIndex = 0;

    function getVisibleCount() {
      var w = window.innerWidth;
      if (w <= 576)  return 1;
      if (w <= 991)  return 3;
      return 5;
    }

    function getTotalCards() {
      return track.querySelectorAll('li.product, .product-card, li').length;
    }

    function getCardWidth() {
      var card = track.querySelector('li.product, .product-card, li');
      if (!card) return 0;
      var style = getComputedStyle(track);
      var gap = parseFloat(style.gap) || 16;
      return card.offsetWidth + gap;
    }

    function slideTo(index) {
      var total = getTotalCards();
      var visible = getVisibleCount();
      var max = Math.max(0, total - visible);
      currentIndex = Math.max(0, Math.min(index, max));
      var offset = -(currentIndex * getCardWidth());
      track.style.transform = 'translateX(' + offset + 'px)';
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        slideTo(currentIndex - getVisibleCount());
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        slideTo(currentIndex + getVisibleCount());
      });
    }

    // Reset on resize
    window.addEventListener('resize', function() { slideTo(0); });
  }

  initProductSlider();

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
