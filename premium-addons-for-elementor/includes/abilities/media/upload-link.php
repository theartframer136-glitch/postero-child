<?php
/**
 * Upload Link.
 *
 * Single-use links an agent sends a local file to, for files that have no
 * public URL: a chat attachment in a code sandbox or a file on disk.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Media;

use PremiumAddons\Admin\Includes\Admin_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Upload_Link.
 *
 * @since 4.11.117
 */
class Upload_Link {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'premium-addons/v1';

	/**
	 * REST route.
	 */
	const REST_ROUTE = '/media-upload';

	/**
	 * Request header carrying the upload token.
	 */
	const TOKEN_HEADER = 'X-PA-Upload-Token';

	/**
	 * Seconds a link stays valid.
	 */
	const TOKEN_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Transient key prefix for stored tokens.
	 */
	const TRANSIENT_PREFIX = 'pa_upload_link_';

	/**
	 * Error codes from Upload_Media::store_file() mapped to their HTTP status.
	 */
	const STORE_ERROR_STATUS = array(
		'premium_addons_invalid_file_size' => 413,
		'premium_addons_invalid_file_type' => 415,
		'premium_addons_not_initialized'   => 507,
	);

	/**
	 * Token data claimed by check_token() for handle_upload().
	 *
	 * The permission callback and the handler run in the same request, and a
	 * claimed token is already deleted, so it is handed over here.
	 *
	 * @var array|null
	 */
	private static $claimed_token_data = null;

	/**
	 * Register the upload route.
	 *
	 * @return void
	 */
	public static function register_routes() {

		// No args: the body is the raw file, never parsed as parameters.
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST, PUT',
				'callback'            => array( __CLASS__, 'handle_upload' ),
				'permission_callback' => array( __CLASS__, 'check_token' ),
			)
		);
	}

	/**
	 * Authorize the upload from its token, as the user who created the link.
	 *
	 * The ability and the user's capability are checked again here, so a link
	 * stops working once either is taken away.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_token( $request ) {

		if ( ! Admin_Helper::is_ability_enabled( 'premium-addons/upload-media' ) ) {
			return new \WP_Error(
				'premium_addons_not_initialized',
				__( 'Uploading media is turned off on this site.', 'premium-addons-for-elementor' ),
				array( 'status' => 403 )
			);
		}

		$token = (string) $request->get_header( self::TOKEN_HEADER );

		if ( '' === $token ) {
			return new \WP_Error(
				'premium_addons_missing_upload_token',
				sprintf(
					/* translators: %s: the request header name. */
					__( 'Send the upload token in the %s header.', 'premium-addons-for-elementor' ),
					self::TOKEN_HEADER
				),
				array( 'status' => 401 )
			);
		}

		$body_error = self::check_body( $request, $token );

		if ( $body_error ) {
			return $body_error;
		}

		$token_data = self::claim( $token );

		if ( ! $token_data ) {
			return new \WP_Error(
				'premium_addons_invalid_upload_token',
				__( 'This upload link is not valid, has expired, or was already used. Call premium-addons/upload-media again for a new one.', 'premium-addons-for-elementor' ),
				array( 'status' => 401 )
			);
		}

		wp_set_current_user( $token_data['user_id'] );

		if ( ! Admin_Helper::check_user_can( 'upload_files' ) ) {
			return new \WP_Error(
				'premium_addons_invalid_upload_user',
				__( 'The user who created this upload link can no longer upload files.', 'premium-addons-for-elementor' ),
				array( 'status' => 403 )
			);
		}

		self::$claimed_token_data = $token_data;

		return true;
	}

	/**
	 * Store the uploaded file in the media library.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return array|\WP_Error The attachment, or an error.
	 */
	public static function handle_upload( $request ) {

		$token_data               = self::$claimed_token_data;
		self::$claimed_token_data = null;

		$upload_media = new Upload_Media();
		$site_limit   = $upload_media->get_site_upload_limit();

		if ( 0 === $site_limit ) {
			return self::with_status( $upload_media->upload_space_error() );
		}

		$size_cap     = null === $site_limit ? $token_data['max_bytes'] : min( $token_data['max_bytes'], $site_limit );
		$request_body = $request->get_body();

		if ( strlen( $request_body ) > $size_cap ) {
			return self::with_status( $upload_media->file_size_error( $size_cap ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_file = wp_tempnam( $token_data['filename'] );

		file_put_contents( $tmp_file, $request_body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- temp file wp_tempnam() just created.

		$stored = $upload_media->store_file( $tmp_file, $token_data['filename'], $token_data, $size_cap );

		return is_wp_error( $stored ) ? self::with_status( $stored ) : $stored;
	}

	/**
	 * Refuse a body that can never be stored, before the token is used up.
	 *
	 * Both are agent slips worth a retry with the same link: a wrong file path
	 * or a multipart upload leaves the body empty.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @param string           $token   The raw token.
	 *
	 * @return \WP_Error|null
	 */
	private static function check_body( $request, $token ) {

		$body_size = strlen( $request->get_body() );

		if ( 0 === $body_size ) {
			return new \WP_Error(
				'premium_addons_missing_file',
				__( 'The request body is empty. Send the file bytes as the raw body (curl --data-binary @file), not as a multipart form (-F). The link still works.', 'premium-addons-for-elementor' ),
				array( 'status' => 400 )
			);
		}

		// A read, not the claim: an unknown token falls through to claim()'s 401.
		$token_data = get_transient( self::transient_key( $token ) );

		if ( is_array( $token_data ) && $body_size > $token_data['max_bytes'] ) {
			$error = ( new Upload_Media() )->file_size_error( $token_data['max_bytes'] );
			$error->add_data( array( 'status' => 413 ) );

			return $error;
		}

		return null;
	}

	/**
	 * Send a store error with its HTTP status, saying the link is used up.
	 *
	 * @param \WP_Error $error The error.
	 *
	 * @return \WP_Error
	 */
	private static function with_status( $error ) {

		$code   = $error->get_error_code();
		$status = isset( self::STORE_ERROR_STATUS[ $code ] ) ? self::STORE_ERROR_STATUS[ $code ] : 500;

		return new \WP_Error(
			$code,
			$error->get_error_message() . ' ' . __( 'This upload link is now used up. Call premium-addons/upload-media again for a new one.', 'premium-addons-for-elementor' ),
			array( 'status' => $status )
		);
	}

	/**
	 * Create an upload link for the current user.
	 *
	 * @param array $input     The ability input, with a sanitized filename.
	 * @param int   $max_bytes The most bytes the upload may carry.
	 *
	 * @return array The link and how to send a file to it.
	 */
	public static function mint( $input, $max_bytes ) {

		$token      = bin2hex( random_bytes( 32 ) );
		$expires_at = time() + self::TOKEN_TTL;
		$upload_url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );

		set_transient(
			self::transient_key( $token ),
			array(
				'user_id'    => get_current_user_id(),
				'filename'   => $input['filename'],
				'alt'        => isset( $input['alt'] ) ? sanitize_text_field( $input['alt'] ) : '',
				'title'      => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '',
				'max_bytes'  => $max_bytes,
				'expires_at' => $expires_at,
			),
			self::TOKEN_TTL
		);

		return array(
			'upload_url'    => $upload_url,
			'upload_method' => 'PUT',
			'token_header'  => self::TOKEN_HEADER,
			'upload_token'  => $token,
			'expires_at'    => gmdate( 'c', $expires_at ),
			'max_bytes'     => $max_bytes,
			'curl_command'  => sprintf(
				"curl -X PUT -H '%s: %s' -H 'Content-Type: application/octet-stream' --data-binary @'/path/to/%s' '%s'",
				self::TOKEN_HEADER,
				$token,
				$input['filename'],
				$upload_url
			),
		);
	}

	/**
	 * Use up a token.
	 *
	 * Only the request whose delete removed the transient wins, so two requests
	 * racing with the same token cannot both store a file.
	 *
	 * @param string $token The raw token.
	 *
	 * @return array|false The token data, or false when the token is unknown, used or expired.
	 */
	public static function claim( $token ) {

		$key        = self::transient_key( $token );
		$token_data = get_transient( $key );

		if ( ! is_array( $token_data ) || ! delete_transient( $key ) ) {
			return false;
		}

		return $token_data['expires_at'] >= time() ? $token_data : false;
	}

	/**
	 * Get the transient key a token is stored under.
	 *
	 * Hashed, so a leaked options table does not hand out live links.
	 *
	 * @param string $token The raw token.
	 *
	 * @return string
	 */
	private static function transient_key( $token ) {
		return self::TRANSIENT_PREFIX . hash( 'sha256', $token );
	}
}
