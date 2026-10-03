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
echo '<select class="wpcsm_monthno wpcsm_multiple" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][]" multiple>';

for ( $i = 1; $i < 13; $i ++ ) {
	echo '<option value="' . esc_attr( $i ) . '" ' . esc_attr( in_array( $i, $condition['value'] ) ? 'selected' : '' ) . '>' . esc_html( $i ) . '</option>';
}

echo '</select>';