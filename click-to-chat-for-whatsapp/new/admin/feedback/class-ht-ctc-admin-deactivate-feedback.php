<?php
/**
 * Admin: Deactivate feedback modal and handler.
 *
 * Provides a feedback modal on the Plugins screen when deactivating the
 * plugin and sends a non-blocking request with the feedback details.
 *
 * @package Click_To_Chat_For_WhatsApp\Admin\Feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Admin_Deactivate_Feedback' ) ) {

	/**
	 * Class HT_CTC_Admin_Deactivate_Feedback
	 *
	 * Renders the deactivate feedback modal on the Plugins screen and
	 * handles the AJAX submission to send feedback details.
	 */
	class HT_CTC_Admin_Deactivate_Feedback {

		/**
		 * Init hooks for deactivate feedback on construction.
		 */
		public function __construct() {

			// If it is the plugins page then call feedback function.
			$this->deactivate();
		}

		/**
		 * Register hooks needed for the deactivate feedback flow.
		 *
		 * Only enqueues and outputs markup on the Plugins screen.
		 */
		private function deactivate() {

			if ( current_user_can( 'manage_options' ) ) {

				// Ajax call - data of feedback form will be sent to server.
				add_action( 'wp_ajax_ht_ctc_deactivate_feedback_details', array( $this, 'ht_ctc_deactivate_feedback_details' ) );

				// TODO: Evaluate whether this approach is best or use server URL.
				global $pagenow;

				if ( 'plugins.php' !== $pagenow ) {
					return;
				}

				// Add feedback form modal in the admin footer.
				add_action( 'admin_footer', array( $this, 'deactivate_feedback_form' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'register_deactivate_feedback_scripts' ) );
			}
		}


		/**
		 * Register and enqueue scripts for the deactivate feedback form.
		 *
		 * Loads only on the Plugins page.
		 *
		 * @param string $hook Current admin page hook suffix.
		 */
		public function register_deactivate_feedback_scripts( $hook ) {

			// Only load on Plugins page.
			if ( 'plugins.php' !== $hook ) {
				return;
			}

			// Register and enqueue the script.
			wp_enqueue_script( 'ht-ctc-admin-deactivate-feedback', plugins_url( 'new/admin/feedback/feedback.js', HT_CTC_PLUGIN_FILE ), array(), HT_CTC_VERSION, true );
			wp_localize_script(
				'ht-ctc-admin-deactivate-feedback',
				'ht_ctc_admin_deactivate_feedback',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ht_ctc_admin_deactivate_feedback_nonce' ),
				)
			);

			wp_enqueue_style( 'ht-ctc-admin-deactivate-feedback', plugins_url( 'new/admin/feedback/feedback.css', HT_CTC_PLUGIN_FILE ), array(), HT_CTC_VERSION );
		}

		/**
		 * Output the feedback form HTML.
		 */
		public function deactivate_feedback_form() {

				// Example deactivate URL retained for reference intentionally removed to avoid commented-out code warnings.
			?>
		<div class="ht-ctc-deactivate-feedback-modal" class="" style="display:none;">
			<div class="ht-ctc-df-modal-content">

				<button class="ht-ctc-df-close">&times;</button>
				<h2 class="ht-ctc-df-heading">We’ll miss you!</h2>
				<p class="ht-ctc-df-sub-heading">Your feedback helps us grow. Could you let us know what went wrong?</p>

				<p class="ht-ctc-df-label" style="margin-bottom:6px;">Your Email (for follow-up, optional):</p>
				<input id="ht-ctc-df-email" type="email" value=""/>

				<textarea id="ht-ctc-df-textarea" placeholder="Share your thoughts, suggestions, or issues here..."></textarea>

				<p class="ht-ctc-df-note">💡 Our team actively reads every feedback and fixes things fast!</p>

				<div class="ht-ctc-df-button-group">
					<button class="ht-ctc-df-skip ht-ctc-df-btn">Skip & Deactivate</button>
					<button class="ht-ctc-df-contact ht-ctc-df-btn">Contact Us</button>
					<button class="ht-ctc-df-send ht-ctc-df-btn">Send & deactivate</button>
				</div>
			</div>
		</div>
			<?php
		}


		/**
		 * Handle the feedback submission via AJAX.
		 *
		 * @return void Sends JSON success immediately after dispatching the request.
		 */
		public function ht_ctc_deactivate_feedback_details() {

			check_ajax_referer( 'ht_ctc_admin_deactivate_feedback_nonce', 'nonce' );

			// Defense-in-depth: also require manage_options at the handler level
			// (the only place this AJAX endpoint is hooked is inside a
			// manage_options gate, but if that gate ever shifts we still want a
			// hard capability check here).
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
			}

			// Sanitize POST values with field-appropriate sanitizers AFTER wp_unslash.
			// (Previously the map_deep used esc_attr — an output escaper, not an input
			// sanitizer — and did not unslash.)
			$user_feedback = '';
			if ( isset( $_POST['userFeedback'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslash + sanitize applied below.
				$raw_feedback = wp_unslash( $_POST['userFeedback'] );
				// sanitize_textarea_field exists since WP 4.7; we've hit
				// fatal errors on older sites without this guard.
				$user_feedback = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $raw_feedback ) : sanitize_text_field( $raw_feedback );
			}

			$user_email = '';
			if ( isset( $_POST['userEmail'] ) ) {
				// sanitize_email validates the email format and strips invalid characters.
				$user_email = sanitize_email( wp_unslash( $_POST['userEmail'] ) );
			}

			// Plugin and site details.
			$ctc_version = defined( 'HT_CTC_VERSION' ) ? HT_CTC_VERSION : '';
			$site_url    = function_exists( 'get_site_url' ) ? get_site_url() : '';
			$wp_version  = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '';
			$php_version = function_exists( 'phpversion' ) ? phpversion() : '';
			$wp_language = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'language' ) : '';

			// Theme name.
			$wp_theme_name = '';
			if ( function_exists( 'wp_get_theme' ) ) {
				$wp_theme = wp_get_theme();
				if ( is_object( $wp_theme ) && method_exists( $wp_theme, 'get' ) ) {
					$theme_name    = $wp_theme->get( 'Name' );
					$wp_theme_name = ! empty( $theme_name ) ? sanitize_text_field( $theme_name ) : '';
				}
			}

			// Active plugins list.
			$active_plugins = array();
			if ( function_exists( 'get_option' ) ) {
				$active_plugins_option = get_option( 'active_plugins', array() );
				if ( is_array( $active_plugins_option ) ) {
					$active_plugins = array_map( 'sanitize_text_field', $active_plugins_option );
				}
			}

			// TODO: Consider collecting additional server details if needed.
			$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

			$feedback_data = array(
				'plugin_name'     => 'Click to Chat for WhatsApp',
				'ctc_version'     => $ctc_version,
				'user_feedback'   => $user_feedback,
				'user_email'      => $user_email,
				'site_url'        => $site_url,
				'wp_version'      => $wp_version,
				'php_version'     => $php_version,
				'wp_language'     => $wp_language,
				'wp_theme'        => $wp_theme_name,
				'active_plugins'  => $active_plugins,
				'server_software' => $server_software,
			);

			// Production endpoint.
			$url = 'https://holithemes.com/wp-json/ht-code/v1/ctc-feedback';

			// Fire-and-forget HTTP request.
			$request_args = array(
				'timeout'  => 5,
				'blocking' => false, // Don't wait for response.
				'headers'  => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'HT-CTC-Pro-Plugin/' . HT_CTC_VERSION,
				),
				'body'     => wp_json_encode( array( 'feedback_data' => $feedback_data ) ),
			);

			wp_remote_post( $url, $request_args );

			// Respond to the AJAX request immediately.
			wp_send_json_success( 'Feedback sent (no wait)' );
		}
	}

	if ( current_user_can( 'manage_options' ) ) {
		new HT_CTC_Admin_Deactivate_Feedback();
	}
} // END class_exists check.
