// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {

  // ---- Product card slider navigation ----
  function initProductSlider() {
    var container = document.querySelector('.product-container');
    if (!container) return;
    // The scrollable element is the ul.products inside #productGrid
    var grid = container.querySelector('#productGrid, .product-slider');
    if (!grid) return;
    var track = grid.querySelector('ul.products') || grid;
    var prevBtn = container.querySelector('.prev-prod');
    var nextBtn = container.querySelector('.next-prod');

    function getScrollAmount() {
      var card = track.querySelector('li.product, .product-card, li');
      if (card) return (card.offsetWidth + 20) * 4;
      return Math.round(grid.offsetWidth);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function() {
        track.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function() {
        track.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
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
