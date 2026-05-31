// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {
  console.log('Art Framer child theme loaded');

  // ---- Force subcategory circles into a single horizontal scrollable row ----
  function fixSubcatRow() {
    // Find any container that directly holds .sub-cat elements
    var $subCats = $('.sub-cat');
    if (!$subCats.length) return;

    var $container = $subCats.first().parent();

    // Log actual container info so we can verify
    console.log('subcategory container tag:', $container.prop('tagName'),
                'id:', $container.attr('id'),
                'class:', $container.attr('class'));

    // Force it into a single scrollable flex row
    $container.css({
      'display':         'flex',
      'flex-direction':  'row',
      'flex-wrap':       'nowrap',
      'overflow-x':      'auto',
      'overflow-y':      'visible',
      'gap':             '16px',
      'width':           '100%',
      'padding-bottom':  '8px',
      'scrollbar-width': 'none'
    });

    // Each sub-cat: prevent shrinking / wrapping
    $subCats.css({
      'flex':            '0 0 auto',
      'display':         'flex',
      'flex-direction':  'column',
      'align-items':     'center',
      'text-align':      'center',
      'width':           'auto'
    });

    // Circular images
    $subCats.find('img').css({
      'width':         '90px',
      'height':        '90px',
      'border-radius': '50%',
      'object-fit':    'cover',
      'border':        '3px solid #c9a84c',
      'display':       'block',
      'margin':        '0 auto 8px'
    });

    // Labels
    $subCats.find('span').css({
      'font-size':   '12px',
      'font-weight': '600',
      'color':       '#333',
      'white-space': 'nowrap'
    });

    // Hide sub-navigation arrows if present
    $container.siblings('.sub-nav').hide();
  }

  // Run immediately and also after a short delay (in case Elementor renders late)
  fixSubcatRow();
  setTimeout(fixSubcatRow, 500);
  setTimeout(fixSubcatRow, 1500);

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
