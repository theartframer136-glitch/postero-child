<?php
/**
 * Fields Handler
 *
 * @package Click_To_Chat
 * @subpackage API
 * @since 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Fields_Handler' ) ) {


	/**
	 * Fields handler class.
	 */
	class HT_CTC_Fields_Handler {

		/**
		 * Get settings fields endpoint.
		 *
		 * @param WP_REST_Request $request Request object.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_settings_fields( $request ) {

			// todo no i18n as now..
			if ( ! current_user_can( 'manage_options' ) ) {
				return HT_CTC_API_Responses::error( 'forbidden', __( 'You do not have permission.', 'click-to-chat-for-whatsapp' ), 403 );
			}

			// click-to-chat-for-whatsapp/v1/get-fields/?group=greetings-settings&v=4.40
			$group = $request->get_param( 'group' );

			// Strict sanitization and validation
			$group = sanitize_key( $group );
			$group = sanitize_file_name( $group );

			if ( empty( $group ) ) {
				return HT_CTC_API_Responses::error( 'invalid_group', 'Invalid group', 400 );
			}

			$fields = array();

			// Load the fields class so we can read its REST surface and call the method.
			HT_CTC_Utils::load_file( 'new/admin2/views/class-ht-ctc-admin-settings-fields.php' );

			// Allowlist lives on HT_CTC_Admin_Settings_Fields::get_group_method_map() so the map
			// stays next to the methods it gates — when a new tab method is added, the
			// matching map entry is added in the same file.
			if ( ! class_exists( 'HT_CTC_Admin_Settings_Fields' ) || ! is_callable( array( 'HT_CTC_Admin_Settings_Fields', 'get_group_method_map' ) ) ) {
				return HT_CTC_API_Responses::error( 'invalid_group', 'Invalid group', 400 );
			}

			$group_method_map = HT_CTC_Admin_Settings_Fields::get_group_method_map();

			if ( ! is_array( $group_method_map ) || ! isset( $group_method_map[ $group ] ) ) {
				return HT_CTC_API_Responses::error( 'invalid_group', 'Invalid group', 400 );
			}

			$method_name = $group_method_map[ $group ];

			if ( is_callable( array( 'HT_CTC_Admin_Settings_Fields', $method_name ) ) ) {
				$fields = HT_CTC_Admin_Settings_Fields::$method_name();
			}

			// // 2. Fallback to JSON (Secondary)
			// if ( empty( $fields ) || ! is_array( $fields ) ) {
			// $json_file = HT_CTC_PLUGIN_DIR . "new/admin2/settings-json/$group.json";

			// if ( file_exists( $json_file ) ) {
			// todo
			// error_log( "Click to Chat: Loading fields from JSON fallback: $json_file" );
			// $json_data = file_get_contents( $json_file );
			// $fields    = json_decode( $json_data, true );
			// }
			// }

			// 3. Final Validation
			if ( empty( $fields ) || ! is_array( $fields ) ) {
				// todo
				// error_log( "Click to Chat: Invalid settings group requested: $group" );
				return HT_CTC_API_Responses::error( 'invalid_group', 'Invalid group', 400 );
			}

			// `success: true` is added by the envelope (HT_CTC_API_Responses::success).
			$response = HT_CTC_API_Responses::success(
				array(
					'fields' => $fields,
				)
			);

			// Cache for 15 days (15 * 24 * 60 * 60 = 1,296,000 seconds)
			// $response->header( 'Cache-Control', 'public, max-age=1296000' );

			return $response;
		}
	}

}
