<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Facts behind the 16 Oct audit items the owner asked to fix:
 * the 2023 demo posts, the blog page's indexability, the Instagram feed's
 * configuration, the cart/wishlist response time, active plugins.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-audit-fixes.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== POSTS PUBLISHED BEFORE 2026 (demo content candidates)\n";
$rows = $wpdb->get_results("SELECT ID, post_title, post_name, post_date, post_status, post_author FROM {$wpdb->posts}
    WHERE post_type='post' AND post_status IN ('publish','draft','pending','private') AND post_date < '2026-01-01' ORDER BY post_date");
foreach ($rows as $r) {
    $cats = wp_get_post_terms($r->ID, 'category', array('fields' => 'names'));
    $thumb = get_post_thumbnail_id($r->ID);
    printf("  #%-6d %-10s %s | %-50s | cats: %s | thumb: %s | words: %d\n", $r->ID, substr($r->post_date, 0, 10), $r->post_status,
        mb_strimwidth($r->post_title, 0, 50, '…'), implode(', ', is_array($cats) ? $cats : array()), $thumb ? $thumb : '-',
        str_word_count(wp_strip_all_tags(get_post_field('post_content', $r->ID))));
}
echo "  count: " . count($rows) . "\n";
echo "\n=== ALL OTHER POSTS (2026)\n";
$rows2 = $wpdb->get_results("SELECT ID, post_title, post_date, post_status FROM {$wpdb->posts} WHERE post_type='post' AND post_status IN ('publish','draft') AND post_date >= '2026-01-01' ORDER BY post_date");
foreach ($rows2 as $r) printf("  #%-6d %-10s %-8s %s\n", $r->ID, substr($r->post_date, 0, 10), $r->post_status, mb_strimwidth($r->post_title, 0, 60, '…'));

echo "\n=== BLOG PAGE\n";
$blog_id = (int) get_option('page_for_posts'); $front = (int) get_option('page_on_front');
echo "  show_on_front: " . get_option('show_on_front') . " | page_for_posts: $blog_id | page_on_front: $front\n";
$blog = get_page_by_path('blog');
if ($blog) echo "  /blog/ page: #{$blog->ID} status={$blog->post_status} rank_math_robots=" . json_encode(get_post_meta($blog->ID, 'rank_math_robots', true)) . " canonical=" . get_post_meta($blog->ID, 'rank_math_canonical_url', true) . "\n";
foreach (array('/blog/', '/' . ($blog ? $blog->post_name : 'blog') . '/') as $path) {
    $r = wp_remote_get(home_url($path), array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0')));
    if (is_wp_error($r)) { echo "  $path ERROR\n"; continue; }
    $b = (string) wp_remote_retrieve_body($r);
    preg_match('#<meta name="robots" content="([^"]*)"#i', $b, $m); preg_match('#<link rel="canonical" href="([^"]*)"#i', $b, $c); preg_match('#<title>([^<]*)</title>#i', $b, $t);
    printf("  %-8s HTTP %s | robots: %s | canonical: %s | title: %s | article links: %d\n", $path, wp_remote_retrieve_response_code($r), $m[1] ?? '(none)', $c[1] ?? '(none)', $t[1] ?? '', preg_match_all('#<h2[^>]*class="[^"]*entry-title#', $b));
    break;
}
$rm = get_option('rank-math-options-titles', array());
foreach (array('pt_post_robots', 'pt_page_robots', 'homepage_robots') as $k) echo "  rank math $k: " . (isset($rm[$k]) ? json_encode($rm[$k]) : '(unset)') . "\n";
$sm = get_option('rank-math-options-sitemap', array());
foreach (array('pt_post_sitemap', 'pt_page_sitemap') as $k) echo "  sitemap $k: " . (isset($sm[$k]) ? $sm[$k] : '(unset)') . "\n";

echo "\n=== INSTAGRAM FEED CONFIG\n";
$opts = $wpdb->get_results("SELECT option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE option_name LIKE 'qligg%' OR option_name LIKE 'insta%' OR option_name LIKE 'sbi%' OR option_name LIKE 'af_ig%' OR option_name LIKE 'af_reels%' ORDER BY option_name");
foreach ($opts as $o) echo "  option {$o->option_name} ({$o->len} bytes)\n";
$feeds = get_posts(array('post_type' => array('qligg_feed', 'insta_gallery', 'qligg'), 'post_status' => 'any', 'posts_per_page' => 10));
foreach ($feeds as $f) {
    echo "  feed post #{$f->ID} {$f->post_type} '{$f->post_title}'\n";
    foreach (get_post_meta($f->ID) as $k => $v) { $val = maybe_unserialize($v[0]); if (is_array($val)) { foreach ($val as $kk => $vv) if (!is_array($vv) && strlen((string) $vv) < 60) echo "      $k.$kk = $vv\n"; } elseif (strlen((string) $val) < 80) echo "      $k = $val\n"; }
}
$ed = get_post_meta(75, '_elementor_data', true);
if (preg_match_all('/\[(insta[-_]gallery|instagram-feed|qligg[^\s\]]*)[^\]]*\]/i', $ed, $mm)) foreach ($mm[0] as $s) echo "  home shortcode: " . stripslashes($s) . "\n";
if (preg_match_all('/"widgetType":"([^"]*(insta|instagram)[^"]*)"/i', $ed, $mw)) echo "  home widgets: " . implode(', ', array_unique($mw[1])) . "\n";
echo "  home post_content mentions instagram: " . (stripos(get_post_field('post_content', 75), 'insta') !== false ? 'yes' : 'no') . "\n";

echo "\n=== CART / WISHLIST RESPONSE TIME (server-side fetch, 3 samples)\n";
foreach (array('/cart/', '/wishlist/', '/shop/', '/') as $path) {
    $times = array();
    for ($i = 0; $i < 3; $i++) { $t0 = microtime(true); $r = wp_remote_get(home_url($path), array('timeout' => 40, 'sslverify' => false, 'headers' => array('User-Agent' => 'af-diag/1.0'))); $times[] = round((microtime(true) - $t0) * 1000); }
    $h = is_wp_error($r) ? array() : wp_remote_retrieve_headers($r);
    printf("  %-10s %s ms | cache: %s | bytes: %d\n", $path, implode('/', $times), is_wp_error($r) ? 'ERR' : (isset($h['x-litespeed-cache']) ? 'litespeed ' . $h['x-litespeed-cache'] : (isset($h['cf-cache-status']) ? 'cf ' . $h['cf-cache-status'] : 'no header')), is_wp_error($r) ? 0 : strlen((string) wp_remote_retrieve_body($r)));
}
$wl = get_page_by_path('wishlist'); echo "  wishlist page: " . ($wl ? "#{$wl->ID} content=" . mb_strimwidth(wp_strip_all_tags($wl->post_content), 0, 80, '…') : 'none') . "\n";

echo "\n=== ACTIVE PLUGINS\n";
foreach ((array) get_option('active_plugins') as $p) echo "  $p\n";
echo "  litespeed options: css_minify=" . get_option('litespeed.conf.optm-css_min') . " css_comb=" . get_option('litespeed.conf.optm-css_comb') . " js_min=" . get_option('litespeed.conf.optm-js_min') . " js_comb=" . get_option('litespeed.conf.optm-js_comb') . " js_defer=" . get_option('litespeed.conf.optm-js_defer') . " lazy=" . get_option('litespeed.conf.media-lazy') . " cache=" . get_option('litespeed.conf.cache') . "\n";
echo "  cloudflare header seen on this request: " . (isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? 'CF-IPCountry present' : 'n/a (cli)') . "\n";
$r = wp_remote_get(home_url('/'), array('timeout' => 40, 'sslverify' => false));
if (!is_wp_error($r)) { $h = wp_remote_retrieve_headers($r); echo "  home response headers: server=" . ($h['server'] ?? '?') . " cf-ray=" . (isset($h['cf-ray']) ? 'yes' : 'no') . " x-litespeed-cache=" . ($h['x-litespeed-cache'] ?? '-') . "\n"; }
echo "\n=== DONE ===\n";
