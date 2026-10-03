<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'CR_Error_Notice' ) ) :

	class CR_Error_Notice {

		const NONCE = 'cr-dismiss-error';

		public function __construct() {
			if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
				add_action( 'wp_ajax_cr_dismiss_error', array( $this, 'dismiss_error' ) );
			}
		}

		public static function output_notice() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$errors = CR_Error_Log::get_active_errors();
			if ( ! $errors ) {
				return;
			}
			?>
			<div class="cr-errors-notice">
				<div class="cr-errors-notice-accent"></div>
				<div class="cr-errors-notice-inner">
					<div class="cr-errors-notice-header">
						<span class="dashicons dashicons-warning"></span>
						<span><?php esc_html_e( 'Issues Detected', 'customer-reviews-woocommerce' ); ?></span>
					</div>
					<div class="cr-errors-notice-list">
						<?php foreach ( $errors as $type => $error ) : ?>
							<div class="cr-errors-notice-item" data-error-type="<?php echo esc_attr( $type ); ?>">
								<span class="cr-errors-notice-bullet"></span>
								<span class="cr-errors-notice-message"><?php echo esc_html( $error['message'] ); ?></span>
								<?php if ( ! empty( $error['action'] ) ) : ?>
									<a class="cr-errors-notice-action" href="<?php echo esc_url( $error['action']['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $error['action']['label'] ); ?><span class="dashicons dashicons-external"></span></a>
								<?php endif; ?>
								<a class="cr-errors-notice-never" href="#"><?php esc_html_e( 'Don\'t show again', 'customer-reviews-woocommerce' ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<a class="cr-errors-notice-close" href="#" aria-label="<?php esc_attr_e( 'Dismiss', 'customer-reviews-woocommerce' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</a>
			</div>
			<?php
		}

		public function dismiss_error() {
			check_ajax_referer( self::NONCE, 'nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error();
			}

			$types = isset( $_POST['types'] ) ? (array) wp_unslash( $_POST['types'] ) : array();
			$never = ! empty( $_POST['never'] );

			foreach ( $types as $type ) {
				CR_Error_Log::dismiss( sanitize_key( $type ), $never );
			}

			wp_send_json_success();
		}

	}

endif;
