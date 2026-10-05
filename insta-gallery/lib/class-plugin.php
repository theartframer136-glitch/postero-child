<?php

namespace QuadLayers\IGG;

use QuadLayers\IGG\Controllers\Backend;
use QuadLayers\IGG\Controllers\Elementor;
use QuadLayers\IGG\Controllers\Frontend;
use QuadLayers\IGG\Controllers\Gutenberg;
use QuadLayers\IGG\Models\Accounts as Models_Accounts;
use QuadLayers\IGG\Models\Settings as Models_Settings;
use QuadLayers\IGG\Services\File_Logger;
use QuadLayers\IGG\Services\Email_Service;

final class Plugin {

	protected static $instance;

	private function __construct() {
		/**
		 * Load plugin textdomain.
		 */
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		/**
		 * Load api classes.
		 */
		Api\Rest\Routes_Library::instance();
		/**
		 * Load plugin classes.ß
		 */
		Frontend::instance();
		Backend::instance();
		Gutenberg::instance();
		Elementor::instance();

		do_action( 'qligg_init' );

		// Filter to add dynamic interval to cron_schedules.
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				// Calculate dynamic interval based on existing tokens
				$dynamic_interval_days = $this->calculate_average_token_interval();

				$schedules['qligg_dynamic_token_check'] = array(
					'interval' => DAY_IN_SECONDS * $dynamic_interval_days,
					'display'  => sprintf( esc_html__( 'Every %d days (dynamic token check)', 'insta-gallery' ), $dynamic_interval_days ),
				);

				// Keep fifty_days for compatibility
				$schedules['fifty_days'] = array(
					'interval' => DAY_IN_SECONDS * 50,
					'display'  => esc_html__( 'Every fifty days', 'insta-gallery' ),
				);

				return $schedules;
			}
		);

		// Action to auto renew account access_token, if it can be done automatically. Send an email to inform admin about access_token expiration.
		add_action(
			'qligg_cron_account',
			array( $this, 'send_expiration_mail' ),
			10,
			1
		);
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'insta-gallery', false, dirname( QLIGG_PLUGIN_BASENAME ) . '/languages' );
	}

	public function send_expiration_mail( $id ) {
		$file_logger = File_Logger::instance();

		// Log start of process
		$file_logger->log( 'INFO', 'send_expiration_mail function called', array( 'account_id' => $id ), 'general' );

		// Get account data and attempt renewal
		$account_data = $this->prepare_account_data( $id, $file_logger );

		// Handle successful token renewal
		if ( $this->is_token_renewed( $account_data ) ) {
			$this->handle_token_renewed( $account_data, $file_logger );
			$this->log_completion( $id, $file_logger );
			return;
		}

		// Handle missing token case
		if ( $this->is_token_missing( $account_data['account_renewed'] ) ) {
			$this->handle_token_missing( $account_data, $file_logger );
			$this->log_completion( $id, $file_logger );
			return;
		}

		// Handle token expiration scenarios
		$this->handle_token_expiration( $account_data, $file_logger );
		$this->log_completion( $id, $file_logger );
	}

	/**
	 * Prepare account data and attempt token renewal
	 */
	private function prepare_account_data( $id, $file_logger ) {
		// Get account data before trying to renew
		$account             = Models_Accounts::instance()->get( $id );
		$old_expiration_date = $account['access_token_expiration_date'];

		// Log account data retrieval
		$file_logger->log(
			'INFO',
			'Token renewal process started',
			array(
				'account_id'         => $id,
				'current_expiration' => $account['access_token_expiration_date'] ?? 'unknown',
				'username'           => $account['username'] ?? 'unknown',
			),
			'token_renewal'
		);

		// Calculate and log days until expiration for debugging
		$current_time              = time();
		$days_until_expiration     = max( 0, ceil( ( $old_expiration_date - $current_time ) / DAY_IN_SECONDS ) );
		$expiration_date_formatted = date_i18n( get_option( 'date_format' ), $old_expiration_date );

		$file_logger->log(
			'INFO',
			'Token expiration analysis',
			array(
				'account_id'                => $id,
				'current_timestamp'         => $current_time,
				'expiration_timestamp'      => $old_expiration_date,
				'days_until_expiration'     => $days_until_expiration,
				'expiration_date_formatted' => $expiration_date_formatted,
			),
			'token_renewal'
		);

		// Attempt to renew the token
		Models_Accounts::instance()->is_access_token_renewed( $account );

		// Get account data again after renewal attempt
		$account_renewed = Models_Accounts::instance()->get( $id );
		$new_expiration  = $account_renewed['access_token_expiration_date'];

		return array(
			'id'                        => $id,
			'account_original'          => $account,
			'account_renewed'           => $account_renewed,
			'old_expiration_date'       => $old_expiration_date,
			'new_expiration'            => $new_expiration,
			'days_until_expiration'     => $days_until_expiration,
			'expiration_date_formatted' => $expiration_date_formatted,
			'admin_email'               => $this->get_admin_email(),
		);
	}

	/**
	 * Check if token was successfully renewed
	 */
	private function is_token_renewed( $account_data ) {
		return $account_data['new_expiration'] > $account_data['old_expiration_date'];
	}

	/**
	 * Check if token is missing completely
	 */
	private function is_token_missing( $account_renewed ) {
		return ! isset( $account_renewed['access_token'] );
	}

	/**
	 * Handle successful token renewal
	 */
	private function handle_token_renewed( $account_data, $file_logger ) {

		$days_extended = round( ( $account_data['new_expiration'] - $account_data['old_expiration_date'] ) / DAY_IN_SECONDS, 2 );

		$file_logger->log(
			'SUCCESS',
			'Token renewed successfully',
			array(
				'account_id'     => $account_data['id'],
				'old_expiration' => gmdate( 'Y-m-d H:i:s', $account_data['old_expiration_date'] ),
				'new_expiration' => gmdate( 'Y-m-d H:i:s', $account_data['new_expiration'] ),
				'days_extended'  => $days_extended,
			),
			'token_renewal'
		);

		$message = sprintf(
			__( "Great news! Your Instagram token has been automatically renewed for Social Feed Gallery.\n\nNew expiration date: %s\n\nNo action needed - your Instagram feed will continue working seamlessly.", 'insta-gallery' ),
			date_i18n( get_option( 'date_format' ), $account_data['new_expiration'] )
		) . $this->get_email_footer();

		$subject = esc_html__( '✅ Instagram Token Renewed Successfully', 'insta-gallery' );

		Email_Service::instance()->send_email_with_retry(
			'renewal_success',
			$account_data['admin_email'],
			$subject,
			$message,
			$file_logger
		);
	}

	/**
	 * Handle token expiration scenarios
	 */
	private function handle_token_expiration( $account_data, $file_logger ) {
		// Log token expiration event
		$file_logger->log(
			'WARNING',
			'Token expiration detected - sending warning email',
			array(
				'account_id'            => $account_data['id'],
				'days_until_expiration' => $account_data['days_until_expiration'],
				'expiration_date'       => $account_data['expiration_date_formatted'],
				'username'              => $account_data['account_renewed']['username'] ?? 'unknown',
			),
			'token_expiration'
		);

		$subject = $this->get_expiration_subject( $account_data );
		$message = $this->get_expiration_message( $account_data ) . $this->get_email_footer();

		Email_Service::instance()->send_email_with_retry(
			'expiration_warning',
			$account_data['admin_email'],
			$subject,
			$message,
			$file_logger
		);
	}

	/**
	 * Handle missing token case
	 */
	private function handle_token_missing( $account_data, $file_logger ) {
		// Log critical missing token event
		$file_logger->log(
			'ERROR',
			'Critical: Token completely missing - manual reconnection required',
			array(
				'account_id'      => $account_data['id'],
				'expiration_date' => $account_data['expiration_date_formatted'],
				'username'        => $account_data['account_original']['username'] ?? 'unknown',
				'issue'           => 'access_token_completely_missing',
				'action_required' => 'manual_reconnection',
			),
			'token_missing'
		);

		$message = sprintf(
			__( "Your Instagram connection for Social Feed Gallery has been lost and cannot be restored automatically.\n\n⚠️ IMMEDIATE ACTION REQUIRED:\n\n1. Go to your WordPress admin dashboard\n2. Navigate to Social Feed Gallery settings: %s\n3. Reconnect your Instagram account\n\nWithout reconnection, your Instagram feeds will stop displaying new content.", 'insta-gallery' ),
			QLIGG_ACCOUNT_URL
		) . $this->get_email_footer();
		$subject = esc_html__( '🚨 URGENT: Instagram Connection Lost - Action Required', 'insta-gallery' );

		Email_Service::instance()->send_email_with_retry(
			'urgent_missing_token',
			$account_data['admin_email'],
			$subject,
			$message,
			$file_logger
		);
	}

	/**
	 * Get appropriate expiration subject based on days until expiration
	 */
	private function get_expiration_subject( $account_data ) {
		$days = $account_data['days_until_expiration'];

		// Token already expired
		if ( $days <= 0 ) {
			return esc_html__( '🔴 Instagram Token Expired - Reconnection Needed', 'insta-gallery' );
		}

		// Token expires within 7 days
		if ( $days <= 7 ) {
			return sprintf(
				esc_html__( '⚠️ URGENT: Instagram Token Expires in %d Day(s)', 'insta-gallery' ),
				$days
			);
		}

		// Token expires in more than 7 days
		return sprintf(
			esc_html__( '📅 Reminder: Instagram Token Expires in %d Days', 'insta-gallery' ),
			$days
		);
	}

	/**
	 * Get appropriate expiration message based on days until expiration
	 */
	private function get_expiration_message( $account_data ) {
		$days           = $account_data['days_until_expiration'];
		$formatted_date = $account_data['expiration_date_formatted'];

		// Token already expired
		if ( $days <= 0 ) {
			return sprintf(
				__( "Your Instagram token for Social Feed Gallery expired on %s.\n\n⚠️ Your Instagram feeds may not display new content until you reconnect.\n\nTo restore functionality:\n1. Go to WordPress Admin → Social Feed Gallery\n2. Reconnect your Instagram account\n3. Authorize the connection\n\nThis is a routine Instagram security requirement that happens every 60 days.", 'insta-gallery' ),
				$formatted_date
			);
		}

		// Token expires within 7 days
		if ( $days <= 7 ) {
			return sprintf(
				__( "⚠️ URGENT: Your Instagram token expires in %1\$d day(s) on %2\$s\n\nYour Social Feed Gallery Instagram feeds will stop working after this date.\n\nTO PREVENT INTERRUPTION:\n1. Go to WordPress Admin → Social Feed Gallery\n2. Reconnect your Instagram account now\n3. Complete the authorization process\n\nDon't wait - reconnect today to ensure uninterrupted service!", 'insta-gallery' ),
				$days,
				$formatted_date
			);
		}

		// Token expires in more than 7 days
		return sprintf(
			__( "Your Instagram token for Social Feed Gallery expires in %1\$d days on %2\$s\n\nThis is a friendly reminder to help you stay ahead of any service interruptions.\n\nWHAT TO DO:\n1. Mark your calendar for %2\$s\n2. A few days before, go to WordPress Admin → Social Feed Gallery\n3. Reconnect your Instagram account\n\nWe'll send you another reminder closer to the expiration date.\n\nThis is normal Instagram security maintenance that occurs every 60 days.", 'insta-gallery' ),
			$days,
			$formatted_date
		);
	}

	/**
	 * Get email footer with QuadLayers branding
	 */
	private function get_email_footer() {
		return sprintf(
			__( "\n\n---\n\nBest regards,\nQuadLayers Team\n\nVisit us: %s\nSupport: Need help with Social Feed Gallery? Contact our support team.", 'insta-gallery' ),
			'https://quadlayers.com'
		);
	}

	/**
	 * Log completion of the process
	 */
	private function log_completion( $id, $file_logger ) {
		$file_logger->log( 'INFO', 'send_expiration_mail function completed', array( 'account_id' => $id ), 'general' );
	}



	/**
	 * Calculate average token interval based on existing accounts
	 * Returns half of the average days until expiration, with min 25 and max 30 days
	 */
	private function calculate_average_token_interval() {
		$accounts = Models_Accounts::instance()->get_all();

		if ( empty( $accounts ) ) {
			// If no accounts, use default of 25 days (half of 50)
			return 25;
		}

		$current_time   = time();
		$total_days     = 0;
		$valid_accounts = 0;

		foreach ( $accounts as $account ) {
			if ( isset( $account['access_token_expiration_date'] ) && $account['access_token_expiration_date'] > $current_time ) {
				$days_until_expiration = ceil( ( $account['access_token_expiration_date'] - $current_time ) / DAY_IN_SECONDS );
				$total_days           += $days_until_expiration;
				++$valid_accounts;
			}
		}

		if ( $valid_accounts === 0 ) {
			// If no valid accounts, use default of 25 days (half of 50)
			return 25;
		}

		// Calculate average days until expiration and take half
		$average_days  = $total_days / $valid_accounts;
		$interval_days = floor( $average_days / 2 );

		// Apply limits: minimum 10 days, maximum 30 days
		$interval_days = max( 10, min( 30, $interval_days ) );

		return $interval_days;
	}

	protected function get_admin_email() {
		$user_settings = Models_Settings::instance()->get();
		if ( isset( $user_settings['mail_to_alert'] ) ) {
			return $user_settings['mail_to_alert'];
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return get_site_option( 'admin_email' );
		}
		return get_option( 'admin_email' );
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

Plugin::instance();
