<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

if ( ! is_array( $condition['value'] ) ) {
	$condition['value'] = [ 'start' => '', 'end' => '' ];
} else {
	$condition['value'] = array_merge( [ 'start' => '', 'end' => '' ], $condition['value'] );
}

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<input type="text" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][start]" value="' . esc_attr( $condition['value']['start'] ) . '" class="wpcsm_time wpcsm_time_input" readonly="readonly"/>';
echo '<input type="text" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][end]" value="' . esc_attr( $condition['value']['end'] ) . '" class="wpcsm_time wpcsm_time_input" readonly="readonly"/>';