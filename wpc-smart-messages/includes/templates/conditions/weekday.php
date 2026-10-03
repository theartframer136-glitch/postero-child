<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

if ( ! is_array( $condition['value'] ) ) {
	$condition['value'] = (array) $condition['value'];
}

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<select class="wpcsm_weekday wpcsm_multiple" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][]" multiple>';
?>
    <option value="mon" <?php echo esc_attr( in_array( 'mon', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Monday', 'wpc-smart-messages' ); ?></option>
    <option value="tue" <?php echo esc_attr( in_array( 'tue', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Tuesday', 'wpc-smart-messages' ); ?></option>
    <option value="wed" <?php echo esc_attr( in_array( 'wed', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Wednesday', 'wpc-smart-messages' ); ?></option>
    <option value="thu" <?php echo esc_attr( in_array( 'thu', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Thursday', 'wpc-smart-messages' ); ?></option>
    <option value="fri" <?php echo esc_attr( in_array( 'fri', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Friday', 'wpc-smart-messages' ); ?></option>
    <option value="sat" <?php echo esc_attr( in_array( 'sat', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Saturday', 'wpc-smart-messages' ); ?></option>
    <option value="sun" <?php echo esc_attr( in_array( 'sun', $condition['value'] ) ? 'selected' : '' ); ?>><?php esc_html_e( 'Sunday', 'wpc-smart-messages' ); ?></option>
<?php
echo '</select>';