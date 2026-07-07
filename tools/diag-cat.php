<?php
/**
 * Diagnose why some category cards render blank. Lists the products in a
 * category in display order with image id/url, stock, type, visibility.
 * Read-only. Run: wp eval-file tools/diag-cat.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
if ( ! function_exists( 'wc_get_product' ) ) { echo "WooCommerce not active\n"; exit(1); }

$slug = 'radha-krishna';
$term = get_term_by('slug', $slug, 'product_cat');
if (!$term) { echo "Category '$slug' not found\n"; exit; }

// Match a typical shop query: visible, published, ordered by menu_order then date
$q = new WP_Query(array(
    'post_type'=>'product','post_status'=>'publish','posts_per_page'=>20,
    'orderby'=>array('menu_order'=>'ASC','date'=>'DESC'),
    'tax_query'=>array(
        array('taxonomy'=>'product_cat','field'=>'term_id','terms'=>$term->term_id),
    ),
));

echo "=== CATEGORY '{$slug}' — display order ({$q->found_posts} found) ===\n\n";
$i=0;
foreach ($q->posts as $post) {
    $p = wc_get_product($post->ID); if(!$p) continue;
    $i++;
    $imgid = $p->get_image_id();
    $url = $imgid ? wp_get_attachment_image_url($imgid,'woocommerce_thumbnail') : '';
    $file = $imgid ? get_attached_file($imgid) : '';
    $exists = ($file && file_exists($file)) ? 'file-OK' : ($imgid ? 'FILE-MISSING' : 'no-featured');
    $vis = get_post_meta($post->ID,'_visibility',true);
    $catvis = has_term('exclude-from-catalog','product_visibility',$post->ID) ? 'HIDDEN-catalog' : 'visible';
    echo sprintf("%2d. #%d [%s] type=%s imgid=%s %s %s\n     %s\n     %s\n",
        $i, $post->ID, ($p->is_in_stock()?'in-stock':'OUTOFSTOCK'),
        $p->get_type(), ($imgid?:'0'), $exists, $catvis,
        mb_substr($post->post_title,0,60), $url ?: '(no url)');
}
echo "\n=== DONE ===\n";
