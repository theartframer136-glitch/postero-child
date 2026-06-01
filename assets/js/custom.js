// Custom JS - The Art Framer Child Theme
jQuery(document).ready(function($) {
  console.warn('Art Framer child theme loaded');

  // ---- DIAGNOSTIC: Find Postero slider init code in inline scripts ----
  document.querySelectorAll('script:not([src])').forEach(function(s) {
    var t = s.textContent;
    if (t.includes('postero-scroll') || t.includes('cat-item') || t.includes('scroll-content') || t.includes('subcategor')) {
      console.warn('SLIDER SCRIPT FOUND:', t.substring(0, 600));
    }
  });

  // Log all jQuery plugin names that contain "scroll", "slider", "carousel"
  Object.keys($.fn).forEach(function(k) {
    if (/scroll|slider|carousel|postero/i.test(k)) {
      console.warn('jQuery plugin:', k);
    }
  });

  // Log all global window vars matching postero/scroll/slider
  Object.keys(window).forEach(function(k) {
    if (/postero|Postero|scroll_|slider_/i.test(k)) {
      console.warn('Global var:', k, typeof window[k]);
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
