// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {
  console.warn('Art Framer child theme loaded');

  // ---- Find and fix subcategory row ----
  // Tries many possible selectors; call repeatedly via MutationObserver
  var fixed = false;

  function tryFixSubcat() {
    if (fixed) return;

    // Broad list of possible selectors for subcategory circle items
    var candidates = [
      '.sub-cat', '.subcategory-slider .sub-cat',
      '#subcategorySlider > *', '.subcategory-slider > *',
      '.category-item', '.cat-item',
      '[class*="subcat"] > *', '[class*="sub-cat"] > *',
      '.product-categories > li', 'ul.product-categories > li',
      '.elementor-widget-woocommerce-product-categories li',
      '.products-cats-list li', '.product_cat',
      '[class*="collection"] li', '[class*="category"] li'
    ];

    var $items = $();
    var matchedSel = '';
    for (var i = 0; i < candidates.length; i++) {
      var $found = $(candidates[i]);
      if ($found.length > 3) { // must be a real list, not a fluke
        $items = $found;
        matchedSel = candidates[i];
        break;
      }
    }

    if (!$items.length) {
      // Fallback: find any UL/DIV/OL that contains 10+ children with images
      $('ul, div, ol').each(function() {
        var $ch = $(this).children();
        if ($ch.length >= 10 && $ch.filter(':has(img)').length >= 8) {
          $items = $ch;
          matchedSel = '(auto-detected) ' + this.tagName + '#' + this.id + '.' + this.className;
          return false; // break
        }
      });
    }

    if (!$items.length) return; // not found yet

    fixed = true;
    var $container = $items.first().parent();
    console.warn('Subcategory container found via:', matchedSel,
                 '| el:', $container.prop('tagName'),
                 '| id:', $container.attr('id'),
                 '| class:', $container.attr('class'));

    // Force single horizontal scrollable row via inline styles (beats Elementor)
    $container[0].style.setProperty('display',         'flex',    'important');
    $container[0].style.setProperty('flex-direction',  'row',     'important');
    $container[0].style.setProperty('flex-wrap',       'nowrap',  'important');
    $container[0].style.setProperty('overflow-x',      'auto',    'important');
    $container[0].style.setProperty('overflow-y',      'visible', 'important');
    $container[0].style.setProperty('gap',             '16px',    'important');
    $container[0].style.setProperty('width',           '100%',    'important');
    $container[0].style.setProperty('padding-bottom',  '8px',     'important');
    $container[0].style.setProperty('scrollbar-width', 'none',    'important');

    $items.each(function() {
      this.style.setProperty('flex',           '0 0 auto', 'important');
      this.style.setProperty('display',        'flex',     'important');
      this.style.setProperty('flex-direction', 'column',   'important');
      this.style.setProperty('align-items',    'center',   'important');
      this.style.setProperty('text-align',     'center',   'important');
      this.style.setProperty('width',          'auto',     'important');
      this.style.setProperty('float',          'none',     'important');
      this.style.setProperty('margin',         '0',        'important');

      var img = $(this).find('img')[0];
      if (img) {
        img.style.setProperty('width',         '90px',              'important');
        img.style.setProperty('height',        '90px',              'important');
        img.style.setProperty('border-radius', '50%',               'important');
        img.style.setProperty('object-fit',    'cover',             'important');
        img.style.setProperty('border',        '3px solid #c9a84c', 'important');
        img.style.setProperty('display',       'block',             'important');
        img.style.setProperty('margin',        '0 auto 8px',        'important');
      }

      var span = $(this).find('span, p, a')[0];
      if (span) {
        span.style.setProperty('font-size',   '12px',   'important');
        span.style.setProperty('font-weight', '600',    'important');
        span.style.setProperty('color',       '#333',   'important');
        span.style.setProperty('white-space', 'nowrap', 'important');
      }
    });

    // Hide sub-nav arrows
    $container.siblings('[class*="sub-nav"], [class*="subnav"]').hide();
  }

  // Run immediately and on delay
  tryFixSubcat();
  setTimeout(tryFixSubcat, 800);
  setTimeout(tryFixSubcat, 2000);

  // Watch for Elementor injecting elements late
  var observer = new MutationObserver(function() {
    if (!fixed) tryFixSubcat();
  });
  observer.observe(document.body, { childList: true, subtree: true });

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
