<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Why is the site slow? Measure the things that actually cause it:
 *   1) server render time + HTML weight of the key pages
 *   2) how many scripts / styles / images / iframes each page pulls
 *   3) LiteSpeed optimisation switches (we force several OFF on deploy)
 *   4) autoloaded options size (classic silent killer)
 *   5) plugin count, transient/postmeta bloat, biggest images
 * Read-only.
 * Run: wp eval-file tools/diag-performance.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
global $wpdb;

function fmt_kb($b){ return number_format($b/1024, 0) . ' KB'; }

echo "=== 1. PAGE RENDER TIME + WEIGHT (server-side, cache-busted) ===\n";
$pages = array(
    'home'          => home_url('/?nocache='.time()),
    'shop category' => home_url('/product-category/digital-canvas-prints/radha-krishna/?nocache='.time()),
    'product'       => home_url('/product/vaikuntha-celestial-court-canvas-wall-art/?nocache='.time()),
    'try-on-wall'   => home_url('/try-on-wall/?nocache='.time()),
);
foreach ($pages as $label => $url) {
    $t0 = microtime(true);
    $r  = wp_remote_get($url, array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Perf')));
    $ms = round((microtime(true) - $t0) * 1000);
    if (is_wp_error($r)) { echo "  {$label}: FETCH FAILED ".$r->get_error_message()."\n"; continue; }
    $b  = wp_remote_retrieve_body($r);
    $scripts = substr_count($b, '<script');
    $styles  = substr_count($b, 'rel=\'stylesheet\'') + substr_count($b, 'rel="stylesheet"');
    $imgs    = substr_count($b, '<img');
    $iframes = substr_count($b, '<iframe');
    $inline  = 0;
    if (preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)</script>#i', $b, $m)) {
        foreach ($m[1] as $code) $inline += strlen($code);
    }
    printf("  %-14s %5d ms | HTML %8s | scripts %3d (inline %7s) | css %2d | img %3d | iframe %2d\n",
           $label, $ms, fmt_kb(strlen($b)), $scripts, fmt_kb($inline), $styles, $imgs, $iframes);
}

echo "\n=== 2. LITESPEED OPTIMISATION SWITCHES ===\n";
$ls = array(
    'cache-priv'      => 'private cache',
    'media-lazy'      => 'LAZY-LOAD IMAGES',
    'media-iframe_lazy'=> 'LAZY-LOAD IFRAMES (YouTube!)',
    'optm-js_defer'   => 'DEFER JS',
    'optm-js_comb'    => 'COMBINE JS',
    'optm-css_min'    => 'minify CSS',
    'optm-js_min'     => 'minify JS',
    'optm-ccss_gen'   => 'critical CSS',
    'cache-ttl_pub'   => 'public cache TTL',
);
foreach ($ls as $k => $label) {
    $v = get_option('litespeed.conf.' . $k, '(unset)');
    if (is_bool($v)) $v = $v ? 'true' : 'false';
    if (is_array($v)) $v = 'array('.count($v).')';
    printf("  %-32s %s = %s\n", $label, $k, var_export($v, true));
}

echo "\n=== 3. AUTOLOADED OPTIONS (should be < 800 KB) ===\n";
$auto = $wpdb->get_row("SELECT COUNT(*) n, SUM(LENGTH(option_value)) bytes FROM {$wpdb->options} WHERE autoload IN ('yes','on')");
echo "  autoloaded rows: {$auto->n}   total size: ".fmt_kb((int)$auto->bytes)."\n";
$big = $wpdb->get_results("SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE autoload IN ('yes','on') ORDER BY len DESC LIMIT 12");
foreach ($big as $o) echo "    ".str_pad($o->option_name, 46)." ".fmt_kb($o->len)."\n";

echo "\n=== 4. PLUGINS ===\n";
$active = (array) get_option('active_plugins', array());
echo "  active plugins: ".count($active)."\n";
foreach ($active as $p) echo "    - {$p}\n";

echo "\n=== 5. DB BLOAT ===\n";
$tr   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
$pm   = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}");
$posts= $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
$vars = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product_variation'");
$rev  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'");
echo "  transients: {$tr} | postmeta rows: {$pm} | posts: {$posts} (orphan variations: {$vars}, revisions: {$rev})\n";

echo "\n=== 6. BIGGEST IMAGES IN UPLOADS (top 15) ===\n";
$up = wp_get_upload_dir();
$files = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($up['basedir'], FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if (!$f->isFile()) continue;
    if (!preg_match('/\.(jpe?g|png|webp)$/i', $f->getFilename())) continue;
    if (preg_match('/-\d+x\d+\.(jpe?g|png|webp)$/i', $f->getFilename())) continue;  // skip generated sizes
    $files[$f->getPathname()] = $f->getSize();
}
arsort($files);
$total = array_sum($files);
echo "  original images: ".count($files)."   total: ".number_format($total/1048576,1)." MB\n";
$i=0; foreach ($files as $p=>$s) { echo "    ".fmt_kb($s)."  ".str_replace($up['basedir'].'/','',$p)."\n"; if(++$i>=15) break; }

echo "\n=== DONE ===\n";
