<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][value]" value=""/>';