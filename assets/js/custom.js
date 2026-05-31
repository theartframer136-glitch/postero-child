// Custom JS - The Art Framer Child Theme
// Do NOT interfere with parent theme sliders
jQuery(document).ready(function($) {
  console.log('Art Framer child theme loaded');

  // Remove broken video elements that produce 404 errors
  $('video').each(function() {
    var $video = $(this);
    var hasBrokenSrc = false;
    $video.find('source').each(function() {
      var src = $(this).attr('src') || '';
      if (src.match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
        hasBrokenSrc = true;
      }
    });
    if (!hasBrokenSrc && $video.attr('src') && $video.attr('src').match(/\.(mp4|webm|ogg)(\?.*)?$/i)) {
      hasBrokenSrc = true;
    }
    if (hasBrokenSrc) {
      $video.on('error', function() {
        $(this).closest('.elementor-widget-video, .elementor-background-video-container, [class*="video"]').hide();
        $(this).remove();
      });
      $video.find('source').on('error', function() {
        $video.closest('.elementor-widget-video, .elementor-background-video-container, [class*="video"]').hide();
        $video.remove();
      });
    }
  });

  // Suppress console 404 noise from missing video files by catching unhandled resource errors
  window.addEventListener('error', function(e) {
    if (e.target && e.target.tagName === 'VIDEO') {
      e.preventDefault();
      $(e.target).closest('.elementor-widget-video, .elementor-background-video-container, [class*="video-wrapper"], [class*="video-container"]').hide();
    }
    if (e.target && e.target.tagName === 'SOURCE') {
      e.preventDefault();
      var $video = $(e.target).closest('video');
      $video.closest('.elementor-widget-video, .elementor-background-video-container, [class*="video-wrapper"], [class*="video-container"]').hide();
    }
  }, true);
});
