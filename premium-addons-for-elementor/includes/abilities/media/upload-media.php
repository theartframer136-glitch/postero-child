<?php
/**
 * Upload Media.
 *
 * Downloads an image or video from a URL into the WordPress media library, or
 * hands out an upload link for a local file, and returns the attachment ID and
 * URL for a build ability to place.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Media;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Upload_Media implements Ability_Handler {

	/**
	 * Attachment meta key holding the URL a file was sideloaded from.
	 */
	const SOURCE_URL_META = '_pa_media_source_url';

	/**
	 * Largest image accepted from a remote URL.
	 */
	const MAX_IMAGE_SIZE = 5 * MB_IN_BYTES;

	/**
	 * Largest video accepted from a remote URL.
	 */
	const MAX_VIDEO_SIZE = 50 * MB_IN_BYTES;

	/**
	 * Seconds to wait for the download. Core defaults to 300, far too long for a
	 * tool call an agent is waiting on, but a video needs more than an image.
	 */
	const DOWNLOAD_TIMEOUT = 60;

	/**
	 * Seconds to wait for the HEAD request that sizes the download.
	 */
	const PROBE_TIMEOUT = 10;

	/**
	 * The type fileinfo reports for a file it cannot name, common for video.
	 */
	const BINARY_MIME_TYPE = 'application/octet-stream';

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'upload-media';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Upload Image or Video to Media Library', 'premium-addons-for-elementor' ),
			'description'         => __( 'Adds an image or video to the media library from a URL or from a file you upload. Only run it after the user has approved the upload.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-media',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'consent' ),
				'properties'           => array(
					'source_url' => array(
						'type'        => 'string',
						'description' => __( 'The public http(s) URL of the image or video to download. Images are capped at 5 MB and videos at 50 MB. Private, loopback and internal addresses are rejected. Leave it out to get an upload link instead.', 'premium-addons-for-elementor' ),
					),
					'filename'   => array(
						'type'        => 'string',
						'description' => __( 'The filename to store the file under, with its extension. Required when source_url is left out; otherwise derived from the source URL when omitted.', 'premium-addons-for-elementor' ),
					),
					'alt'        => array(
						'type'        => 'string',
						'description' => __( 'The alt text to store on the new attachment. Describe what the image or video shows.', 'premium-addons-for-elementor' ),
					),
					'title'      => array(
						'type'        => 'string',
						'description' => __( 'The attachment title. Taken from the filename when omitted.', 'premium-addons-for-elementor' ),
					),
					'consent'    => array(
						'type'        => 'boolean',
						'description' => __( 'Must be true. Confirms the user explicitly approved adding this file to their media library. Never set it without asking them first.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The attachment the file was stored as, or, without a source_url, the upload link to send the file to.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'id'            => array(
						'type'        => 'integer',
						'description' => __( 'The attachment ID. Pass it with the URL as the media control value in premium-addons/insert-widget or premium-addons/update-element-settings.', 'premium-addons-for-elementor' ),
					),
					'url'           => array(
						'type'        => 'string',
						'description' => __( 'The full size file URL on this site.', 'premium-addons-for-elementor' ),
					),
					'width'         => array(
						'type'        => 'integer',
						'description' => __( 'The full size width in pixels of the image or video, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
					),
					'height'        => array(
						'type'        => 'integer',
						'description' => __( 'The full size height in pixels of the image or video, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
					),
					'mime_type'     => array(
						'type'        => 'string',
						'description' => __( 'The stored MIME type.', 'premium-addons-for-elementor' ),
					),
					'alt'           => array(
						'type'        => 'string',
						'description' => __( 'The alt text stored on the attachment.', 'premium-addons-for-elementor' ),
					),
					'reused'        => array(
						'type'        => 'boolean',
						'description' => __( 'True when this URL was already in the library, so the existing attachment was returned instead of downloading it again. Any alt text or title passed with the call is applied only when you can edit that attachment.', 'premium-addons-for-elementor' ),
					),
					'upload_url'    => array(
						'type'        => 'string',
						'description' => __( 'The address to send the file to.', 'premium-addons-for-elementor' ),
					),
					'upload_method' => array(
						'type'        => 'string',
						'description' => __( 'The HTTP method to send the file with. If the host refuses it, POST the same body instead, still with Content-Type: application/octet-stream (a form content type breaks on real files). POST bodies are also capped by the host\'s post_max_size.', 'premium-addons-for-elementor' ),
					),
					'token_header'  => array(
						'type'        => 'string',
						'description' => __( 'The request header that carries upload_token.', 'premium-addons-for-elementor' ),
					),
					'upload_token'  => array(
						'type'        => 'string',
						'description' => __( 'Send it in the token_header header. Works once, for 5 minutes.', 'premium-addons-for-elementor' ),
					),
					'expires_at'    => array(
						'type'        => 'string',
						'description' => __( 'When the link stops working, in ISO 8601 UTC.', 'premium-addons-for-elementor' ),
					),
					'max_bytes'     => array(
						'type'        => 'integer',
						'description' => __( 'The largest file the link accepts. Images are still capped at 5 MB.', 'premium-addons-for-elementor' ),
					),
					'curl_command'  => array(
						'type'        => 'string',
						'description' => __( 'Run it from your code-execution sandbox or shell with the file path filled in. The response is the stored attachment. An HTML 413 page means the web server allows smaller uploads than max_bytes. A 401 or 403 with a code other than premium_addons_* (such as rest_not_logged_in) means a security plugin blocks logged-out REST requests, so this site cannot take uploads this way. In that case, or without a shell or code execution, ask the user for a public URL or to upload the file in WordPress instead.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'upload_files' );
			},
			// Not idempotent: every call without a source_url mints a new link.
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		if ( ! isset( $input['consent'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_consent',
				__( 'Adding a file to the media library needs the user\'s explicit approval. Ask them first, then call this again with consent set to true.', 'premium-addons-for-elementor' )
			);
		}

		// Strict compare: the gate is a deliberate confirmation, so a truthy
		// "false" string or a 1 must not pass for it.
		if ( true !== $input['consent'] ) {
			return new \WP_Error(
				'premium_addons_invalid_consent',
				__( 'Consent must be exactly true. Ask the user to approve adding this file to their media library, then call this again.', 'premium-addons-for-elementor' )
			);
		}

		$source_url = isset( $input['source_url'] ) ? trim( $input['source_url'] ) : '';

		if ( '' === $source_url ) {
			return $this->mint_upload_link( $input );
		}

		// wp_http_validate_url() rejects anything that is not public http(s) —
		// other schemes, loopback, private ranges and non-standard ports.
		if ( ! wp_http_validate_url( $source_url ) ) {
			return new \WP_Error(
				'premium_addons_invalid_source_url',
				__( 'The source URL must be a public http(s) address. Local, private and internal addresses are not allowed.', 'premium-addons-for-elementor' )
			);
		}

		$existing = $this->find_by_source_url( $source_url );

		if ( $existing ) {

			// The file is already here, but this call may carry better alt text or
			// a better title. upload_files alone must not rewrite another user's file.
			if ( Admin_Helper::check_user_can( 'edit_post', $existing ) ) {
				$this->apply_input_meta( $existing, $input );
			}

			return array_merge( Helpers::format_attachment( $existing ), array( 'reused' => true ) );
		}

		$site_limit = $this->get_site_upload_limit();

		if ( 0 === $site_limit ) {
			return $this->upload_space_error();
		}

		$size_cap = $this->get_size_cap( $source_url, $site_limit );

		if ( is_wp_error( $size_cap ) ) {
			return $size_cap;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$tmp_file = $this->download( $source_url, $size_cap );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$filename = ! empty( $input['filename'] ) ? sanitize_file_name( $input['filename'] ) : $this->filename_from_url( $source_url );
		$stored   = $this->store_file( $tmp_file, $filename, $input, $size_cap );

		if ( ! is_wp_error( $stored ) ) {
			update_post_meta( $stored['id'], self::SOURCE_URL_META, esc_url_raw( $source_url ) );
		}

		return $stored;
	}

	/**
	 * Create a link the agent sends a local file to.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|\WP_Error
	 */
	private function mint_upload_link( $input ) {

		$filename = isset( $input['filename'] ) ? sanitize_file_name( $input['filename'] ) : '';

		if ( '' === $filename ) {
			return new \WP_Error(
				'premium_addons_missing_filename',
				__( 'A filename with its extension is required to get an upload link. Pass source_url instead to add a file from a public URL.', 'premium-addons-for-elementor' )
			);
		}

		// Caught here, a wrong file type costs the agent a call instead of a used-up link.
		if ( ! $this->is_media_filename( $filename ) ) {
			return new \WP_Error(
				'premium_addons_invalid_filename',
				__( 'The filename must end in an image or video extension this site accepts, such as .jpg, .png, .webp or .mp4. SVG files are not supported.', 'premium-addons-for-elementor' )
			);
		}

		$site_limit = $this->get_site_upload_limit();

		if ( 0 === $site_limit ) {
			return $this->upload_space_error();
		}

		$max_bytes = null === $site_limit ? self::MAX_VIDEO_SIZE : min( self::MAX_VIDEO_SIZE, $site_limit );

		return Upload_Link::mint( array_merge( $input, array( 'filename' => $filename ) ), $max_bytes );
	}

	/**
	 * Whether a filename names an image or video type this site stores.
	 *
	 * SVG is excluded because validate_file() can never accept it.
	 *
	 * @since 4.11.117
	 *
	 * @param string $filename The filename.
	 *
	 * @return bool
	 */
	private function is_media_filename( $filename ) {

		$mime_type = wp_check_filetype( $filename )['type'];

		if ( ! $mime_type || 'image/svg+xml' === $mime_type ) {
			return false;
		}

		return $this->is_video( $mime_type ) || 0 === strpos( $mime_type, 'image/' );
	}

	/**
	 * Build the error for a site with no upload space left.
	 *
	 * @since 4.11.117
	 *
	 * @return \WP_Error
	 */
	public function upload_space_error() {
		return new \WP_Error(
			'premium_addons_not_initialized',
			__( 'This site has used up its upload space. Delete files from the media library before adding more.', 'premium-addons-for-elementor' )
		);
	}

	/**
	 * Check a temp file and add it to the media library.
	 *
	 * Shared by the URL mode and the upload link route. The temp file is
	 * deleted on every failure.
	 *
	 * @since 4.11.117
	 *
	 * @param string $tmp_file The temp file path.
	 * @param string $filename The filename to store it under.
	 * @param array  $input    The alt text and title to apply.
	 * @param int    $size_cap The most bytes to accept.
	 *
	 * @return array|\WP_Error The attachment, or an error.
	 */
	public function store_file( $tmp_file, $filename, $input, $size_cap ) {

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$error = $this->validate_file( $tmp_file, $filename, $size_cap );

		if ( is_wp_error( $error ) ) {
			wp_delete_file( $tmp_file );
			return $error;
		}

		// No $desc, so the title falls back to the filename; apply_input_meta()
		// is the single writer for a caller-supplied one, new and reused alike.
		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $tmp_file,
			)
		);

		// A rejected sideload leaves the temp file on disk.
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp_file );
			return $attachment_id;
		}

		$this->apply_input_meta( $attachment_id, $input );

		return array_merge( Helpers::format_attachment( $attachment_id ), array( 'reused' => false ) );
	}

	/**
	 * Apply the caller's alt text and title to an attachment.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $input         The ability input.
	 *
	 * @return void
	 */
	private function apply_input_meta( $attachment_id, $input ) {

		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		if ( ! empty( $input['title'] ) ) {

			$title = sanitize_text_field( $input['title'] );

			// wp_update_post() re-stamps post_modified and fires save_post even when
			// the title is unchanged, so a repeated URL-mode call for a reused file
			// would still change the library. update_post_meta() above already
			// no-ops on an unchanged value.
			if ( get_post_field( 'post_title', $attachment_id, 'raw' ) !== $title ) {
				wp_update_post(
					array(
						'ID'         => $attachment_id,
						'post_title' => $title,
					)
				);
			}
		}
	}

	/**
	 * Get the bytes this site may still store, on a multisite network.
	 *
	 * Core enforces the network's per-file limit and space quota from a filter
	 * that only loads in wp-admin and that wp_die()s on failure, so neither runs
	 * for a sideload on a REST request.
	 *
	 * @return int|null The byte limit, 0 when the quota is used up, or null when unlimited.
	 */
	public function get_site_upload_limit() {

		if ( ! is_multisite() || get_site_option( 'upload_space_check_disabled' ) ) {
			return null;
		}

		$file_limit = KB_IN_BYTES * (int) get_site_option( 'fileupload_maxk', 1500 );

		return min( $file_limit, (int) get_upload_space_available() );
	}

	/**
	 * Get the most bytes to download, from a HEAD request.
	 *
	 * The type decides the cap, but download_url() needs the limit before the
	 * body lands. The response headers are only a hint to size the download:
	 * validate_file() still decides the type from the contents. Without a usable
	 * answer the larger video cap applies.
	 *
	 * @param string   $source_url The URL to download.
	 * @param int|null $site_limit The site's upload limit, null when unlimited.
	 *
	 * @return int|\WP_Error The byte cap, or an error when the file is known to exceed it.
	 */
	private function get_size_cap( $source_url, $site_limit ) {

		$response = Helpers::fetch_public_url(
			'premium_addons_invalid_source_url',
			function () use ( $source_url ) {
				return wp_safe_remote_head(
					$source_url,
					array(
						'timeout'     => self::PROBE_TIMEOUT,
						'redirection' => 5,
					)
				);
			}
		);

		$is_usable    = 200 === wp_remote_retrieve_response_code( $response );
		$content_type = $is_usable ? wp_remote_retrieve_header( $response, 'content-type' ) : '';
		$is_image     = is_string( $content_type ) && 0 === strpos( $content_type, 'image/' );
		$size_cap     = $is_image ? self::MAX_IMAGE_SIZE : self::MAX_VIDEO_SIZE;

		if ( null !== $site_limit ) {
			$size_cap = min( $size_cap, $site_limit );
		}

		$length = $is_usable ? wp_remote_retrieve_header( $response, 'content-length' ) : '';

		if ( is_numeric( $length ) && (int) $length > $size_cap ) {
			return $this->file_size_error( $size_cap );
		}

		return $size_cap;
	}

	/**
	 * Build the error for a file over the size cap.
	 *
	 * @param int $size_cap The cap in bytes.
	 *
	 * @return \WP_Error
	 */
	public function file_size_error( $size_cap ) {
		return new \WP_Error(
			'premium_addons_invalid_file_size',
			sprintf(
				/* translators: %s: the maximum file size. */
				__( 'The file is larger than the %s limit. Use a smaller or more compressed file.', 'premium-addons-for-elementor' ),
				size_format( $size_cap )
			)
		);
	}

	/**
	 * Download a remote file to a temp path, bounded by the size cap.
	 *
	 * download_url() takes no size argument and streams the whole body to disk,
	 * so a huge response would land before filesize() could reject it. The HTTP
	 * layer does accept a byte limit and stops writing past it, but only through
	 * the request args — hence the filter around the one call. The limit is set
	 * one byte over the cap so a truncated body still reads as over the cap and
	 * validate_file() rejects it.
	 *
	 * Helpers::fetch_public_url() adds the second guard, rejecting the addresses
	 * core treats as public on every redirect hop. The downloaded body could never
	 * reach the library anyway — validate_file() only accepts real images and videos — but the
	 * distinct error codes and response timing would still answer "is this internal
	 * host up" for whoever drives the agent.
	 *
	 * @param string $source_url The URL to download.
	 * @param int    $size_cap   The most bytes to accept.
	 *
	 * @return string|\WP_Error The temp file path, or an error.
	 */
	private function download( $source_url, $size_cap ) {

		$limit_size = function ( $args ) use ( $size_cap ) {
			$args['limit_response_size'] = $size_cap + 1;

			return $args;
		};

		add_filter( 'http_request_args', $limit_size );

		$tmp_file = Helpers::fetch_public_url(
			'premium_addons_invalid_source_url',
			function () use ( $source_url ) {
				return download_url( $source_url, self::DOWNLOAD_TIMEOUT );
			}
		);

		remove_filter( 'http_request_args', $limit_size );

		return $tmp_file;
	}

	/**
	 * Find an attachment already sideloaded from this URL.
	 *
	 * An agent that retries would otherwise fill the library with copies of the
	 * same file, so the source URL is recorded on upload and matched here.
	 *
	 * @param string $source_url The source URL.
	 * @return int The attachment ID, or 0 when the URL is new.
	 */
	private function find_by_source_url( $source_url ) {

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => self::SOURCE_URL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => esc_url_raw( $source_url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Check a downloaded file is an image or video this site accepts.
	 *
	 * Reads the type out of the file contents, so a script renamed to .png is
	 * rejected and a URL carrying no extension at all — the shape most CDNs
	 * serve — still resolves. wp_check_filetype_and_ext() cannot do the second: it
	 * only inspects the bytes once the passed filename already names a known
	 * extension. SVG carries no image header and so is rejected, matching core's
	 * default.
	 *
	 * The type is checked before the size because the cap depends on it.
	 *
	 * @param string $tmp_file The downloaded temp file path.
	 * @param string $filename The filename it will be stored under (by reference,
	 *                         re-extensioned to match the real contents).
	 * @param int    $size_cap The download cap from get_size_cap().
	 *
	 * @return \WP_Error|null Error when the file is not an accepted image or video.
	 */
	private function validate_file( $tmp_file, &$filename, $size_cap ) {

		$file_type = $this->resolve_file_type( $tmp_file, $filename );

		if ( ! $file_type ) {
			return new \WP_Error(
				'premium_addons_invalid_file_type',
				__( 'The downloaded file is not an image or video type this site accepts. Check that the URL points directly at an image or video file. SVG files are not supported.', 'premium-addons-for-elementor' )
			);
		}

		// The download cap also applies: a video the headers called an image was
		// cut off at the image cap, so a body at that cap is incomplete.
		$type_cap = $this->is_video( $file_type['type'] ) ? self::MAX_VIDEO_SIZE : self::MAX_IMAGE_SIZE;
		$max_size = min( $type_cap, $size_cap );

		if ( filesize( $tmp_file ) > $max_size ) {
			return $this->file_size_error( $max_size );
		}

		// The URL often implies no extension, or the wrong one.
		if ( wp_check_filetype( $filename )['type'] !== $file_type['type'] ) {
			$filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . $file_type['ext'];
		}

		return null;
	}

	/**
	 * Resolve the type and extension a downloaded file is stored with.
	 *
	 * fileinfo often names a video differently from the site's MIME list
	 * (video/x-msvideo for .avi) or not at all (application/octet-stream), so,
	 * like core's own upload check, a video keeps the type its filename names
	 * when the contents are video or unnamed binary.
	 *
	 * @param string $tmp_file The downloaded temp file path.
	 * @param string $filename The filename it will be stored under.
	 *
	 * @return array|false Array with `ext` and `type`, or false when the site does not accept the file.
	 */
	private function resolve_file_type( $tmp_file, $filename ) {

		$mime_type = $this->detect_mime_type( $tmp_file );

		if ( ! $mime_type ) {
			return false;
		}

		$named_type = wp_check_filetype( $filename );
		$is_video   = $this->is_video( $mime_type ) || self::BINARY_MIME_TYPE === $mime_type;

		if ( $is_video && $named_type['type'] && $this->is_video( $named_type['type'] ) ) {
			return $named_type;
		}

		// Unnamed binary passes only as a named video, never through a site
		// that happens to allow application/octet-stream.
		if ( self::BINARY_MIME_TYPE === $mime_type ) {
			return false;
		}

		// get_allowed_mime_types() is keyed by an extension pattern (jpg|jpeg|jpe),
		// so the lookup answers "may this site store it" and names it in one step.
		$extensions = array_search( $mime_type, get_allowed_mime_types(), true );

		if ( ! $extensions ) {
			return false;
		}

		return array(
			'ext'  => strtok( $extensions, '|' ),
			'type' => $mime_type,
		);
	}

	/**
	 * Read a downloaded file's type from its contents.
	 *
	 * wp_get_image_mime() reads image headers only, and core has no video
	 * equivalent, so fileinfo sniffs the rest. Its answer is kept only when it is
	 * a video type or unnamed binary: fileinfo names any file, scripts included.
	 *
	 * @param string $tmp_file The downloaded temp file path.
	 *
	 * @return string|false The MIME type, or false when it is neither an image nor a video.
	 */
	private function detect_mime_type( $tmp_file ) {

		$image_mime = wp_get_image_mime( $tmp_file );

		if ( $image_mime ) {
			return $image_mime;
		}

		if ( ! extension_loaded( 'fileinfo' ) ) {
			return false;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );

		if ( ! $finfo ) {
			return false;
		}

		$mime_type = finfo_file( $finfo, $tmp_file );

		finfo_close( $finfo );

		if ( ! $mime_type ) {
			return false;
		}

		return $this->is_video( $mime_type ) || self::BINARY_MIME_TYPE === $mime_type ? $mime_type : false;
	}

	/**
	 * Whether a MIME type is a video type.
	 *
	 * @param string $mime_type The MIME type.
	 *
	 * @return bool
	 */
	private function is_video( $mime_type ) {
		return 0 === strpos( $mime_type, 'video/' );
	}

	/**
	 * Derive a filename from a source URL.
	 *
	 * @param string $source_url The source URL.
	 * @return string
	 */
	private function filename_from_url( $source_url ) {

		$path     = wp_parse_url( $source_url, PHP_URL_PATH );
		$filename = is_string( $path ) ? sanitize_file_name( basename( $path ) ) : '';

		// A URL with no usable path segment still needs a name to sideload under;
		// validate_file() replaces it with the one the contents call for.
		return '' !== $filename ? $filename : 'media';
	}
}
