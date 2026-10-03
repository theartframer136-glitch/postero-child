<?php
/**
 * Admin Assets Class
 *
 * @package Click_To_Chat
 * @subpackage Administration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HT_CTC_Admin_Page_Scripts' ) ) {

	/**
	 * Admin Page Scripts Class
	 */
	class HT_CTC_Admin_Page_Scripts {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			// add_filter( 'wp_resource_hints', array( $this, 'add_resource_hints' ), 10, 2 );
		}


		/**
		 * Add Resource Hints
		 * Establish early connections to external origins used dynamically by the React/JS modules.
		 *
		 * @param array  $hints URLs to print for resource hints.
		 * @param string $relation_type The relation type the URLs are printed for, e.g. 'preconnect'.
		 * @return array
		 */
		// public function add_resource_hints( $hints, $relation_type ) {
		// if ( 'preconnect' === $relation_type ) {
		// Only add if we are on a plugin page
		// if ( function_exists( 'get_current_screen' ) ) {
		// $screen = get_current_screen();
		// if ( $screen && strpos( $screen->id, 'click-to-chat' ) !== false ) {
		// $hints[] = 'https://ipinfo.io';
		// }
		// }
		// }
		// return $hints;
		// }


		/**
		 * Enqueue scripts and styles for the CTC admin SPA page.
		 *
		 * Orchestrator only — each step lives in its own private method below.
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		public function enqueue_scripts( $hook ) {

			// Outer gate: skip on non-CTC admin pages. Each enqueue_* helper below has its own inner gate for page-specific assets.
			if ( ! in_array( $hook, $this->allowed_hooks(), true ) ) {
				return;
			}

			$this->ensure_utils_loaded();

			do_action( 'ht_ctc_ah_before_admin_enqueue_scripts', $hook );

			$ctc = $this->build_localize_data();

			$this->enqueue_intl_tel_input( $hook );
			$this->enqueue_wp_media( $hook );
			$this->enqueue_wp_editor( $hook );
			$this->enqueue_main_assets( $ctc, $hook );

			// Prevents "Dark Mode Flicker" (FOUC). linear script execution before CSS paints.
			add_action( 'admin_head', array( $this, 'add_theme_script' ) );
		}

		/**
		 * Admin hook names this loader is allowed to run on.
		 *
		 * The base list is just the main SPA page. PRO / extensions / future sub-pages
		 * can register their own admin hooks via the `ht_ctc_fh_admin_allowed_hooks`
		 * filter — each helper inside this class then gates further on the specific
		 * hook so different pages can opt in to different asset bundles.
		 *
		 * @return string[]
		 */
		private function allowed_hooks() {
			// Hook for the top-level menu with slug 'click-to-chat' from
			// ht_ctc_admin_menu.php add_menu_page().
			$hooks = array( 'toplevel_page_click-to-chat' );

			return apply_filters( 'ht_ctc_fh_admin_allowed_hooks', $hooks );
		}

		/**
		 * Safety net: ensure the Utils class is available even if the main loader didn't include it yet.
		 *
		 * @return void
		 */
		private function ensure_utils_loaded() {
			if ( class_exists( 'HT_CTC_Utils' ) ) {
				return;
			}

			if ( file_exists( HT_CTC_PLUGIN_DIR . 'new/inc/utils/class-ht-ctc-utils.php' ) ) {
				include_once HT_CTC_PLUGIN_DIR . 'new/inc/utils/class-ht-ctc-utils.php';
			}
		}

		/**
		 * Get the saved admin theme after constraining it to supported values.
		 *
		 * @return string
		 */
		private function get_admin_theme() {
			$ht_ctc_admin_settings = HT_CTC_Utils::get_option( 'ht_ctc_admin_settings' );
			$theme                 = isset( $ht_ctc_admin_settings['theme'] ) ? sanitize_key( $ht_ctc_admin_settings['theme'] ) : 'light';
			$allowed_themes        = array( 'light', 'dark', 'system' );

			return in_array( $theme, $allowed_themes, true ) ? $theme : 'light';
		}

		/**
		 * Build the data payload that ships to the admin React app as `ht_ctc_admin_var`.
		 *
		 * Includes initial settings, REST endpoints, i18n strings, theme, and per-module
		 * dynamic-import paths.
		 *
		 * @return array
		 */
		private function build_localize_data() {

			$assets_dir = defined( 'HT_CTC_DEBUG_MODE' ) ? 'dev' : 'min';

			$theme = $this->get_admin_theme();

			$intl_tel_input_utils = plugins_url( 'new/admin/admin_assets/intl/js/utils.js', HT_CTC_PLUGIN_FILE );

			// Get all allowed settings.
			if ( ! class_exists( 'HT_CTC_Settings_Data' ) ) {
				require_once HT_CTC_PLUGIN_DIR . 'new/inc/commons/class-ht-ctc-settings-data.php';
			}

			$settings_groups = HT_CTC_Settings_Data::get_settings_groups();

			// Defaults for never-saved groups come from HT_CTC_Utils::get_option()'s own
			// fallback path — no separate defaults/fallback build is needed here.
			// (The old ht_ctc_fh_admin_var_fallback_values result was never consumed.)
			$initial_settings = array();
			foreach ( $settings_groups as $group ) {
				$initial_settings[ $group ] = HT_CTC_Utils::get_option( $group );
			}

			// ex: "wp-json/click-to-chat-for-whatsapp/v1/save-settings/".
			$save_settings_endpoint = class_exists( 'HT_CTC_Rest_API' ) ? HT_CTC_Rest_API::route( 'save-settings/' ) : '';
			// ex: "wp-json/click-to-chat-for-whatsapp/v1/get-fields/".
			$get_fields_endpoint = class_exists( 'HT_CTC_Rest_API' ) ? HT_CTC_Rest_API::route( 'get-fields/' ) : '';

			$ctc = array(
				'version'         => HT_CTC_VERSION,
				// Admin locale — part of the JS field-cache key (fields carry translated
				// strings, so a language switch must invalidate the cached tabs).
				'locale'          => get_user_locale(),
				'initialSettings' => $initial_settings,
				'paths'           => array(
					'plugin_url'        => defined( 'HT_CTC_PLUGIN_DIR_URL' ) ? HT_CTC_PLUGIN_DIR_URL : plugin_dir_url( HT_CTC_PLUGIN_FILE ),
					'front_css'         => plugins_url( 'new/inc/assets/css/' . ( defined( 'HT_CTC_DEBUG_MODE' ) ? 'dev/main.dev.css' : 'main.css' ), HT_CTC_PLUGIN_FILE ),
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'intlTelInput'      => plugins_url( 'new/admin/admin_assets/intl/js/intlTelInput.min.js', HT_CTC_PLUGIN_FILE ),
					'intlTelInputUtils' => $intl_tel_input_utils,
				),
				'api'             => array(
					'settings' => array(
						'save'      => $save_settings_endpoint,
						'getFields' => $get_fields_endpoint,
					),
				),
				'wprest_nonce'    => wp_create_nonce( 'wp_rest' ),
				'nonce'           => wp_create_nonce( 'ht_ctc_admin_nonce' ),
				// localization. i18n
				// todo(4.42): i18n and have to update the content and at js file
				'i18n'            => array(
					'save'          => 'Save',
					'saved'         => 'Settings saved successfully.',
					'error'         => 'Error saving settings.',
					'delete'        => 'Are you sure you want to delete this?',
					'deleteSuccess' => 'Deleted successfully.',
					'deleteError'   => 'Error deleting.',
				),
				'theme'           => $theme,
				'preview'         => array(
					'templatesBasePath' => plugins_url( "new/admin2/assets/$assets_dir/js/modules/preview/templates/", HT_CTC_PLUGIN_FILE ),
					// Used by greetings preview templates to substitute the {site} variable.
					'site'              => get_bloginfo( 'name' ),
					// WP timezone offset in hours (mirrors the front end's ctc.tz =
					// get_option('gmt_offset')); used by the PRO multi-agent preview
					// to compute business-hours online/offline against WP "now".
					'wpTzOffset'        => get_option( 'gmt_offset' ),
				),

				/*
				 * Lazy modules: each entry is a JS file App.js dynamically import()s.
				 * All paths use $assets_dir so dev loads source and prod loads min.
				 *
				 * HOW AN ENTRY LOADS — set ONE of these triggers (an entry with
				 * neither `tabs` nor `delay` is inert: it just never loads, no error):
				 *   • tabs  → loaded when one of these admin tabs is opened
				 *             (App.loadTabSettings — load before render, then run
				 *             `method` after render). Use for tab-specific UI.
				 *   • delay → loaded once, `delay` ms after boot, regardless of tab
				 *             (App.loadDelayedModules). Use for global, always-on
				 *             features kept out of the initial bundle (e.g. preview).
				 *   • on-demand → no trigger here; loaded explicitly by key from JS
				 *             when needed (e.g. App.loadAndInitIntlInput reads
				 *             modulesPath.intlInput directly). Use for rare/contextual loads.
				 *
				 * PER-ENTRY KEYS:
				 *   path      — URL of the JS module to import() (required).
				 *   tabs      — tab ids that trigger loading (see above).
				 *   delay     — ms after boot to load (see above).
				 *   method    — export name called after load: method(arg|context, context, app).
				 *   arg       — optional first arg for `method`.
				 *   managerId — register module.default as a named manager (app.managers[id])
				 *               instead of/in addition to calling `method`; lets other
				 *               code + PRO look it up (RepeaterManager, PreviewManager).
				 *   rendererId— register module.default as a field renderer.
				 */
				'modulesPath'     => array(
					'intlInput'       => array(
						'path'   => plugins_url( "new/admin2/assets/$assets_dir/js/modules/logic/IntlInput.js", HT_CTC_PLUGIN_FILE ),
						// 'tabs'   => array( 'general-settings', 'greetings-settings' ),
						'method' => 'initIntlInput',
						'arg'    => 'intl_number',
					),
					'displaySettings' => array(
						'path'   => plugins_url( "new/admin2/assets/$assets_dir/js/modules/logic/DisplaySettings.js", HT_CTC_PLUGIN_FILE ),
						'tabs'   => array( 'display-settings', 'group-settings', 'share-settings' ),
						'method' => 'initDisplaySettings',
					),
					'repeater'        => array(
						'path'      => plugins_url( "new/admin2/assets/$assets_dir/js/modules/managers/RepeaterManager.js", HT_CTC_PLUGIN_FILE ),
						'tabs'      => array( 'general-settings', 'greetings-settings', 'display-settings', 'analytics-settings' ),
						'managerId' => 'repeater',
					),
					'actions'         => array(
						'path'   => plugins_url( "new/admin2/assets/$assets_dir/js/modules/logic/Actions.js", HT_CTC_PLUGIN_FILE ),
						'tabs'   => array( 'advanced-settings' ),
						'method' => 'initActions',
					),
					// Live preview: global (not tab-bound). Loaded `delay` ms after
					// boot by App.loadDelayedModules(), so it stays out of the
					// initial bundle. registerManager('preview', …) lets PRO hook in
					// via the ctc_manager_registered_preview event.
					'preview'         => array(
						'path'      => plugins_url( "new/admin2/assets/$assets_dir/js/modules/managers/PreviewManager.js", HT_CTC_PLUGIN_FILE ),
						'managerId' => 'preview',
						'delay'     => 1000,
					),
					'editor'          => array(
						// $editor_module_filename is either BlockEditor.js or BlockEditorTinymce.js depending on the greetings_editor setting.
						'path'       => plugins_url( "new/admin2/assets/$assets_dir/js/modules/components/layouts/BlockEditorTinymce.js", HT_CTC_PLUGIN_FILE ),
						'tabs'       => array( 'greetings-settings', 'woo-overwrite-settings' ),
						// same as the php array key config (block_editor|block_editor_tinymce)
						'rendererId' => 'block_editor_tinymce',
					),
				),
			);

			// Pro Version
			// if ( defined( 'HT_CTC_PRO_VERSION' ) ) {
			// $ctc['pro_version'] = HT_CTC_PRO_VERSION;
			// }

			$ctc = apply_filters( 'ht_ctc_fh_admin_var', $ctc );

			return $ctc;
		}

		/**
		 * Build the WP enqueue strategy arg (in_footer + defer/async) compatible with the running WP version.
		 *
		 * Returns `true` on WP < 6.3 (the legacy `$in_footer` boolean), or the strategy
		 * array form on WP >= 6.3.
		 *
		 * @param string $strategy 'defer' | 'async'.
		 * @return array|true
		 */
		private function script_strategy( $strategy ) {
			$wp_ver = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '1.0';

			if ( version_compare( $wp_ver, '6.3', '>=' ) ) {
				return array(
					'in_footer' => true,
					'strategy'  => $strategy,
				);
			}

			return true;
		}

		/**
		 * Register and enqueue the intl-tel-input phone library.
		 *
		 * Registered on every allowed admin hook so that other pages can use it as a
		 * dependency, but only enqueued on the main SPA page.
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		private function enqueue_intl_tel_input( $hook ) {

			wp_register_style( 'ctc_admin_intl_css', plugins_url( 'new/admin/admin_assets/intl/css/intlTelInput.min.css', HT_CTC_PLUGIN_FILE ), array(), HT_CTC_VERSION );
			wp_register_script( 'ctc_admin_intl_js', plugins_url( 'new/admin/admin_assets/intl/js/intlTelInput.min.js', HT_CTC_PLUGIN_FILE ), array(), HT_CTC_VERSION, $this->script_strategy( 'defer' ) );

			if ( 'toplevel_page_click-to-chat' !== $hook ) {
				return;
			}

			wp_enqueue_style( 'ctc_admin_intl_css' );
			wp_enqueue_script( 'ctc_admin_intl_js' );
		}

		/**
		 * Enqueue WP core media picker (required by Layouts.js 'block_upload_image').
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		private function enqueue_wp_media( $hook ) {
			if ( 'toplevel_page_click-to-chat' !== $hook ) {
				return;
			}

			wp_enqueue_media();
		}

		/**
		 * Enqueue WP core TinyMCE editor (required by Layouts.js 'block_editor_tinymce').
		 *
		 * Cannot be robustly lazy-loaded from JS due to PHP-dependent settings
		 * (tinyMCEPreInit, l10n).
		 *
		 * @param string $hook Current admin page hook.
		 * @return void
		 */
		private function enqueue_wp_editor( $hook ) {
			if ( 'toplevel_page_click-to-chat' !== $hook ) {
				return;
			}

			if ( function_exists( 'wp_enqueue_editor' ) ) {
				wp_enqueue_editor();
			}
		}

		/**
		 * Register/enqueue the main admin CSS + the React/JS module bundle.
		 *
		 * Injects the localized `$ctc` data ahead of the bundle. On WP >= 6.5 the script
		 * is enqueued as a true ES module via wp_enqueue_script_module(); on older WP the
		 * `type="module"` attribute is added via the script_loader_tag filter.
		 *
		 * @param array  $ctc  Localize-data payload built by build_localize_data().
		 * @param string $hook Current admin page hook — used to compute per-page JS deps.
		 * @return void
		 */
		private function enqueue_main_assets( $ctc, $hook ) {

			if ( defined( 'HT_CTC_DEBUG_MODE' ) ) {
				$js  = 'dev/js/admin.dev.js';
				$css = 'dev/css/admin.css';
			} else {
				$js  = 'min/js/admin.js';
				$css = 'min/css/admin.css';
			}

			wp_enqueue_style( 'ctc_admin_css', plugins_url( "new/admin2/assets/$css", HT_CTC_PLUGIN_FILE ), array(), HT_CTC_VERSION );

			// Encode with hex flags so any '<', '>', '&', or quotes in saved values cannot
			// affect the surrounding inline <script> context.
			$ctc_json = wp_json_encode( $ctc, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

			// Fall back to an empty object if encoding fails (e.g. invalid UTF-8 in a stored
			// option) so the admin SPA still boots instead of hitting a JS syntax error.
			if ( false === $ctc_json ) {
				HT_CTC_Utils::debug_log(
					'enqueue_main_assets: wp_json_encode( $ctc ) failed; admin settings fell back to empty object',
					array( 'json_error' => function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : '' )
				);
				$ctc_json = '{}';
			}

			// $data_code = 'var ht_ctc_admin_var = ' . wp_json_encode( $ctc ) . ';';
			$data_code = 'var ht_ctc_admin_var = ' . $ctc_json . ';';

			// In debug/dev mode, define __DEV__ for the logging system.
			// In production builds, webpack's DefinePlugin replaces __DEV__ with false.
			if ( defined( 'HT_CTC_DEBUG_MODE' ) ) {
				$data_code .= 'var __DEV__ = true;';
			}

			$js_url = plugins_url( "new/admin2/assets/$js", HT_CTC_PLUGIN_FILE );

			// Per-page JS dependencies. Only pages that enqueue intl above need it as a dep.
			$ctc_admin_js_dependencies = array();
			if ( 'toplevel_page_click-to-chat' === $hook ) {
				$ctc_admin_js_dependencies[] = 'ctc_admin_intl_js';
			}

			/**
			 * Allow extensions to add JS dependencies for the main admin bundle on a per-hook basis.
			 *
			 * @param string[] $ctc_admin_js_dependencies Script handles.
			 * @param string   $hook                      Current admin page hook.
			 */
			$ctc_admin_js_dependencies = apply_filters( 'ht_ctc_fh_admin_js_dependencies', $ctc_admin_js_dependencies, $hook );

			if ( function_exists( 'wp_enqueue_script_module' ) ) {
				// Register data shim.
				wp_register_script( 'ctc_admin_data', false, array(), HT_CTC_VERSION, false );
				wp_add_inline_script( 'ctc_admin_data', $data_code );
				wp_enqueue_script( 'ctc_admin_data' );

				// Enqueue script dependencies manually since modules don't depend on scripts.
				foreach ( $ctc_admin_js_dependencies as $dep ) {
					// todo: either register before this or here.
					wp_enqueue_script( $dep );
				}

				wp_enqueue_script_module( 'ctc_admin_js', $js_url, array(), HT_CTC_VERSION );
			} else {
				// Fallback: Use filter add_type_attribute to add type="module"
				// Note: script_loader_tag provides ($tag, $handle, $src). We only need 2 params ($tag, $handle).
				add_filter( 'script_loader_tag', array( $this, 'add_type_attribute' ), 10, 2 );
				wp_enqueue_script( 'ctc_admin_js', $js_url, $ctc_admin_js_dependencies, HT_CTC_VERSION, true );
				wp_add_inline_script( 'ctc_admin_js', $data_code, 'before' );
			}
		}

		/**
		 * Add type="module" to scripts (Fallback for WordPress < 6.5).
		 *
		 * Standard wp_enqueue_script() does not support ES modules. This filter manually
		 * injects the type="module" attribute for older WP versions that lack
		 * wp_enqueue_script_module().
		 *
		 * Output Examples:
		 *
		 * wp_enqueue_script_module():
		 * <script type="module" src=".../admin.dev.js?ver=4.39" id="ctc_admin_js-js-module"></script>
		 *
		 * wp_enqueue_script() + filter:
		 * <script type="module" src=".../admin.dev.js?ver=4.39" id="ctc_admin_js-js"></script>
		 *
		 * @param string $tag    The script tag HTML.
		 * @param string $handle The script handle.
		 * @return string Modified script tag.
		 */
		public function add_type_attribute( $tag, $handle ) {
			// Add module type to admin JS handles.
			if ( 'ctc_admin_js' === $handle || 'ctc_admin_search_js' === $handle ) {
				// Check if it already has type attribute.
				if ( false === strpos( $tag, 'type=' ) ) {
					$tag = str_replace( 'src=', 'type="module" src=', $tag );
				}
			}
			return $tag;
		}

		/**
		 * Add Synchronous Theme Script
		 * Prevents "Dark Mode Flicker" (FOUC) by assigning the `data-theme` to the HTML root
		 * linearly before the CSS fully paints.
		 */
		public function add_theme_script() {
			$theme  = $this->get_admin_theme();
			$script = '';

			if ( 'system' === $theme ) {
				$script = "if(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.setAttribute('data-theme', 'dark');}";
			} elseif ( 'dark' === $theme ) {
				$script = "document.documentElement.setAttribute('data-theme', 'dark');";
			}

			if ( '' === $script ) {
				return;
			}

			if ( function_exists( 'wp_print_inline_script_tag' ) ) {
				wp_print_inline_script_tag( $script );
				return;
			}

			// WP < 5.7: Lets JS files handles (FOUC).
		}
	}

}
