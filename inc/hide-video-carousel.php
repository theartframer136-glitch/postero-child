<?php
if (!defined('ABSPATH')) exit;
/**
 * Drop the second video carousel under "Products In Motion".
 *
 * Owner, 2026-09-07: "here we have 2 video section keep the top video slider
 * section and remove the buttom video section".
 *
 * The page carries two:
 *   48b4f36  the [youtube_circle_slider] row, now the studio's nine clips  KEEP
 *   d49c1e8  an Elementor nested-carousel of five older videos             REMOVE
 *            (Ganesh-Video, Buddha-14, Black-Krishna-Enhanced, Dance-Real,
 *             Luxury_Modern_Home_Interior)
 *
 * NOT DONE BY EDITING THE PAGE. Removing the container in Elementor means
 * rewriting the home page's single 193 KB _elementor_data blob, and a malformed
 * edit there takes the home page down — which has happened before. This drops
 * the container as it renders, so the page's own data is untouched and deleting
 * this file brings the carousel straight back.
 *
 * Output buffering rather than CSS, so the five videos are never in the HTML at
 * all: a display:none carousel still costs the visitor five requests for video
 * metadata, on a host that is already CPU-capped and paying for bandwidth.
 *
 * A display:none rule is kept as well. It is redundant when the buffering works
 * and is the safety net if a future Elementor changes those hook names — the
 * section stays hidden either way, rather than silently reappearing.
 */

/** The container to drop. Filterable, so it can be changed without a deploy. */
function af_hidden_carousel_id() {
    return (string) apply_filters('af_hidden_carousel_id', 'd49c1e8');
}

function af_hvc_is_target($element) {
    return is_object($element)
        && method_exists($element, 'get_id')
        && $element->get_id() === af_hidden_carousel_id();
}

add_action('elementor/frontend/container/before_render', function ($element) {
    if (!af_hvc_is_target($element)) return;
    $GLOBALS['af_hvc_buffering'] = true;
    ob_start();
}, 5);

add_action('elementor/frontend/container/after_render', function ($element) {
    if (!af_hvc_is_target($element)) return;
    if (!empty($GLOBALS['af_hvc_buffering'])) {
        ob_end_clean();                       // throw the container away
        $GLOBALS['af_hvc_buffering'] = false;
    }
}, 999);

/**
 * Safety net. If after_render never fires for any reason, an open buffer would
 * swallow the rest of the page — the one way this could do real damage. So on
 * shutdown, flush anything still held rather than lose it.
 */
add_action('shutdown', function () {
    if (!empty($GLOBALS['af_hvc_buffering'])) {
        $GLOBALS['af_hvc_buffering'] = false;
        while (ob_get_level() > 0) { @ob_end_flush(); }
    }
}, 0);

add_action('wp_head', function () {
    if (!is_front_page() && !is_home()) return;
    $id = esc_attr(af_hidden_carousel_id()); ?>
<style>
/* redundant while the buffering above works; the fallback if it ever stops */
.elementor-element-<?php echo $id; ?>{display:none !important;}
</style>
<?php }, 20);
