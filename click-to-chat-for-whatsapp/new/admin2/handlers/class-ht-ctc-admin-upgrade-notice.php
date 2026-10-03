<?php
/**
 * Admin2 PRO upsell notice.
 *
 * Renders the "Upgrade to PRO" banner on admin pages, plus:
 *   - the stylesheet for the banner
 *   - inline JS that handles the dismiss button
 *   - the AJAX handler that persists dismissal to ht_ctc_notices
 *
 * Only shown when PRO has never been installed, the user hasn't dismissed it,
 * and at least 5 days have passed since first install.
 *
 * Split out of HT_CTC_Admin_Core_Hooks so PRO-upsell concerns live in one file.
 *
 * @package Click_To_Chat
 * @subpackage Admin2
 * @since 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Admin_Upgrade_Notice' ) ) {

	/**
	 * PRO upsell admin notice.
	 */
	class HT_CTC_Admin_Upgrade_Notice {

		/**
		 * Wait time before showing the banner after first install (seconds).
		 */
		const WAIT_TIME = 432000; // 5 * 24 * 60 * 60.

		/**
		 * Constructor: register the dismiss AJAX handler and the banner (if eligible).
		 */
		public function __construct() {
			add_action( 'wp_ajax_ht_ctc_admin_dismiss_notices', array( $this, 'dismiss_notices' ) );

			if ( $this->should_show() ) {
				add_action( 'admin_notices', array( $this, 'upgrade_notice' ) );
				add_action( 'admin_footer', array( $this, 'admin_upgrade_notice_scripts' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_upgrade_notice_styles' ) );
			}

			// // Development / testing only — uncomment to force-display the banner
			// // on every admin page load (bypasses the 5-day wait and the dismissed check).
			// // DO NOT enable in production: all admins see the banner on every page.
			// add_action( 'admin_notices', array( $this, 'pro_notice' ) );
			// add_action( 'admin_footer', array( $this, 'admin_pro_notice_scripts' ) );
		}

		/**
		 * Whether the PRO upsell banner should be shown on the current request.
		 *
		 * @return bool
		 */
		private function should_show() {

			$ht_ctc_pro_plugin_details = HT_CTC_Utils::get_option( 'ht_ctc_pro_plugin_details' );
			if ( isset( $ht_ctc_pro_plugin_details['version'] ) ) {
				return false;
			}

			$ht_ctc_notices = HT_CTC_Utils::get_option( 'ht_ctc_notices' );
			if ( isset( $ht_ctc_notices['pro_banner'] ) ) {
				return false;
			}

			$ht_ctc_plugin_details = HT_CTC_Utils::get_option( 'ht_ctc_plugin_details' );
			$first_install_time    = ( isset( $ht_ctc_plugin_details['first_install_time'] ) ) ? esc_attr( $ht_ctc_plugin_details['first_install_time'] ) : 1;

			return ( time() - $first_install_time ) > self::WAIT_TIME;
		}

		/**
		 * Enqueue the upgrade to PRO notice stylesheet (only when the banner is shown).
		 *
		 * @return void
		 */
		public function enqueue_upgrade_notice_styles() {
			$css = defined( 'HT_CTC_DEBUG_MODE' ) ? 'dev/admin-notice/upgrade-banner.css' : 'min/admin-notice/upgrade-banner.css';

			wp_enqueue_style(
				'ht-ctc-upgrade-banner',
				plugins_url( "new/admin2/assets/$css", HT_CTC_PLUGIN_FILE ),
				array(),
				HT_CTC_VERSION
			);
		}

		/**
		 * Render the PRO upsell banner.
		 *
		 * @return void
		 */
		public function upgrade_notice() {
			?>
		<div class="notice is-dismissible ht-ctc-notice ht-ctc-notice-pro-banner" data-db="pro_banner">
			<div class="ht-ctc-pro-inner">
				<div class="ht_ctc_pro_icon_box">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zap"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
				</div>
				<div class="ht-ctc-pro-content">
					<h3 class="ht-ctc-pro-title">Skyrocket Your Conversions <span class="pro-badge">PRO</span></h3>
					<p class="ht-ctc-pro-desc">
						Power up your chat widget. Unlock <strong>Multi-Agent</strong>, <strong>Form Filling</strong>, <strong>Business Hours</strong>, and <strong>Country Filters</strong>.
					</p>
					<div class="ht-ctc-pro-features">
						<span class="feature-tag">✨ Google Ads Tracking</span>
						<span class="feature-tag">📊 Analytics</span>
						<span class="feature-tag">🪝 Webhooks</span>
						<span class="feature-tag">🎯 Advanced Triggers</span>
					</div>
				</div>
				<div class="ht-ctc-pro-actions">
					<a href="https://holithemes.com/plugins/click-to-chat/pricing/" target="_blank" class="button button-upgrade">
						Get PRO Now
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
					<a href="#" class="button-dismiss button-dismiss-text">Maybe later</a>
				</div>
			</div>
		</div>
			<?php
		}

		/**
		 * Inline JS to handle dismissing the PRO notice.
		 *
		 * @return void
		 */
		public function admin_upgrade_notice_scripts() {
			?>
		<script>
			(function () {

				if (document.readyState === "complete" || document.readyState === "interactive") {
					ready();
				} else {
					document.addEventListener("DOMContentLoaded", ready);
				}

				function serialize(obj) {
					return Object.keys(obj).reduce(function (a, k) {
						a.push(k + '=' + encodeURIComponent(obj[k]));
						return a;
					}, []).join('&');
				}

				function ready() {
					setTimeout(function () {
						const buttons = document.querySelectorAll(".ht-ctc-notice-pro-banner .notice-dismiss, .ht-ctc-notice-pro-banner .button-dismiss");
						for (let i = 0; i < buttons.length; i++) {
							buttons[i].addEventListener('click', function (e) {
								e.preventDefault();

								var element = e.target.closest('.is-dismissible');
								var db = (element.hasAttribute('data-db')) ? element.getAttribute('data-db') : 'fallback';

								const http = new XMLHttpRequest();
								http.open('POST', ajaxurl, true);
								http.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=UTF-8");
								http.send(serialize({
									'action': 'ht_ctc_admin_dismiss_notices',
									'db': db,
									'nonce': <?php echo wp_json_encode( wp_create_nonce( 'ht-ctc-notices' ) ); ?>
								}));

								element.remove();
							});
						}
					}, 1000);
				}

			})();
		</script>
			<?php
		}

		/**
		 * AJAX: Dismiss admin notices.
		 *
		 * @return void Sends JSON success and exits.
		 */
		public function dismiss_notices() {

			// Verify the request is genuine (nonce / CSRF) before any authorization or work.
			check_ajax_referer( 'ht-ctc-notices', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			}

			$post_data = ( $_POST ) ? map_deep( wp_unslash( $_POST ), 'sanitize_text_field' ) : array();

			$db_key = ( isset( $post_data['db'] ) ) ? esc_attr( $post_data['db'] ) : '';

			// Only known notice keys may be dismissed. Bail before touching the DB so a
			// valid nonce can't be replayed with an arbitrary/'fallback' key to spam writes.
			$db_key_values = array(
				'pro_banner',
			);

			if ( '' === $db_key || ! in_array( $db_key, $db_key_values, true ) ) {
				wp_send_json_error( array( 'message' => 'Invalid notice key' ) );
			}

			$time      = time();
			$db_values = get_option( 'ht_ctc_notices', array() );

			$update_values = is_array( $db_values ) ? $db_values : array();

			$update_values['version']             = HT_CTC_VERSION;
			$update_values[ $db_key ]             = $time;
			$update_values[ "{$db_key}_version" ] = HT_CTC_VERSION;

			update_option( 'ht_ctc_notices', $update_values );

			wp_send_json_success();
		}
	}
} // END class_exists check
