<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WOOSW_LITE' ) ? WOOSW_LITE : WOOSW_FILE, 'woosw_activate' );
register_deactivation_hook( defined( 'WOOSW_LITE' ) ? WOOSW_LITE : WOOSW_FILE, 'woosw_deactivate' );
add_action( 'admin_init', 'woosw_check_version' );

function woosw_check_version() {
	if ( ! empty( get_option( 'woosw_version' ) ) && ( get_option( 'woosw_version' ) < WOOSW_VERSION ) ) {
		wpc_log( 'woosw', 'upgraded' );
		update_option( 'woosw_version', WOOSW_VERSION, false );
	}
}

function woosw_activate() {
	wpc_log( 'woosw', 'installed' );
	update_option( 'woosw_version', WOOSW_VERSION, false );
}

function woosw_deactivate() {
	wpc_log( 'woosw', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}