// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {

  // ---- Product card slider navigation ----
  function initProductSlider() {
    var container = document.querySelector('.product-container');
    if (!container) return;
    var grid = container.querySelector('#productGrid, .product-slider');
    var prevBtn = container.querySelector('.prev-prod');
    var nextBtn = container.querySelector('.next-prod');
    if (!grid) return;

    function getScrollAmount() {
      var card = grid.querySelector('li, .product-card, article');
      if (card) return card.offsetWidth + 20;
      return Math.round(grid.offsetWidth / 4) + 20;
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        grid.scrollBy({ left: -getScrollAmount() * 4, behavior: 'smooth' });
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        grid.scrollBy({ left: getScrollAmount() * 4, behavior: 'smooth' });
      });
    }
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
