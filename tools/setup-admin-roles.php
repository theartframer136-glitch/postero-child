<?php
/**
 * Granular admin roles (spec §2): Order Manager, Content Manager,
 * Customer Support Executive. Also grants the af_view_contact_messages
 * capability so these roles (and admins/shop managers) can use the
 * wp-admin "Contact Messages" screen.
 * Idempotent: roles are re-created on every run so cap changes apply.
 * Run: wp eval-file tools/setup-admin-roles.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== Setup granular admin roles ===\n\n";

/* Order caps shared by order-facing roles */
$order_caps = array(
    'read'                    => true,
    'edit_shop_orders'        => true,
    'edit_others_shop_orders' => true,
    'read_private_shop_orders'=> true,
    'publish_shop_orders'     => true,
    'edit_shop_order'         => true,
    'read_shop_order'         => true,
    'af_view_contact_messages'=> true,
);

/* 1. Order Manager — orders + reports, nothing else */
remove_role( 'af_order_manager' );
add_role( 'af_order_manager', 'Order Manager', array_merge( $order_caps, array(
    'view_woocommerce_reports' => true,
) ) );
echo "ROLE  af_order_manager (Order Manager): orders, reports, contact messages\n";

/* 2. Content Manager — posts, pages, media, comments + products */
$editor = get_role( 'editor' );
$content_caps = $editor ? $editor->capabilities : array( 'read' => true, 'edit_posts' => true, 'edit_pages' => true, 'upload_files' => true );
$content_caps = array_merge( $content_caps, array(
    'edit_products'           => true,
    'edit_others_products'    => true,
    'publish_products'        => true,
    'read_private_products'   => true,
    'edit_product'            => true,
    'read_product'            => true,
    'assign_product_terms'    => true,
    'manage_product_terms'    => true,
) );
remove_role( 'af_content_manager' );
add_role( 'af_content_manager', 'Content Manager', $content_caps );
echo "ROLE  af_content_manager (Content Manager): posts, pages, media, products\n";

/* 3. Customer Support Executive — view/annotate orders + contact messages */
remove_role( 'af_support' );
add_role( 'af_support', 'Customer Support Executive', $order_caps );
echo "ROLE  af_support (Customer Support Executive): orders, contact messages\n";

/* 4. Grant contact-message access to admins and shop managers */
foreach ( array( 'administrator', 'shop_manager' ) as $r ) {
    $role = get_role( $r );
    if ( $role && ! $role->has_cap( 'af_view_contact_messages' ) ) {
        $role->add_cap( 'af_view_contact_messages' );
        echo "CAP   af_view_contact_messages -> {$r}\n";
    }
}

echo "\n=== DONE. Assign roles in wp-admin -> Users -> (edit user) -> Role. ===\n";
