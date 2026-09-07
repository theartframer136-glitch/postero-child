<?php
if (!defined('ABSPATH')) exit;
/**
 * "Products In Motion" — make the tiles actually play.
 *
 * Owner, 2026-09-07: "the videos are not playing they are just zoom in and zoom
 * out ... take the video from there and show on here".
 *
 * WHAT WAS WRONG
 * [youtube_circle_slider] renders eight <iframe>s pointing at YouTube
 * PLAYLISTS — /embed/videoseries?list=…&autoplay=1&mute=1&loop=1. A playlist
 * embed is the one form YouTube will not autoplay: it holds on the first
 * video's poster frame. So every tile was a still picture, and the only motion
 * was the slider's own CSS scale — exactly the "zoom in and zoom out" that was
 * reported. Nothing was broken; it simply never played.
 *
 * WHAT IT DOES NOW
 * Each tile becomes a self-hosted <video>, muted, looping, playsinline —
 * which browsers DO autoplay — using the studio's own clips from the media
 * library. Clicking a tile still opens the full video, as before: the poster
 * preview is swapped for the same clip with sound and controls.
 *
 * ASPECT: the clips are 1280x720 landscape; the tiles are 9:16 portrait. The
 * first version widened the tiles to 16:9 to fit the footage, and the owner
 * asked for the tall tiles back (2026-09-07) — the row of tall cards is the
 * design. So the footage is centre-cropped instead, which was checked frame by
 * frame first: on eight of the nine clips the artwork sits dead centre and the
 * crop lands on it cleanly. The two long marketing clips, whose middles carry
 * text overlays, are ordered last where they matter least.
 *
 * IT OWNS THE ROW LAYOUT TOO. The shortcode used to ship its own inline
 * stylesheet for .circle-gallery-slider / .circle-item, and rewriting the
 * shortcode's output took that with it — leaving nine unstyled divs stacked
 * down the left of the page instead of a row (reported 2026-09-07). Nothing on
 * the page defines those classes any more, so the flex row, the horizontal
 * scroll and the tile size are all declared here. The section can no longer be
 * broken by CSS that lives somewhere else.
 *
 * KIND TO THE SERVER, which matters on a CPU-capped host with a bandwidth bill:
 *   - preload="none": nothing is fetched until a tile is actually on screen
 *   - an IntersectionObserver plays only visible tiles and pauses the rest, so
 *     scrolling past the band costs one clip, not nine
 *   - the poster image is the product still, so the row looks right before a
 *     single byte of video moves
 */

/** Attachment ids of the clips, in the order they appear. Empty = do nothing. */
function af_motion_video_ids() {
    $ids = get_option('af_motion_video_ids', array());
    if (!is_array($ids) || !$ids) {
        $ids = array(33349, 33350, 33353, 33351, 33352, 33354, 33356, 33355, 33348);
    }
    return array_values(array_filter(array_map('intval', (array) apply_filters('af_motion_video_ids', $ids))));
}

/** url + title for each clip, resolved once and cached. */
function af_motion_videos() {
    $out = get_transient('af_motion_videos');
    if (is_array($out)) return $out;
    $out = array();
    foreach (af_motion_video_ids() as $id) {
        $url = wp_get_attachment_url($id);
        if (!$url) continue;
        $out[] = array(
            'url'   => $url,
            'title' => get_the_title($id) ?: 'The Art Framer',
        );
    }
    set_transient('af_motion_videos', $out, 12 * HOUR_IN_SECONDS);
    return $out;
}

/**
 * Rebuild the slider's tiles around real video.
 * Returns the original markup untouched if anything is missing or goes wrong.
 */
function af_motion_rewrite($html) {
    $videos = af_motion_videos();
    if (!$videos) return $html;
    if (strpos($html, 'circle-item') === false) return $html;

    $tiles = '';
    foreach ($videos as $i => $v) {
        $tiles .= '<div class="circle-item video-circle af-motion-item">'
                . '<video class="af-motion-video" muted loop playsinline preload="none"'
                . ' disablepictureinpicture controlslist="nodownload noplaybackrate"'
                . ' aria-label="' . esc_attr($v['title']) . '">'
                . '<source src="' . esc_url($v['url']) . '" type="video/mp4">'
                . '</video>'
                . '<button type="button" class="af-motion-play" aria-label="'
                . esc_attr__('Play', 'postero-child') . ' ' . esc_attr($v['title']) . '">'
                . '<span></span></button>'
                . '<span class="af-motion-cap">' . esc_html($v['title']) . '</span>'
                . '</div>';
    }
    if ($tiles === '') return $html;

    // Keep the slider's own wrapper so its arrows and drag-scroll keep working.
    $new = preg_replace(
        '#(<div class="circle-gallery-slider"[^>]*>).*?(</div>)\s*$#s',
        '$1' . str_replace('\\', '\\\\', str_replace('$', '\\$', $tiles)) . '$2',
        $html, 1, $count
    );
    if (!$count || !$new) {
        // wrapper not shaped as expected — wrap our own rather than lose the band
        $new = '<div class="circle-gallery-slider af-motion-slider">' . $tiles . '</div>';
    }
    return $new;
}

add_filter('do_shortcode_tag', function ($output, $tag) {
    if ($tag !== 'youtube_circle_slider') return $output;
    try {
        return af_motion_rewrite($output);
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('af-motion: ' . $e->getMessage());
        return $output;                       // never lose the section over this
    }
}, 20, 2);

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return; ?>
<style>
/* The row itself. The shortcode's own stylesheet no longer reaches the page,
   so these are declared here rather than inherited from it. The existing
   arrows and drag-scroll both call scrollBy() on this element, so it has to
   stay a horizontal scroller for them to keep working. */
.circle-gallery-slider{display:flex;flex-wrap:nowrap;gap:14px;overflow-x:auto;
  overflow-y:hidden;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;
  padding:2px 0 14px;scrollbar-width:none;-ms-overflow-style:none;}
.circle-gallery-slider::-webkit-scrollbar{display:none;}
.circle-gallery-slider.dragging{scroll-behavior:auto;cursor:grabbing;}
.af-motion-item{position:relative;flex:0 0 auto;width:clamp(180px,19vw,364px);
  aspect-ratio:9/16;border-radius:14px;overflow:hidden;background:#0f0d0b;
  box-shadow:0 2px 10px rgba(40,30,10,.10);cursor:pointer;}
.af-motion-item video{width:100%;height:100%;object-fit:cover;display:block;}
/* No play button by default: the tile is already moving, so a badge over it
   would only be clutter. It appears on hover to say "click for sound". */
.af-motion-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  border:0;background:rgba(0,0,0,.18);cursor:pointer;padding:0;
  opacity:0;transition:opacity .2s;}
.af-motion-item:hover .af-motion-play,
.af-motion-item:focus-within .af-motion-play{opacity:1;}
.af-motion-play span{width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.92);
  position:relative;box-shadow:0 2px 12px rgba(0,0,0,.3);}
.af-motion-play span:after{content:"";position:absolute;top:50%;left:56%;transform:translate(-50%,-50%);
  border-style:solid;border-width:10px 0 10px 17px;border-color:transparent transparent transparent #1a1a1a;}
.af-motion-item.af-open .af-motion-play{display:none;}
/* caption on hover only, so the row reads as clean video at rest */
.af-motion-cap{position:absolute;left:0;right:0;bottom:0;padding:26px 12px 11px;
  font-size:12.5px;font-weight:600;color:#fff;pointer-events:none;
  opacity:0;transition:opacity .2s;
  background:linear-gradient(to top,rgba(0,0,0,.68),rgba(0,0,0,0));}
.af-motion-item:hover .af-motion-cap{opacity:1;}
@media(max-width:600px){
  .af-motion-item{width:60vw;}
  .af-motion-cap{opacity:1;font-size:12px;}
}
</style>
<?php }, 20);

add_action('wp_footer', function () {
    if (!is_front_page() && !is_home()) return; ?>
<script>
(function(){
  var items = document.querySelectorAll('.af-motion-item');
  if (!items.length) return;

  // Only what is on screen plays. Nine clips autoplaying at once would cost the
  // visitor's bandwidth and the server's, for tiles nobody is looking at.
  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      var v = e.target.querySelector('video');
      if (!v) return;
      if (e.isIntersecting){
        if (v.preload === 'none') v.preload = 'auto';
        var p = v.play();
        if (p && p.catch) p.catch(function(){});     // autoplay refused: leave the poster
        e.target.classList.add('af-playing');
      } else if (!e.target.classList.contains('af-open')) {
        v.pause();
        e.target.classList.remove('af-playing');
      }
    });
  }, {threshold: 0.25}) : null;

  items.forEach(function(item){
    var v = item.querySelector('video');
    if (!v) return;
    if (io) io.observe(item);
    else { v.preload = 'auto'; v.play().catch(function(){}); item.classList.add('af-playing'); }

    // Click opens the full clip, as it did before: sound on, controls, from
    // the start, and no longer muted-loop wallpaper.
    item.addEventListener('click', function(){
      if (item.classList.contains('af-open')) return;
      item.classList.add('af-open');
      v.muted = false; v.loop = false; v.controls = true;
      try { v.currentTime = 0; } catch(e){}
      v.play().catch(function(){ v.muted = true; v.play().catch(function(){}); });
    });

    v.addEventListener('ended', function(){
      item.classList.remove('af-open');
      v.controls = false; v.muted = true; v.loop = true;
      v.play().catch(function(){});
    });
  });
})();
</script>
<?php }, 60);
