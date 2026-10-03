<?php
/**
 * @var $date_key
 * @var $date
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $date_key ) ) {
	$date_key = Wpced_Helper()->generate_key();
}

$date = array_merge( [ 'type' => '0', 'val' => '' ], $date );
?>
<div class="wpced-skipped-date">
    <button type="button" class="button wpced-date-remove">×</button>
    <label>
        <select class="wpced-date-type"
                name="wpced_settings[skipped_dates][<?php echo esc_attr( $date_key ); ?>][type]">
            <option value="0" <?php selected( $date['type'], '0' ); ?>><?php esc_html_e( 'Weekly on every Sunday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="1" <?php selected( $date['type'], '1' ); ?>><?php esc_html_e( 'Weekly on every Monday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="2" <?php selected( $date['type'], '2' ); ?>><?php esc_html_e( 'Weekly on every Tuesday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="3" <?php selected( $date['type'], '3' ); ?>><?php esc_html_e( 'Weekly on every Wednesday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="4" <?php selected( $date['type'], '4' ); ?>><?php esc_html_e( 'Weekly on every Thursday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="5" <?php selected( $date['type'], '5' ); ?>><?php esc_html_e( 'Weekly on every Friday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="6" <?php selected( $date['type'], '6' ); ?>><?php esc_html_e( 'Weekly on every Saturday', 'wpc-estimated-delivery-date' ); ?></option>
            <option value="cus" <?php selected( $date['type'], 'cus' ); ?>
                    disabled><?php esc_html_e( 'Custom (Premium)', 'wpc-estimated-delivery-date' ); ?></option>
        </select> </label> <label>
        <input type="text" class="wpced-date-val"
               name="wpced_settings[skipped_dates][<?php echo esc_attr( $date_key ); ?>][val]"
               value="<?php echo esc_attr( $date['val'] ); ?>" readonly/>
    </label>
</div>