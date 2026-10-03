<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<select name="wpcsm_conditions[' . esc_attr( $index ) . '][value]">';
?>
    <option value="onsale" <?php selected( $condition['value'], 'onsale' ); ?>><?php esc_html_e( 'On sale', 'wpc-smart-messages' ); ?></option>
    <option value="featured" <?php selected( $condition['value'], 'featured' ); ?>><?php esc_html_e( 'Featured', 'wpc-smart-messages' ); ?></option>
    <option value="instock" <?php selected( $condition['value'], 'instock' ); ?>><?php esc_html_e( 'In stock', 'wpc-smart-messages' ); ?></option>
    <option value="outofstock" <?php selected( $condition['value'], 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'wpc-smart-messages' ); ?></option>
    <option value="backorder" <?php selected( $condition['value'], 'backorder' ); ?>><?php esc_html_e( 'On backorder', 'wpc-smart-messages' ); ?></option>
    <option value="managing_stock" <?php selected( $condition['value'], 'managing_stock' ); ?>><?php esc_html_e( 'Managing stock', 'wpc-smart-messages' ); ?></option>
    <option value="sold_individually" <?php selected( $condition['value'], 'sold_individually' ); ?>><?php esc_html_e( 'Sold individually', 'wpc-smart-messages' ); ?></option>
<?php
echo '</select>';