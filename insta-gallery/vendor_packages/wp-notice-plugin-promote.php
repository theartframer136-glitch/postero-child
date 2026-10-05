<?php

if ( class_exists( 'QuadLayers\\WP_Notice_Plugin_Promote\\Load' ) ) {
	add_action('init', function() {
		/**
		 *  Promote constants
		 */
		define( 'QLIGG_PROMOTE_LOGO_SRC', plugins_url( '/assets/backend/img/icon-128x128.gif', QLIGG_PLUGIN_FILE ) );
		/**
		 * Notice review
		 */
		define( 'QLIGG_PROMOTE_REVIEW_URL', 'https://wordpress.org/support/plugin/insta-gallery/reviews/?filter=5#new-post' );
		/**
		 * Notice premium sell
		 */
		define( 'QLIGG_PROMOTE_PREMIUM_SELL_SLUG', 'insta-gallery-pro' );
		define( 'QLIGG_PROMOTE_PREMIUM_SELL_NAME', 'Social Feed Gallery PRO' );
		define( 'QLIGG_PROMOTE_PREMIUM_INSTALL_URL', 'https://quadlayers.com/products/instagram-feed-gallery/?utm_source=qligg_plugin&utm_medium=dashboard_notice&utm_campaign=premium_upgrade&utm_content=premium_install_button' );
		define( 'QLIGG_PROMOTE_PREMIUM_SELL_URL', 'https://quadlayers.com/products/instagram-feed-gallery/?utm_source=qligg_plugin&utm_medium=dashboard_notice&utm_campaign=premium_upgrade&utm_content=premium_link' );
		/**
		 * Notice cross sell 1
		 */
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_1_SLUG', 'wp-whatsapp-chat' );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_1_NAME', 'Social Chat' );
		define(
			'QLIGG_PROMOTE_CROSS_INSTALL_1_TITLE',
			wp_kses(
				sprintf(
					'<h3 style="margin:0">%s</h3>',
					esc_html__( 'Turn more visitors into customers.', 'insta-gallery' )
				),
				array(
					'h3' => array(
						'style' => array()
					)
				)
			)
		);
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_1_DESCRIPTION', esc_html__( 'Social Chat allows your users to start a conversation from your website directly to your WhatsApp phone number with one click.', 'insta-gallery' ) );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_1_URL', 'https://quadlayers.com/products/whatsapp-chat/?utm_source=qligg_plugin&utm_medium=dashboard_notice&utm_campaign=cross_sell&utm_content=social_chat_link' );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_1_LOGO_SRC', plugins_url( '/assets/backend/img/wp-whatsapp-chat.jpeg', QLIGG_PLUGIN_FILE ) );
		/**
		 * Notice cross sell 2
		 */
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_2_SLUG', 'wp-tiktok-feed' );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_2_NAME', 'TikTok Feed' );
		define(
			'QLIGG_PROMOTE_CROSS_INSTALL_2_TITLE',
			wp_kses(
				sprintf(
					'<h3 style="margin:0">%s</h3>',
					esc_html__( 'Add TikTok videos in minutes.', 'insta-gallery' )
				),
				array(
					'h3' => array(
						'style' => array()
					)
				)
			)
		);
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_2_DESCRIPTION', esc_html__( 'Display TikTok videos on your WordPress site with responsive galleries, custom layouts, and an engaging lightbox popup.', 'insta-gallery' ) );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_2_URL', 'https://quadlayers.com/products/tiktok-feed/?utm_source=qligg_plugin&utm_medium=dashboard_notice&utm_campaign=cross_sell&utm_content=tiktok_feed_link' );
		define( 'QLIGG_PROMOTE_CROSS_INSTALL_2_LOGO_SRC', plugins_url( '/assets/backend/img/wp-tiktok-feed.jpg', QLIGG_PLUGIN_FILE ) );

		new \QuadLayers\WP_Notice_Plugin_Promote\Load(
			QLIGG_PLUGIN_FILE,
			array(
				array(
					'type'               => 'ranking',
					'notice_delay'       => WEEK_IN_SECONDS,
					'notice_logo'        => QLIGG_PROMOTE_LOGO_SRC,
					'notice_title'       => wp_kses(
						sprintf(
							'<h3 style="margin:0">%s</h3>',
							esc_html__( 'Enjoying Social Feed Gallery?', 'insta-gallery' )
						),
						array(
							'h3' => array(
								'style' => array()
							)
						)
					),
					'notice_description' => esc_html__( 'A quick 5-star review helps us keep improving the plugin and supporting users like you. It only takes 2 seconds — thank you!', 'insta-gallery' ),
					'notice_link'        => QLIGG_PROMOTE_REVIEW_URL,
					'notice_more_link'   => 'https://quadlayers.com/account/support/?utm_source=qligg_plugin&utm_medium=dashboard_notice&utm_campaign=support&utm_content=report_bug_button',
					'notice_more_label'  => esc_html__(
						'Report a bug',
						'insta-gallery'
					),
				),
				array(
					'plugin_slug'        => QLIGG_PROMOTE_PREMIUM_SELL_SLUG,
					'plugin_install_link'   => QLIGG_PROMOTE_PREMIUM_INSTALL_URL,
					'plugin_install_label'  => esc_html__(
						'Purchase Now',
						'insta-gallery'
					),
					'notice_delay'       => MONTH_IN_SECONDS,
					'notice_logo'        => QLIGG_PROMOTE_LOGO_SRC,
					'notice_title'       => wp_kses(
						sprintf(
							'<h3 style="margin:0">%s</h3>',
							esc_html__( 'Save 20% today!', 'insta-gallery' )
						),
						array(
							'h3' => array(
								'style' => array()
							)
						)
					),
					'notice_description' => sprintf(
						esc_html__(
							'Today we have a special gift for you. Use the coupon code %1$s within the next 48 hours to receive a %2$s discount on the premium version of the %3$s plugin.',
							'insta-gallery'
						),
						'ADMINPANEL20%',
						'20%',
						QLIGG_PROMOTE_PREMIUM_SELL_NAME
					),
					'notice_more_link'   => QLIGG_PROMOTE_PREMIUM_SELL_URL,
				),
				array(
					'plugin_slug'        => QLIGG_PROMOTE_CROSS_INSTALL_1_SLUG,
					'notice_delay'       => MONTH_IN_SECONDS * 3,
					'notice_logo'        => QLIGG_PROMOTE_CROSS_INSTALL_1_LOGO_SRC,
					'notice_title'       => QLIGG_PROMOTE_CROSS_INSTALL_1_TITLE,
					'notice_description' => QLIGG_PROMOTE_CROSS_INSTALL_1_DESCRIPTION,
					'notice_more_link'   => QLIGG_PROMOTE_CROSS_INSTALL_1_URL
				),
				array(
					'plugin_slug'        => QLIGG_PROMOTE_CROSS_INSTALL_2_SLUG,
					'notice_delay'       => MONTH_IN_SECONDS * 6,
					'notice_logo'        => QLIGG_PROMOTE_CROSS_INSTALL_2_LOGO_SRC,
					'notice_title'       => QLIGG_PROMOTE_CROSS_INSTALL_2_TITLE,
					'notice_description' => QLIGG_PROMOTE_CROSS_INSTALL_2_DESCRIPTION,
					'notice_more_link'   => QLIGG_PROMOTE_CROSS_INSTALL_2_URL
				),
			)
		);
	});
}
