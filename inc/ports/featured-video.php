<?php
/**
 * Featured video: the plugin "YITH WooCommerce Featured Video" 1.58.0, moved
 * into the theme (3 products show a YouTube video in place of the main image).
 *
 * inc/ports/ywcfav/ holds the plugin's own files, copied unchanged: the
 * functions file, the frontend and manager classes and both templates. The
 * CSS and JS are copied byte for byte to assets/ports/ywcfav/assets/ with the
 * plugin's folder layout, so the handles, versions, dependencies, the
 * ywcfav_params / ywcfav_args objects and the gallery markup stay the same.
 * What is not copied is the YITH plugin framework (about 40 PHP files loaded
 * on every request), which the storefront never used.
 *
 * The data stays where the plugin kept it: product meta _video_url and
 * _video_image_url, option ywcfav_aspectratio (16_9) and
 * ywcfav_video_placeholder_id.
 *
 * Admin keeps the "Featured Video URL" box on the product edit screen, with
 * the plugin's own save handler (it fetches the video thumbnail as before).
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (defined('YWCFAV_VERSION') || function_exists('YITH_Featured_Audio_Video_Init')) {
    return; // the plugin is active and does the work
}

define('YWCFAV_VERSION', '1.58.0');
define('YWCFAV_ASSETS_URL', get_stylesheet_directory_uri() . '/assets/ports/ywcfav/assets/');
define('YWCFAV_TEMPLATE_PATH', __DIR__ . '/ywcfav/templates/');

if (!function_exists('yit_load_js_file')) {
    // plugin-fw/yit-functions.php:815-822
    function yit_load_js_file($filename) {
        if (!((defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) || isset($_GET['yith_script_debug']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $filename = str_replace('.js', '.min.js', $filename);
        }
        return $filename;
    }
}

require_once __DIR__ . '/ywcfav/functions.yith-wc-featured-audio-video.php';
require_once __DIR__ . '/ywcfav/class.ywcfav-manager.php';

/* YITH_WC_Audio_Video::include_video_scripts (class.yith-woocommerce-audio-video-content.php:69-96) */
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_style('ywcfav_style', YWCFAV_ASSETS_URL . 'css/ywcfav_frontend.css', array(), YWCFAV_VERSION);
        wp_enqueue_script('vimeo-api', YWCFAV_ASSETS_URL . 'js/lib/vimeo_player.js', array(), YWCFAV_VERSION, true);
        wp_enqueue_script('youtube-api', YWCFAV_ASSETS_URL . 'js/lib/youtube_api.js', array('jquery'), YWCFAV_VERSION, true);

        wp_register_script(
            'ywcfav_video',
            YWCFAV_ASSETS_URL . 'js/' . yit_load_js_file('ywcfav_video.js'),
            array('jquery', 'youtube-api', 'vimeo-api', 'ywcfav_frontend'),
            YWCFAV_VERSION,
            true
        );
        wp_localize_script('ywcfav_video', 'ywcfav_args', array(
            'product_gallery_trigger_class' => '.' . ywcfav_get_product_gallery_trigger(),
        ));
    }
}, 20);

if (is_admin()) {
    /* YITH_Featured_Audio_Video_Admin: only the product field and its save
       handler (class.ywcfav-admin.php:200-303), unchanged. */
    if (!class_exists('AF_Ywcfav_Admin', false)) {
        class AF_Ywcfav_Admin {
            public function __construct() {
                add_action('woocommerce_product_options_general_product_data', array($this, 'add_video_field'));
                add_action('woocommerce_admin_process_product_object', array($this, 'set_custom_product_meta'), 10, 1);
            }

            public function add_video_field() {
                $args = apply_filters(
                    'ywcfav_simple_url_video_args',
                    array(
                        'id'          => '_video_url',
                        'label'       => __('Featured Video URL', 'yith-woocommerce-featured-video'),
                        'placeholder' => __('Video URL', 'yith-woocommerce-featured-video'),
                        'desc_tip'    => true,
                        'description' => sprintf(__('Enter the URL for the video you want to show in place of the featured image in the product detail page. (the services enabled are: YouTube and Vimeo ).', 'yith-woocommerce-featured-video')),
                    )
                );
                wc_get_template('admin/add_simple_url_video.php', $args, '', YWCFAV_TEMPLATE_PATH);
            }

            public function set_custom_product_meta($product) {
                $video_url     = isset($_POST['_video_url']) ? wp_unslash($_POST['_video_url']) : ''; // phpcs:ignore
                $old_value_url = $product->get_meta('_video_url');

                if ($video_url !== $old_value_url) {
                    $product->update_meta_data('_video_url', $video_url);
                    $img_id = '';
                    if (!empty($video_url)) {
                        $video_info = explode(':', ywcfav_video_type_by_url($video_url));
                        $img_id     = $this->save_video_thumbnail(array(
                            'host' => $video_info[0],
                            'id'   => isset($video_info[1]) ? $video_info[1] : '',
                        ));
                    }
                    $product->update_meta_data('_video_image_url', $img_id);
                }
            }

            public function save_video_thumbnail($video_info) {
                $name   = isset($video_info['name']) ? $video_info['name'] : $video_info['id'];
                $result = false;
                switch ($video_info['host']) {
                    case 'vimeo':
                        if (function_exists('simplexml_load_file')) {
                            $img_url = 'http://vimeo.com/api/v2/video/' . $video_info['id'] . '.xml';
                            $xml     = simplexml_load_file($img_url);
                            $img_url = isset($xml->video->thumbnail_large) ? (string) $xml->video->thumbnail_large : '';
                            if (!empty($img_url)) {
                                $tmp = getimagesize($img_url);
                                if (!is_wp_error($tmp)) {
                                    $result = 'ok';
                                }
                            }
                        }
                        break;
                    case 'youtube':
                        $youtube_url = 'https://img.youtube.com/vi/' . $video_info['id'] . '/';
                        foreach (array('maxresdefault', 'hqdefault', 'mqdefault', 'sqdefault') as $image_size) {
                            $img_url      = $youtube_url . $image_size . '.jpg';
                            $get_response = wp_remote_get($img_url);
                            $result       = !is_wp_error($get_response) && '200' === $get_response['response']['code'] ? 'ok' : 'no';
                            if ('ok' === $result) {
                                break;
                            }
                        }
                        break;
                }

                if ('ok' === $result) {
                    return ywcfav_save_remote_image($img_url, $name);
                }
                return get_option('ywcfav_video_placeholder_id');
            }
        }
        new AF_Ywcfav_Admin();
    }
} else {
    /* The plugin builds the frontend class whenever YITH Zoom Magnifier is not
       active (it is not installed here). */
    require_once __DIR__ . '/ywcfav/class.ywcfav-frontend.php';
    YITH_Featured_Audio_Video_Frontend();
}
