<?php

namespace QuadLayers\IGG\Controllers;

use QuadLayers\IGG\Helpers;
use QuadLayers\IGG\Models\Feeds as Models_Feeds;
use QuadLayers\IGG\Models\Accounts as Models_Accounts;
use QuadLayers\IGG\Models\Settings as Models_Settings;
use QuadLayers\IGG\Api\Rest\Endpoints\Backend\Accounts\Get as API_Rest_Accounts_Get;
use QuadLayers\IGG\Api\Rest\Endpoints\Backend\Feeds\Get as API_Rest_Feeds_Get;
use QuadLayers\IGG\Api\Rest\Endpoints\Backend\Feeds\Clear_Cache as API_Rest_Feeds_Clear_Cache;
use QuadLayers\IGG\Api\Rest\Endpoints\Backend\Settings\Get as API_Rest_Setting_Get;

use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\User_Profile as Api_Rest_User_Profile;
use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\User_Media as Api_Rest_User_Media;
use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\User_Stories as Api_Rest_User_Stories;
use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\Media_Comments as Api_Rest_Media_Comments;
use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\Tagged_Media as Api_Rest_Tagged_Media;
use QuadLayers\IGG\Api\Rest\Endpoints\Frontend\Hashtag_Media as Api_Rest_Hashtag_Media;


/**
 * Backend Class
 */
class Backend {

	protected static $instance;
	protected static $menu_slug = 'qligg_backend';

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'init', array( __CLASS__, 'init_add_account' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'add_capability' ) );
		add_action( 'admin_head', array( __CLASS__, 'add_premium_js' ) );
		add_action( 'admin_footer', array( __CLASS__, 'add_premium_styles' ) );
	}

	public static function get_menu_slug() {
		return self::$menu_slug;
	}

	public function add_menu() {
		$menu_slug = self::get_menu_slug();

		add_menu_page(
			QLIGG_PLUGIN_NAME,
			QLIGG_PLUGIN_NAME,
			'qligg_manage_feeds',
			$menu_slug,
			'__return_null',
			'dashicons-camera'
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Welcome', 'insta-gallery' ),
			esc_html__( 'Welcome', 'insta-gallery' ),
			'qligg_manage_feeds',
			$menu_slug,
			'__return_null'
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Accounts', 'insta-gallery' ),
			esc_html__( 'Accounts', 'insta-gallery' ),
			'qligg_manage_feeds',
			"{$menu_slug}&tab=accounts",
			'__return_null'
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Feeds', 'insta-gallery' ),
			esc_html__( 'Feeds', 'insta-gallery' ),
			'qligg_manage_feeds',
			"{$menu_slug}&tab=feeds",
			'__return_null'
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Settings', 'insta-gallery' ),
			esc_html__( 'Settings', 'insta-gallery' ),
			'qligg_manage_feeds',
			"{$menu_slug}&tab=settings",
			'__return_null'
		);
		add_submenu_page(
			$menu_slug,
			esc_html__( 'Premium', 'insta-gallery' ),
			sprintf(
				'%s <i class="dashicons dashicons-awards"></i>',
				esc_html__( 'Premium', 'insta-gallery' )
			),
			'qligg_manage_feeds',
			"{$menu_slug}&tab=premium",
			'__return_null'
		);
	}

	public function add_capability() {
		$role = get_role( 'administrator' );
		$role->add_cap( 'qligg_manage_feeds', true );
	}

	public static function register_scripts() {

		global $wp_version;

		$store   = include QLIGG_PLUGIN_DIR . 'build/store/js/index.asset.php';
		$backend = include QLIGG_PLUGIN_DIR . 'build/backend/js/index.asset.php';

		wp_register_script(
			'qligg-store',
			plugins_url( '/build/store/js/index.js', QLIGG_PLUGIN_FILE ),
			$store['dependencies'],
			$store['version'],
			true
		);

		wp_localize_script(
			'qligg-store',
			'qligg_store',
			array(
				'WP_VERSION'        => $wp_version,
				'QLIGG_REST_ROUTES' => array(
					'accounts' => API_Rest_Accounts_Get::get_rest_path(),
					'feeds'    => API_Rest_Feeds_Get::get_rest_path(),
					'settings' => API_Rest_Setting_Get::get_rest_path(),
					'cache'    => API_Rest_Feeds_Clear_Cache::get_rest_path(),
				),
			)
		);

		wp_register_style(
			'qligg-backend',
			plugins_url( '/build/backend/css/style.css', QLIGG_PLUGIN_FILE ),
			array(
				'media-views',
				'wp-components',
				'wp-editor',
			),
			QLIGG_PLUGIN_VERSION
		);

		wp_register_script(
			'qligg-backend',
			plugins_url( '/build/backend/js/index.js', QLIGG_PLUGIN_FILE ),
			$backend['dependencies'],
			$backend['version'],
			true
		);

		wp_localize_script(
			'qligg-backend',
			'qligg_backend',
			array(
				'plugin_url'           => plugins_url( '/', QLIGG_PLUGIN_FILE ),
				'QLIGG_PLUGIN_NAME'    => QLIGG_PLUGIN_NAME,
				'QLIGG_PLUGIN_VERSION' => QLIGG_PLUGIN_VERSION,
				'QLIGG_PLUGIN_FILE'    => QLIGG_PLUGIN_FILE,
				'QLIGG_PLUGIN_DIR'     => QLIGG_PLUGIN_DIR,
				'QLIGG_DOMAIN'         => QLIGG_DOMAIN,
				'QLIGG_PREFIX'         => QLIGG_PREFIX,
				'QLIGG_WORDPRESS_URL'  => QLIGG_WORDPRESS_URL,
				'QLIGG_REVIEW_URL'     => QLIGG_REVIEW_URL,
				'QLIGG_GROUP_URL'      => QLIGG_GROUP_URL,
				'QLIGG_DEVELOPER'      => QLIGG_DEVELOPER,
				'QLIGG_BUSSINESS_LINK' => Helpers::get_business_access_token_link(),
				'QLIGG_PERSONAL_LINK'  => Helpers::get_personal_access_token_link(),
				'QLIGG_MODELS_FEED'    => Models_Feeds::instance()->get_args(),
				'QLIGG_MODELS_SETTING' => Models_Settings::instance()->get_args(),
			)
		);

		wp_localize_script(
			'qligg-backend',
			'qligg_frontend',
			array(
				'settings'       => Models_Settings::instance()->get(),
				'restRoutePaths' => array(
					'username'    => Api_Rest_User_Media::get_rest_url(),
					'tag'         => Api_Rest_Hashtag_Media::get_rest_url(),
					'tagged'      => Api_Rest_Tagged_Media::get_rest_url(),
					'stories'     => Api_Rest_User_Stories::get_rest_url(),
					'comments'    => Api_Rest_Media_Comments::get_rest_url(),
					'userprofile' => Api_Rest_User_Profile::get_rest_url(),
				),
			)
		);
	}

	public static function enqueue_scripts() {

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::get_menu_slug() ) {
			return;
		}

		/**
		 * Enqueue WordPress REST API settings for authentication
		 */
		wp_enqueue_script( 'wp-api' );

		/**
		 * Load admin scripts
		 */
		wp_enqueue_media();
		wp_enqueue_style( 'qligg-backend' );
		wp_enqueue_script( 'qligg-backend' );
		/**
		 * Load frontend scripts
		 */
		wp_enqueue_script( 'masonry' );
		wp_enqueue_script( 'qligg-swiper' );
		wp_enqueue_style( 'qligg-swiper' );
		wp_enqueue_style( 'qligg-frontend' );
	}

	public static function init_add_account() {

		if ( ! is_admin() || ! current_user_can( 'qligg_manage_feeds' ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['qligg_nonce'] ) || ! wp_verify_nonce( $_REQUEST['qligg_nonce'], 'qligg_add_account' ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['accounts'][0]['id'], $_REQUEST['accounts'][0]['access_token'], $_REQUEST['accounts'][0]['access_token_type'], $_REQUEST['accounts'][0]['expires_in'] ) ) {
			return;
		}

		$account = Models_Accounts::instance()->get( $_REQUEST['accounts'][0]['id'] );

		if ( $account ) {
			$account = Models_Accounts::instance()->update_from_oauth_callback( $_REQUEST['accounts'][0]['id'], $_REQUEST['accounts'][0] );
		} else {
			$account = Models_Accounts::instance()->create( $_REQUEST['accounts'][0] );
		}

			/*
			 * Redirect via php is not working because it preserve the hash in the url
			if ( wp_safe_redirect( admin_url() ) ) {
				exit;
			}
			*/

			/**
			 * Don't escape because it replace & with &#038;
			 */
		?>
				<script type="text/javascript">
					var url = "<?php echo QLIGG_ACCOUNT_URL; ?>";
					var currentHash = window.location.hash;
					try {
						// Remove #_=_, #_ or # from URL if present (Facebook OAuth artifacts)
						if (currentHash === "#_=_" || currentHash === "#_" || currentHash === "#") {
							window.location.replace(url);
						} else {
							window.location.replace(url + currentHash);
						}
					} catch (error) {
						console.error('[QLIGG Redirect Debug] Error during redirect:', error);
						console.error('[QLIGG Redirect Debug] Error message:', error.message);
						console.error('[QLIGG Redirect Debug] Error stack:', error.stack);
					}
				</script>
			<?php
	}

	public static function add_premium_js() {
		?>
			<script>
				var QLIGG_IS_PREMIUM = false;
			</script>
		<?php
	}

	public static function add_premium_styles() {
		?>
			<style>
				.qligg-premium-field {
					opacity: 0.5;
					pointer-events: none;
				}
				.qligg-premium-field input,
				.qligg-premium-field textarea,
				.qligg-premium-field select {
					background-color: #eee;
				}
				.qligg-premium-field .description {
					display: inline-block !important;
				}
			</style>
		<?php
	}

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
