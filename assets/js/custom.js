jQuery(document).ready(function($) {
  // Wait for slick to initialize then destroy it and convert to grid
  function fixCategorySlider() {
    var slider = $('.top-category-slider');
    if (slider.length === 0) return;
    
    // If slick is initialized, destroy it
    if (slider.hasClass('slick-initialized')) {
      slider.slick('unslick');
    }
    
    // Hide nav buttons
    $('.cat-nav').hide();
    
    // Apply grid styles directly
    slider.css({
      'display': 'grid',
      'grid-template-columns': 'repeat(5, 1fr)',
      'gap': '12px',
      'width': '100%'
    });
    
    // Fix each slide
    slider.find('.slick-slide, li, .cat-item, .product-cat').css({
      'width': '100%',
      'float': 'none',
      'margin': '0'
    });
    
    // Fix images
    slider.find('img').css({
      'width': '100%',
      'height': '140px',
      'object-fit': 'cover',
      'border-radius': '8px'
    });
  }
  
  // Try immediately and after delays
  fixCategorySlider();
  setTimeout(fixCategorySlider, 500);
  setTimeout(fixCategorySlider, 1500);
  setTimeout(fixCategorySlider, 3000);
  
  // Also on window load
  $(window).on('load', function() {
    setTimeout(fixCategorySlider, 500);
  });
});
