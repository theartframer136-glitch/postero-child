<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Read-only. For each plugin built into the theme (inc/plugin-ports.php):
 * how much PHP it has loaded in this process, and signs that the site uses it
 * (its shortcodes, Elementor widgets, posts, meta or settings). Used to find
 * ports that load on every page view for nothing.
 */
if (!defined('ABSPATH')) exit;
global $wpdb;
$dir = wp_normalize_path(get_stylesheet_directory() . '/inc/ports/');
$by = array();
foreach (get_included_files() as $f) {
    $f = wp_normalize_path($f);
    if (strpos($f, $dir) !== 0) continue;
    $rest = substr($f, strlen($dir));
    $port = preg_replace('#[/.].*$#', '', $rest);
    if (!isset($by[$port])) $by[$port] = array(0, 0);
    $by[$port][0]++; $by[$port][1] += filesize($f);
}
arsort($by);
echo "=== PHP loaded per port in this process (files, KB)\n";
foreach ($by as $p => $v) printf("  %-22s %4d files %6d KB\n", $p, $v[0], $v[1] / 1024);

$like = function ($needle) use ($wpdb) {
    $l = '%' . $wpdb->esc_like($needle) . '%';
    $a = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_content LIKE %s", $l));
    $ids = $wpdb->get_col($wpdb->prepare("SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_status='publish' AND m.meta_key='_elementor_data' AND m.meta_value LIKE %s LIMIT 8", $l));
    return sprintf('content %d, elementor %d%s', $a, count($ids), $ids ? ' (#' . implode(' #', $ids) . ')' : '');
};
$meta = function ($key) use ($wpdb) { return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value<>''", $key)); };
$ptype = function ($t) use ($wpdb) { return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", $t)); };
$tax = function ($t) use ($wpdb) { return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy=%s AND count>0", $t)); };

echo "\n=== signs of use\n";
echo '  revslider        [rev_slider: ' . $like('[rev_slider') . ' | widget slider_revolution: ' . $like('"slider_revolution"') . ' | sliders: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}revslider_sliders") . "\n";
echo '  instagram-feed   [insta-gallery: ' . $like('[insta-gallery') . ' | qligg widget: ' . $like('qligg') . ' | insta widget: ' . $like('"insta-gallery') . ' | feeds option: ' . strlen((string) json_encode(get_option('insta_gallery_feeds'))) . " bytes\n";
echo '  featured-video   products with a video: ' . ($meta('_video_url') + $meta('video_url')) . "\n";
echo '  smart-messages   messages: ' . $ptype('wpc_smart_message') . ' | [wpcsm: ' . $like('[wpcsm') . "\n";
echo '  mas-brands       brands in use: ' . $tax('mas_product_brand') . ' / product_brand: ' . $tax('product_brand') . ' | widget: ' . $like('mas_brand') . "\n";
echo '  delivery-date    settings: ' . (get_option('wpced_settings') ? 'set' : 'none') . ' | rules: ' . strlen((string) json_encode(get_option('wpced_rules'))) . " bytes\n";
echo '  google-reviews   [google-reviews: ' . $like('google-reviews') . ' | gr_latest_results: ' . strlen((string) json_encode(get_option('gr_latest_results'))) . " bytes\n";
echo '  customer-reviews [cusrev: ' . $like('[cusrev') . ' | reviews: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type='review' AND comment_approved='1'") . "\n";
echo '  quick-view       [woosq: ' . $like('woosq') . "\n";
echo '  premium-addons   premium- widgets: ' . $like('"widgetType":"premium-') . "\n";
echo '  essential-addons eael- widgets: ' . $like('"widgetType":"eael-') . "\n";
echo '  elementor-pro    pro-only widgets (slides, nav-menu, form, posts): slides ' . $like('"widgetType":"slides"') . ' | form ' . $like('"widgetType":"form"') . "\n";
echo '  transposh        Hindi (/hi/) visits are a traffic question; translations stored: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}translations") . "\n";
echo '  hostinger        options: ' . implode(', ', $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'hostinger%' LIMIT 12")) . "\n";
echo '  click-to-chat    settings: ' . (get_option('ht_ctc_chat_options') ? 'set' : 'none') . "\n";
echo '  code-snippets    active snippets: ' . (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}snippets WHERE active=1") . "\n";
echo '  hfcm             snippets: ' . (int) @$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}hfcm_scripts WHERE status='active'") . "\n";
