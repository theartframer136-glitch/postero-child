<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://codingfix.com
 * @since      1.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/admin/partials
 */

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<?php
$transposh_installed = true;
if ( ! is_plugin_active( 'transposh-translation-filter-for-wordpress/transposh.php' ) ) {
	$transposh_installed = false;
}
if ( $transposh_installed ) {
	include_once WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/constants.php';

	$lsft_default_styles = LSFT_PLUGIN_PATH . 'assets/styles';
	$upload_dir          = wp_upload_dir();
	$lsft_custom_styles  = $upload_dir['basedir'] . '/lsft/custom-styles/';
	if ( ! file_exists( $lsft_custom_styles ) ) {
		wp_mkdir_p( $lsft_custom_styles );
	}

	$hidden = false;
	if ( ! class_exists( 'transposh_plugin' ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>
		Language Switcher for Transposh plugin depends on Transposh plugin and can\'t do anything without it: please, install and activate Transposh plugin before to activate Edit Translation Button for Transposh.
		</p></div>';
		$hidden = true;
	}
	$transposh_options = get_option( TRANSPOSH_OPTIONS );
	$used_languages    = explode( ',', $transposh_options['viewable_languages'] );
	$default_lang      = $transposh_options['default_language'];
	$usable_langs      = explode( ',', $transposh_options['viewable_languages'] );
	if ( count( $usable_langs ) < 1 ) {
		echo '<div class="notice notice-error is-dismissible"><p class="plugin-invalid">
		<strong>Transposh languages not found.</strong> Maybe you still didn\'t set any languages in Transposh Languages settings. You must set at least 2 languages to use in your website in order to use Language Switcher for Transposh.
		</p></div>';
		$hidden = true;
	}

	$options   = get_option( 'cfxlsft_options' );
	$flag_path = LSFT_PLUGIN_URL . 'assets/flags';
	if ( 'tp' === $options['flag_type'] ) {
		$flag_path = plugins_url() . '/transposh-translation-filter-for-wordpress/' . TRANSPOSH_DIR_IMG . '/flags';
	}
	$en_flag = 'gb';
	if ( isset( $options['usa_flag'] ) && 'on' === $options['usa_flag'] ) {
		$en_flag = 'us';
	}
}
?>
<div id="cfxlsft-general" class="wrap">
	<h2>Language Switcher for Transposh Settings (LSFT)</h2>
	<?php

	if ( false !== $transposh_installed ) {
		$default_tab = 'general';

		$lsft_tab = $default_tab;
		if ( isset( $_GET['tab'] ) ) {
			if ( isset( $_GET['_wpnonce'] ) ) {
				if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'intnavontabs' ) ) {
					$lsft_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
				}
			}
		}

		$lsft_tabs = array( 'general', 'styles', 'shortcodes', 'more_tools' );
		if ( ! in_array( $lsft_tab, $lsft_tabs, true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => 'language-switcher-settings',
						'tab'     => $default_tab,
						'message' => '1',
					),
					admin_url( 'options-general.php' )
				)
			);
		}

		?>
	<section id="transposh-settings" style="padding-top:20px;">
		<h3>Languages currently enabled in Transposh settings</h3>
		<div class="default-lang_wrapper">
			<div class="lang-div">
				<?php
				if ( strtolower( $default_lang ) === 'en' ) {
					?>
					<img src="<?php echo esc_html( $flag_path ); ?>/<?php echo esc_html( $en_flag ); ?>.png" alt="English" />
					<?php
				} else {
					?>
					<img src="<?php echo esc_html( $flag_path ); ?>/<?php echo esc_html( transposh_consts::get_language_flag( $default_lang ) ); ?>.png" />
					<?php
				}
				?>
				<span class="locale-code"><?php echo esc_html( 'on' === $options['original_lang_names'] ? transposh_consts::get_language_orig_name( $default_lang ) : transposh_consts::get_language_name( $default_lang ) ); ?> - default</span>
			</div>
			<?php
			foreach ( $usable_langs as $lang ) {
				if ( $lang !== $default_lang ) {
					?>
					<div class="lang-div">
						<?php
						if ( 'en' === $lang ) {
							?>
							<img src="<?php echo esc_html( $flag_path ); ?>/<?php echo esc_html( $en_flag ); ?>.png" />
							<?php
						} else {
							?>
							<img src="<?php echo esc_html( $flag_path ); ?>/<?php echo esc_html( transposh_consts::get_language_flag( $lang ) ); ?>.png" />
							<?php
						}
						?>
						<!-- <span><?php echo esc_html( ucfirst( $lang ) ); ?></span> -->
						<span class="locale-code"><?php echo esc_html( 'on' === $options['original_lang_names'] ? transposh_consts::get_language_orig_name( $lang ) : transposh_consts::get_language_name( $lang ) ); ?> <?php echo esc_html( ( $lang === $default_lang ) ? ' - default' : '' ); ?></span>
					</div>
					<?php
				}
			}
			?>
			<!-- </div> -->
			<!-- </div> -->
		</div>
	</section>
	<br />
	<br />
	<div class="row">
		<div class="col-12">
			<nav class="nav-tab-wrapper lsft">
				<?php
				$url_gen     = admin_url( 'admin.php?page=language-switcher-settings&amp;tab=general' );
				$url_stl     = admin_url( 'admin.php?page=language-switcher-settings&amp;tab=styles' );
				$url_shc     = admin_url( 'admin.php?page=language-switcher-settings&amp;tab=shortcodes' );
				$url_mrt     = admin_url( 'admin.php?page=language-switcher-settings&amp;tab=more_tools' );
				$tab_url_gen = wp_nonce_url( $url_gen, 'intnavontabs', '_wpnonce' );
				$tab_url_stl = wp_nonce_url( $url_stl, 'intnavontabs', '_wpnonce' );
				$tab_url_shc = wp_nonce_url( $url_shc, 'intnavontabs', '_wpnonce' );
				$tab_url_mrt = wp_nonce_url( $url_mrt, 'intnavontabs', '_wpnonce' );
				?>
				<a href="<?php echo esc_html( $tab_url_gen ); ?>" data-target="general" class="nav-tab <?php echo ( 'general' === $lsft_tab ) ? 'active' : ''; ?>">General</a>
				<a href="<?php echo esc_html( $tab_url_stl ); ?>" data-target="styles" class="nav-tab <?php echo ( 'styles' === $lsft_tab ) ? 'active' : ''; ?>">Styles</a>
				<a href="<?php echo esc_html( $tab_url_shc ); ?>" data-target="shortcodes" class="nav-tab <?php echo ( 'shortcodes' === $lsft_tab ) ? 'active' : ''; ?>">Shortcodes</a>
				<a href="<?php echo esc_html( $tab_url_mrt ); ?>" data-target="more_tools" class="nav-tab <?php echo ( 'more_tools' === $lsft_tab ) ? 'active' : ''; ?>">More Tools</a>
			</nav>
			<div class="tab-content ls">
				<div id="general" class="tab-panel <?php echo ( 'general' === $lsft_tab ) ? 'active' : ''; ?>">
					<form method="post" action="admin-post.php" class="<?php echo $hidden ? 'hidden' : ''; ?>">
						<input type="hidden" name="action" value="save_cfxlsft_options" />
						<input type="hidden" name="activeTab" id="activeTab" value="<?php echo esc_html( $lsft_tab ); ?>" />
						<!-- Adding security through hidden referrer field -->
						<?php wp_nonce_field( 'cfxlsft' ); ?>
						<section id="automode">
							<!-- <div class="postbox"> -->
							<div class="settingsbox">
								<div class="row">
									<div class="col-6">
										<h3>Automode</h3>
										<div class="settings-group">
											<div class="switch-holder">
												<div class="switch-toggle">
													<input type="checkbox" id="automode-toggler" name="automode" <?php echo 'on' === $options['automode'] ? 'checked' : ''; ?>>
													<label for="automode-toggler"></label>
												</div>
												<p>
													Set Auto mode On to let Language Swtcher for Transposh automatically append itself to the chosen menu. Default: On. <span style="font-weight: bold;">This won't work in the new FSE themes:</span> set Automode off and use shortcodes instead to put your language switcher in the navigation
												</p>
											</div>
										</div>
										<h3>Redirect to home?</h3>
										<div class="settings-group">
											<div>
												<div class="switch-holder">
													<div class="switch-toggle">
														<input type="checkbox" id="redirect-to-home" name="redirect_to_home" <?php echo 'on' === $options['redirect_to_home'] ? 'checked' : ''; ?>>
														<label for="redirect-to-home"></label>
													</div>
													<p>
														If set to On, the user will be redirected to the home page after having changed the language; otherwise he will be redirected to the same page he was visiting. Default: Off
													</p>

												</div>
											</div>
										</div>
										<h3>Original languages' names?</h3>
										<div class="settings-group">
											<div>
												<div class="switch-holder">
													<div class="switch-toggle">
														<input type="checkbox" id="original_lang_names" name="original_lang_names" <?php echo 'on' === $options['original_lang_names'] ? 'checked' : ''; ?>>
														<label for="original_lang_names"></label>
													</div>
													<p>
														If set to On, original languages names will be used instead of their english names.
													</p>
												</div>
											</div>
										</div>
										<h3>Use USA flag for english language</h3>
										<div class="settings-group">
											<div>
												<div class="switch-holder">
													<div class="switch-toggle">
														<input type="checkbox" id="usa_flag" name="usa_flag" <?php echo 'on' === $options['usa_flag'] ? 'checked' : ''; ?>>
														<label for="usa_flag"></label>
													</div>
													<p>
														If set to On, USA flag will be used for english language (even is you're using Transposh flags).
													</p>
												</div>
											</div>
										</div>
										<h3>Use Transposh flags?</h3>
										<div class="settings-group">
											<div>
												<div class="switch-holder">
													<div class="switch-toggle">
														<input type="checkbox" id="flag_type" name="flag_type" <?php echo 'tp' === $options['flag_type'] ? 'checked' : ''; ?>>
														<label for="flag_type"></label>
													</div>
													<p>
														If set to On, Transposh flags set will be used.
													</p>
												</div>
											</div>
										</div>
									</div>
									<div class="col-6">
										<section id="switcher-type">
											<h3>Switcher type</h3>
											<p>Choose the type of Language Switcher you want to use:</p>
											<?php
											$select_options = array(
												'Flags' => 'flags',
												'Codes' => 'codes',
												'Native dropdown (select)' => 'select',
												'Custom dropdown (list)' => 'list',
											);
											?>
											<select name="switcher_type" id="switcher_type">
												<?php
												foreach ( $select_options as $label => $value ) {
													$selected = '';
													if ( $value === $options['switcher_type'] ) {
														$selected = 'selected';
													}
													?>
													<option value="<?php echo esc_html( $value ); ?>" <?php echo esc_html( $selected ); ?>><?php echo esc_html( $label ); ?></option>
													<?php
												}
												?>
											</select>
											<?php
											$value = sanitize_text_field( wp_unslash( $options['switcher_type'] ) );

											$class = 'hidden';
											if ( 'list' === $value || 'select' === $value ) {
												$class = '';
											}
											?>
											<div id="dropdown_styles" class="<?php echo esc_html( $class ); ?>">
												<div id="list-styles" class="<?php echo 'yes' === $options['select_as_list'] ? '' : 'hidden'; ?>">
													<h4>List items (flags, text, code or flags and text)</h4>
													<select name="custom_list_items" id="custom_list_items">
														<?php
														$list_items_options = array(
															'Flag only'     => 'flag-only',
															'Text only'     => 'text-only',
															'Code only'     => 'code-only',
															'Flag and text' => 'flag-and-text',
														);
														foreach ( $list_items_options as $key => $item ) {
															$data     = '';
															$selected = '';
															if ( $item === $options['custom_list_items'] ) {
																$selected = 'selected';
															}
															if ( 'flag-only' === $item || 'flag-and-text' === $item ) {
																$data = 'data-requires-images=1';
															}
															?>
															<option value="<?php echo esc_html( $item ); ?>" <?php echo esc_html( $selected ) . ' ' . esc_html( $data ); ?>><?php echo esc_html( $key ); ?></option>
															<?php
														}
														?>
													</select>
													<br>
												</div>
											</div>
											<br>
											<?php
											$class = '';
											?>
										</section>
										<section id="menus">
											<h3>Menus</h3>
											<p>Set where the Language Switcher will show up:</p>
											<div id="menu_locations_wrapper">
												<?php
												$menus                   = get_registered_nav_menus();
												$existing_menu_locations = explode( ',', $options['menu_locations'] );
												foreach ( $menus as $key => $value ) {
													$checked = '';
													if ( in_array( $key, $existing_menu_locations, true ) ) {
														$checked = 'checked';
													}
													?>
													<div class="menu_location"><input type="checkbox" name="menu_locations[]" value="<?php echo esc_html( $key ); ?>" <?php echo esc_html( $checked ); ?> /><label><?php echo esc_html( $value ); ?> <i>(<?php echo esc_html( $key ); ?>)</i></label></div>
													<?php
												}
												?>
											</div>
										</section>
										<section id="switcher-classes">
											<br />
											<br />
											<h3>Menu item classes</h3>
											<p>If your theme uses some specific class for navigation menu items, add them here. Be sure to separate the classes only by a comma. LFST will add these classes to the menu items for the language switcher in order they look and feel accordingly to your theme.</p>
											<input type="text" name="menu_classes" placeholder="class1,class2,class3..." value="<?php echo esc_html( $options['menu_classes'] ); ?>" />
										</section>
									</div>
								</div>
							</div>
							<!-- </div> -->
						</section>
						<br>
						<br>
						<input type="submit" value="Save changes" class="button-primary" />
					</form>

				</div>

				<div id="styles" class="tab-panel <?php echo ( 'styles' === $lsft_tab ) ? 'active' : ''; ?>">
					<form method="post" action="admin-post.php" id="custom-style-form" class="no-class">
						<input type="hidden" name="action" value="save_cfxlsft_options" />
						<input type="hidden" name="activeTab" id="activeTab" value="<?php echo esc_html( $lsft_tab ); ?>" />
						<!-- Adding security through hidden referrer field -->
						<?php wp_nonce_field( 'cfxlsft' ); ?>
						<section id="switcher-styles">
							<!-- <div class="postbox"> -->
							<div class="settingsbox">
								<?php
								$class = '';
								if ( 'flags' !== $options['switcher_type'] ) {
									$class = '';
								}
								?>
								<section id="style-editor">
									<br>
									<br>
									<section id="custom-css-section">
										<div id="custom_css_editor">
											<h3>Language Switcher style editor</h3>
											<p>If you need to customize CSS rules for Language Switcher FT, you can select here one of the basic stylesheets used by default and use it as a starting point to create your unique style. </p>
											<p>Just load the file, click the button "Copy to clipboard and paste it in your custom css file or in your cstomizer section dedicated to the custom CSS and start to edit.</p>

											<br>
											<br>
											<div id="copy-button">
												<button id="reset-css" class="button button-link-delete">Reset to Default</button>
												<button id="copy-to-clipboard">Copy to clipboard</button>
												<!-- <button id="clear-codemirror" class="button">Clear</button> -->
											</div>
											<textarea id="code_editor_page_css" rows="5" name="custom_style" class="codemirror widefat textarea <?php echo esc_html( $class ); ?>"></textarea>
										</div>
									</section>
								</section>
							</div>
							<!-- </div> -->
						</section>
						<br><br>
						<input type="submit" value="Save changes" class="button-primary" />
					</form>
				</div>

				<div id="shortcodes" class="tab-panel <?php echo ( 'shortcodes' === $lsft_tab ) ? 'active' : ''; ?>">
					<section id="shortcodes-wrapper">
						<div class="settingsbox">
							<h3>Shortcodes</h3>
							<p>Just click on the shortcode you want to use to copy it to the clipboard :)</p>
							<br />
							<table>
								<tr>
									<td>
										Horizontal flags:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_horizontal_flags]</span>
									</td>
								</tr>
								<tr>
									<td>
										Vertical flags:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_vertical_flags]</span>
									</td>
								</tr>
								<tr>
									<td>
										Horizontal codes:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_horizontal_codes]</span>
									</td>
								</tr>
								<tr>
									<td>
										Vertical codes:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_vertical_codes]</span>
									</td>
								</tr>
								<tr>
									<td>
										Native select names:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_native_dropdown_codes]</span>
									</td>
								</tr>
								<tr>
									<td>
										Native select names:
									</td>
									<td>
										<span class="shortcode-sel">[lsft_native_dropdown_text]</span>
									</td>
								</tr>
								<tr>
									<td>
										Custom list (flags):
									</td>
									<td>
										<span class="shortcode-sel">[lsft_custom_dropdown_flags]</span>
									</td>
								</tr>
								<tr>
									<td>
										Custom list (names):
									</td>
									<td>
										<span class="shortcode-sel">[lsft_custom_dropdown_names]</span>
									</td>
								</tr>
								<tr>
									<td>
										Custom list (codes):
									</td>
									<td>
										<span class="shortcode-sel">[lsft_custom_dropdown_codes]</span>
									</td>
								</tr>
								<tr>
									<td>
										Custom list (flags and names):
									</td>
									<td>
										<span class="shortcode-sel">[lsft_custom_dropdown_flags_names]</span>
									</td>
								</tr>
							</table>
							
						

						</div>
					</section>
				</div>

				<div id="more_tools" class="tab-panel <?php echo ( 'more_tools' === $lsft_tab ) ? 'active' : ''; ?>">
					<div class="settingsbox">
						<h3>More Tools</h3>
						<p>Here you find other plugins by Codingfix, that's me :)</p>
						<div class="more-tools-plugins">
							<div class="plugin-item">
								<img src="<?php echo esc_url( LSFT_PLUGIN_URL . 'assets/images/plg-icon-256x256.png' ); ?>" alt="Pluginer - Bulk install for WordPress plugins" />
								<div class="plugin-item-description">
									<h3><a href="https://wordpress.org/plugins/instalist/" target="_blank">Pluginer (formerly Instalist) - Bulk install for WordPress plugins</a></h3>
									<p>Stop wasting time installing plugins one-by-one: Speed Up WordPress Setup with Pluginer.<br>Pluginer lets you create reusable plugin lists and install them all in a single click.<br><a href="https://wordpress.org/plugins/instalist/" target="_blank">Get Pluginer Free!</a></p>
								</div>
							</div>
							<hr>
							<div class="plugin-item">
								<img src="<?php echo esc_url( LSFT_PLUGIN_URL . 'assets/images/cht-icon-256x256.png' ); ?>" alt="Chat Everywhere - Start a Whatsapp or Telegram chat from buttons, links, images, menu items and more!" />
								<div class="plugin-item-description">
									<h3><a href="https://wordpress.org/plugins/chat-everywhere/" target="_blank">Chat Everywhere - Start a Whatsapp or Telegram chat from buttons, links, images, menu items and more!</a></h3>
									<p>Start chats from any element. Just add the default class (or a custom class) to the DOM element you want and clicking on it will start a chat instantly. <br>You use Whatsapp, Telegram or both. <br><a href="https://wordpress.org/plugins/chat-everywhere/ target="_blank">Get Chat Everywhere Free!</a></p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<br />
				<br />
			</div>

		</div>
	</div>

</div>
		<?php
	}
