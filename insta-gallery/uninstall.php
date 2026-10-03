<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( -1 );
}

if ( ! is_multisite() ) {
	$qligg = get_option( 'insta_gallery_settings' );
	if ( ! empty( $qligg['insta_flush'] ) ) {
		delete_option( 'insta_gallery_settings' );
		delete_option( 'insta_gallery_setting' );
		delete_option( 'insta_gallery_items' );
		delete_option( 'insta_gallery_feeds' );
		delete_option( 'insta_gallery_token' );
		delete_option( 'insta_gallery_accounts' );
		delete_option( 'insta_gallery_iac' );
	}
}

$cron_jobs = get_option( 'cron' );

if ( ! empty( $cron_jobs ) && is_array( $cron_jobs ) ) {
	foreach ( $cron_jobs as $timestamp => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( $hooks as $hook_name => $events ) {
			if ( $hook_name === 'qligg_cron_account' ) {
				foreach ( $events as $key => $details ) {
					$account_id = $details['args'][0];
					wp_clear_scheduled_hook( 'qligg_cron_account', array( $account_id ) );
				}
			}
		}
	}
}
