// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {
  console.warn('Art Framer child theme loaded');

  // ---- Fix subcategory row (ul.postero-scroll-content > li.cat-item) ----
  function fixSubcatRow() {
    var $ul = $('ul.postero-scroll-content');
    if (!$ul.length) return;

    // Force flex row via inline styles — beats any JS that sets transform/left
    $ul.each(function() {
      var el = this;
      el.style.setProperty('display',         'flex',    'important');
      el.style.setProperty('flex-direction',  'row',     'important');
      el.style.setProperty('flex-wrap',       'nowrap',  'important');
      el.style.setProperty('overflow-x',      'auto',    'important');
      el.style.setProperty('overflow-y',      'visible', 'important');
      el.style.setProperty('gap',             '16px',    'important');
      el.style.setProperty('width',           '100%',    'important');
      el.style.setProperty('transform',       'none',    'important');
      el.style.setProperty('transition',      'none',    'important');
      el.style.setProperty('scrollbar-width', 'none',    'important');

      // Undo any left-offset the parent slider JS applied to children
      $(el).find('li.cat-item').each(function() {
        this.style.setProperty('flex',           '0 0 auto', 'important');
        this.style.setProperty('position',       'static',   'important');
        this.style.setProperty('left',           'auto',     'important');
        this.style.setProperty('top',            'auto',     'important');
        this.style.setProperty('display',        'flex',     'important');
        this.style.setProperty('flex-direction', 'column',   'important');
        this.style.setProperty('align-items',    'center',   'important');
        this.style.setProperty('width',          'auto',     'important');
        this.style.setProperty('float',          'none',     'important');
        this.style.setProperty('margin',         '0',        'important');
        this.style.setProperty('transform',      'none',     'important');
      });
    });
  }

  // Run after page ready, after parent theme slider init, and again later
  setTimeout(fixSubcatRow, 300);
  setTimeout(fixSubcatRow, 1000);
  setTimeout(fixSubcatRow, 2500);

  // Intercept any ongoing slider tick that repositions items
  var _origAnimate = $.fn.animate;
  $.fn.animate = function(props) {
    if (this.is('ul.postero-scroll-content') || this.closest('ul.postero-scroll-content').length) {
      return this; // block animate calls on the subcategory list
    }
    return _origAnimate.apply(this, arguments);
  };

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
