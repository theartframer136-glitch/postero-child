<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Captcha' ) ) :

	class CR_Captcha {

		const TYPE_NONE       = '';
		const TYPE_TURNSTILE  = 'turnstile';
		const TYPE_HCAPTCHA   = 'hcaptcha';
		const TYPE_RECAPTCHA2 = 'recaptcha2';
		const TYPE_RECAPTCHA3 = 'recaptcha3';

		const HANDLE_RECAPTCHA = 'cr-recaptcha';
		const HANDLE_HCAPTCHA  = 'cr-hcaptcha';
		const HANDLE_TURNSTILE = 'cr-turnstile';
		const HANDLE_SCRIPT    = 'cr-captcha';

		private static $settings = null;

		public static function get_types() {
			return array(
				self::TYPE_NONE       => __( 'No captcha', 'customer-reviews-woocommerce' ),
				self::TYPE_TURNSTILE  => __( 'Cloudflare Turnstile', 'customer-reviews-woocommerce' ),
				self::TYPE_HCAPTCHA   => __( 'hCaptcha', 'customer-reviews-woocommerce' ),
				self::TYPE_RECAPTCHA2 => __( 'reCAPTCHA V2', 'customer-reviews-woocommerce' ),
				self::TYPE_RECAPTCHA3 => __( 'reCAPTCHA V3', 'customer-reviews-woocommerce' )
			);
		}

		public static function get_settings() {
			if ( null === self::$settings ) {
				$type = self::TYPE_NONE;
				$site_key = '';
				$secret_key = '';
				$form_settings = CR_Forms_Settings::get_default_form_settings();
				if (
					is_array( $form_settings ) &&
					isset( $form_settings['captcha'] ) &&
					is_array( $form_settings['captcha'] )
				) {
					$captcha = $form_settings['captcha'];
					$type = isset( $captcha['type'] ) ? strval( $captcha['type'] ) : self::TYPE_NONE;
					$site_key = isset( $captcha['site_key'] ) ? trim( strval( $captcha['site_key'] ) ) : '';
					$secret_key = isset( $captcha['secret_key'] ) ? trim( strval( $captcha['secret_key'] ) ) : '';
				} elseif ( 'yes' === get_option( 'ivole_enable_captcha', 'no' ) ) {
					$type = self::TYPE_RECAPTCHA2;
					$site_key = trim( get_option( 'ivole_captcha_site_key', '' ) );
					$secret_key = trim( get_option( 'ivole_captcha_secret_key', '' ) );
				}
				if ( ! array_key_exists( $type, self::get_types() ) ) {
					$type = self::TYPE_NONE;
				}
				self::$settings = array(
					'type'       => $type,
					'site_key'   => $site_key,
					'secret_key' => $secret_key
				);
			}
			return self::$settings;
		}

		public static function reset_settings() {
			self::$settings = null;
		}

		public static function get_type() {
			$settings = self::get_settings();
			return $settings['type'];
		}

		public static function get_site_key() {
			$settings = self::get_settings();
			return $settings['site_key'];
		}

		public static function get_secret_key() {
			$settings = self::get_settings();
			return $settings['secret_key'];
		}

		public static function is_enabled() {
			$settings = self::get_settings();
			return (
				self::TYPE_NONE !== $settings['type'] &&
				'' !== $settings['site_key'] &&
				'' !== $settings['secret_key']
			);
		}

		public static function is_invisible( $type = null ) {
			$type = ( null === $type ) ? self::get_type() : $type;
			return self::TYPE_RECAPTCHA3 === $type;
		}

		public static function get_response_field( $type = null ) {
			$type = ( null === $type ) ? self::get_type() : $type;
			switch ( $type ) {
				case self::TYPE_TURNSTILE:
					return 'cf-turnstile-response';
				case self::TYPE_HCAPTCHA:
					return 'h-captcha-response';
				case self::TYPE_RECAPTCHA2:
				case self::TYPE_RECAPTCHA3:
					return 'g-recaptcha-response';
				default:
					return '';
			}
		}

		public static function get_verify_url( $type = null ) {
			$type = ( null === $type ) ? self::get_type() : $type;
			switch ( $type ) {
				case self::TYPE_TURNSTILE:
					return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
				case self::TYPE_HCAPTCHA:
					return 'https://api.hcaptcha.com/siteverify';
				case self::TYPE_RECAPTCHA2:
				case self::TYPE_RECAPTCHA3:
					return 'https://www.google.com/recaptcha/api/siteverify';
				default:
					return '';
			}
		}

		private static function get_api_handle( $type ) {
			switch ( $type ) {
				case self::TYPE_TURNSTILE:
					return self::HANDLE_TURNSTILE;
				case self::TYPE_HCAPTCHA:
					return self::HANDLE_HCAPTCHA;
				case self::TYPE_RECAPTCHA2:
				case self::TYPE_RECAPTCHA3:
					return self::HANDLE_RECAPTCHA;
				default:
					return '';
			}
		}

		private static function get_api_url( $type, $site_key, $lang ) {
			switch ( $type ) {
				// the widgets are rendered explicitly by 'js/cr-captcha.js', but the
				// scripts below are loaded without 'render=explicit' so that automatic
				// rendering keeps working for other plugins on the same page
				// Turnstile does not accept a language in the URL of its script, so
				// 'js/cr-captcha.js' passes it in the parameters of a widget instead
				case self::TYPE_TURNSTILE:
					return 'https://challenges.cloudflare.com/turnstile/v0/api.js';
				case self::TYPE_HCAPTCHA:
					return 'https://js.hcaptcha.com/1/api.js' .
						( $lang ? '?hl=' . rawurlencode( $lang ) : '' );
				case self::TYPE_RECAPTCHA2:
					return 'https://www.google.com/recaptcha/api.js' .
						( $lang ? '?hl=' . rawurlencode( $lang ) : '' );
				case self::TYPE_RECAPTCHA3:
					return 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $site_key ) .
						( $lang ? '&hl=' . rawurlencode( $lang ) : '' );
				default:
					return '';
			}
		}

		public static function register_scripts( $lang = '' ) {
			if ( ! self::is_enabled() ) {
				return;
			}
			$settings = self::get_settings();
			$api_handle = self::get_api_handle( $settings['type'] );
			wp_register_script(
				$api_handle,
				self::get_api_url( $settings['type'], $settings['site_key'], $lang ),
				array(),
				null,
				true
			);
			wp_register_script(
				self::HANDLE_SCRIPT,
				plugins_url( 'js/cr-captcha.js', dirname( dirname( __FILE__ ) ) ),
				array( 'jquery', $api_handle ),
				Ivole::CR_VERSION,
				true
			);
			wp_localize_script(
				self::HANDLE_SCRIPT,
				'crCaptchaConfig',
				array(
					'type'          => $settings['type'],
					'siteKey'       => $settings['site_key'],
					'language'      => $lang,
					'theme'         => 'light',
					'responseField' => self::get_response_field( $settings['type'] ),
					'errorText'     => __( 'Please confirm that you are not a robot', 'customer-reviews-woocommerce' )
				)
			);
		}

		public static function enqueue_scripts() {
			if ( ! self::is_enabled() ) {
				return;
			}
			wp_enqueue_script( self::HANDLE_SCRIPT );
		}

		public static function get_widget_html() {
			if ( ! self::is_enabled() ) {
				return '';
			}
			$settings = self::get_settings();
			$attributes = ' data-crcaptcha-type="' . esc_attr( $settings['type'] ) . '"' .
				' data-sitekey="' . esc_attr( $settings['site_key'] ) . '"' .
				' data-crcaptchaid="' . esc_attr( substr( str_shuffle( md5( microtime() ) ), 0, 5 ) ) . '"';
			if ( self::is_invisible( $settings['type'] ) ) {
				return '<div class="cr-captcha cr-captcha-invisible"' . $attributes . '>' .
					'<input type="hidden" class="cr-captcha-response" name="' .
					esc_attr( self::get_response_field( $settings['type'] ) ) . '" value="" />' .
					'</div>';
			}
			return '<div class="cr-recaptcha cr-captcha cr-captcha-widget"' . $attributes . '></div>';
		}

		public static function verify( $token = null ) {
			if ( ! self::is_enabled() ) {
				return true;
			}
			$settings = self::get_settings();
			if ( null === $token || '' === $token ) {
				$token = '';
				$fields = array( 'cr_captcha_response', self::get_response_field( $settings['type'] ) );
				foreach ( $fields as $field ) {
					if ( $field && isset( $_POST[ $field ] ) && $_POST[ $field ] ) {
						$token = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
						break;
					}
				}
			}
			if ( ! $token ) {
				return false;
			}
			$body = array(
				'secret'   => $settings['secret_key'],
				'response' => $token
			);
			if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				$body['remoteip'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			}
			$response = wp_remote_post( self::get_verify_url( $settings['type'] ), array( 'body' => $body ) );
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$result = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $result ) || empty( $result['success'] ) ) {
				return false;
			}
			if ( self::TYPE_RECAPTCHA3 === $settings['type'] && isset( $result['score'] ) ) {
				$threshold = floatval( apply_filters( 'cr_captcha_score_threshold', 0.5 ) );
				if ( floatval( $result['score'] ) < $threshold ) {
					return false;
				}
			}
			return true;
		}

	}

endif;
