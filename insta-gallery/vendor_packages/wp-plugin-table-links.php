<?php

if ( class_exists( 'QuadLayers\\WP_Plugin_Table_Links\\Load' ) ) {
	add_action('init', function() {
		new \QuadLayers\WP_Plugin_Table_Links\Load(
			QLIGG_PLUGIN_FILE,
			array(
				array(
					'text'   => esc_html__( 'Settings', 'insta-gallery' ),
					'url'    => admin_url( 'admin.php?page=qligg_backend' ),
					'target' => '_self',
				),
				array(
					'text' => esc_html__( 'Premium', 'insta-gallery' ),
					'url'  => 'https://quadlayers.com/products/instagram-feed-gallery/?utm_source=qligg_plugin&utm_medium=plugin_table&utm_campaign=premium_upgrade&utm_content=premium_link',
					'color' => 'green',
					'target' => '_blank',
				),
				array(
					'place' => 'row_meta',
					'text'  => esc_html__( 'Support', 'insta-gallery' ),
					'url'   => 'https://quadlayers.com/account/support/?utm_source=qligg_plugin&utm_medium=plugin_table&utm_campaign=support&utm_content=support_link',
				),
				array(
					'place' => 'row_meta',
					'text'  => esc_html__( 'Documentation', 'insta-gallery' ),
					'url'   => 'https://quadlayers.com/documentation/instagram-feed-gallery/?utm_source=qligg_plugin&utm_medium=plugin_table&utm_campaign=documentation&utm_content=documentation_link',
				),
			)
		);
	});

}
