<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Stage 1 asset optimization via LiteSpeed Cache (wp eval-file).
 *
 * SAFE-ONLY pass. Enables minification + lazy loading, which strip
 * bytes without reordering or merging assets.
 *
 * Deliberately NOT enabled (these are what break Elementor stores —
 * combined files reorder dependencies, UCSS/CCSS drop styles Elementor
 * injects late, JS defer races jQuery-inline widgets):
 *   optm-css_comb, optm-js_comb, optm-ucss, optm-css_async,
 *   optm-js_defer, optm-js_inline_defer
 * Those need staged rollout with visual verification per template.
 *
 * Logs before/after for every key so a regression can be traced and
 * reverted from the deploy output.
 */

if (!defined('ABSPATH')) exit;

$log = function ($m) { echo "[optm] $m\n"; };

if (!defined('LSCWP_V') && !class_exists('\LiteSpeed\Core')) {
    $log('LiteSpeed Cache not active — skipped');
    return;
}
$log('LiteSpeed Cache detected' . (defined('LSCWP_V') ? ' v' . LSCWP_V : ''));

// key => [value, human label]
$safe = array(
    'optm-css_min'   => array(1, 'minify CSS'),
    'optm-js_min'    => array(1, 'minify JS'),
    'optm-html_min'  => array(1, 'minify HTML'),
    'media-lazy'     => array(1, 'lazy-load images'),
    'media-lazy_placeholder_resp' => array(1, 'responsive lazy placeholders (prevents layout shift)'),
    'media-iframe_lazy' => array(1, 'lazy-load iframes'),
    'optm-qs_rm'     => array(1, 'remove query strings from static assets'),
    'optm-emoji_rm'  => array(1, 'remove WordPress emoji script'),
);

// Explicitly assert the risky ones stay OFF, so a future deploy or a
// stray admin click can't silently turn them on and break the store.
$keep_off = array(
    'optm-css_comb'  => 'combine CSS',
    'optm-js_comb'   => 'combine JS',
    'optm-ucss'      => 'unique CSS (UCSS)',
    'optm-css_async' => 'async/critical CSS',
    'optm-js_defer'  => 'defer JS',
);

$changed = 0;

$apply = function ($key, $val, $label) use (&$changed, $log) {
    $opt = 'litespeed.conf.' . $key;
    $cur = get_option($opt, null);
    if ($cur === null) {
        $log("  ~ $key ($label): option absent — skipping (unknown LSCWP schema)");
        return;
    }
    if ((int) $cur === (int) $val) {
        $log("  = $key ($label): already " . (int) $val);
        return;
    }
    update_option($opt, (int) $val);
    $log("  + $key ($label): " . (int) $cur . ' -> ' . (int) $val);
    $changed++;
};

$log('enabling safe optimizations:');
foreach ($safe as $key => $spec) {
    $apply($key, $spec[0], $spec[1]);
}

$log('asserting risky optimizations stay OFF:');
foreach ($keep_off as $key => $label) {
    $apply($key, 0, $label);
}

// Exclude the cart/checkout/account fragments from lazy loading —
// lazy images inside AJAX-replaced markup can render blank.
$excl_opt = 'litespeed.conf.media-lazy_uri_exc';
$cur_excl = get_option($excl_opt, null);
if (is_array($cur_excl)) {
    $want = array('/cart', '/checkout', '/my-account');
    $merged = array_values(array_unique(array_merge($cur_excl, $want)));
    if ($merged !== $cur_excl) {
        update_option($excl_opt, $merged);
        $log('  + lazy-load exclusions: cart, checkout, my-account');
        $changed++;
    }
}

if ($changed) {
    // Purge so the next request rebuilds with the new pipeline.
    if (method_exists('\LiteSpeed\Purge', 'purge_all')) {
        \LiteSpeed\Purge::purge_all('asset optimization changed');
        $log('cache purged');
    } elseif (function_exists('do_action')) {
        do_action('litespeed_purge_all');
        $log('cache purge signalled');
    }
}

$log("done — $changed setting(s) changed");
