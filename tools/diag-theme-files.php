<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. Are the theme's core files on the server, and does the site
 * serve them? Written 7 Oct, after 58750637's deploy removed style.css,
 * custom.css, checkout.css, custom.js, 404.php, search.php and the crawl
 * guard's source from the live theme (restored by the next deploy).
 *
 * Nothing is written. The probes are plain GETs of the home page and of the
 * three asset files, each with a throwaway query string so no cache answers.
 *
 * Run: wp eval-file tools/diag-theme-files.php --allow-root
 */
if (!defined('ABSPATH')) exit(1);

$dir = get_stylesheet_directory();
$bad = 0;
echo "=== files in the theme on the server\n";
foreach (array('style.css', 'functions.php', '404.php', 'search.php', 'assets/css/custom.css', 'assets/css/checkout.css',
               'assets/js/custom.js', 'assets/css/ar-wall.css', 'assets/js/ar-wall.js', 'ops/mu/af-crawl-guard.php',
               'inc/retired-products.php') as $f) {
    $p = $dir . '/' . $f;
    $ok = is_file($p);
    if (!$ok) $bad++;
    printf("  %-30s %s\n", $f, $ok ? filesize($p) . ' bytes, ' . gmdate('Y-m-d H:i', filemtime($p)) . ' UTC' : 'MISSING');
}

echo "\n=== the theme as WordPress sees it\n";
$t = wp_get_theme();
$err = $t->errors();
echo '  ' . $t->get('Name') . ' ' . $t->get('Version') . ', parent ' . ($t->parent() ? $t->parent()->get('Name') : '(none)') . ', errors: ' . (is_wp_error($err) ? implode('; ', $err->get_error_messages()) : 'none') . "\n";
if (is_wp_error($err) || !$t->parent()) $bad++;
echo '  active stylesheet=' . get_option('stylesheet') . ' template=' . get_option('template') . "\n";

echo "\n=== what the site serves\n";
$bust = 'afv=' . substr(md5(uniqid('', true)), 0, 8);
$r = wp_remote_get(home_url('/?' . $bust), array('timeout' => 40, 'redirection' => 2));
$html = is_wp_error($r) ? '' : (string) wp_remote_retrieve_body($r);
echo '  home page: ' . (is_wp_error($r) ? 'ERROR ' . $r->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($r) . ', ' . strlen($html) . ' bytes') . "\n";
$base = get_stylesheet_directory_uri();
foreach (array('assets/css/custom.css', 'assets/css/checkout.css', 'assets/js/custom.js') as $f) {
    $linked = strpos($html, $base . '/' . $f) !== false || strpos($html, '/themes/postero-child/' . $f) !== false;
    $a = wp_remote_get($base . '/' . $f . '?' . $bust, array('timeout' => 30));
    $code = is_wp_error($a) ? 'ERROR' : (int) wp_remote_retrieve_response_code($a);
    $len = is_wp_error($a) ? 0 : strlen((string) wp_remote_retrieve_body($a));
    if ($code !== 200 || $len < 100) $bad++;
    printf("  %-24s HTTP %s, %d bytes, linked from the home page: %s\n", $f, $code, $len, $f === 'assets/css/checkout.css' ? '(checkout only)' : ($linked ? 'yes' : 'no (LiteSpeed may combine it)'));
}
$g = wp_remote_get(home_url('/?wc-ajax=get_refreshed_fragments&' . $bust), array('timeout' => 30));
echo '  crawl guard: ' . (is_wp_error($g) ? 'ERROR' : (wp_remote_retrieve_header($g, 'x-af-guard') ?: 'no marker')) . "\n";

echo "\n=== " . ($bad ? "PROBLEMS: $bad" : 'ALL GOOD') . "\n";
