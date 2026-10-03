<?php
/**
 * Admin Header View
 *
 * @package Click_To_Chat
 * @subpackage Administration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Admin_Header' ) ) {

	/**
	 * Admin Header Class
	 */
	class HT_CTC_Admin_Header {

		/**
		 * Display the admin header.
		 */
		public static function display() {

			$ht_ctc_admin_settings = HT_CTC_Utils::get_option( 'ht_ctc_admin_settings' );

			// Auto save status (1 or empty).
			$auto_save = isset( $ht_ctc_admin_settings['auto_save'] ) ? $ht_ctc_admin_settings['auto_save'] : '';

			// Current theme (light/dark/system)
			$theme = isset( $ht_ctc_admin_settings['theme'] ) ? $ht_ctc_admin_settings['theme'] : 'light';
			?>
			<!-- Header -->
			<header class="admin-header">

				<div class="header-left">
					<button type="button" id="menu-toggle" class="menu-toggle">
						<?php HT_CTC_Icons::render( 'menu', 'ctc-icon' ); ?>
					</button>
					<!-- Mobile: current section label -->
					<span id="mobile-section-label" class="mobile-section-label"></span>
					<div class="logo" id="logo-home">
						<div class="logo-icon">
							<span class="dashicons dashicons-format-chat"></span>
						</div>
						<div class="logo-text">Click to Chat
							<?php do_action( 'ht_ctc_admin_header_logo_after' ); ?>
						</div>
					</div>
				</div>


				<div class="header-right">
					<!-- Switch to legacy admin — todo: move to settings dropdown or remove once admin2 is stable -->
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=click-to-chat&admin_ui=2019' ), 'ht_ctc_switch_ui', '_htnonce' ) ); ?>"
						class="switch-interface" data-tip="Switch to previous Admin UI" data-tip-pos="bottom">
						<?php HT_CTC_Icons::render( 'arrow-left-right', 'ctc-icon' ); ?>
						<span class="switch-text">Previous UI</span>
					</a>

					<button type="button" id="save-button" class="save-button">
						<span class="default-state">
							<?php HT_CTC_Icons::render( 'save', 'ctc-icon' ); ?>
							<span class="text-label"><?php esc_html_e( 'Save Changes', 'click-to-chat-for-whatsapp' ); ?></span>
						</span>
						<span class="loading-state" style="display:none;">
							<?php HT_CTC_Icons::render( 'loader-2', 'ctc-icon ctc-spin' ); ?>
							<span>Saving...</span>
						</span>
					</button>
					<!-- Settings Dropdown Button -->
					<!-- todo: name attr.. save .. in db.... -->
					<!-- check.. if better way..  -->
					<div class="settings-dropdown-wrapper">
						<button type="button" id="settings-toggle" class="settings-toggle" aria-label="<?php esc_attr_e( 'Settings', 'click-to-chat-for-whatsapp' ); ?>">
							<?php HT_CTC_Icons::render( 'settings', 'ctc-icon' ); ?>
						</button>
						<div id="settings-dropdown" class="settings-dropdown hidden">
							<div class="dropdown-item theme-selector">
								<fieldset class="theme-fieldset" role="radiogroup" aria-label="Theme Selection">
									<legend class="dropdown-section-title">Theme</legend>
									<div class="theme-options">
										<label class="theme-option">
											<input type="radio" name="ht_ctc_admin_settings[theme]" value="light" <?php checked( $theme, 'light' ); ?> />
											<span class="theme-icon">
												<?php HT_CTC_Icons::render( 'sun', 'ctc-icon' ); ?>
												<!-- <span class="theme-label">Light</span> -->
											</span>
										</label>
										<label class="theme-option">
											<input type="radio" name="ht_ctc_admin_settings[theme]" value="dark" <?php checked( $theme, 'dark' ); ?> />
											<span class="theme-icon">
												<?php HT_CTC_Icons::render( 'moon', 'ctc-icon' ); ?>
												<!-- <span class="theme-label">Dark</span> -->
											</span>
										</label>
										<label class="theme-option">
											<input type="radio" name="ht_ctc_admin_settings[theme]" value="system" <?php checked( $theme, 'system' ); ?> />
											<span class="theme-icon">
												<?php HT_CTC_Icons::render( 'monitor', 'ctc-icon' ); ?>
												<!-- <span class="theme-label">Auto</span> -->
											</span>
										</label>
									</div>
								</fieldset>
							</div>
							<label class="dropdown-item">
								<input name="ht_ctc_admin_settings[auto_save]" <?php checked( $auto_save, '1' ); ?>  value="1" type="checkbox" id="auto-save-toggle"/>
								<span>Auto Save</span>
							</label>
						</div>
					</div>
				</div>

			</header>
			<?php
		}
	}
}
