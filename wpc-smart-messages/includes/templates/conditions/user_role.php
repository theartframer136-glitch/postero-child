<?php
defined( 'ABSPATH' ) || exit;

/**
 * @var $index
 * @var $condition
 */

global $wp_roles;

if ( ! is_array( $condition['value'] ) ) {
	$condition['value'] = (array) $condition['value'];
}

echo '<input type="hidden" name="wpcsm_conditions[' . esc_attr( $index ) . '][group]" value="' . esc_attr( $condition['group'] ) . '"/>';
echo '<select class="wpcsm_userrole wpcsm_multiple" name="wpcsm_conditions[' . esc_attr( $index ) . '][value][]" multiple>';
echo '<option value="wpcsm_user" ' . ( in_array( 'wpcsm_user', $condition['value'] ) ? 'selected' : '' ) . '>' . esc_html__( 'User (logged in)', 'wpc-smart-messages' ) . '</option>';
echo '<option value="wpcsm_guest" ' . ( in_array( 'wpcsm_guest', $condition['value'] ) ? 'selected' : '' ) . '>' . esc_html__( 'Guest (not logged in)', 'wpc-smart-messages' ) . '</option>';

foreach ( $wp_roles->roles as $role => $details ) {
	echo '<option value="' . esc_attr( $role ) . '" ' . ( in_array( $role, $condition['value'] ) ? 'selected' : '' ) . '>' . esc_html( $details['name'] ) . '</option>';
}

echo '</select>';