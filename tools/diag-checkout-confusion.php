<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY. Follows up the 14 Oct purchase-path QA (all checks passed) on
 * what a shopper would still find confusing:
 *   - the checkout tab reads "Cart - The ART FRAMER"
 *   - /favicon.ico is a 404
 *   - "elementorFrontendConfig is not defined" on About, Blog, Try on Wall,
 *     Frame the Moment
 *   - Canada, UK and India addresses are quoted a US delivery price
 * Makes no changes, prints no secrets.
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-checkout-confusion.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }

echo "=== CHECKOUT TITLE\n";
foreach (array('cart', 'checkout', 'myaccount', 'shop') as $k) {
    $id = function_exists('wc_get_page_id') ? wc_get_page_id($k) : 0;
    $p  = $id > 0 ? get_post($id) : null;
    printf("  %-9s #%d /%s/ title=\"%s\" status=%s rank_math_title=\"%s\"\n", $k, $id,
        $p ? $p->post_name : '-', $p ? $p->post_title : '-', $p ? $p->post_status : '-',
        $id > 0 ? get_post_meta($id, 'rank_math_title', true) : '');
}
$co = get_page_by_path('checkout');
if ($co) printf("  page at /checkout/: #%d \"%s\"\n", $co->ID, $co->post_title);
$rm = get_option('rank-math-options-titles');
if (is_array($rm)) foreach ($rm as $k => $v) if (is_string($v) && preg_match('/^pt_page_title$|^pt_page_/', $k)) echo "  rank math $k = $v\n";

echo "\n=== FAVICON\n";
$icon = (int) get_option('site_icon');
printf("  site_icon attachment: %d  url: %s\n", $icon, $icon ? get_site_icon_url(32) : '-');
printf("  favicon.ico in web root: %s\n", file_exists(ABSPATH . 'favicon.ico') ? 'yes' : 'no');

echo "\n=== ELEMENTOR / SCRIPT OPTIMISATION\n";
printf("  elementor active: %s  version: %s\n", defined('ELEMENTOR_VERSION') ? 'yes' : 'no', defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '-');
foreach (array('about', 'blog', 'try-on-wall', 'frame-the-moment', 'contact') as $slug) {
    $p = get_page_by_path($slug);
    if (!$p) { echo "  /$slug/ not a page\n"; continue; }
    printf("  /%s/ #%d template=%s edit_mode=%s elementor_data=%s\n", $slug, $p->ID,
        get_post_meta($p->ID, '_wp_page_template', true) ?: '-',
        get_post_meta($p->ID, '_elementor_edit_mode', true) ?: '-',
        strlen((string) get_post_meta($p->ID, '_elementor_data', true)) . 'b');
}
global $wpdb;
$ls = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options}
    WHERE option_name LIKE 'litespeed.conf.optm-js%' OR option_name LIKE 'litespeed.conf.optm-exc%'
       OR option_name LIKE 'litespeed.conf.optm-ucss%' OR option_name LIKE 'litespeed.conf.optm-guest%'");
foreach ($ls as $o) echo '  ' . $o->option_name . ' = ' . mb_strimwidth(preg_replace('/\s+/', ' ', $o->option_value), 0, 200, '…') . "\n";
$active = (array) get_option('active_plugins');
echo '  plugins: ' . implode(', ', array_map(function ($p) { return dirname($p); }, $active)) . "\n";

echo "\n=== WHERE THE SHOP SELLS AND SHIPS\n";
foreach (array('woocommerce_allowed_countries', 'woocommerce_specific_allowed_countries', 'woocommerce_all_except_countries',
               'woocommerce_ship_to_countries', 'woocommerce_specific_ship_to_countries', 'woocommerce_default_country',
               'af_distance_fallback_band') as $o) {
    $v = get_option($o, '(unset)');
    echo "  $o = " . (is_array($v) ? '[' . implode(',', $v) . ']' : $v) . "\n";
}
if (class_exists('WC_Shipping_Zones')) {
    foreach (WC_Shipping_Zones::get_zones() as $z) {
        $locs = array_map(function ($l) { return $l->code; }, $z['zone_locations']);
        $meths = array_map(function ($m) { return $m->id . ($m->enabled === 'yes' ? '' : '(off)'); }, $z['shipping_methods']);
        printf("  zone \"%s\": %s -> %s\n", $z['zone_name'], implode(',', $locs) ?: '-', implode(',', $meths) ?: '-');
    }
    $rest = new WC_Shipping_Zone(0);
    echo '  zone "rest of world" -> ' . (implode(',', array_map(function ($m) { return $m->id . ($m->enabled === 'yes' ? '' : '(off)'); }, $rest->get_shipping_methods())) ?: '-') . "\n";
}
$orders = $wpdb->get_results("SELECT meta_value c, COUNT(*) n FROM {$wpdb->postmeta} WHERE meta_key IN ('_shipping_country') GROUP BY meta_value");
foreach ($orders as $o) echo "  past orders shipped to {$o->c}: {$o->n}\n";
if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    $t = $wpdb->prefix . 'wc_order_addresses';
    foreach ($wpdb->get_results("SELECT country c, COUNT(*) n FROM $t WHERE address_type='shipping' GROUP BY country") as $o) echo "  (HPOS) orders shipped to {$o->c}: {$o->n}\n";
}
echo "=== DONE\n";
