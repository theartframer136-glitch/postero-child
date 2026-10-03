<?php
namespace QuadLayers\IGG\Entity;

use QuadLayers\WP_Orm\Entity\CollectionEntity;

class Feed extends CollectionEntity {
	public static $primaryKey  = 'id'; //phpcs:ignore
	public $id                = 0;
	public $account_id        = '';
	public $source            = 'username';
	public $tag               = 'wordpress'; //phpcs:ignore
	public $order_by          = 'top_media';
	public $layout            = 'gallery';
	public $limit             = 12;
	public $columns           = 3;  // Deprecated
	public $spacing           = 10; // Deprecated
	public $lazy              = false;
	public $responsive        = array(
		'desktop'     => array(
			'columns' => 3,
			'spacing' => 10,
		),
		'tablet'      => array(
			'columns' => 2,
			'spacing' => 8,
		),
		'mobile'      => array(
			'columns' => 1,
			'spacing' => 6,
		),
		'breakpoints' => array(
			'tablet' => 768,
			'mobile' => 480,
		),
	);
	public $aspect_ratio      = array(
		'width'  => 1,
		'height' => 1,
	);
	public $highlight         = array(
		'tag'      => '',
		'id'       => '',
		'position' => '1,3,5',
	);
	public $reel              = array(
		'hide' => false,
	);
	public $copyright         = array(
		'hide'        => false,
		'placeholder' => '',
	);
	public $profile           = array(
		'display'      => false,
		'username'     => '',
		'nickname'     => '',
		'website'      => '',
		'biography'    => '',
		'link_text'    => 'Follow',
		'website_text' => 'Website',
		'avatar'       => '',
	);
	public $box               = array(
		'display'    => false,
		'padding'    => 1,
		'radius'     => 0,
		'background' => '#fefefe',
		'profile'    => false,
		'desc'       => '',
		'text_color' => '#000000',
	);
	public $mask              = array(
		'display'        => true,
		'background'     => '#000000',
		'icon_color'     => '#ffffff',
		'likes_count'    => true,
		'comments_count' => true,
	);
	public $card              = array(
		'display'          => false,
		'radius'           => 1,
		'font_size'        => 12,
		'background'       => '#ffffff',
		'background_hover' => '',
		'text_color'       => '#000000',
		'padding'          => 5,
		'likes_count'      => true,
		'text_length'      => 10,
		'comments_count'   => true,
		'text_align'       => 'left',
	);
	public $carousel          = array(
		'centered_slides'   => false,
		'autoplay'          => false,
		'autoplay_interval' => 3000,
		'navarrows'         => true,
		'navarrows_color'   => '',
		'pagination'        => true,
		'pagination_color'  => '',
	);
	public $modal             = array(
		'display'           => true,
		'profile'           => true,
		'media_description' => true,
		'likes_count'       => true,
		'comments_count'    => true,
		'comments_list'     => false,
		'text_align'        => 'left',
		'modal_align'       => 'right',
		'text_length'       => 10000,
		'font_size'         => 12,
	);
	public $button            = array(
		'display'          => true,
		'text'             => 'View on Instagram',
		'text_color'       => '#ffff',
		'background'       => '',
		'background_hover' => '',
	);
	public $button_load       = array(
		'display'          => false,
		'text'             => 'Load more...',
		'text_color'       => '#ffff',
		'background'       => '',
		'background_hover' => '',
	);
}
