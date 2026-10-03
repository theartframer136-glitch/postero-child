<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://codingfix.com
 * @since      1.0.0
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/public
 */

// [ ] change ids and stylesheets for shortcodes and widgtes.
// [ ] move content of the different stylesheets in cfx-language-switcher-for-transposh-public.css.
// [ ] remove Flag style select in admin general tab.
// [ ] allow user to copy or download the style they want to apply so they can put in their custom css and edit what they have to change.
// [ ] allow some very basic customization of styles like flag size (for menu and shorcode), horizontal and vertical alignment (for shortcodes).
// [ ] check filter_input filter option.


/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Cfx_Language_Switcher_For_Transposh
 * @subpackage Cfx_Language_Switcher_For_Transposh/public
 * @author     Marco Gasi <codingfix@codingfix.com>
 */
class Cfx_Language_Switcher_For_Transposh_Public {


	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The options of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $options    The options of this plugin.
	 */
	private $options;

	/**
	 * The english flag which will be used by this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $en_flag    The english flag which will be used by this plugin..
	 */
	private $en_flag;

	/**
	 * The path of the flag icons used by this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $flag_path    The path of the flag icons used by this plugin.
	 */
	private $flag_path;

	/**
	 * The options of Transposh Translation Filter plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $transposh_options    The options of Transposh Translation Filter plugin.
	 */
	private $transposh_options;

	/**
	 * The default language set in Transposh Translation Filter plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $default_lang    The default language set in Transposh Translation Filter plugin.
	 */
	private $default_lang;

	/**
	 * The current language set in Transposh Translation Filter plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $current_lang    The current language set in Transposh Translation Filter plugin.
	 */
	private $current_lang;

	/**
	 * The options of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $used_languages    The languages actually used in current website.
	 */
	private $used_languages;

	/**
	 * The array of the languages set (cache).
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $languages    The languages saved in cache.
	 */
	private $languages;

	/**
	 * The size of the flag.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $flag_size    The size in pixels fo the flag icon.
	 */
	private $flag_size;

	/**
	 * The path to the LSFT stylesheets.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $style_path    he path to the LSFT stylesheets.
	 */
	private $style_path;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = isset( $version ) ? $version : '1.0.0';
		$this->options     = get_option( 'cfxlsft_options', array() );
		if ( file_exists( WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/utils.php' ) && file_exists( WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/constants.php' ) ) {
			include_once WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/utils.php';
			include_once WP_PLUGIN_DIR . '/transposh-translation-filter-for-wordpress/core/constants.php';
			if ( ! defined( 'TRANSPOSH_OPTIONS' ) ) {
				define( 'TRANSPOSH_OPTIONS', 'transposh_options' );
			}
			if ( ! defined( 'TRANSPOSH_DIR_IMG' ) ) {
				define( 'TRANSPOSH_DIR_IMG', '' );
			}
			$this->plugin_name       = $plugin_name;
			$this->version           = $version;
			$this->options           = get_option( 'cfxlsft_options' );
			$this->style_path        = LSFT_PLUGIN_URL . 'assets/styles/';
			$this->en_flag           = $this->get_en_flag();
			$this->flag_path         = $this->get_flag_path();
			$this->transposh_options = get_option( TRANSPOSH_OPTIONS );
			$this->default_lang      = isset( $this->transposh_options['default_language'] ) ? $this->transposh_options['default_language'] : 'en';
			if ( isset( $this->transposh_options['viewable_languages'] ) ) {
				$this->used_languages = explode( ',', $this->transposh_options['viewable_languages'] );
			} else {
				$this->used_languages = array( 'en' );
			}
			$this->current_lang = $this->get_current_lang();
		}
	}

	/**
	 * Returns the array of the languages set.
	 *
	 * @since    1.8.1
	 */
	private function get_languages() {
		if ( isset( $this->languages ) && is_array( $this->languages ) && count( $this->languages ) > 0 ) {
			return $this->languages;
		}
		$current   = $this->get_current_lang();
		$flag_base = $this->get_flag_path();
		$used      = $this->used_languages;
		if ( ! in_array( $this->default_lang, $used, true ) ) {
			array_unshift( $used, $this->default_lang );
		}
		$languages = array();
		foreach ( $used as $lang ) {
			$lang_name   = $this->get_lang_name( $lang );
			$flag_name   = $this->get_flag_name( $lang );
			$target      = $this->get_target_page( $lang );
			$languages[] = array(
				'code'       => $lang,
				'name'       => $lang_name,
				'flag'       => $flag_name,
				'flag_url'   => $flag_base . '/' . $flag_name . '.png',
				'alt'        => $lang_name,
				'url'        => $target,
				'is_current' => ( $current === $lang ),
				'is_default' => ( $this->default_lang === $lang ),
				'classes'    => array( 'no_translate' ),
			);
		}
		$this->languages = $languages;
		return $this->languages;
	}

	/**
	 * Returns the name of a language.
	 *
	 * @param string $lang selected language.
	 * @since    1.8.1
	 */
	private function get_lang_name( $lang ) {
		return isset( $this->options['original_lang_names'] ) && 'on' === $this->options['original_lang_names'] ? ucfirst( transposh_consts::get_language_orig_name( $lang ) ) : ucfirst( transposh_consts::get_language_name( $lang ) );
	}

	/**
	 * Returns the current language set in Transposh Translation Filter plugin.
	 *
	 * @since    1.0.0
	 */
	public function get_current_lang() {
		$current_lang = 'en';
		if ( isset( $_SERVER['REQUEST_URI'] ) && isset( $_SERVER['SERVER_NAME'] ) ) {
			$current_lang = transposh_utils::get_language_from_url(
				sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
				isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ? 'https://' : 'http://' .
				sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) )
			);
			if ( empty( $current_lang ) ) {
				$current_lang = $this->default_lang;
			}
		}
		return $current_lang;
	}

	/**
	 * Register shortcodes
	 *
	 * @since version 1.2.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'lsft_horizontal_flags', array( $this, 'shortcode_horizontal_flags' ) );
		add_shortcode( 'lsft_vertical_flags', array( $this, 'shortcode_vertical_flags' ) );
		add_shortcode( 'lsft_horizontal_codes', array( $this, 'shortcode_horizontal_codes' ) );
		add_shortcode( 'lsft_vertical_codes', array( $this, 'shortcode_vertical_codes' ) );
		add_shortcode( 'lsft_custom_dropdown_flags', array( $this, 'shortcode_custom_dropdown_flags' ) );
		add_shortcode( 'lsft_custom_dropdown_flags_names', array( $this, 'shortcode_custom_dropdown_flags_names' ) );
		add_shortcode( 'lsft_custom_dropdown_names', array( $this, 'shortcode_custom_dropdown_names' ) );
		add_shortcode( 'lsft_custom_dropdown_codes', array( $this, 'shortcode_custom_dropdown_codes' ) );
		add_shortcode( 'lsft_native_dropdown_text', array( $this, 'shortcode_native_dropdown_text' ) );
		add_shortcode( 'lsft_native_dropdown_codes', array( $this, 'shortcode_native_dropdown_codes' ) );
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cfx-language-switcher-for-transposh-public.css', array(), $this->version, 'all' );
		wp_enqueue_style( $this->plugin_name . '-lsft', $this->style_path . 'lsft.css', array(), $this->version, 'all' );

		// if custom style exists, insert it inline.
		if ( ! empty( $this->options['custom_style'] ) ) {
			wp_add_inline_style( $this->plugin_name, $this->options['custom_style'] );
		}
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cfx-language-switcher-for-transposh-public.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Returns the flag which will be used as english flag (UK or USA flag).
	 *
	 * @since    1.0.0
	 */
	public function get_en_flag() {
		$en_flag = 'gb';
		if ( isset( $this->options['usa_flag'] ) && 'on' === $this->options['usa_flag'] ) {
			$en_flag = 'us';
		}
		return $en_flag;
	}

	/**
	 * Returns the path to the flag icons depending on the flags chosen by the developer.
	 *
	 * @since    1.0.0
	 */
	public function get_flag_path() {
		$flag_path = LSFT_PLUGIN_URL . 'assets/flags';
		if ( isset( $this->options['flag_type'] ) && 'tp' === $this->options['flag_type'] ) {
			$flag_path = plugins_url() . '/transposh-translation-filter-for-wordpress/' . TRANSPOSH_DIR_IMG . '/flags';
		}
		return $flag_path;
	}

	/**
	 * Returns the name of the flag icon.
	 *
	 * @param string $lang selected language.
	 * @since    1.0.0
	 */
	public function get_flag_name( $lang ) {
		// Se per errore viene passato l'intero array del linguaggio, estraiamo solo il codice.
		if ( is_array( $lang ) ) {
			$lang = isset( $lang['code'] ) ? $lang['code'] : 'en';
		}

		if ( isset( $this->options['flag_type'] ) && 'tp' === $this->options['flag_type'] ) {
			$flag_name = transposh_consts::get_language_flag( $lang );
		} elseif ( 'en' === $lang ) {
			$flag_name = $this->en_flag;
		} else {
			$flag_name = transposh_consts::get_language_flag( $lang );
		}
		return $flag_name;
	}

	/**
	 * Returns the URL the user will be redirected to when he change the website language.
	 *
	 * @param string $lang selected language.
	 * @since    1.0.0
	 * in version 1.0.17 returns the page the user is visiting when he switched to another language
	 * last change reverted in version 1.0.20 because it just didn't work.
	 */
	public function get_target_page( $lang ) {
		$site_url = get_site_url();
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$current_page = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}
		if ( isset( $this->options['redirect_to_home'] ) && 'on' !== $this->options['redirect_to_home'] && is_array( $current_page ) ) {
			if ( $this->get_current_lang() === $this->default_lang ) {
				$slug = $current_page['path'];
			} else {
				$lang_length = strlen( $this->current_lang );
				$slug        = substr( $current_page['path'], $lang_length + 1 );
			}
			if ( $this->default_lang !== $lang ) {
				$target = $site_url . '/' . $lang . $slug;
			} else {
				$target = $site_url . $slug;
			}
		} else {
			$target = $site_url . '/' . $lang;
			if ( $this->default_lang === $lang ) {
				$target = $site_url;
			}
		}

		return $target;
	}

	/**
	 * Renderizza il bottone "Edit" solo se l'utente ha i permessi.
	 *
	 * @param string $layout The layout to use (horizontal, vertical, dropdown, select).
	 * @param string $classes Additional classes to add to the button.
	 * @return string The rendered HTML of the edit button or an empty string.
	 * @since 1.8.1
	 */
	private function maybe_render_edit_button( string $layout, string $classes = '' ) {
		if ( $this->get_current_lang() === $this->default_lang ) {
			return '';
		}

		$user          = wp_get_current_user();
		$allowed_roles = array( 'editor', 'administrator', 'author' );
		if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
			return '';
		}

		$classes = trim( $classes . ' edit_translation no_translate' );
		$link    = '<a class="menu-link" href="#"> Edit</a>';

		return ( 'select' === $layout )
		? '<option class="' . esc_attr( $classes ) . '">' . $link . '</option>'
		: '<li class="' . esc_attr( $classes ) . '">' . $link . '</li>';
	}

	/**
	 * Renders a language switcher item.
	 *
	 * @param array  $item language item data.
	 * @param string $variant item variant (flag-only, text-only, code-only, flag-and-text).
	 * @param string $context context where the item is rendered (menu, list, shortcode, etc).
	 * @param string $classes additional classes to add to the item.
	 * @since    1.8.1
	 */
	public function render_item( array $item, string $variant, string $context = 'link', string $classes = '' ) {
		$label_code = esc_html( $item['code'] );
		$label_name = esc_html( $item['name'] );
		$flag_url   = esc_url( $item['flag_url'] );
		$url        = esc_url( $item['url'] );
		$alt        = esc_attr( $item['alt'] );
		$classes    = trim( $classes );
		// Contenuto in base alla variante.
		switch ( $variant ) {
			case 'flag-only':
				$content = '<img src="' . $flag_url . '" alt="' . $alt . '" />';
				break;
			case 'text-only':
				$content = $label_name;
				break;
			case 'code-only':
				$content = $label_code;
				break;
			case 'flag-and-text':
			default:
				$content = '<img src="' . $flag_url . '" alt="' . $alt . '" /> <span class="lsft-label">' . $label_name . '</span>';
				break;
		}

		// Wrapping per context.
		if ( 'option' === $context ) {
			$selected = ! empty( $item['is_current'] ) ? ' selected' : '';
			// Nelle option niente immagini: scegli etichetta in base alla variante.
			$option_label = ( 'code-only' === $variant ) ? $label_code : $label_name;
			return '<option value="' . $label_code . '" data-target="' . $url . '" class="no_translate"' . $selected . '>' . $option_label . '</option>';
		}

		if ( 'toggle' === $context ) {
			$class_attr = $classes ? ' class="' . $classes . '"' : '';
			return '<a href="#" id="stylable-list-first-item" aria-expanded="false"' . $class_attr . '>' . $content . '</a>';
		}

		$class_attr = $classes ? ' class="' . $classes . '"' : '';
		return '<a href="' . $url . '"' . $class_attr . '>' . $content . '</a>';
	}

	/**
	 * Contenitori standardizzati.
	 *
	 * @param string $html The inner HTML of the list.
	 * @param string $layout The layout to use (horizontal, vertical).
	 * @param array  $opts Additional options (id, classes).
	 * @return string The rendered HTML of the list container.
	 * @since 1.8.1
	 */
	private function render_container_list( $html, $layout, $opts ) {
		$id          = isset( $opts['id'] ) ? ' id="' . $opts['id'] . '"' : '';
		$orientation = ( 'horizontal' === $layout ) ? 'cfxlsft-horizontal' : 'cfxlsft-vertical';
		$is_menu     = isset( $opts['is_menu'] ) && $opts['is_menu'];
		if ( $is_menu ) {
			return "<li class='menu-item'><ul$id class='cfxlsft-list $orientation'>$html</ul></li>";
		}
		return "<ul$id class='cfxlsft-list $orientation'>$html</ul>";
	}

	/**
	 * Renders a dropdown container.
	 *
	 * @param string $toggle_html The HTML of the toggle item.
	 * @param string $items_html The inner HTML of the dropdown items.
	 * @param array  $opts Additional options (id, classes).
	 * @return string The rendered HTML of the dropdown container.
	 * @since 1.8.1
	 */
	private function render_container_dropdown( $toggle_html, $items_html, $opts ) {
		$id      = isset( $opts['id'] ) ? ' id="' . $opts['id'] . '"' : '';
		$classes = isset( $opts['classes'] ) ? ' ' . $opts['classes'] : '';
		$is_menu = isset( $opts['is_menu'] ) && $opts['is_menu'];
		if ( $is_menu ) {
			return "<li class='menu-item'><ul$id class='stylable-list $classes'>$toggle_html<ul id='lsft-sub-menu'>$items_html</ul></ul></li>";
		}
		return "<ul$id class='stylable-list $classes'>$toggle_html<ul id='lsft-sub-menu'>$items_html</ul></ul>";
	}

	/**
	 * Renders a select container.
	 *
	 * @param string $html The inner HTML of the select.
	 * @param array  $opts Additional options (id, classes).
	 * @return string The rendered HTML of the select container.
	 * @since 1.8.1
	 */
	private function render_container_select( $html, $opts ) {
		$id       = isset( $opts['id'] ) ? ' id="' . $opts['id'] . '"' : '';
		$is_menu  = isset( $opts['is_menu'] ) && $opts['is_menu'];
		$onchange = 'window.location.href=this.options[this.selectedIndex].getAttribute("data-target")';
		if ( $is_menu ) {
			return "<li class='menu-item'><select$id class='cfxlsft_select' onchange='$onchange'>$html</select></li>";
		}
		return "<select$id class='cfxlsft_select' onchange='$onchange'>$html</select>";
	}

	/**
	 * Renders the language switcher.
	 *
	 * @param string $variant The variant to use (flag-only, text-only, code-only, flag-and-text).
	 * @param string $layout The layout to use (horizontal, vertical, dropdown, select).
	 * @param array  $opts Additional options (id, classes).
	 * @return string The rendered HTML of the language switcher.
	 * @since 1.8.1
	 */
	public function render_switcher( string $variant, string $layout, array $opts = array() ) {
		$languages = $this->get_languages();
		if ( empty( $languages ) || count( $languages ) <= 1 ) {
			return '';
		}

		$items_html   = '';
		$menu_classes = isset( $opts['classes'] ) ? $opts['classes'] : '';

		switch ( $layout ) {
			case 'select':
				foreach ( $languages as $lang ) {
					$items_html .= $this->render_item( $lang, $variant, 'option' );
				}
				$items_html .= $this->maybe_render_edit_button( 'select' );
				return $this->render_container_select( $items_html, $opts );

			case 'dropdown':
				$current = array_filter(
					$languages,
					function ( $l ) {
						return $l['is_current'];
					}
				);
				$current = ! empty( $current ) ? reset( $current ) : $languages[0];

				$toggle_html = $this->render_item( $current, $variant, 'toggle', 'menu-link' );
				foreach ( $languages as $lang ) {
					$items_html .= "<li class='no_translate $menu_classes'>" . $this->render_item( $lang, $variant, 'link', 'menu-link' ) . '</li>';
				}
				$items_html .= $this->maybe_render_edit_button( 'dropdown', $menu_classes );
				return $this->render_container_dropdown( $toggle_html, $items_html, $opts );

			default: // 'horizontal' o 'vertical'
				foreach ( $languages as $lang ) {
					$current     = $lang['is_current'] ? 'current-lang' : '';
					$items_html .= "<li class='switch_lang no_translate $menu_classes $current'>" . $this->render_item( $lang, $variant, 'link', 'menu-link' ) . '</li>';
				}
				$items_html .= $this->maybe_render_edit_button( 'list', $menu_classes );
				return $this->render_container_list( $items_html, $layout, $opts );
		}
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_horizontal_flags() {
		return $this->render_switcher( 'flag-only', 'horizontal', array( 'id' => 'sh_lsft_horizontal_flags' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_horizontal_codes() {
		return $this->render_switcher( 'code-only', 'horizontal', array( 'id' => 'sh_lsft_horizontal_codes' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_vertical_flags() {
		return $this->render_switcher( 'flag-only', 'vertical', array( 'id' => 'sh_lsft_vertical_flags' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_vertical_codes() {
		return $this->render_switcher( 'code-only', 'vertical', array( 'id' => 'sh_lsft_vertical_codes' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_custom_dropdown_flags() {
		return $this->render_switcher( 'flag-only', 'dropdown', array( 'id' => 'sh_lsft_custom_dropdown_flags' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_custom_dropdown_codes() {
		return $this->render_switcher( 'code-only', 'dropdown', array( 'id' => 'sh_lsft_custom_dropdown_codes' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_custom_dropdown_names() {
		return $this->render_switcher( 'text-only', 'dropdown', array( 'id' => 'sh_lsft_custom_dropdown_names' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_custom_dropdown_flags_names() {
		return $this->render_switcher( 'flag-and-text', 'dropdown', array( 'id' => 'sh_lsft_custom_dropdown_flags_names' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_native_dropdown_codes() {
		return $this->render_switcher( 'code-only', 'select', array( 'id' => 'sh_lsft_select_code' ) );
	}

	/**
	 * Returns the markup for the list items.
	 *
	 * @since    1.8.1
	 */
	public function shortcode_native_dropdown_text() {
		return $this->render_switcher( 'text-only', 'select', array( 'id' => 'sh_lsft_select_text' ) );
	}

	/**
	 * Adds the language switcher to the selected menu.
	 *
	 * @param string   $items The menu items HTML.
	 * @param stdClass $args  The menu arguments.
	 * @return string The modified menu items HTML.
	 * @since    1.8.1
	 */
	public function cfxlsft_add_menu_item( $items, $args ) {
		if ( ! isset( $this->options['automode'] ) || 'on' !== $this->options['automode'] ) {
			return $items;
		}

		$menu_locations = ! empty( $this->options['menu_locations'] ) ? explode( ',', $this->options['menu_locations'] ) : array( 'primary' );
		if ( ! in_array( $args->theme_location, $menu_locations, true ) ) {
			return $items;
		}

		$menu_classes  = isset( $this->options['menu_classes'] ) ? str_replace( ',', ' ', $this->options['menu_classes'] ) : '';
		$switcher_type = isset( $this->options['switcher_type'] ) ? $this->options['switcher_type'] : 'flags';

		$mapping = array(
			'flags'  => array( 'flag-only', 'horizontal' ),
			'codes'  => array( 'code-only', 'horizontal' ),
			'list'   => array( ( $this->options['custom_list_items'] ?? 'flag-only' ), 'dropdown' ),
			'select' => array(
				( isset( $this->options['custom_list_items'] ) && 'code-only' === $this->options['custom_list_items'] ) ? 'code-only' : 'text-only',
				'select',
			),
		);

		if ( isset( $mapping[ $switcher_type ] ) ) {
			list($variant, $layout) = $mapping[ $switcher_type ];
			// Per il menu non usiamo container <ul> esterni ma aggiungiamo i list item direttamente.
			$items .= $this->render_switcher(
				$variant,
				$layout,
				array(
					'id'      => 'cfxlsft-menu-' . $switcher_type,
					'classes' => $menu_classes,
					'is_menu' => true,
				),
			);
		}

		return $items;
	}

	/**
	 * Actually actyivate or deactivate the Transposh Translation Editor.
	 *
	 * @since    1.0.0
	 */
	public function cfxlsft_edit_button_action() {
		?>
	<script type="text/javascript">
		jQuery(document).ready(function($) {
		var urlParam = function(name) {
			var results = new RegExp('[\?&]' + name + '=([^&#]*)')
			.exec(window.location.search);

			return (results !== null) ? results[1] || 0 : false;
		}

		$(document).on('click', '.edit_translation', function(e) {
			e.preventDefault();
			var currentUrl = window.location.href;
			var currentOrigin = window.location.origin;
			var currentPath = window.location.pathname;
			var param = urlParam('tpedit');
			var newUrl = '';
			if (param === false) {
			newUrl = currentOrigin + currentPath + '?tpedit=1';
			$(this).attr('href', newUrl);
			} else {
			newUrl = currentOrigin + currentPath;
			$(this).attr('href', newUrl);
			}
			window.location.href = newUrl;
		})

		})
	</script>
		<?php
	}
	// [ ] rivedere css per glishortcodes non liste perché gli elementi non hanno margini che li distanzino l'uno dall'altro
	// [ ] fare in modo che l'ormai unico file css sia editabile e salvabile dall'utente, magari nel database e poi estrarlo a applicarlo inline nella pagina di frontend
	// [ ] controllare widget
	// [ ] controllare che funzioni bene con temi particolari come Divi, Elementor, WP Bakery ecc.
}
