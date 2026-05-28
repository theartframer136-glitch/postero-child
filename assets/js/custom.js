jQuery(document).ready(function($) {
  
  // Function to fix category grid whenever content loads
  function fixCatGrid(container) {
    if (!container) container = document;
    
    // Find the category display area
    var catDisplay = $(container).find('[class*="cat-display"], [class*="category-display"], [id*="cat-content"], [id*="catContent"], .elementor-widget-postero-product-categories ul, ul.products');
    
    catDisplay.each(function() {
      $(this).css({
        'display': 'grid',
        'grid-template-columns': 'repeat(5, 1fr)',
        'gap': '12px',
        'width': '100%',
        'list-style': 'none',
        'padding': '0',
        'margin': '0'
      });
      $(this).find('li, .cat-item, .product-cat').css({
        'width': '100%',
        'float': 'none',
        'margin': '0'
      });
      $(this).find('img').css({
        'width': '100%',
        'height': '140px',
        'object-fit': 'cover',
        'border-radius': '8px'
      });
    });
  }

  // Intercept AJAX calls and fix grid after response
  $(document).ajaxComplete(function(event, xhr, settings) {
    setTimeout(function() { fixCatGrid(); }, 100);
  });

  // Fix on button click
  $(document).on('click', '.top-cat-btn', function() {
    setTimeout(function() { fixCatGrid(); }, 300);
    setTimeout(function() { fixCatGrid(); }, 800);
    setTimeout(function() { fixCatGrid(); }, 1500);
  });

  // Initial fix
  fixCatGrid();
  setTimeout(function() { fixCatGrid(); }, 1000);
  
  // Also watch for DOM changes
  if (window.MutationObserver) {
    var observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length > 0) {
          setTimeout(function() { fixCatGrid(); }, 100);
        }
      });
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }
});
