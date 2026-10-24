<?php
/**
 * Home page: the first banner starts downloading straight away.
 *
 * The banner at the top of the home page is an Elementor slideshow. Its
 * pictures are CSS backgrounds written into Elementor's per-page stylesheet,
 * so the browser only learns the first banner exists after it has fetched and
 * read that stylesheet — one of ~80 on the page. Meanwhile WordPress, which
 * cannot see CSS backgrounds, marks the first <img> it prints as the page's
 * most important picture (fetchpriority="high"): on the home page that is the
 * "Try it on your wall" picture further down, which on most screens is not
 * shown at all. Measured 24 Oct 2026 (tools/diag-home-hero.php,
 * check-page-html.yml): the banner is the biggest thing above the fold and is
 * among the last pictures to start.
 *
 * So the first slide of each slideshow is announced in <head> with
 * <link rel="preload" fetchpriority="high">, limited by a media query to the
 * screens where that slideshow is shown (there is one for phones, one for
 * tablet and up), and WordPress's guess is withdrawn on that page. The files
 * are the very same ones the slideshow already uses — same URL, same pixels —
 * only fetched first. Read from Elementor's own data, so a banner the owner
 * changes in the editor is followed without touching this file.
 */
if (!defined('ABSPATH')) exit;

/**
 * Elementor's devices as ranges of screen width, from its active breakpoints
 * ($bps: device => max width). Desktop is everything above the largest one
 * below widescreen; widescreen, when active, starts at its own value.
 */
function af_hero_device_ranges($bps) {
    $order = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop');
    $ranges = array();
    $lo = 0;
    foreach ($order as $d) {
        if (!isset($bps[$d])) continue;
        $ranges[$d] = array($lo, (int) $bps[$d]);
        $lo = (int) $bps[$d] + 1;
    }
    if (isset($bps['widescreen'])) {
        $ranges['desktop'] = array($lo, (int) $bps['widescreen'] - 1);
        $ranges['widescreen'] = array((int) $bps['widescreen'], 0);
    } else {
        $ranges['desktop'] = array($lo, 0);
    }
    return $ranges; // 0 as the upper end means "and wider"
}

/** The screens left showing once $hidden devices are hidden, as a media query ('' = none, 'all' = every screen). */
function af_hero_media($ranges, $hidden) {
    $parts = array();
    $open = null;
    foreach ($ranges as $d => $r) {
        if (in_array($d, $hidden, true)) {
            if ($open) { $parts[] = $open; $open = null; }
            continue;
        }
        $open = $open ? array($open[0], $r[1]) : $r;
    }
    if ($open) $parts[] = $open;
    $q = array();
    foreach ($parts as $p) {
        if ($p[0] <= 0 && $p[1] === 0) return 'all';
        if ($p[0] <= 0) $q[] = "(max-width: {$p[1]}px)";
        elseif ($p[1] === 0) $q[] = "(min-width: {$p[0]}px)";
        else $q[] = "(min-width: {$p[0]}px) and (max-width: {$p[1]}px)";
    }
    return implode(', ', $q);
}

/**
 * First slide of every slideshow in Elementor page data, with the screens it
 * shows on. A widget is hidden on a device when it, or anything it sits in,
 * is set to hide there. Returns list of array(url, media).
 */
function af_hero_first_slides($elements, $ranges, $hidden = array()) {
    $out = array();
    foreach ((array) $elements as $el) {
        if (!is_array($el)) continue;
        $s = isset($el['settings']) && is_array($el['settings']) ? $el['settings'] : array();
        $h = $hidden;
        foreach (array_keys($ranges) as $d) {
            if (!empty($s['hide_' . $d])) $h[] = $d;
        }
        if (isset($el['widgetType']) && $el['widgetType'] === 'slides' && !empty($s['slides'][0]['background_image']['url'])) {
            $media = af_hero_media($ranges, $h);
            if ($media !== '') $out[] = array((string) $s['slides'][0]['background_image']['url'], $media);
        }
        if (!empty($el['elements'])) {
            $out = array_merge($out, af_hero_first_slides($el['elements'], $ranges, $h));
        }
    }
    return $out;
}

/** The preload tags for the front page, remembered until the page is saved again. */
function af_hero_preloads() {
    static $done = null;
    if ($done !== null) return $done;
    $done = array();
    $front = (int) get_option('page_on_front');
    if (!$front) return $done;
    $key = 'af_hero_preload_' . $front . '_' . md5((string) get_post_field('post_modified_gmt', $front));
    $hit = get_transient($key);
    if (is_array($hit)) return $done = $hit;

    $raw = get_post_meta($front, '_elementor_data', true);
    $data = is_string($raw) ? json_decode($raw, true) : $raw;
    $bps = array('mobile' => 767, 'tablet' => 1024);
    if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->breakpoints)) {
        $bps = array();
        foreach (\Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() as $name => $bp) {
            $bps[$name] = (int) $bp->get_value();
        }
    }
    if (is_array($data)) {
        // Two slideshows for the same screens would both be shown; only the
        // first one on the page is the banner at the top.
        $seen = array();
        foreach (af_hero_first_slides($data, af_hero_device_ranges($bps)) as $p) {
            if (isset($seen[$p[1]])) continue;
            $seen[$p[1]] = true;
            $done[] = $p;
        }
    }
    set_transient($key, $done, WEEK_IN_SECONDS);
    return $done;
}

function af_hero_is_home() {
    return !is_admin() && is_front_page() && !is_paged();
}

add_action('wp_head', function () {
    if (!af_hero_is_home()) return;
    foreach (af_hero_preloads() as $p) {
        $media = $p[1] === 'all' ? '' : ' media="' . esc_attr($p[1]) . '"';
        echo '<link rel="preload" as="image" href="' . esc_url($p[0]) . '" fetchpriority="high"' . $media . ">\n";
    }
}, 1);

// With the banner announced, WordPress's own guess would only compete with it.
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (isset($attr['fetchpriority']) && $attr['fetchpriority'] === 'high' && af_hero_is_home() && af_hero_preloads()) {
        unset($attr['fetchpriority']);
    }
    return $attr;
}, 99);
