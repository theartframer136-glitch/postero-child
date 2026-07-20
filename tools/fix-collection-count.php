<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Raise the "Shop by Collection" Essential-Addons Woo Product Carousel product
 * count so the slider shows all products in a collection (was capped ~12).
 * Sets eael_product_carousel_products_count = 40 on every eael-woo-product-carousel
 * widget on the front page. Backs up the original _elementor_data once. Reports
 * each carousel's category/query settings so we can confirm it's the right one.
 * Idempotent. Run: wp eval-file tools/fix-collection-count.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$TARGET = '40';
$fp = (int) get_option('page_on_front');
$raw = get_post_meta($fp, '_elementor_data', true);
if (empty($raw)) { echo "No _elementor_data on front page\n"; exit; }

// one-time backup
if (!get_post_meta($fp, '_af_elementor_backup_collection', true)) {
    update_post_meta($fp, '_af_elementor_backup_collection', wp_slash($raw));
    echo "Backed up original _elementor_data.\n";
}

$data = json_decode($raw, true);
if (!is_array($data)) { echo "decode failed\n"; exit; }

$changed = 0;
$walk = function(&$node) use (&$walk, &$changed, $TARGET){
    if (!is_array($node)) return;
    if (isset($node['widgetType']) && $node['widgetType'] === 'eael-woo-product-carousel'){
        if (!isset($node['settings']) || !is_array($node['settings'])) $node['settings'] = array();
        // report existing category/query settings for confirmation
        $rep = array();
        foreach ($node['settings'] as $k=>$v){
            if (preg_match('/product_count|products_count|categor|orderby|order|filter|tax|term|number|per_page/i',$k) && is_scalar($v))
                $rep[$k] = (string)$v;
        }
        echo "carousel id=".($node['id']??'?').": ".($rep? json_encode($rep):'(no count/category keys)')."\n";
        // set the product count keys EAEL may use
        $node['settings']['eael_product_carousel_products_count'] = $TARGET;
        $node['settings']['eael_product_carousel_product_count']  = $TARGET;
        $node['settings']['posts_per_page'] = $TARGET;
        $changed++;
    }
    if (isset($node['elements']) && is_array($node['elements'])) foreach ($node['elements'] as &$c){ $walk($c); }
};
foreach ($data as &$sec){ $walk($sec); }
unset($sec);

if ($changed){
    $new = wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    update_post_meta($fp, '_elementor_data', wp_slash($new));
    delete_post_meta($fp, '_elementor_css');
    if (class_exists('\\Elementor\\Plugin')) { try { \Elementor\Plugin::$instance->files_manager->clear_cache(); } catch (\Throwable $e){} }
    clean_post_cache($fp);
    echo "Updated {$changed} carousel widget(s) -> products_count={$TARGET}.\n";
} else {
    echo "No eael-woo-product-carousel widgets found.\n";
}
echo "=== DONE ===\n";
