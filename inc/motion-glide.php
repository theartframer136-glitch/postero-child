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
        // All ten clips from the studio's folder. The two long marketing
        // films are last: their middles carry text overlays, which a 9:16
        // centre crop lands badly on.
        $ids = array(33349, 33350, 33353, 33351, 33352, 33357,
                     33354, 33356, 33355, 33348);
    }
    return array_values(array_filter(array_map('intval', (array) apply_filters('af_motion_video_ids', $ids))));
}

/** url + title for each clip, resolved once and cached. */
function af_motion_videos() {
    // Key derived from the id list, so adding or reordering a clip picks a new
    // cache entry by itself. A fixed key held a stale nine-clip list for twelve
    // hours after the tenth was added, and the row went on showing nine.
    $key = 'af_motion_videos_' . substr(md5(implode(',', af_motion_video_ids())), 0, 10);
    $out = get_transient($key);
    if (is_array($out)) return $out;
    $out = array();
    foreach (af_motion_video_ids() as $id) {
        $url = wp_get_attachment_url($id);
        if (!$url) continue;
        // The YouTube id for this clip, so a click can open the video on
        // YouTube as it did before these tiles became self-hosted. The
        // matcher in functions.php already stores videoid => local URL; this
        // is that map read backwards, and it costs nothing.
        $yt = '';
        $local = get_option('af_pim_local');
        if (is_array($local)) {
            foreach ($local as $vid => $vurl) {
                if ($vurl === $url || basename((string) $vurl) === basename((string) $url)) {
                    $yt = (string) $vid;
                    break;
                }
            }
        }
        $out[] = array(
            'url'   => $url,
            'title' => get_the_title($id) ?: 'The Art Framer',
            'yt'    => $yt,
        );
    }
    set_transient($key, $out, 12 * HOUR_IN_SECONDS);
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
        $tiles .= '<div class="circle-item video-circle af-motion-item"'
                . (!empty($v['yt']) ? ' data-yt="' . esc_attr($v['yt']) . '"' : '') . '>'
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

    $tiles .= '';   // arrows are siblings of the track, added below

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
    // No arrows: the owner asked for the row to slide, not to be clicked
    // through (2026-09-07). It moves on its own and can still be dragged or
    // flicked, so nothing is unreachable without a control.
    return '<div class="af-motion-shell">' . $new . '</div>';
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
<link rel="stylesheet" id="af-motion-glide" href="<?php echo esc_url(af_static_url('motion-glide.css')); ?>" data-no-optimize="1">
<?php }, 20);

add_action('wp_footer', function () {
    if (!is_front_page() && !is_home()) return; ?>
<script id="af-motion-glide" src="<?php echo esc_url(af_static_url('motion-glide.js')); ?>" data-no-optimize="1"></script>
<?php }, 60);
