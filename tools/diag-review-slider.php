<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Why the review carousel's arrows do nothing.
 *
 * The arrows belong to a slider that a plugin builds in the browser. When the
 * slider never initialises, its markup still renders — a row of cards and a
 * pair of arrows — and every click lands on a control nothing is listening to.
 * Reading that from the outside is guesswork, so this prints the facts the
 * browser would get: the widget markup, how many slides are in it, the
 * navigation elements, and which slider scripts the page actually references.
 *
 * Changes nothing.
 *
 * Run: wp eval-file tools/diag-review-slider.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== REVIEW SLIDER DIAGNOSIS ===\n";

$res = wp_remote_get(add_query_arg('afdiag', time(), home_url('/')),
    array('timeout' => 60, 'sslverify' => false,
          'headers' => array('User-Agent' => 'Mozilla/5.0 AF-Diag')));
if (is_wp_error($res)) { echo "  FETCH FAILED: " . $res->get_error_message() . "\n=== DONE ===\n"; return; }
$html = wp_remote_retrieve_body($res);
printf("  homepage: %d bytes, HTTP %s\n", strlen($html), wp_remote_retrieve_response_code($res));

// ── which slider libraries the page pulls in ──
echo "\n-- scripts the page references (slider-related) --\n";
if (preg_match_all('#<script[^>]+src=["\']([^"\']+)["\'][^>]*>#i', $html, $m)) {
    $hits = 0;
    foreach ($m[1] as $i => $src) {
        if (!preg_match('#swiper|slick|owl|glide|splide|review#i', $src)) continue;
        $tag = $m[0][$i];
        $flag = array();
        if (stripos($tag, ' defer') !== false) $flag[] = 'defer';
        if (stripos($tag, ' async') !== false) $flag[] = 'async';
        printf("  %s%s\n", $src, $flag ? '   [' . implode(',', $flag) . ']' : '');
        $hits++;
    }
    if (!$hits) echo "  none — no slider library is loaded on this page\n";
    printf("  (total scripts on the page: %d)\n", count($m[1]));
} else { echo "  no <script src> tags at all\n"; }

// ── the widget itself ──
echo "\n-- review widget markup --\n";
$pos = false;
foreach (array('id="g-review"', 'grwp', 'g-review', 'google-review') as $needle) {
    $pos = stripos($html, $needle);
    if ($pos !== false) { echo "  found by: {$needle} at byte {$pos}\n"; break; }
}
if ($pos === false) {
    echo "  the review widget is not in the served html at all\n";
} else {
    $start = max(0, $pos - 400);
    $chunk = substr($html, $start, 6000);

    // counts that say whether this is a slider or a plain row
    $counts = array(
        'swiper-slide'          => substr_count($chunk, 'swiper-slide'),
        'swiper-wrapper'        => substr_count($chunk, 'swiper-wrapper'),
        'swiper-initialized'    => substr_count($chunk, 'swiper-initialized'),
        'swiper-button-next'    => substr_count($chunk, 'swiper-button-next'),
        'swiper-button-prev'    => substr_count($chunk, 'swiper-button-prev'),
        'swiper-button-disabled'=> substr_count($chunk, 'swiper-button-disabled'),
        'grwp_slider'           => substr_count($chunk, 'grwp_slider'),
        'data-slider'           => substr_count($chunk, 'data-slider'),
    );
    foreach ($counts as $k => $v) printf("  %-24s %d\n", $k, $v);

    // every element that looks like a navigation control, with its attributes
    echo "\n-- navigation controls found --\n";
    if (preg_match_all('#<(?:div|button|a|span)[^>]*class=["\'][^"\']*(?:arrow|nav|button-next|button-prev|prev|next)[^"\']*["\'][^>]*>#i',
                       $chunk, $nav)) {
        foreach (array_slice($nav[0], 0, 12) as $n) {
            echo "  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth($n, 0, 220, '…'))) . "\n";
        }
    } else { echo "  none inside the widget markup\n"; }

    // the inline init the plugin prints — if it is missing, nothing ever binds
    echo "\n-- inline slider init near the widget --\n";
    $region = substr($html, max(0, $pos - 20000), 60000);
    if (preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.{0,4000}?)</script>#is', $region, $sc)) {
        $shown = 0;
        foreach ($sc[1] as $code) {
            if (!preg_match('#new\s+Swiper|swiper|slider|carousel#i', $code)) continue;
            echo "  ---\n  " . trim(preg_replace('/\s+/', ' ', mb_strimwidth($code, 0, 400, '…'))) . "\n";
            if (++$shown >= 4) break;
        }
        if (!$shown) echo "  no inline script near the widget mentions a slider\n";
    } else { echo "  no inline scripts in that region\n"; }

    // first 1200 characters of the widget itself, so its real structure is visible
    echo "\n-- widget markup head (1200 chars) --\n";
    echo "  " . trim(preg_replace('/\s+/', ' ', substr($chunk, 0, 1200))) . "\n";
}

// ── did our loader run, and did the arrows get a library? ──
echo "\n-- theme's slider-asset loader --\n";
if (preg_match('#<!-- af: review slider[^>]*-->#', $html, $c)) {
    echo "  " . trim($c[0]) . "\n";
} else {
    echo "  the loader did not run on this page (widget shortcode not detected)\n";
}
$lib = preg_match('#src=["\'][^"\']*embedder-for-google-reviews[^"\']*\.js#i', $html);
printf("  slider library on the page: %s\n", $lib ? 'YES' : 'NO  <-- arrows cannot work');

// Is this response even coming from the current theme code? Two markers the
// theme prints unconditionally in the footer answer that: if they are missing
// too, the page is being served from cache (or the footer never ran) and no
// amount of changing the loader would show up here.
echo "\n-- is the served page running current theme code? --\n";
foreach (array(
    'af-review-overlap-fix' => 'footer style block (printed on every page)',
    'af-quickpanel'         => 'floating quick panel (printed on every page)',
) as $marker => $what) {
    printf("  %-24s %s   (%s)\n", $marker,
           strpos($html, $marker) !== false ? 'present' : 'MISSING', $what);
}
$hdr = wp_remote_retrieve_header($res, 'x-litespeed-cache');
printf("  x-litespeed-cache header: %s\n", $hdr ? $hdr : '(none)');

// ── what the plugin actually ships, on disk ──
// The loader falls back to the plugin's own files, so the names it can find
// decide whether anything loads at all.
echo "\n-- files the reviews plugin ships --\n";
$pdir = WP_PLUGIN_DIR . '/embedder-for-google-reviews';
if (!is_dir($pdir)) {
    echo "  plugin directory not found at {$pdir}\n";
} else {
    $found = 0;
    foreach (array('/*.js', '/*.css', '/dist/*.js', '/dist/*.css', '/dist/*/*.js', '/dist/*/*.css',
                   '/assets/*.js', '/assets/*/*.js', '/public/*.js', '/public/*/*.js') as $g) {
        foreach ((array) glob($pdir . $g) as $f) {
            printf("  %-60s %6d bytes\n", str_replace($pdir . '/', '', $f), (int) @filesize($f));
            if (++$found >= 25) break 2;
        }
    }
    if (!$found) echo "  no js/css found in the usual places\n";
}

// ── does the loader leave a machine-readable trace? ──
// HTML comments do not survive the page minifier, so the loader also prints a
// tag with its state in an attribute. That is what to trust here.
echo "\n-- loader state tag --\n";
if (preg_match('#<script[^>]*id=["\']af-grwp-loader["\'][^>]*>#i', $html, $tg)) {
    echo "  " . trim($tg[0]) . "\n";
} else {
    echo "  no state tag on the page\n";
}

// ── plugins that could own this widget ──
echo "\n-- active plugins mentioning reviews or sliders --\n";
foreach ((array) get_option('active_plugins', array()) as $p) {
    if (preg_match('#review|slider|swiper|testimonial#i', $p)) echo "  {$p}\n";
}

echo "=== DONE ===\n";
