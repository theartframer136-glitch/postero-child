<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';

echo '<select class="input-compare" name="wpcsm_conditions[' . esc_attr( $index ) . '][compare]">';
echo '<option value="including" ' . selected( 'including', $condition['compare'], false ) . '>' . esc_html__( 'including', 'wpc-smart-messages' ) . '</option>';
echo '<option value="excluding" ' . selected( 'excluding', $condition['compare'], false ) . '>' . esc_html__( 'excluding', 'wpc-smart-messages' ) . '</option>';
echo '</select>';

echo '<select class="wpcsm_term_selector" data-taxonomy="' . esc_attr( $condition['type'] ) . '" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][]" multiple="multiple">';

if ( ! empty( $condition['value'] ) ) {
	foreach ( $condition['value'] as $val ) {
		if ( $term = get_term_by( 'slug', $val, $condition['type'] ) ) {
			echo '<option value="' . esc_attr( $val ) . '" selected>' . esc_html( $term->name ) . '</option>';
		}
	}
}

echo '</select>';