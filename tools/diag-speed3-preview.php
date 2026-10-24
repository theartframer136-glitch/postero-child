<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only preview of speed step 3 against the live data, before it ships:
 * the banner preloads inc/home-hero.php would print, and the srcsets
 * inc/markup-fixes.php would give home card pictures. Carries a copy of the
 * new functions (with their caching switched off, so nothing is written).
 */
if (!defined('ABSPATH')) exit;
function af_diag_noop() { return true; }
function af_diag_miss() { return false; }
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
    $hit = af_diag_miss($key);
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
    af_diag_noop($key, $done, WEEK_IN_SECONDS);
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

function af_mf_card_srcset($html, $resolve) {
    $re = '~<img\b[^>]*\bclass\s*=\s*["\'][^"\']*\b(?:hover-img|main-img)\b[^>]*>~i';
    $src = function ($tag) {
        if (preg_match('~\bsrcset\s*=~i', $tag)) return '';
        return preg_match('~\bsrc\s*=\s*["\']([^"\']*/wp-content/uploads/[^"\']*?-\d{2,4}x\d{2,4}\.(?:jpe?g|png|webp))["\']~i', $tag, $s) ? $s[1] : '';
    };
    if (!preg_match_all($re, $html, $all)) return $html;
    $urls = array();
    foreach ($all[0] as $tag) { $u = $src($tag); if ($u !== '') $urls[$u] = true; }
    if (!$urls) return $html;
    $map = (array) call_user_func($resolve, array_keys($urls));
    $sizes = (string) apply_filters('af_home_card_sizes', '(max-width: 767px) 72vw, (max-width: 1199px) 40vw, 320px');
    return preg_replace_callback($re, function ($m) use ($src, $map, $sizes) {
        $tag = $m[0];
        $u = $src($tag);
        if ($u === '' || empty($map[$u])) return $tag;
        $add = ' srcset="' . esc_attr($map[$u]) . '"';
        if (!preg_match('~\bsizes\s*=~i', $tag)) $add .= ' sizes="' . esc_attr($sizes) . '"';
        return preg_replace('~^<img\b~i', '<img' . $add, $tag, 1);
    }, $html);
}

/**
 * Sized upload URLs (name-WxH.ext) => srcset of the same picture in every copy
 * of the same shape ('' when unknown or there is no other copy). The home page
 * has close to 300 of these, so the attachments are found in one query and the
 * answers kept together for a day, rather than a lookup and a stored row each.
 */
function af_mf_sized_upload_srcsets($urls) {
    $key = 'af_mf_card_srcsets';
    $cache = af_diag_miss($key);
    if (!is_array($cache)) $cache = array();
    $out = array(); $todo = array();
    foreach ($urls as $u) {
        $h = md5($u);
        if (isset($cache[$h])) $out[$u] = $cache[$h]; else $todo[$u] = true;
    }
    if (!$todo) return $out;

    $up = wp_get_upload_dir();
    $base = trailingslashit(set_url_scheme($up['baseurl'], 'https'));
    $want = array(); // _wp_attached_file => list of sized URLs
    foreach (array_keys($todo) as $u) {
        $out[$u] = '';
        if (!preg_match('~^(.*)-(\d{2,4})x(\d{2,4})\.(jpe?g|png|webp)$~i', set_url_scheme(strtok($u, '?'), 'https'), $m)) continue;
        if (strpos($m[1], $base) !== 0) continue;
        $rel = substr($m[1], strlen($base));
        foreach (array("{$rel}.{$m[4]}", "{$rel}-scaled.{$m[4]}") as $f) $want[$f][] = array($u, (int) $m[2], (int) $m[3]);
    }
    if ($want) {
        global $wpdb;
        $in = implode(',', array_fill(0, count($want), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN ($in)",
            array_keys($want)
        ));
        $ids = array();
        foreach ((array) $rows as $r) $ids[(int) $r->post_id] = $r->meta_value;
        if ($ids) update_meta_cache('post', array_keys($ids));
        foreach ($ids as $id => $file) {
            $meta = wp_get_attachment_metadata($id);
            if (!is_array($meta)) continue;
            foreach ($want[$file] as $w) {
                $calc = wp_calculate_image_srcset(array($w[1], $w[2]), $w[0], $meta, $id);
                if (is_string($calc) && $calc !== '') $out[$w[0]] = $calc;
            }
        }
    }
    foreach (array_keys($todo) as $u) $cache[md5($u)] = $out[$u];
    af_diag_noop($key, $cache, DAY_IN_SECONDS);
    return $out;
}


echo "=== banner preloads ===\n";
foreach (af_hero_preloads() as $p) echo "  {$p[1]}  =>  {$p[0]}\n";
echo "\n=== home card srcsets ===\n";
$up = wp_get_upload_dir()['baseurl'];
$urls = array("$up/2026/09/TP-050014-6030-512x1024.jpg", "$up/2026/09/LI-190027-5030-574x1024.jpg", "$up/2026/09/scene1-149-768x576.jpg", "$up/2026/09/nothing-here-512x1024.jpg");
$t = microtime(true);
foreach (af_mf_sized_upload_srcsets($urls) as $u => $s) echo "  " . basename($u) . "\n     " . ($s === '' ? '(none)' : $s) . "\n";
printf("  looked up in %.0f ms\n", (microtime(true) - $t) * 1000);
echo "\n=== card size ===\n";
print_r(function_exists('wc_get_image_size') ? wc_get_image_size('woocommerce_thumbnail') : 'no wc');
