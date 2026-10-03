<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Utils' ) ) :

	class CR_Utils {

		public static function cr_locate_template( $template_name, $template_path, $default_path ) {
			$template = locate_template(
				array(
					trailingslashit( $template_path ) . $template_name,
					$template_name,
				)
			);
			if ( ! $template ) {
				$template = $default_path . $template_name;
			}
			return apply_filters( 'cr_locate_template', $template, $template_name, $template_path, $default_path );
		}

		public static function cr_language_attributes() {
			$doctype = 'html';
			$attributes = array();

			if ( function_exists( 'is_rtl' ) && is_rtl() ) {
				$attributes[] = 'dir="rtl"';
			}

			$lang = get_bloginfo( 'language' );
			if ( $lang ) {
				if ( 'text/html' === get_option( 'html_type' ) || 'html' === $doctype ) {
					$attributes[] = 'lang="' . esc_attr( $lang ) . '"';
				}

				if ( 'text/html' !== get_option( 'html_type' ) || 'xhtml' === $doctype ) {
					$attributes[] = 'xml:lang="' . esc_attr( $lang ) . '"';
				}
			}

			$output = implode( ' ', $attributes );

			echo $output;
		}

		public static function cr_get_plugin_dir_url() {
			return plugin_dir_url( dirname( dirname( __FILE__ ) ) );
		}

		/**
		* Base URL of the CusRev API. Define CR_API_URL in wp-config.php to point it
		* at a local/dev environment
		*/
		public static function cr_api_url() {
			return defined( 'CR_API_URL' ) ? untrailingslashit( CR_API_URL ) : 'https://api.cusrev.com';
		}

	}

endif;
