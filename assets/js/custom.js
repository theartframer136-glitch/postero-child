// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {
  console.log('Art Framer child theme loaded');

  // ---- DIAGNOSTIC: log what's actually in the Shop by Collection section ----
  // Find the section containing the circular category images
  var diagnosticSelectors = [
    '.sub-cat', '.subcategory-slider', '#subcategorySlider',
    '.category-item', '.product-category', '.cat-item',
    '.elementor-widget-woocommerce-product-categories .products li',
    '.product_cat', '[class*="subcat"]', '[class*="sub_cat"]',
    '[id*="subcat"]', '[id*="sub_cat"]', '[id*="subcategory"]',
    '.product-categories li', 'ul.product-categories > li'
  ];

  console.log('--- Subcategory selector scan ---');
  diagnosticSelectors.forEach(function(sel) {
    var n = $(sel).length;
    if (n > 0) {
      var first = $(sel).first();
      console.log('FOUND [' + n + '] via "' + sel + '" | parent tag:', first.parent().prop('tagName'), '| parent id:', first.parent().attr('id'), '| parent class:', first.parent().attr('class'));
    }
  });

  // Also log anything that looks like a circular-image grid near "Shop"
  $('img').each(function() {
    var $img = $(this);
    var cs = window.getComputedStyle(this);
    if (cs.borderRadius && parseInt(cs.borderRadius) > 30) {
      var $p = $img.parent();
      var $gp = $p.parent();
      console.log('Circular img found | parent:', $p.prop('tagName'), $p.attr('class'), '| grandparent:', $gp.prop('tagName'), $gp.attr('class'));
    }
  });

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
