<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Assign filterable global attributes (pa_size, pa_colors, pa_frame) to every
 * published product so the shop/category filters actually work.
 * - pa_size  : the artwork's real size parsed from its title (one term), else a
 *              standard set. Meaningful — narrows results.
 * - pa_colors: standard frame colors (Black, Silver, Gold, Rose Gold).
 * - pa_frame : standard frame types (Without, Fibre, Floating, Aluminium).
 * Attributes are set visible=false (kept out of the Additional Information table
 * to avoid clutter) but the term assignment makes ?filter_* work.
 * SAFE: preserves existing (Phase 1) attributes; idempotent.
 * Run: wp eval-file tools/assign-filter-attributes.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

// Ensure an attribute taxonomy exists; return its full taxonomy name (pa_xxx)
function af_ensure_taxonomy($slug, $label) {
    $tax = wc_attribute_taxonomy_name($slug); // pa_slug
    if (!taxonomy_exists($tax)) {
        $id = wc_attribute_taxonomy_id_by_name($slug);
        if (!$id) {
            wc_create_attribute(array('name'=>$label,'slug'=>$slug,'type'=>'select','order_by'=>'menu_order','has_archives'=>false));
        }
        register_taxonomy($tax, array('product'), array('hierarchical'=>false,'show_ui'=>false,'query_var'=>true,'rewrite'=>false));
    }
    return $tax;
}

// Get or create a term, return term_id
function af_term_id($tax, $name) {
    $t = get_term_by('name', $name, $tax);
    if ($t) return (int)$t->term_id;
    $r = wp_insert_term($name, $tax);
    return is_wp_error($r) ? 0 : (int)$r['term_id'];
}

$tax_size  = af_ensure_taxonomy('size',   'Size');
$tax_color = af_ensure_taxonomy('colors', 'Colors');
$tax_frame = af_ensure_taxonomy('frame',  'Frame');

$STD_SIZES  = array('24×36 in','30×45 in','36×54 in','48×72 in');
$COLORS     = array('Black','Silver','Gold','Rose Gold');
$FRAMES     = array('Without Frame','Fibre Frame','Floating Frame','Aluminium Frame');

// Pre-create color & frame terms
$color_ids = array(); foreach ($COLORS as $c) $color_ids[] = af_term_id($tax_color, $c);
$frame_ids = array(); foreach ($FRAMES as $f) $frame_ids[] = af_term_id($tax_frame, $f);
$color_ids = array_filter($color_ids); $frame_ids = array_filter($frame_ids);

$ids = get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids'));
echo "=== ASSIGN FILTER ATTRIBUTES (" . count($ids) . " products) ===\n\n";

$done = 0;
foreach ($ids as $pid) {
    $product = wc_get_product($pid);
    if (!$product) continue;

    // Real size from title, else standard set
    $title = $product->get_name();
    $size_names = array();
    if (preg_match('/(\d+(?:\.\d+)?)\s*[×xX*]\s*(\d+(?:\.\d+)?)/u', $title, $m)) {
        $w = rtrim(rtrim($m[1],'0'),'.'); $h = rtrim(rtrim($m[2],'0'),'.');
        $unit = (stripos($title,'ft')!==false || stripos($title,'feet')!==false) ? ' ft' : ' in';
        $size_names = array($w.'×'.$h.$unit);
    } else {
        $size_names = $STD_SIZES;
    }
    $size_ids = array(); foreach ($size_names as $s) $size_ids[] = af_term_id($tax_size, $s);
    $size_ids = array_filter($size_ids);

    // Preserve existing attributes, add/replace the 3 filterable ones (hidden)
    $attrs = $product->get_attributes();

    $mk = function($tax, $ids, $visible=false) {
        $a = new WC_Product_Attribute();
        $a->set_id(wc_attribute_taxonomy_id_by_name(str_replace('pa_','',$tax)));
        $a->set_name($tax);
        $a->set_options($ids);
        $a->set_visible($visible);
        $a->set_variation(false);
        return $a;
    };
    $attrs[$tax_size]  = $mk($tax_size,  $size_ids);
    $attrs[$tax_color] = $mk($tax_color, $color_ids);
    $attrs[$tax_frame] = $mk($tax_frame, $frame_ids);

    $product->set_attributes($attrs);
    $product->save();

    // Ensure terms are actually attached (WC does this on save, but be explicit)
    wp_set_object_terms($pid, $size_ids,  $tax_size,  false);
    wp_set_object_terms($pid, $color_ids, $tax_color, false);
    wp_set_object_terms($pid, $frame_ids, $tax_frame, false);

    clean_post_cache($pid);
    wc_delete_product_transients($pid);
    $done++;
}
echo "Assigned filterable size/color/frame to {$done} products.\n";
echo "=== DONE ===\n";
