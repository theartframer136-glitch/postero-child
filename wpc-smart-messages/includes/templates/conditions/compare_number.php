<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

if ( ! is_array( $condition['value'] ) ) {
	$condition['value'] = [ 'compare' => '', 'number' => '' ];
} else {
	$condition['value'] = array_merge( [ 'compare' => '', 'number' => '' ], $condition['value'] );
}

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';

echo '<select class="input-compare" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][compare]">';
echo '<option value="equal" ' . selected( $condition['value']['compare'], 'equal', false ) . '>' . esc_html__( 'Equal to', 'wpc-smart-messages' ) . '</option>';
echo '<option value="not_equal" ' . selected( $condition['value']['compare'], 'not_equal', false ) . '>' . esc_html__( 'Not equal to', 'wpc-smart-messages' ) . '</option>';
echo '<option value="greater" ' . selected( $condition['value']['compare'], 'greater', false ) . '>' . esc_html__( 'Greater than', 'wpc-smart-messages' ) . '</option>';
echo '<option value="greater_equal" ' . selected( $condition['value']['compare'], 'greater_equal', false ) . '>' . esc_html__( 'Greater or equal to', 'wpc-smart-messages' ) . '</option>';
echo '<option value="less" ' . selected( $condition['value']['compare'], 'less', false ) . '>' . esc_html__( 'Less than', 'wpc-smart-messages' ) . '</option>';
echo '<option value="less_equal" ' . selected( $condition['value']['compare'], 'less_equal', false ) . '>' . esc_html__( 'Less or equal to', 'wpc-smart-messages' ) . '</option>';
echo '</select>';

echo '<input type="number" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][number]" value="' . esc_attr( $condition['value']['number'] ) . '"/>';