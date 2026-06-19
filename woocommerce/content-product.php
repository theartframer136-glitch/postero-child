<?php
/**
 * Child theme override: content-product.php
 * Restores standard WooCommerce hooks so Add to Cart button and custom
 * PHP hooks (Quick View, discount badge) fire correctly on shop/category pages.
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}
?>
<li <?php wc_product_class( '', $product ); ?>>
    <?php
    do_action( 'woocommerce_before_shop_loop_item' );
    do_action( 'woocommerce_before_shop_loop_item_title' );
    do_action( 'woocommerce_shop_loop_item_title' );
    do_action( 'woocommerce_after_shop_loop_item_title' );
    do_action( 'woocommerce_after_shop_loop_item' );
    ?>
</li>
