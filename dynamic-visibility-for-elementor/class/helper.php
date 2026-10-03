<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later
#phpcs:ignoreFile
namespace DynamicVisibilityForElementor;
use Elementor\Icons_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {
	use Plugins;
	use Filesystem;
	use Wp;
	use Meta;
	use Elementor;
	use Form;
	use Strings;
	use Image;
	use Navigation;
	use Notices;
	use Options;
	use Date;
	use Pagination;
	use I18n;
	use Validation;

	/**
	 * @var array<string>
	 */
	const ALLOWED_HTML_WRAPPER_TAGS = [
		'article',
		'aside',
		'div',
		'footer',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'header',
		'main',
		'nav',
		'ol',
		'p',
		'section',
		'span',
		'ul',
		'code',
	];

	/**
	 * @var array<string>
	 */
	const NOT_ALLOWED_USER_FIELDS = [
		'user_login',
		'login',
		'user_pass',
		'pass',
		'user_email',
		'email',
		'user_registered',
		'registered',
		'user_activation_key',
		'activation_key',
		'user_status',
		'status',
	];

	/**
	 * @return bool
	 */
	public static function current_user_is_admin() {
		if ( is_multisite() ) {
			return is_super_admin();
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param int $post_id
	 * @return bool
	 */
	public static function can_current_user_view_post( $post_id ) {
		if ( current_user_can( 'read_post', $post_id ) || is_post_publicly_viewable( $post_id ) ) {
			return true;
		}
		return 'elementor_library' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id );
	}

	public static function can_register_unsafe_controls() {
		if ( self::current_user_is_admin() ) {
			return true;
		}
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return false;
		}
		if ( ( $_REQUEST['action'] ?? '' ) === 'elementor_ajax' ) {
			return false;
		}
		return true;
	}

	public static function update_elementor_control( $widget, $control_name, $callback ) {
		$elementor = \ElementorPro\Plugin::elementor();
		$control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), $control_name );
		if ( is_wp_error( $control_data ) ) {
			return;
		}
		$control_data = $callback( $control_data );
		$widget->update_control( $control_name, $control_data );
	}

	/** Make sure the given dir is created and has protection files. */
	public static function ensure_dir( $path ) {
		if ( file_exists( $path . '/index.php' ) ) {
			return $path;
		}
		wp_mkdir_p( $path );
		$files = [
			[
				'file' => 'index.php',
				'content' => [
					'<?php',
					'// Silence is golden.',
				],
			],
			[
				'file' => '.htaccess',
				'content' => [
					'Options -Indexes',
					'<ifModule mod_headers.c>',
					'	<Files *.*>',
					'       Header set Content-Disposition attachment',
					'	</Files>',
					'</IfModule>',
				],
			],
		];
		foreach ( $files as $file ) {
			if ( ! file_exists( trailingslashit( $path ) . $file['file'] ) ) {
				$content = implode( PHP_EOL, $file['content'] );
				@ file_put_contents( trailingslashit( $path ) . $file['file'], $content );
			}
		}
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param string $key
	 * @param string $old_default
	 * @return string
	 */
	public static function get_migrated_icon( $settings, $key, $old_default ) {
		$old_key = $key;
		$new_key = 'selected_' . $key;
		$migration_allowed = Icons_Manager::is_migration_allowed();
		// old default
		if ( ! isset( $settings[ $old_key ] ) && ! $migration_allowed ) {
			$settings[ $old_key ] = $old_default;
		}
		$migrated = isset( $settings['__fa4_migrated'][ $new_key ] );
		$is_new = empty( $settings[ $old_key ] ) && $migration_allowed;
		if ( $migrated || $is_new ) {
			ob_start();
			Icons_Manager::render_icon( $settings[$new_key] ?? '', [ 'aria-hidden' => 'true' ] );
			$s = ob_get_clean();
			return $s ? $s : '';
		} else {
			$class = $settings[$old_key];
			return "<i class='$class'></i>";
		}
	}

	/**
	 * Is Condition Satisfied
	 *
	 * @param mixed $field
	 * @param string $status
	 * @param mixed $value
	 * @return boolean
	 */
	public static function is_condition_satisfied( $field, $status, $value ) {
		switch ( $status ) {
			case 'isset':
				return ! empty( $field );
			case 'not':
				return empty( $field );
			case 'lt':
				if ( is_numeric( $field ) ) {
					$field = floatval( $field );
				}
				if ( is_numeric( $value ) ) {
					$value = floatval( $value );
				}
				if ( is_array( $field ) && count( $field ) < $value ) {
					return true;
				}
				return $field < $value;
			case 'lte':
				if ( is_numeric( $field ) ) {
					$field = floatval( $field );
				}
				if ( is_numeric( $value ) ) {
					$value = floatval( $value );
				}
				if ( is_array( $field ) && count( $field ) <= $value ) {
					return true;
				}
				return $field <= $value;
			case 'gt':
				if ( is_numeric( $field ) ) {
					$field = floatval( $field );
				}
				if ( is_numeric( $value ) ) {
					$value = floatval( $value );
				}
				if ( is_array( $field ) && count( $field ) > $value ) {
					return true;
				}
				return $field > $value;
			case 'gte':
				if ( is_numeric( $field ) ) {
					$field = floatval( $field );
				}
				if ( is_numeric( $value ) ) {
					$value = floatval( $value );
				}
				if ( is_array( $field ) && count( $field ) >= $value ) {
					return true;
				}
				return $field >= $value;
			case 'contain':
				if ( is_array( $field ) && in_array( $value, $field ) ) {
					return true;
				}
				if ( is_string( $field ) && $value !== '' && strpos( $field, $value ) !== false ) {
					return true;
				}
				return false;
			case 'not_contain':
				if ( empty( $field ) ) {
					return true;
				}
				if ( is_array( $field ) && ! in_array( $value, $field ) ) {
					return true;
				}
				if ( is_string( $field ) && $value !== '' && strpos( $field, $value ) === false ) {
					return true;
				}
				return false;
			case 'starts_with':
				$field = Helper::to_readable_string( $field );
				$value = Helper::to_readable_string( $value );
				if ( $value === '' ) {
					return true;
				}
				return strpos( $field, $value ) === 0;
			case 'ends_with':
				$field = Helper::to_readable_string( $field );
				$value = Helper::to_readable_string( $value );
				if ( $value === '' ) {
					return true;
				}
				return substr( $field, -strlen( $value ) ) === $value;
			case 'in_array':
				if ( ! is_array( $value ) ) {
					$value = Helper::to_readable_string( $value );
					$value = Helper::str_to_array( ',', $value );
				}
				if ( in_array( $field, $value ) ) {
					return true;
				}
				return false;
			case 'not_in_array':
				if ( ! is_array( $value ) ) {
					$value = Helper::to_readable_string( $value );
					$value = Helper::str_to_array( ',', $value );
				}
				return ! in_array( $field, $value );
			case 'not_value':
				return $field != $value;
			case 'value':
				return $field == $value;
			case 'not_value_i':
				return strcasecmp( Helper::to_readable_string( $field ), Helper::to_readable_string( $value ) ) !== 0;
			case 'value_i':
				return strcasecmp( Helper::to_readable_string( $field ), Helper::to_readable_string( $value ) ) === 0;
		}
		return false;
	}

	/**
	 * Map form condition status to is_condition_satisfied keys
	 *
	 * Form extensions use different keys (valued, empty, equal) than
	 * is_condition_satisfied (isset, not, value). This function maps
	 * form-specific keys and optionally applies inversion.
	 *
	 * @param string $status The form condition status
	 * @param bool $invert Whether to invert the condition
	 * @return string The mapped status for is_condition_satisfied
	 */
	public static function map_form_condition_status( $status, $invert = false ) {
		$map = [
			'valued' => 'isset',
			'empty' => 'not',
			'equal' => 'value',
		];

		$mapped = $map[ $status ] ?? $status;

		if ( $invert ) {
			$invert_map = [
				'isset' => 'not',
				'not' => 'isset',
				'value' => 'not_value',
				'not_value' => 'value',
				'lt' => 'gte',
				'gte' => 'lt',
				'gt' => 'lte',
				'lte' => 'gt',
				'contain' => 'not_contain',
				'not_contain' => 'contain',
			];
			$mapped = $invert_map[ $mapped ] ?? $mapped;
		}

		return $mapped;
	}

	/**
	 * Get Client IP Address
	 *
	 * Determines the real IP address of the client, accounting for proxies,
	 * load balancers, CDNs, and other network configurations.
	 *
	 * SPDX-FileCopyrightText: 2016-2025 Elementor Team <developers@elementor.com>
	 * SPDX-License-Identifier: GPL-3.0-or-later
	 * 
	 * This function is based on ElementorPro\Core\Utils::get_client_ip()
	 * from Elementor Pro v3.29.0 and adapted for use in Dynamic Content 
	 * for Elementor on 2025-07-11 to avoid dependency.
	 *
	 * @return string The client's IP address
	 */
	public static function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Trusted reverse-proxy IPs. Forwarded headers (X-Forwarded-For, etc.) are
		 * read only when the request comes from one of these proxies; empty by default.
		 * Behind a CDN/proxy, add your edge IPs:
		 *   add_filter( 'dce/visibility/trusted_proxies', fn() => [ '10.0.0.1' ] );
		 *
		 * @param string[] $trusted_proxies
		 */
		$trusted_proxies = (array) apply_filters( 'dce/visibility/trusted_proxies', [] );

		if ( $trusted_proxies && in_array( $remote_addr, $trusted_proxies, true ) ) {
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$chain = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
				for ( $i = count( $chain ) - 1; $i >= 0; $i-- ) {
					if ( filter_var( $chain[ $i ], FILTER_VALIDATE_IP ) && ! in_array( $chain[ $i ], $trusted_proxies, true ) ) {
						return $chain[ $i ];
					}
				}
			}
			foreach ( [ 'HTTP_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED' ] as $key ) {
				if ( ! empty( $_SERVER[ $key ] ) ) {
					$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
					if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
						return $value;
					}
				}
			}
		}

		if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return $remote_addr;
		}

		// Fallback local ip.
		return '127.0.0.1';
	}
}
