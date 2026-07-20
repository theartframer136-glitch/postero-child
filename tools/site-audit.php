<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * READ-ONLY full site audit for theartframer.us
 * Reports current state to compare against the requirement document.
 * Makes NO changes. Run: wp eval-file tools/site-audit.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

function h( $t ) { echo "\n========== $t ==========\n"; }

// ── 1. Environment ─────────────────────────────────────────────
h( 'ENVIRONMENT' );
echo 'WP version      : ' . get_bloginfo( 'version' ) . "\n";
echo 'Site URL        : ' . home_url() . "\n";
echo 'WooCommerce     : ' . ( defined('WC_VERSION') ? WC_VERSION : 'NOT ACTIVE' ) . "\n";
$theme = wp_get_theme();
echo 'Active theme    : ' . $theme->get('Name') . ' v' . $theme->get('Version') . "\n";
echo 'Parent theme    : ' . ( $theme->parent() ? $theme->parent()->get('Name') : 'none' ) . "\n";

// ── 2. Active plugins ──────────────────────────────────────────
h( 'ACTIVE PLUGINS' );
$active = get_option( 'active_plugins', array() );
sort( $active );
foreach ( $active as $p ) {
    $data = @get_plugin_data( WP_PLUGIN_DIR . '/' . $p );
    $name = ( $data && ! empty($data['Name']) ) ? $data['Name'] : $p;
    $ver  = ( $data && ! empty($data['Version']) ) ? $data['Version'] : '';
    echo '  - ' . $name . ' ' . $ver . "\n";
}

// Check for spec-required plugin capabilities
h( 'SPEC PLUGIN CAPABILITY CHECK' );
$checks = array(
    'Multicurrency (WOOCS)'        => function_exists('woocs_link_to_woocs') || class_exists('WOOCS'),
    'Elementor'                    => defined('ELEMENTOR_VERSION'),
    'Elementor Pro'                => defined('ELEMENTOR_PRO_VERSION'),
    'WP Rocket'                    => defined('WP_ROCKET_VERSION'),
    'LiteSpeed Cache'              => defined('LSCWP_V'),
    'RankMath'                     => class_exists('RankMath'),
    'Yoast SEO'                    => defined('WPSEO_VERSION'),
    'Wordfence'                    => class_exists('wordfence'),
    'YITH Wishlist'                => defined('YITH_WCWL'),
    'Smart Wishlist'               => function_exists('woosw_init') || class_exists('WPCleverWoosw'),
    'Quick View'                   => class_exists('WPCleverWoosq') || function_exists('woosq_init'),
    'Easy Digital Downloads'       => class_exists('Easy_Digital_Downloads'),
    'Customer Reviews (CusRev)'    => defined('IVOLE_VERSION') || class_exists('CR_Reviews'),
    'Facebook for WooCommerce'     => class_exists('WC_Facebookcommerce'),
    'Pinterest for WooCommerce'    => class_exists('Pinterest_For_Woocommerce'),
    'Google Listings & Ads'        => class_exists('Automattic\\WooCommerce\\GoogleListingsAndAds\\Container'),
    'CedCommerce'                  => false, // varies
    'Klaviyo'                      => class_exists('WooCommerceKlaviyo') || defined('KLAVIYO_WOOCOMMERCE_VERSION'),
    'Mailchimp'                    => class_exists('MailChimp_WooCommerce'),
    'Stripe Gateway'              => class_exists('WC_Stripe') || class_exists('WC_Gateway_Stripe'),
    'PayPal Gateway'              => class_exists('WC_Gateway_Paypal') || class_exists('WooCommerce\\PayPalCommerce\\PluginModule'),
);
foreach ( $checks as $label => $present ) {
    echo '  [' . ( $present ? 'YES' : ' no' ) . '] ' . $label . "\n";
}

// ── 3. Payment gateways enabled ────────────────────────────────
h( 'PAYMENT GATEWAYS (enabled)' );
if ( function_exists('WC') && WC()->payment_gateways() ) {
    foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gw ) {
        echo '  - ' . $gw->get_title() . ' (' . $gw->id . ')' . "\n";
    }
} else { echo "  (WooCommerce gateways unavailable)\n"; }

// ── 4. Currencies ──────────────────────────────────────────────
h( 'CURRENCY' );
echo '  Base currency : ' . get_woocommerce_currency() . "\n";

// ── 5. Products overview ───────────────────────────────────────
h( 'PRODUCTS OVERVIEW' );
$counts = wp_count_posts( 'product' );
echo '  Published     : ' . ( $counts->publish ?? 0 ) . "\n";
echo '  Draft         : ' . ( $counts->draft ?? 0 ) . "\n";
$types = wp_count_terms( array( 'taxonomy' => 'product_type', 'hide_empty' => false ) );
// product type breakdown
global $wpdb;
$type_rows = $wpdb->get_results( "
    SELECT t.name AS type, COUNT(*) AS n
    FROM {$wpdb->posts} p
    JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
    JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy='product_type'
    JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
    WHERE p.post_type='product' AND p.post_status='publish'
    GROUP BY t.name
" );
echo "  Product types :\n";
foreach ( $type_rows as $r ) { echo '     ' . $r->type . ' = ' . $r->n . "\n"; }

// ── 6. Sample product field completeness (first 5 published) ───
h( 'SAMPLE PRODUCT COMPLETENESS (first 8 published)' );
$sample = get_posts( array( 'post_type'=>'product','post_status'=>'publish','numberposts'=>8,'orderby'=>'date','order'=>'DESC' ) );
foreach ( $sample as $sp ) {
    $prod = wc_get_product( $sp->ID );
    $img_ct = $prod ? ( ( $prod->get_image_id() ? 1 : 0 ) + count( $prod->get_gallery_image_ids() ) ) : 0;
    $attrs  = $prod ? count( $prod->get_attributes() ) : 0;
    $has_faq = ( strpos( $sp->post_content, 'afaq-' ) !== false ) ? 'Y' : 'n';
    $has_tbl = ( strpos( $sp->post_content, '<table' ) !== false ) ? 'Y' : 'n';
    $has_short = trim($sp->post_excerpt) !== '' ? 'Y' : 'n';
    $seo = get_post_meta($sp->ID,'rank_math_title',true) || get_post_meta($sp->ID,'_yoast_wpseo_title',true) ? 'Y' : 'n';
    echo sprintf( "  #%d %-40s img:%d attrs:%d short:%s faq:%s tbl:%s seo:%s\n",
        $sp->ID, mb_substr($sp->post_title,0,40), $img_ct, $attrs, $has_short, $has_faq, $has_tbl, $seo );
}

// ── 7. Product attributes (global) ─────────────────────────────
h( 'GLOBAL PRODUCT ATTRIBUTES (taxonomies)' );
if ( function_exists('wc_get_attribute_taxonomies') ) {
    $taxes = wc_get_attribute_taxonomies();
    if ( $taxes ) { foreach ( $taxes as $t ) { echo '  - ' . $t->attribute_label . ' (pa_' . $t->attribute_name . ')' . "\n"; } }
    else { echo "  (none defined — no global size/frame/color attributes)\n"; }
}

// ── 8. Product categories ──────────────────────────────────────
h( 'PRODUCT CATEGORIES' );
$cats = get_terms( array( 'taxonomy'=>'product_cat','hide_empty'=>false,'orderby'=>'name' ) );
echo '  Total categories: ' . count($cats) . "\n";
foreach ( $cats as $c ) {
    $parent = $c->parent ? get_term($c->parent)->name : '—';
    echo sprintf( "  - %-32s (count:%d, parent:%s)\n", $c->name, $c->count, $parent );
}

// ── 9. Menus ───────────────────────────────────────────────────
h( 'NAV MENUS' );
$menus = wp_get_nav_menus();
foreach ( $menus as $m ) {
    $items = wp_get_nav_menu_items( $m->term_id );
    echo '  Menu: ' . $m->name . ' (' . count($items) . ' items)' . "\n";
    foreach ( $items as $it ) {
        $indent = $it->menu_item_parent ? '       ' : '     ';
        echo $indent . '- ' . $it->title . "\n";
    }
}

// ── 10. Pages present ──────────────────────────────────────────
h( 'PAGES (published)' );
$pages = get_posts( array('post_type'=>'page','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC') );
foreach ( $pages as $pg ) { echo '  - ' . $pg->post_title . '  (/' . $pg->post_name . ')' . "\n"; }

// Spec page checklist
h( 'SPEC PAGE CHECKLIST' );
$want = array('about','contact','help','faq','faqs','shipping','return','refund','privacy','terms','content-ethics','legal-imprint','wishlist','track','compare','gift-card','wholesale','corporate','artists','blog');
$existing_slugs = wp_list_pluck( $pages, 'post_name' );
foreach ( $want as $w ) {
    $found = false;
    foreach ( $existing_slugs as $s ) { if ( strpos($s,$w)!==false ) { $found=true; break; } }
    echo '  [' . ($found?'YES':' no') . '] page matching "' . $w . '"' . "\n";
}

// ── 11. Homepage ───────────────────────────────────────────────
h( 'HOMEPAGE' );
$front = get_option('show_on_front');
echo '  show_on_front : ' . $front . "\n";
if ( $front === 'page' ) {
    $fid = get_option('page_on_front');
    $fp  = get_post($fid);
    echo '  Front page    : ' . ( $fp ? $fp->post_title . ' (/' . $fp->post_name . ')' : 'none' ) . "\n";
    echo '  Built with    : ' . ( get_post_meta($fid,'_elementor_edit_mode',true) ? 'Elementor' : 'theme/Gutenberg' ) . "\n";
}

// ── 12. Theme template overrides in child ──────────────────────
h( 'CHILD THEME TEMPLATE OVERRIDES' );
$child_dir = get_stylesheet_directory();
$found_tpl = glob( $child_dir . '/{*.php,woocommerce/**/*.php,template-parts/**/*.php}', GLOB_BRACE );
if ( $found_tpl ) { foreach ( $found_tpl as $f ) { echo '  - ' . str_replace($child_dir.'/','',$f) . "\n"; } }
else { echo "  (only functions.php — no template overrides)\n"; }

echo "\n========== AUDIT COMPLETE ==========\n";
