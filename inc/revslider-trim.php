<?php
/**
 * Slider Revolution's files only on pages that have a slider.
 *
 * The slider (inc/ports/revslider, global setting "Include libraries
 * globally") puts its stylesheet sr7.css, its two scripts tptools.js and
 * sr7.js, a ~40 KB inline script in <head> and its font links on EVERY page —
 * the cart and checkout included (checked 24 Oct 2026, check-page-html.yml).
 * The site has one slider, on the home page. Everywhere else the browser
 * downloads and runs all of that for nothing, and the stylesheet holds up the
 * first paint.
 *
 * After the slider has queued its files, they are taken off again unless the
 * page needs them, judged by the slider's own rules: a [rev_slider] / [sr7]
 * shortcode or an Elementor slider widget in the page being shown, a slider
 * widget in a sidebar, or the page listed in the slider's "pages to include"
 * setting. Nothing else on the site uses these files (no other code refers to
 * tp-tools, _tpt or SR7). Pages with a slider are unchanged.
 */
if (!defined('ABSPATH')) exit;

/** Whether the page being shown needs Slider Revolution's files. */
function af_rs_page_needs_slider() {
    if (is_active_widget(false, false, 'rev-slider-widget', true)) return true;
    $obj = get_queried_object();
    if ($obj instanceof WP_Post) {
        if (has_shortcode((string) $obj->post_content, 'rev_slider') || has_shortcode((string) $obj->post_content, 'sr7')) return true;
        $el = get_post_meta($obj->ID, '_elementor_data', true);
        if (is_string($el) && (strpos($el, 'slider_revolution') !== false || strpos($el, 'rev_slider') !== false)) return true;
    }
    // The slider's own list of pages to load on: respected, whatever it holds.
    $gs = get_option('revslider-global-settings', '');
    if (!is_array($gs)) $gs = json_decode((string) $gs, true);
    if (is_array($gs) && !empty($gs['includeids'])) return true;
    return (bool) apply_filters('af_rs_page_needs_slider', false);
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !class_exists('RevSliderFront', false)) return;
    if (!wp_script_is('sr7', 'enqueued') && !wp_style_is('sr7css', 'enqueued')) return;
    if (af_rs_page_needs_slider()) return;

    foreach (array('tp-tools', 'sr7', 'sr7migration') as $h) wp_dequeue_script($h);
    wp_dequeue_style('sr7css');
    // The head script, font links and footer output it added with the files.
    foreach (array('wp_head', 'wp_footer', 'style_loader_tag') as $tag) {
        if (empty($GLOBALS['wp_filter'][$tag])) continue;
        foreach ($GLOBALS['wp_filter'][$tag]->callbacks as $prio => $cbs) {
            foreach ($cbs as $cb) {
                $f = $cb['function'];
                if (is_array($f) && isset($f[0]) && (is_a($f[0], 'RevSliderFront') || $f[0] === 'RevSliderFront')) {
                    remove_filter($tag, $f, $prio);
                }
            }
        }
    }
}, 99);
