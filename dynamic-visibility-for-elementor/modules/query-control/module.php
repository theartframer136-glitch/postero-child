<?php

// SPDX-FileCopyrightText: 2018-2026 Ovation S.r.l. <help@dynamic.ooo>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace DynamicVisibilityForElementor\Modules\QueryControl;

use Elementor\Core\Editor\Editor;
use DynamicVisibilityForElementor\Helper;
use Elementor\Core\Base\Module as Base_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Module extends Base_Module {

	public function __construct() {
		$this->add_actions();
	}

	/**
	 * @return string
	 */
	public function get_name() {
		return 'dce-query-control';
	}

	/**
	 * @return void
	 */
	protected function add_actions() {
		add_action( 'elementor/ajax/register_actions', [ $this, 'register_ajax_actions' ] );
	}

	public function ajax_call_filter_autocomplete( array $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		if ( empty( $data['query_type'] ) || empty( $data['q'] ) ) {
			throw new \Exception( 'Bad Request' );
		}

		$query_type = sanitize_key( $data['query_type'] );

		switch ( $query_type ) {
			case 'acf':
				$results = $this->get_acf( $data );
				break;
			case 'acfposts':
				$results = $this->get_acfposts( $data );
				break;
			case 'acf_groups':
				$results = $this->get_acf_groups( $data );
				break;
			case 'acf_flexible_content_layouts':
				$results = $this->get_acf_flexible_content_layouts( $data );
				break;
			case 'capabilities':
				$results = $this->get_capabilities( $data );
				break;
			case 'cryptocurrency_coins':
				$results = $this->get_cryptocurrency_coins( $data );
				break;
			case 'cryptocurrency_convert':
				$results = $this->get_cryptocurrency_convert( $data );
				break;
			case 'dsh_fields':
				$results = $this->get_dsh_fields( $data );
				break;
			case 'fields':
				$results = $this->get_fields( $data );
				break;
			case 'jet':
				$results = $this->get_jet( $data );
				break;
			case 'metas':
				$results = $this->get_metas( $data );
				break;
			case 'metabox':
				$results = $this->get_metabox( $data );
				break;
			case 'metabox_relationship':
				$results = $this->get_metabox_relationship( $data );
				break;
			case 'options':
				$results = $this->get_options( $data );
				break;
			case 'pods':
				$results = $this->get_pods( $data );
				break;
			case 'posts':
				$results = $this->get_posts( $data );
				break;
			case 'search_and_filter_v3_query_ids':
				$results = $this->get_search_and_filter_v3_query_ids( $data );
				break;
			case 'taxonomies_fields':
				$results = $this->get_taxonomies_fields( $data );
				break;
			case 'terms':
				$results = $this->get_terms( $data );
				break;
			case 'terms_fields':
				$results = $this->get_terms_fields( $data );
				break;
			case 'users':
				$results = $this->get_users( $data );
				break;
			default:
				throw new \Exception( 'Invalid query type.' );
		}

		return [
			'results' => $results,
		];
	}

	/**
	 * Get all Search and Filter v3 query ids
	 *
	 * @param array<mixed> $data
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_search_and_filter_v3_query_ids( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$queries = \Search_Filter\Queries::find(
			[
				'status' => 'enabled',
				'number' => 0,
				'meta_query' => [
					[
						'key' => 'query_integration',
						'value' => 'dynamicooo/dynamic-visibility-for-elementor',
						'compare' => '=',
					],
				],
			],
			'records'
		);

		if ( ! empty( $data['q'] ) ) {
			$queries = array_filter($queries, function ( $query ) use ( $data ) {
				return stripos( $query->name, $data['q'] ) !== false;
			});
		}

		$result = [];
		foreach ( $queries as $query ) {
			$result[] = [
				'id' => $query->id,
				'text' => esc_attr( $query->name ),
			];
		}
		return $result;
	}

	/**
	 * Get all options from the database and filter them by the like parameter if provided
	 *
	 * @param array<mixed> $data
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_options( $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new \Exception( 'Access denied.' );
		}

		// Get all options and filter them
		$option_keys = array_keys( wp_load_alloptions() );
		$options = array_combine( $option_keys, $option_keys );

		// Filter options if search query is provided
		if ( ! empty( $data['q'] ) ) {
			$options = array_filter($options, function ( $key ) use ( $data ) {
				return stripos( $key, $data['q'] ) !== false;
			}, ARRAY_FILTER_USE_KEY);
		}

		return array_map(function ( $key ) {
			return [
				'id' => $key,
				'text' => esc_attr( $key ),
			];
		}, array_keys( $options ));
	}

	protected function get_fields( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		$object_types = $data['object_type'];
		if ( ! is_array( $object_types ) ) {
			$object_types = array( $object_types );
		}

		foreach ( $object_types as $object_type ) {
			switch ( $object_type ) {
				case 'post':
					$fields = Helper::get_post_fields( $data['q'] );
					break;
				case 'user':
					$fields = Helper::get_user_fields( $data['q'] );
					break;
				case 'term':
					$fields = Helper::get_term_fields( $data['q'] );
					break;
				default:
					// The method may not exist when get_fields is called by get_dsh_fields
					continue 2;
			}
			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field_key => $field_name ) {
					$results[] = [
						'id' => $field_key,
						'text' => ( $data['object_type'] == 'any' ? '[' . esc_attr( $object_type ) . '] ' : '' ) . esc_attr( $field_name ),
					];
				}
			}
		}
		return $results;
	}

	/**
	 * @param array<mixed> $data
	 * @return array<int|string,mixed>
	 */
	protected function get_dsh_fields( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];

		$object_type = $data['object_type'];

		$library_fields = \DynamicShortcodes\Plugin::instance()->library_manager->get_fields(
			[
				'type' => $object_type,
				'format' => 'list',
			]
		);

		$searched_value = $data['q'] ?? '';

		$library_fields_filtered = array_filter( $library_fields, function ( $field ) use ( $searched_value ) {
			return stripos( $field, $searched_value ) === 0;
		});

		$results = static::format_as_result( $library_fields_filtered );

		// The functions from the library retrieve data only for the current post, whereas the functions on Dynamic Content for Elementor retrieve it for all posts.
		// Here we define calls to retrieve all fields
		$types = [
			'acf' =>
				[
					'function' => 'get_acf',
					'data' => [
						'object_type' => [],
						'q' => $searched_value,
					],
				],
			'author' =>
				[
					'function' => 'get_fields',
					'data' => [
						'object_type' => 'user',
						'q' => $searched_value,
					],
				],
			'cookie' =>
				[
					'function' => false, // Not applicable as we already retrieve them with Dynamic Shortcodes Library
				],
			'jet' =>
				[
					'function' => 'get_jet',
					'data' => [
						'object_type' => [],
						'q' => $searched_value,
					],
				],
			'metabox' =>
				[
					'function' => 'get_metabox',
					'data' => [
						'object_type' => [],
						'q' => $searched_value,
					],
				],
			'option' =>
				[
					'function' => false, // Not applicable as we already retrieve them with Dynamic Shortcodes Library
				],
			'pods' =>
				[
					'function' => false,
					// TODO: Set function to retrieve Pods fields for all posts
				],
			'post' => [
				'function' => 'get_fields',
				'data' => [
					'object_type' => 'post',
					'q' => $searched_value,
				],
			],
			'term' =>
				[
					'function' => 'get_fields',
					'data' => [
						'object_type' => 'term',
						'q' => $searched_value,
					],
				],
			'toolset' =>
				[
					'function' => false,
					// TODO: Set function to retrieve Toolset fields for all posts
				],
			'user' =>
				[
					'function' => 'get_fields',
					'data' => [
						'object_type' => 'user',
						'q' => $searched_value,
					],
				],
			'woo' => [
				'function' => false,
			],
		];

		if ( ! empty( $types[ $object_type ]['function'] ) ) {
			switch ( $object_type ) {
				case 'acf':
					$other_results = $this->get_acf( $types[ $object_type ]['data'] );
					break;
				case 'author':
				case 'post':
				case 'term':
				case 'user':
					$other_results = $this->get_fields( $types[ $object_type ]['data'] );
					break;
				case 'jet':
					$other_results = $this->get_jet( $types[ $object_type ]['data'] );
					break;
				case 'metabox':
					$other_results = $this->get_metabox( $types[ $object_type ]['data'] );
					break;
				default:
					$other_results = [];
					break;
			}
			$other_results_filtered = array_filter( $other_results, function ( $item ) use ( $object_type ) {
				return \DynamicShortcodes\Plugin::instance()->library_manager->is_not_hidden_field(
					[
						'type' => $object_type,
					],
					$item['id']
				);
			});

			if ( ! empty( $other_results_filtered ) ) {
				$results += array_values( $other_results_filtered );
			}
		}

		return $results;
	}

	/**
	 * @param array<string> $fields
	 * @return array<int|string,mixed>
	 */
	protected static function format_as_result( $fields ) {
		$results = [];
		foreach ( $fields as $field ) {
			$results[] = [
				'id' => $field,
				'text' => $field,
			];
		}
		return $results;
	}

	protected function get_terms_fields( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		$results = $this->get_fields( $data );
		$terms = Helper::get_taxonomy_terms( null, true, $data['q'] );
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $field_key => $field_name ) {
				$term = Helper::get_term_by( 'id', $field_key );
				$field_key = 'term_' . $term->slug;
				$results[] = [
					'id' => $field_key,
					'text' => ( $data['object_type'] == 'any' ? '[taxonomy_term] ' : '' ) . esc_attr( $field_name ),
				];
			}
		}
		return $results;
	}

	protected function get_taxonomies_fields( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		$results = $this->get_fields( $data );
		$taxonomies = Helper::get_taxonomies( false, null, $data['q'] );
		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $field_key => $field_name ) {
				if ( $field_key ) {
					$field_key = 'taxonomy_' . $field_key;
					$results[] = [
						'id' => $field_key,
						'text' => '[taxonomy] ' . esc_attr( $field_name ),
					];
				}
			}
		}
		return $results;
	}

	protected function get_metas( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		switch ( $data['object_type'] ) {
			case 'post':
				$fields = Helper::get_post_metas( false, $data['q'] );
				break;
			case 'user':
				$fields = Helper::get_user_metas( false, $data['q'] );
				break;
			case 'term':
				$fields = Helper::get_term_metas( false, $data['q'] );
				break;
			default:
				throw new \Exception( 'Invalid object type.' );
		}
		foreach ( $fields as $field_key => $field_name ) {
			if ( $field_key ) {
				$results[] = [
					'id' => $field_key,
					'text' => esc_attr( $field_name ),
				];
			}
		}
		return $results;
	}

	protected function get_pods( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		switch ( $data['object_type'] ) {
			case 'relationship':
				$fields = Helper::get_relationship_pods( false, $data['q'] );
				break;
			default:
				throw new \Exception( 'Invalid object type.' );
		}
		foreach ( $fields as $field_key => $field_name ) {
			if ( $field_key ) {
				$results[] = [
					'id' => $field_key,
					'text' => esc_attr( $field_name ),
				];
			}
		}
		return $results;
	}

	protected function get_posts( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		$object_type = $data['object_type'] ?? 'any';

		$query_params = [
			'post_type' => $object_type,
			's' => $data['q'],
			'posts_per_page' => -1,
		];
		if ( 'attachment' === $query_params['post_type'] ) {
			$query_params['post_status'] = 'inherit';
		}
		$query = new \WP_Query( $query_params );
		foreach ( $query->posts as $post ) {
			$post_title = $post->post_title;
			if ( empty( $data['object_type'] ) || $object_type == 'any' ) {
				$post_title = '[' . $post->ID . '] ' . $post_title . ' (' . $post->post_type . ')';
			}
			if ( ! empty( $data['object_type'] ) && $object_type == 'elementor_library' ) {
				$etype = get_post_meta( $post->ID, '_elementor_template_type', true );
				$post_title = '[' . $post->ID . '] ' . $post_title . ' (' . $post->post_type . ' > ' . $etype . ')';
			}

			$results[] = [
				'id' => $post->ID,
				'text' => esc_html( $post_title ),
			];
		}

		return $results;
	}

	protected function get_jet( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		if ( ! Helper::is_plugin_active( 'jet-engine' ) ) {
			return [];
		}

		$results = [];

		if ( 'relations' === ( $data['object_type'] ?? false ) ) {
			// Retrieve all JetEngine Relations
			$relations = jet_engine()->relations->get_relations_for_js();
			foreach ( $relations as $relation ) {
				if ( strlen( $data['q'] ) > 2 ) {
					if ( strpos( $relation['label'], $data['q'] ) === false ) {
						continue;
					}
				}
				$results[] = [
					'id' => $relation['value'],
					'text' => esc_attr( $relation['label'] ),
				];
			}
		} else {
			// Retrieve all JetEngine Fields, grouped by CPT
			$jet_fields_by_cpt = jet_engine()->meta_boxes->get_fields_for_context( 'post_type' );

			foreach ( $jet_fields_by_cpt as $cpt ) {
				foreach ( $cpt as $field ) {
					if ( strlen( $data['q'] ) > 2 ) {
						if ( strpos( $field['name'], $data['q'] ) === false
							&& strpos( $field['title'], $data['q'] ) === false ) {
							continue;
						}
					}
					$results[] = [
						'id' => $field['name'],
						'text' => esc_attr( $field['title'] ),
					];
				}
			}
		}

		return $results;
	}

	/**
	 * Get Meta Box Fields
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_metabox( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		if ( ! Helper::is_plugin_active( 'metabox' ) ) {
			return [];
		}

		$results = [];
		$found_metabox = [];
		$types = ( ! empty( $data['object_type'] ) ) ? (array) $data['object_type'] : [];

		// Retrieve all Meta Box Fields
		$all_fields = rwmb_get_registry( 'meta_box' )->all();

		// Filter by Types
		if ( ! empty( $types ) ) {
			foreach ( $all_fields as $id_meta => $value ) {
				foreach ( $value->fields as $key => $value ) {
					if ( in_array( $value['type'] ?? '', $types, true ) ) {
						$found_metabox[] = $value;
					}
				}
			}
		}

		foreach ( $found_metabox as $key => $field ) {
			$name = strtolower( $field['name'] ?? '' );
			if ( strlen( $data['q'] ) > 2 ) {
				if ( strpos( (string) $key, $data['q'] ) === false && strpos( (string) $name, $data['q'] ) === false ) {
					continue;
				}
			}
			$results[] = [
				'id' => $field['id'],
				'text' => esc_attr( $name ),
			];
		}

		return $results;
	}

	/**
	 * Get Meta Box Relationships
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_metabox_relationship( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		if ( ! Helper::is_plugin_active( 'metabox' ) ) {
			return [];
		}

		$results = [];
		$relationships = \MB_Relationships_API::get_all_relationships();

		foreach ( $relationships as $key => $relationship ) {
			$title = \MB_Relationships_API::get_relationship_settings( $key )['menu_title'] ?? '';
			if ( strlen( $data['q'] ) > 2 ) {
				if ( strpos( $key, $data['q'] ) === false && strpos( $title, $data['q'] ) === false ) {
					continue;
				}
			}
			$results[] = [
				'id' => $key,
				'text' => esc_attr( $title ),
			];
		}

		return $results;
	}

	/**
	 * Retrieves all ACF fields recursively and filters them by the search term if provided.
	 *
	 * @param array<string,mixed> $data Array of request data. Expects 'q' as the search term.
	 * @return array<int,array<string,string>> Returns an array of items ready for Select2 in the format:
	 *                                         [ ['id' => 'field_key', 'text' => 'Field Label'], ... ]
	 * @throws \Exception If the current user lacks the required capability.
	 */
	protected function get_acf( $data ) {
		if ( ! current_user_can( \Elementor\Core\Editor\Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$search = $data['q'] ?? '';
		$results_assoc = [];

		/** @var array<int,array<string,mixed>> $field_groups */
		$field_groups = acf_get_field_groups();

		// Recursively collect all ACF fields from each group
		foreach ( $field_groups as $group ) {
			/** @var array<string,mixed>|false|null $fields */
			$fields = acf_get_fields( $group );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					$this->collect_acf_fields_recursively( $field, '', $results_assoc );
				}
			}
		}

		// Filter results if a search term is provided
		if ( ! empty( $search ) ) {
			$results_assoc = array_filter(
				$results_assoc,
				function ( $field_label, $field_key ) use ( $search ) {
					return (
						stripos( $field_key, $search ) !== false
						|| stripos( $field_label, $search ) !== false
					);
				},
				ARRAY_FILTER_USE_BOTH
			);
		}

		$return = [];
		foreach ( $results_assoc as $field_key => $field_label ) {
			$return[] = [
				'id'   => $field_key,
				'text' => esc_attr( $field_label ),
			];
		}

		return $return;
	}

	/**
	* Collects ACF fields recursively, building concatenated keys for group sub-fields.
	*
	* When encountering a group field, the current prefix is concatenated with an underscore.
	* When encountering a repeater or flexible content field, the prefix is reset (i.e. set to an empty string)
	* so that sub-fields inside these fields do not inherit the parent's prefix.
	*
	* @param array<string,mixed> $field   The ACF field definition.
	* @param string              $prefix  The current prefix used for nested fields.
	* @param array<string,string> &$results Reference array mapping 'field_key' => 'Field Label'.
	* @return void
	*/
	protected function collect_acf_fields_recursively( $field, $prefix, &$results ) {
		// Retrieve field name and type
		$field_name = isset( $field['name'] ) ? (string) $field['name'] : '';
		$field_type = isset( $field['type'] ) ? $field['type'] : '';

		// Handle group fields: do not add the group itself, but use its name as prefix for its children.
		if ( 'group' === $field_type ) {
			$new_prefix = $prefix ? $prefix . '_' . $field_name : $field_name;
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				foreach ( $field['sub_fields'] as $sub_field ) {
					$this->collect_acf_fields_recursively( $sub_field, $new_prefix, $results );
				}
			}
			return; // Do not add the group field itself.
		}

		// Handle repeater and flexible content fields:
		// For these, add the repeater field itself (with any existing prefix) but then reset the prefix for its children.
		if ( 'repeater' === $field_type || 'flexible_content' === $field_type ) {
			$current_key = $prefix ? $prefix . '_' . $field_name : $field_name;
			// Add the repeater field itself.
			$results[ $current_key ] = isset( $field['label'] ) && ! empty( $field['label'] ) ? (string) $field['label'] : $field_name;
			// Reset the prefix for sub-fields inside the repeater.
			$new_prefix = '';
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				foreach ( $field['sub_fields'] as $sub_field ) {
					$this->collect_acf_fields_recursively( $sub_field, $new_prefix, $results );
				}
			}
			// Additionally, handle flexible content layouts.
			if ( 'flexible_content' === $field_type && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
						foreach ( $layout['sub_fields'] as $sub_field ) {
							$this->collect_acf_fields_recursively( $sub_field, $new_prefix, $results );
						}
					}
				}
			}
			return;
		}

		// For normal fields, build the key using the current prefix (if any).
		$current_key = $prefix ? $prefix . '_' . $field_name : $field_name;
		// Use the field label if available; otherwise, fallback to the field name.
		$label = isset( $field['label'] ) && ! empty( $field['label'] ) ? (string) $field['label'] : $field_name;
		$results[ $current_key ] = $label;
	}


	/**
	 * Get ACF Field Groups
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_acf_groups( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$groups = acf_get_field_groups();
		$results = [];
		foreach ( $groups as $group ) {
			$results[] = [
				'id' => $group['key'],
				'text' => esc_attr( $group['title'] ),
			];
		}
		return $results;
	}

	protected function get_acf_flexible_content_layouts( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$groups = acf_get_field_groups();
		$layouts = [];
		foreach ( $groups as $group ) {
			$group_fields = acf_get_fields( $group );
			foreach ( $group_fields as $fields ) {
				if ( $fields['type'] == 'flexible_content' ) {
					foreach ( $fields as $field_key => $field_value ) {
						if ( is_array( $field_value ) && self::array_key_matches_regex( '/layout_[a-zA-Z0-9]+/', $field_value ) ) {
							foreach ( $field_value as $layout_single ) {
								$layouts[] = [
									'id' => $layout_single['name'],
									'text' => esc_attr( $layout_single['name'] ),
								];
							}
						}
					}
				}
			}
		}
		return $layouts;
	}

	protected function get_acfposts( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$data['object_type'] = array( 'text', 'textarea', 'select', 'number', 'date_time_picker', 'date_picker', 'oembed', 'file', 'url', 'image', 'wysiwyg' );
		$results = $this->get_acf( $data );
		$results[] = array(
			'id' => 'title',
			'text' => esc_html__( 'Core > Title [post_title] (text)', 'dynamic-visibility-for-elementor' ),
		);
		$results[] = array(
			'id' => 'content',
			'text' => esc_html__( 'Core > Content [post_content] (text)', 'dynamic-visibility-for-elementor' ),
		);
		$results[] = array(
			'id' => 'taxonomy',
			'text' => esc_html__( 'Core > Taxonomy MetaData (taxonomies)', 'dynamic-visibility-for-elementor' ),
		);
		$results[] = array(
			'id' => 'date',
			'text' => esc_html__( 'Core > Date [post_date] (datetime)', 'dynamic-visibility-for-elementor' ),
		);
		return $results;
	}

	protected function array_key_matches_regex( $regex, $array ) {
		foreach ( $array as $key => $value ) {
			if ( preg_match( $regex, $key ) ) {
				return $key;
			}
		}
		return false;
	}

	protected function get_terms( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		$taxonomies = ( ! empty( $data['object_type'] ) ) ? $data['object_type'] : get_object_taxonomies( '' );
		$query_params = [
			'taxonomy' => $taxonomies,
			'search' => $data['q'],
			'hide_empty' => false,
		];
		$terms = get_terms( $query_params );
		foreach ( $terms as $term ) {
			$term_name = $term->name;
			if ( empty( $data['object_type'] ) ) {
				$taxonomy = get_taxonomy( $term->taxonomy );
				$term_name = $taxonomy->labels->singular_name . ': ' . $term_name;
			}
			$results[] = [
				'id' => $term->term_id,
				'text' => esc_attr( $term_name ),
			];
		}
		return $results;
	}

	/**
	 * Get all user capabilities from all roles
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,array<string,string>>
	 */
	protected function get_capabilities( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$all_capabilities = [];
		foreach ( wp_roles()->roles as $role ) {
			foreach ( array_keys( $role['capabilities'] ?? [] ) as $cap ) {
				$all_capabilities[ $cap ] = $cap;
			}
		}

		// Filter by search term
		if ( ! empty( $data['q'] ) ) {
			$all_capabilities = array_filter( $all_capabilities, function ( $cap ) use ( $data ) {
				return stripos( $cap, $data['q'] ) !== false;
			});
		}

		// Sort alphabetically
		ksort( $all_capabilities );

		return array_map(
			function ( $cap ) {
				return [
					'id' => $cap,
					'text' => esc_attr( $cap ),
				];
			},
			array_keys( $all_capabilities )
		);
	}

	/**
	 * Get Users and filter by search term
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_users( $data ) {
		if ( ! current_user_can( 'list_users' ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$query_params = [
			'fields' => [ 'ID', 'display_name' ],
		];

		if ( ! empty( $data['q'] ) ) {
			$query_params['search'] = '*' . $data['q'] . '*';
			$query_params['search_columns'] = [ 'user_login', 'user_nicename', 'display_name' ];
		}

		return array_map(
			function ( $user ) {
				return [
					'id' => $user->ID,
					'text' => esc_attr( $user->display_name ),
				];
			},
			get_users( $query_params )
		);
	}

	/**
	 * Get cryptocurrency coins with search functionality
	 *
	 * @param array<mixed> $data
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_cryptocurrency_coins( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$crypto = \DynamicVisibilityForElementor\Plugin::instance()->cryptocurrency;

		// Return empty if no API key (controlled at widget/dynamic tag level)
		if ( ! $crypto->has_api_key() ) {
			return [];
		}

		try {
			$all_coins = $crypto->get_available_coins();
		} catch ( \DynamicVisibilityForElementor\CryptocurrencyApiError $e ) {
			// Return empty if API fails
			return [];
		}

		$results = [];
		$search_term = strtolower( $data['q'] );

		foreach ( $all_coins as $coin ) {
			$name = $coin['name'] ?? '';
			$symbol = $coin['symbol'] ?? '';
			$id = $coin['id'] ?? '';

			// Search in name and symbol
			if ( stripos( $name, $search_term ) !== false || stripos( $symbol, $search_term ) !== false ) {
				$results[] = [
					'id' => $id,
					'text' => esc_attr( $name . ' (' . $symbol . ')' ),
				];
			}

			// Limit results to prevent performance issues
			if ( count( $results ) >= 50 ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Get cryptocurrency conversion options with search functionality
	 *
	 * @param array<mixed> $data
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_cryptocurrency_convert( $data ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$crypto = \DynamicVisibilityForElementor\Plugin::instance()->cryptocurrency;

		// Return empty if no API key (controlled at widget/dynamic tag level)
		if ( ! $crypto->has_api_key() ) {
			return [];
		}

		try {
			$all_fiat = $crypto->get_fiat();
			$all_coins = $crypto->get_available_coins();
		} catch ( \DynamicVisibilityForElementor\CryptocurrencyApiError $e ) {
			// Return empty if API fails
			return [];
		}

		$results = [];
		$search_term = strtolower( $data['q'] );

		// Search in fiat currencies
		foreach ( $all_fiat as $fiat ) {
			$name = $fiat['name'] ?? '';
			$symbol = $fiat['symbol'] ?? '';
			$id = $fiat['id'] ?? '';

			if ( stripos( $name, $search_term ) !== false || stripos( $symbol, $search_term ) !== false ) {
				$results[] = [
					'id' => $id,
					'text' => esc_attr( $name . ' (' . $symbol . ')' ),
				];
			}
		}

		// Search in cryptocurrencies
		foreach ( $all_coins as $coin ) {
			$name = $coin['name'] ?? '';
			$symbol = $coin['symbol'] ?? '';
			$id = $coin['id'] ?? '';

			if ( stripos( $name, $search_term ) !== false || stripos( $symbol, $search_term ) !== false ) {
				$results[] = [
					'id' => $id,
					'text' => esc_attr( $name . ' (' . $symbol . ')' ),
				];
			}

			// Limit results to prevent performance issues
			if ( count( $results ) >= 50 ) {
				break;
			}
		}

		return $results;
	}





	/**
	 * Calls function to get value titles depending on ajax query type
	 *
	 * @param array<string,mixed> $request
	 * @return array<int|string,mixed>
	 * @throws \Exception If query type is invalid or user lacks permissions
	 */
	public function ajax_call_control_value_titles( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		// Check if query_type exists and is a string
		if ( empty( $request['query_type'] ) || ! is_string( $request['query_type'] ) ) {
			throw new \Exception( 'Invalid query type.' );
		}

		$query_type = sanitize_key( $request['query_type'] );

		switch ( $query_type ) {
			case 'acf':
				return $this->get_value_titles_for_acf( $request );
			case 'acf_flexible_content_layouts':
				return $this->get_value_titles_for_acf_flexible_content_layouts( $request );
			case 'acfposts':
				return $this->get_value_titles_for_acfposts( $request );
			case 'capabilities':
				return $this->get_value_titles_for_capabilities( $request );
			case 'cryptocurrency_coins':
				return $this->get_value_titles_for_cryptocurrency_coins( $request );
			case 'cryptocurrency_convert':
				return $this->get_value_titles_for_cryptocurrency_convert( $request );
			case 'dsh_fields':
				return $this->get_value_titles_for_dsh_fields( $request );
			case 'fields':
				return $this->get_value_titles_for_fields( $request );
			case 'metas':
				return $this->get_value_titles_for_metas( $request );
			case 'posts':
				return $this->get_value_titles_for_posts( $request );
			case 'search_and_filter_v3_query_ids':
				return $this->get_value_titles_for_search_and_filter_v3_query_ids( $request );
			case 'taxonomies':
				return $this->get_value_titles_for_taxonomies( $request );
			case 'taxonomies_fields':
				return $this->get_value_titles_for_taxonomies_fields( $request );
			case 'terms':
				return $this->get_value_titles_for_terms( $request );
			case 'terms_fields':
				return $this->get_value_titles_for_terms_fields( $request );
			case 'users':
				return $this->get_value_titles_for_users( $request );
			default:
				throw new \Exception( 'Invalid query type.' );
		}
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<int|string,mixed>
	 */
	protected function get_value_titles_for_search_and_filter_v3_query_ids( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		if ( empty( $ids ) ) {
			return [];
		}

		$results = [];

		foreach ( $ids as $id ) {
			$query = \Search_Filter\Queries\Query::find([
				'id' => $id,
			]);
			$results[ $id ] = esc_attr( $query->get_name() ?? '' );
		}

		return $results;
	}

	/**
	 * Retrieves the ACF label for given field keys.
	 * If acf_get_field() doesn't find a valid field (or label is empty),
	 * it falls back to returning the original key as the label.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,string>
	 * @throws \Exception If user lacks permissions
	 */
	protected function get_value_titles_for_acf( $request ) {
		if ( ! current_user_can( \Elementor\Core\Editor\Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		if ( empty( $ids ) ) {
			return [];
		}

		$results = [];
		foreach ( $ids as $field_key ) {
			$field = acf_get_field( $field_key );

			if ( $field && ! empty( $field['label'] ) ) {
				// Field found, label is set
				$results[ $field_key ] = $field['label'];
			} else {
				// Field not found or label is empty, fallback to the original field key
				$results[ $field_key ] = $field_key;
			}
		}

		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_acf_flexible_content_layouts( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];
		foreach ( $ids as $aid ) {
			$results[ $aid ] = $aid;
		}
		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_acfposts( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = $this->get_value_titles_for_acf( $request );

		$core['title'] = esc_html__( 'Title', 'dynamic-visibility-for-elementor' );
		$core['content'] = esc_html__( 'Content', 'dynamic-visibility-for-elementor' );
		$core['taxonomy'] = esc_html__( 'Taxonomy MetaData', 'dynamic-visibility-for-elementor' );
		$core['date'] = esc_html__( 'Date', 'dynamic-visibility-for-elementor' );

		foreach ( $ids as $aid ) {
			if ( isset( $core[ $aid ] ) ) {
				$results[ $aid ] = $core[ $aid ];
			}
		}
		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_metas( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];

		switch ( $request['object_type'] ) {
			case 'post':
				$fields = Helper::get_post_metas( false, $ids[0] );
				break;
			case 'user':
				$fields = Helper::get_user_metas( false, $ids[0] );
				break;
			case 'term':
				$fields = Helper::get_term_metas( false, $ids[0] );
				break;
			default:
				return $results;
		}

		foreach ( $ids as $aid ) {
			foreach ( $fields as $field_key => $field_name ) {
				if ( in_array( $field_key, $ids ) ) {
					$results[ $field_key ] = $field_name;
				}
			}
		}

		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_fields( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];
		if ( $request['object_type'] == 'any' ) {
			$object_types = array( 'post', 'user', 'term' );
		} else {
			$object_types = array( $request['object_type'] );
		}

		foreach ( $object_types as $object_type ) {
			foreach ( $ids as $id ) {
				// Returns a value equal to the key
				$results[ $id ] = $id;
			}
		}
		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_dsh_fields( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		return $this->get_value_titles_for_fields( $request );
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<int|string,mixed>
	 */
	protected function get_value_titles_for_posts( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];
		$is_ctp = false;
		if ( ! empty( $ids ) ) {
			$first = reset( $ids );
			$is_ctp = ! is_numeric( $first );
		}
		if ( $is_ctp ) {
			$post_types = Helper::get_public_post_types();
			if ( ! empty( $ids ) ) {
				foreach ( $ids as $aid ) {
					if ( isset( $post_types[ $aid ] ) ) {
						$results[ $aid ] = $post_types[ $aid ];
					}
				}
			}
		} else {
			foreach ( $ids as $id ) {
				if ( ! current_user_can( 'read_post', $id ) ) {
					continue;
				}
				$results[ $id ] = '[' . $id . '] ' . wp_kses_post( get_the_title( $id ) );
			}
		}
		return $results;
	}

	/**
	 * Get term titles based on term IDs or slugs
	 *
	 * @param array<string,mixed> $request Request data containing term identifiers
	 * @return array<int|string,mixed> Array of term IDs/names pairs
	 */
	protected function get_value_titles_for_terms( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$id = $request['id'];

		$ids = (array) $id;
		$results = [];

		foreach ( $ids as $term_id ) {
			if ( is_numeric( $term_id ) ) {
				// Search by numeric ID
				$term = get_term( (int) $term_id );
				if ( $term instanceof \WP_Term ) {
					$results[ $term->term_id ] = sanitize_text_field( $term->name );
				}
			} else {
				// Search by slug
				$terms = get_terms([
					'slug' => sanitize_text_field( $term_id ),
					'hide_empty' => false,
				]);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$results[ $term->term_id ] = sanitize_text_field( $term->name );
					}
				}
			}
		}

		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_taxonomies( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];
		foreach ( $ids as $value ) {
			$taxonomies = Helper::get_taxonomies( false, null, $value );
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $field_key => $field_name ) {
					if ( $field_key ) {
						$results[ $field_key ] = $field_name;
					}
				}
			}
		}
		return $results;
	}

	/**
	 * Get capability titles by IDs
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,string>
	 */
	protected function get_value_titles_for_capabilities( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$results = [];

		foreach ( $ids as $cap ) {
			$results[ $cap ] = esc_attr( $cap );
		}

		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<int|string,mixed>
	 */
	protected function get_value_titles_for_users( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$results = [];
		foreach ( (array) $request['id'] as $user_id ) {
			if ( $user = get_userdata( $user_id ) ) {
				$results[ $user_id ] = esc_attr( $user->display_name );
			}
		}

		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_terms_fields( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$ids_post = array();
		$ids_term = array();
		foreach ( $ids as $aid ) {
			if ( substr( $aid, 0, 5 ) == 'term_' ) {
				$ids_term[] = substr( $aid, 5 );
			} else {
				$ids_post[] = $aid;
			}
		}
		$results = [];
		if ( ! empty( $ids_post ) ) {
			$request['id'] = $ids_post;
			$posts = $this->get_value_titles_for_fields( $request );
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $key => $value ) {
					$results[ $key ] = $value;
				}
			}
		}
		if ( ! empty( $ids_term ) ) {
			$request['id'] = $ids_term;
			$terms = $this->get_value_titles_for_terms( $request );
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $key => $value ) {
					$results[ 'term_' . $key ] = $value;
				}
			}
		}
		return $results;
	}

	/**
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_taxonomies_fields( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$ids_post = array();
		$ids_tax = array();
		foreach ( $ids as $aid ) {
			if ( substr( $aid, 0, 9 ) == 'taxonomy_' ) {
				$ids_tax[] = substr( $aid, 9 );
			} else {
				$ids_post[] = $aid;
			}
		}
		$results = [];
		if ( ! empty( $ids_post ) ) {
			$request['id'] = $ids_post;
			$posts = $this->get_value_titles_for_fields( $request );
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $key => $value ) {
					$results[ $key ] = $value;
				}
			}
		}
		if ( ! empty( $ids_tax ) ) {
			$request['id'] = $ids_tax;
			$taxonomies = $this->get_value_titles_for_taxonomies( $request );
			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $key => $value ) {
					$results[ 'taxonomy_' . $key ] = $value;
				}
			}
		}
		return $results;
	}

	/**
	 * Get cryptocurrency coins titles by IDs
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_cryptocurrency_coins( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$crypto = \DynamicVisibilityForElementor\Plugin::instance()->cryptocurrency;

		// Return empty if no API key (controlled at widget/dynamic tag level)
		if ( ! $crypto->has_api_key() ) {
			return [];
		}

		$results = [];

		foreach ( $ids as $id ) {
			try {
				$coin_info = $crypto->get_crypto_info( $id );
				if ( $coin_info ) {
					$results[ $id ] = esc_attr( $coin_info['name'] . ' (' . $coin_info['symbol'] . ')' );
				}
			} catch ( \DynamicVisibilityForElementor\CryptocurrencyApiError $e ) {
				// Skip if API fails - no fallback
				continue;
			}
		}

		return $results;
	}

	/**
	 * Get cryptocurrency conversion titles by IDs
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	protected function get_value_titles_for_cryptocurrency_convert( $request ) {
		if ( ! current_user_can( Editor::EDITING_CAPABILITY ) ) {
			throw new \Exception( 'Access denied.' );
		}

		$ids = (array) $request['id'];
		$crypto = \DynamicVisibilityForElementor\Plugin::instance()->cryptocurrency;

		// Return empty if no API key (controlled at widget/dynamic tag level)
		if ( ! $crypto->has_api_key() ) {
			return [];
		}

		$results = [];

		foreach ( $ids as $id ) {
			try {
				$currency_info = $crypto->get_fiat_and_crypto_info( $id );
				if ( is_array( $currency_info ) && isset( $currency_info['name'], $currency_info['symbol'] ) ) {
					$results[ $id ] = esc_attr( $currency_info['name'] . ' (' . $currency_info['symbol'] . ')' );
				}
			} catch ( \DynamicVisibilityForElementor\CryptocurrencyApiError $e ) {
				// Skip if API fails - no fallback
				continue;
			}
		}

		return $results;
	}

	public function register_ajax_actions( $ajax_manager ) {
		$ajax_manager->register_ajax_action( 'dce_query_control_value_titles', [ $this, 'ajax_call_control_value_titles' ] );
		$ajax_manager->register_ajax_action( 'dce_query_control_filter_autocomplete', [ $this, 'ajax_call_filter_autocomplete' ] );
	}
}
