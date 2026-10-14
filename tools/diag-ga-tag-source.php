<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. GA4 shows page views but no ecommerce events (view_item,
 * add_to_cart, begin_checkout, purchase) in 90 days. inc/analytics.php sends
 * them only when af_ga4_id is saved, while the live pages already load
 * gtag G-F3PB80TT6Y from somewhere else. This names that somewhere: every
 * option and postmeta holding the ID, and the theme's own GA settings.
 * Makes no changes, prints no secrets.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;
echo "=== THEME GA SETTINGS\n";
foreach (array('af_ga4_id', 'af_gtm_id') as $o) echo "  $o = \"" . get_option($o, '') . "\"\n";
echo "  cookie consent module: " . (function_exists('af_cc_allowed') ? 'yes' : (file_exists(get_stylesheet_directory() . '/inc/cookie-consent.php') ? 'file present' : 'no')) . "\n";
echo "\n=== OPTIONS HOLDING THE ID\n";
foreach ($wpdb->get_results("SELECT option_name, LENGTH(option_value) len, autoload FROM {$wpdb->options} WHERE option_value LIKE '%G-F3PB80TT6Y%' OR option_value LIKE '%googletagmanager%' LIMIT 20") as $r) {
    $v = (string) get_option($r->option_name);
    $i = stripos($v, 'G-F3PB80TT6Y'); if ($i === false) $i = stripos($v, 'googletagmanager');
    echo "  {$r->option_name} ({$r->len}b, autoload {$r->autoload}): …" . preg_replace('/\s+/', ' ', mb_strcut(is_string($v) ? $v : wp_json_encode($v), max(0, $i - 120), 300)) . "…\n";
}
echo "\n=== POSTMETA / POSTS HOLDING THE ID\n";
foreach ($wpdb->get_results("SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE '%G-F3PB80TT6Y%' LIMIT 10") as $r) echo "  meta #{$r->post_id} {$r->meta_key}\n";
foreach ($wpdb->get_results("SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE post_content LIKE '%G-F3PB80TT6Y%' LIMIT 10") as $r) echo "  post #{$r->ID} {$r->post_type} {$r->post_status} \"{$r->post_title}\"\n";
echo "\n=== THEME FILES HOLDING THE ID\n";
foreach (array(get_stylesheet_directory(), get_template_directory()) as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->getExtension() !== 'php' || $f->getSize() > 3000000) continue; if (strpos(file_get_contents($f->getPathname()), 'G-F3PB80TT6Y') !== false) echo '  ' . str_replace(ABSPATH, '', $f->getPathname()) . "\n"; }
}
echo "\n=== WHAT WP_HEAD PRINTS ABOUT GTAG\n";
global $wp_filter;
foreach (array('wp_head', 'wp_footer', 'wp_body_open') as $h) {
    if (empty($wp_filter[$h])) continue;
    foreach ($wp_filter[$h]->callbacks as $prio => $cbs) foreach ($cbs as $cb) {
        $f = $cb['function'];
        try {
            $r = is_array($f) ? new ReflectionMethod($f[0], $f[1]) : (is_string($f) && strpos($f, '::') ? new ReflectionMethod($f) : new ReflectionFunction($f));
            $file = $r->getFileName(); if (!$file) continue;
            $src = implode('', array_slice(file($file), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1));
            if (stripos($src, 'gtag') !== false || stripos($src, 'googletagmanager') !== false)
                echo "  $h:$prio " . str_replace(ABSPATH, '', $file) . ':' . $r->getStartLine() . "\n";
        } catch (\Throwable $e) {}
    }
}
echo "=== DONE\n";
