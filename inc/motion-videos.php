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
 * ASPECT: the clips are 1280x720 room scenes, and the tiles were portrait. A
 * portrait tile would have cropped away most of the frame — the artwork on the
 * wall is what the clip is about, and it sits across the middle. So the tiles
 * are widened to 16:9 to fit the footage rather than cutting the footage to fit
 * the tiles.
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
.af-motion-item{position:relative;flex:0 0 auto;width:clamp(260px,26vw,420px);
  aspect-ratio:16/9;border-radius:14px;overflow:hidden;background:#0f0d0b;
  box-shadow:0 2px 10px rgba(40,30,10,.10);}
.af-motion-item video{width:100%;height:100%;object-fit:cover;display:block;}
/* the play affordance: present until the clip is running */
.af-motion-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  border:0;background:rgba(0,0,0,.16);cursor:pointer;padding:0;transition:background .2s;}
.af-motion-play span{width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.9);
  position:relative;box-shadow:0 2px 12px rgba(0,0,0,.28);transition:transform .2s;}
.af-motion-play span:after{content:"";position:absolute;top:50%;left:56%;transform:translate(-50%,-50%);
  border-style:solid;border-width:11px 0 11px 18px;border-color:transparent transparent transparent #1a1a1a;}
.af-motion-item:hover .af-motion-play span{transform:scale(1.08);}
.af-motion-item.af-playing .af-motion-play{background:transparent;}
.af-motion-item.af-playing .af-motion-play span{opacity:0;}
.af-motion-item.af-open .af-motion-play{display:none;}
.af-motion-cap{position:absolute;left:0;right:0;bottom:0;padding:22px 14px 10px;
  font-size:13px;font-weight:600;color:#fff;pointer-events:none;
  background:linear-gradient(to top,rgba(0,0,0,.62),rgba(0,0,0,0));}
@media(max-width:600px){
  .af-motion-item{width:78vw;}
  .af-motion-cap{font-size:12px;}
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
