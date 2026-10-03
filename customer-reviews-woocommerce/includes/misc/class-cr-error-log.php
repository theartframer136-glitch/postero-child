<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'CR_Error_Log' ) ) :

	class CR_Error_Log {

		const OPTION = 'ivole_errors';
		const USER_META = 'cr_dismissed_errors';
		const LOCK_PREFIX = 'cr_err_lock_';
		const LOCK_TTL = 5 * MINUTE_IN_SECONDS;
		const MAX_TYPES = 30;
		const MAX_MESSAGE = 500;
		const MAX_LABEL = 50;

		/**
		 * Stores the latest occurrence of an error of a given type.
		 * Only one entry per type is kept; repeated occurrences bump the counter.
		 * $action is an array with 'label' and 'url' keys, shown as a link next to the message.
		 */
		public static function record( $type, $message, $action ) {
			$type = sanitize_key( $type );
			if ( ! $type ) {
				return;
			}

			// throttle writes because errors can be triggered by public AJAX requests
			if ( get_transient( self::LOCK_PREFIX . $type ) ) {
				return;
			}
			set_transient( self::LOCK_PREFIX . $type, 1, self::LOCK_TTL );

			$errors = get_option( self::OPTION, array() );
			if ( ! is_array( $errors ) ) {
				$errors = array();
			}

			$now = time();
			$existing = isset( $errors[$type] ) ? $errors[$type] : array();

			$errors[$type] = array(
				'message' => self::sanitize_message( $message ),
				'action' => self::sanitize_action( $action ),
				'first_seen' => isset( $existing['first_seen'] ) ? $existing['first_seen'] : $now,
				'last_seen' => $now,
				'count' => isset( $existing['count'] ) ? intval( $existing['count'] ) + 1 : 1
			);

			if ( count( $errors ) > self::MAX_TYPES ) {
				uasort( $errors, function( $a, $b ) {
					return intval( $b['last_seen'] ) - intval( $a['last_seen'] );
				} );
				$errors = array_slice( $errors, 0, self::MAX_TYPES, true );
			}

			update_option( self::OPTION, $errors, false );
		}

		/**
		 * Removes an error once the underlying problem is resolved.
		 */
		public static function clear( $type ) {
			$type = sanitize_key( $type );
			$errors = get_option( self::OPTION, array() );

			if ( ! is_array( $errors ) || ! isset( $errors[$type] ) ) {
				return;
			}

			unset( $errors[$type] );
			// drop the throttle so that the next occurrence is recorded immediately
			delete_transient( self::LOCK_PREFIX . $type );
			self::clear_dismissals( $type );
			update_option( self::OPTION, $errors, false );
		}

		/**
		 * Drops the 'never show again' choice of every user, so that the error is visible if it happens again.
		 */
		private static function clear_dismissals( $type ) {
			$users = get_users( array(
				'meta_key' => self::USER_META,
				'fields' => 'ID'
			) );

			foreach ( $users as $user_id ) {
				$dismissed = self::get_dismissals( $user_id );
				if ( ! isset( $dismissed[$type] ) ) {
					continue;
				}

				unset( $dismissed[$type] );
				if ( $dismissed ) {
					update_user_meta( $user_id, self::USER_META, $dismissed );
				} else {
					delete_user_meta( $user_id, self::USER_META );
				}
			}
		}

		/**
		 * Errors that the current user has not dismissed, newest first.
		 */
		public static function get_active_errors( $user_id = 0 ) {
			$errors = get_option( self::OPTION, array() );
			if ( ! is_array( $errors ) ) {
				return array();
			}

			foreach ( $errors as $type => $error ) {
				if ( self::is_dismissed( $type, $error['last_seen'], $user_id ) ) {
					unset( $errors[$type] );
				}
			}

			uasort( $errors, function( $a, $b ) {
				return intval( $b['last_seen'] ) - intval( $a['last_seen'] );
			} );

			return $errors;
		}

		/**
		 * $never = true keeps the error hidden even if it happens again.
		 */
		public static function dismiss( $type, $never = false, $user_id = 0 ) {
			$type = sanitize_key( $type );
			$user_id = $user_id ? intval( $user_id ) : get_current_user_id();
			if ( ! $type || ! $user_id ) {
				return false;
			}

			$dismissed = self::get_dismissals( $user_id );
			$dismissed[$type] = $never ? 'never' : time();

			return update_user_meta( $user_id, self::USER_META, $dismissed );
		}

		/**
		 * $last_seen comes from the recorded error, so a new occurrence un-hides it.
		 */
		public static function is_dismissed( $type, $last_seen, $user_id = 0 ) {
			$user_id = $user_id ? intval( $user_id ) : get_current_user_id();
			$dismissed = self::get_dismissals( $user_id );
			$type = sanitize_key( $type );

			if ( ! isset( $dismissed[$type] ) ) {
				return false;
			}

			if ( 'never' === $dismissed[$type] ) {
				return true;
			}

			return intval( $dismissed[$type] ) >= intval( $last_seen );
		}

		private static function get_dismissals( $user_id ) {
			$dismissed = $user_id ? get_user_meta( $user_id, self::USER_META, true ) : array();

			return is_array( $dismissed ) ? $dismissed : array();
		}

		private static function sanitize_message( $message ) {
			if ( is_wp_error( $message ) ) {
				$message = $message->get_error_message();
			} elseif ( ! is_scalar( $message ) ) {
				$message = wp_json_encode( $message );
			}

			return substr( wp_strip_all_tags( strval( $message ) ), 0, self::MAX_MESSAGE );
		}

		private static function sanitize_action( $action ) {
			if ( ! is_array( $action ) || empty( $action['label'] ) || empty( $action['url'] ) ) {
				return array();
			}

			$url = esc_url_raw( $action['url'], array( 'http', 'https' ) );
			if ( ! $url ) {
				return array();
			}

			return array(
				'label' => substr( wp_strip_all_tags( strval( $action['label'] ) ), 0, self::MAX_LABEL ),
				'url' => $url
			);
		}

	}

endif;
