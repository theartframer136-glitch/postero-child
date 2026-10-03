<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<input type="text" name="wpcsm_conditions[' . esc_attr( $index ) . '][value]" value="' . esc_attr( $condition['value'] ) . '" class="wpcsm_date_time wpcsm_date_input" readonly="readonly"/>';