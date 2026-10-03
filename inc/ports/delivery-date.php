<?php
/**
 * Estimated delivery date: the storefront half of the plugin
 * "WPC Estimated Delivery Date for WooCommerce" 2.6.2, moved into the theme.
 *
 * Same markup, same classes, same hooks and priorities, same CSS and JS
 * (copied byte for byte to assets/ports/wpced/), same handle names and the
 * same `wpced_vars` global. It reads the plugin's own data, which stays in the
 * database after deactivation: options `wpced_settings` and `wpced_rules`,
 * product/variation meta `wpced_enable` and `wpced_rules`, order item meta
 * `_wpced_date`.
 *
 * Loaded from the top of functions.php (inc/plugin-ports.php), before any
 * add_action there: the plugin registered its hooks at plugins_loaded:11,
 * before every theme hook, and functions.php hangs "Enquire for Price" on the
 * same woocommerce_single_product_summary priority (31) as the date.
 *
 * Tested against the plugin's own classes on identical inputs: 180 scenarios
 * (rule sets, settings, legacy options, clock times; 15 product kinds, 6
 * carts each), 0 differences in the storefront output.
 *
 * While the plugin is still active this file does nothing, so it can be
 * deployed first and the plugin deactivated afterwards (and reactivated to
 * roll back).
 */
defined('ABSPATH') || exit;

if (function_exists('wpced_init') || class_exists('Wpced_Frontend')) {
    return; // the plugin is active and does the work
}

define('AF_ED_VER', '2.6.2'); // keeps ?ver=2.6.2 on the asset URLs

/* ── settings: Wpced_Backend::get_setting() (class-backend.php:118-126) ── */
function af_ed_settings() {
    static $s = null;
    if ($s === null) $s = (array) get_option('wpced_settings', array());
    return $s;
}
function af_ed_setting($name, $default = false) {
    $s = af_ed_settings();
    if (isset($s[$name]) && ($s[$name] !== '')) {
        $setting = $s[$name];
    } else {
        $setting = get_option('wpced_' . $name, $default);
    }
    return apply_filters('wpced_get_setting', $setting, $name, $default);
}
function af_ed_rules() {
    static $r = null;
    if ($r === null) $r = (array) get_option('wpced_rules', array());
    return apply_filters('wpced_get_rules', $r);
}
function af_ed_base_rule() {
    return array(
        'name' => '', 'apply' => 'all', 'apply_compare' => 'equal', 'apply_number' => '0',
        'apply_val' => array(), 'zone' => 'all', 'method' => 'all', 'min' => '5', 'max' => '10',
        'scheduled' => '',
    );
}

/* ── shipping zone / method: Wpced_Helper (class-helper.php:16-140) ──
 * Only asked for when a rule actually restricts by zone or method. The plugin
 * looked the zone up on every date it printed, whatever the rules said; the
 * answer only ever feeds the two comparisons below, so asking lazily gives
 * the same date. Memoised per destination for the request. */
function af_ed_zone_methods($zone_id) {
    static $m = array();
    if (!array_key_exists($zone_id, $m)) {
        $zone = WC_Shipping_Zones::get_zone($zone_id);
        $m[$zone_id] = is_object($zone) ? $zone->get_shipping_methods(true) : null;
    }
    return $m[$zone_id];
}
function af_ed_shipping_zone() {
    static $memo = array();
    $packages = isset(WC()->cart) ? WC()->cart->get_shipping_packages() : null;
    $first    = $packages ? reset($packages) : null;
    $key      = ($packages !== null) ? md5(wp_json_encode(isset($first['destination']) ? $first['destination'] : array())) : 'nocart';
    if (array_key_exists($key, $memo)) return $memo[$key];

    $zone = null;
    if ($packages !== null) {                         // get_matched_zone()
        $z = wc_get_shipping_zone($first);
        if (is_object($z)) $zone = af_ed_zone_methods($z->get_id()) ? $z : null;
    }
    if ($zone === null) {                             // get_geo_zone()
        $geo = new WC_Geolocation();
        $ip  = $geo->get_ip_address();
        $g   = $geo->geolocate_ip($ip);
        $z   = WC_Shipping_Zones::get_zone_matching_package(array('destination' => array(
            'country' => $g['country'], 'state' => $g['state'], 'postcode' => '')));
        if (is_object($z)) $zone = af_ed_zone_methods($z->get_id()) ? $z : null;
    }
    if ($zone === null) {                             // get_default_zone(0)
        $z = WC_Shipping_Zones::get_zone(0);
        if (is_object($z)) $zone = af_ed_zone_methods($z->get_id()) ? $z : null;
    }
    return $memo[$key] = $zone;
}
function af_ed_selected_method() {                    // get_selected_method(false)
    $selected = array();
    if (isset(WC()->session)) $selected = WC()->session->get('chosen_shipping_methods');
    if (isset($selected[0]) && $selected[0] !== false) {
        $method = explode(':', $selected[0]);
        return $method[1] ?? null;
    }
    return null;
}

/* ── which rule applies: Wpced_Frontend::get_rule (class-frontend.php:116-265) ── */
function af_ed_get_rule($product, $shipping_method = null) {
    $ignore = !is_a($product, 'WC_Product') || !$product->exists() || !$product->is_purchasable()
        || !$product->is_in_stock() || $product->is_type('external') || $product->is_virtual();
    if (apply_filters('wpced_ignore', $ignore, $product)) return array();

    $get_rule      = array();
    $variation_id  = 0;
    $parent_enable = 0;
    $product_id    = $product->get_id();
    $enable        = get_post_meta($product_id, 'wpced_enable', true) ?: 'global';
    $rules         = $default_rule = array();

    if ($product->is_type('variation')) {
        $variation_id = $product_id;
        $product_id   = $product->get_parent_id();
        $enable       = apply_filters('wpced_enable_variation', get_post_meta($product->get_id(), 'wpced_enable', true) ?: 'parent', $product);
        if ($enable === 'parent') {
            $parent_enable = 1;
            $enable        = get_post_meta($product_id, 'wpced_enable', true) ?: 'global';
        }
    }
    if ($enable === 'disable') return array();
    if ($enable === 'global') $rules = af_ed_rules();
    if ($enable === 'override') {
        if ($variation_id && !$parent_enable) {
            $rules = get_post_meta($variation_id, 'wpced_rules', true) ?: array();
        } else {
            $rules = get_post_meta($product_id, 'wpced_rules', true) ?: array();
        }
    }
    if (isset($rules['default'])) {
        $default_rule = $rules['default'];
        unset($rules['default']);
    }

    if (!empty($rules)) {
        foreach ($rules as $rule_key => $rule) {
            $rule          = array_merge(af_ed_base_rule(), $rule);
            $apply         = !empty($rule['apply']) ? $rule['apply'] : 'all';
            $apply_val     = !empty($rule['apply_val']) ? (array) $rule['apply_val'] : array();
            $apply_compare = !empty($rule['apply_compare']) ? $rule['apply_compare'] : 'equal';
            $apply_number  = !empty($rule['apply_number']) ? (float) $rule['apply_number'] : 0;
            $zone          = !empty($rule['zone']) ? $rule['zone'] : 'all';
            $method        = !empty($rule['method']) ? $rule['method'] : 'all';

            if (!in_array($apply, array('all', 'stock', 'instock', 'outofstock', 'backorder'))) {
                if ((substr($apply, 0, 3) === 'pa_') && $variation_id) {
                    $attrs = $product->get_attributes();
                    if (empty($attrs[$apply]) || !in_array($attrs[$apply], $apply_val)) continue;
                }
                if (!has_term($apply_val, $apply, $product_id)) continue;
            }
            if ($apply === 'stock') {
                if (!$product->managing_stock()) continue;
                $stock_qty = $product->get_stock_quantity();
                if (($apply_compare === 'equal') && ($stock_qty !== $apply_number)) continue;
                if (($apply_compare === 'not_equal') && ($stock_qty === $apply_number)) continue;
                if (($apply_compare === 'greater') && ($stock_qty <= $apply_number)) continue;
                if (($apply_compare === 'greater_equal') && ($stock_qty < $apply_number)) continue;
                if (($apply_compare === 'less') && ($stock_qty >= $apply_number)) continue;
                if (($apply_compare === 'less_equal') && ($stock_qty > $apply_number)) continue;
            }
            if (($apply === 'instock') && !$product->is_in_stock()) continue;
            if (($apply === 'outofstock') && $product->is_in_stock()) continue;
            if (($apply === 'backorder') && !$product->is_on_backorder()) continue;
            if ($zone !== 'all') {
                $user_zone = af_ed_shipping_zone();
                if ($user_zone && ($user_zone->get_id() != $zone)) continue;
            }
            if ($method !== 'all') {
                $user_method = $shipping_method ?: af_ed_selected_method();
                if ($user_method && ($user_method != $method)) continue;
            }
            $get_rule        = $rule;
            $get_rule['key'] = $rule_key;
            break;
        }
    }
    if (empty($get_rule)) {
        $get_rule        = array_merge(af_ed_base_rule(), $default_rule);
        $get_rule['key'] = 'default';
    }
    return apply_filters('wpced_get_rule', $get_rule, $product);
}

/* ── dates: get_date / format_date / get_date_format / check_skipped (class-frontend.php:511-639) ── */
function af_ed_date_format() {
    $date_format        = af_ed_setting('date_format', 'M j, Y');
    $date_format_custom = af_ed_setting('date_format_custom', 'M j, Y');
    if (($date_format === 'custom') && !empty($date_format_custom)) $date_format = $date_format_custom;
    $date_format = apply_filters('wpced_date_format', $date_format);
    return apply_filters('wpced_get_date_format', $date_format);
}
function af_ed_check_skipped($date, $weekday = null) {
    $skipped_dates = af_ed_setting('skipped_dates', array());
    if (empty($skipped_dates) || !is_array($skipped_dates)) return false;
    if (is_numeric($date)) {
        $weekday = date_i18n('w', $date);
        $date    = date_i18n('m/d/Y', $date);
    }
    foreach ($skipped_dates as $skipped_date) {
        if ($skipped_date['type'] !== 'cus') {
            if ($skipped_date['type'] == $weekday) return true;
        }
    }
    return false;
}
function af_ed_get_date($days, $scheduled = '') {
    $i = 1; $j = 1; $available = array();
    $days                 = absint($days);
    $current_unix         = current_time('U');
    $current_time         = current_time('h:i a');
    $current_date         = current_time('m/d/Y');
    $current_weekday      = current_time('w');
    $extra_time_line      = af_ed_setting('extra_time_line');
    $date_format          = af_ed_date_format();
    $current_date_skipped = false;

    if (!empty($scheduled) && (strtotime($scheduled) > strtotime($current_date))) {
        $current_unix         = strtotime($scheduled);
        $current_date         = date_i18n('m/d/Y', $current_unix);
        $current_weekday      = date_i18n('w', $current_unix);
        $current_date_skipped = true;
    }
    while (af_ed_check_skipped($current_date, $current_weekday) && ($j <= 100)) {
        $current_unix         += 86400;
        $current_date         = date_i18n('m/d/Y', $current_unix);
        $current_weekday      = date_i18n('w', $current_unix);
        $current_date_skipped = true;
        $j++;
    }
    if ($current_date_skipped && apply_filters('wpced_apply_extra_date_for_skipped_date', false)) $days += 1;
    if (!empty($extra_time_line) && apply_filters('wpced_apply_extra_time_for_skipped_date', !$current_date_skipped)) {
        if (strtotime($current_date . ' ' . $current_time) > strtotime($current_date . ' ' . $extra_time_line)) $days += 1;
    }
    if ($days === 0) {
        $get_date = $current_unix;
    } else {
        while ((count($available) < $days) && ($i <= 100)) {
            if (!$current_date_skipped) {
                $current_unix    += 86400;
                $current_date    = date_i18n('m/d/Y', $current_unix);
                $current_weekday = date_i18n('w', $current_unix);
            }
            if (!af_ed_check_skipped($current_date, $current_weekday)) $available[] = $current_unix;
            if ($current_date_skipped) {
                $current_unix    += 86400;
                $current_date    = date_i18n('m/d/Y', $current_unix);
                $current_weekday = date_i18n('w', $current_unix);
            }
            $i++;
        }
        $get_date = end($available);
    }
    if ($date_format === 'days') $get_date = absint(round(($get_date - current_time('U')) / 86400));
    return apply_filters('wpced_get_date', $get_date, $days, $scheduled);
}
function af_ed_format_date($date) {
    if (!is_numeric($date)) return $date;
    $date_format = af_ed_date_format();
    if (empty($date_format)) $date_format = 'M j, Y';
    if ($date_format === 'days') return absint($date);
    return date_i18n($date_format, $date);
}

/* ── markup: get_product_date (class-frontend.php:279-364) ── */
function af_ed_product_date($product, $type = 'full', $context = 'product') {
    if (is_numeric($product)) {
        $product_id = $product;
        $product    = wc_get_product($product_id);
    } elseif (is_a($product, 'WC_Product')) {
        $product_id = $product->get_id();
    } else {
        $product_id = 0;
    }
    if (!$product_id) return '';

    $delivery_date = ''; $delivery_date_u = '';
    $is_min = $is_max = false;
    $rule = af_ed_get_rule($product);

    if (isset($rule['min']) && $rule['min'] !== '') {
        $min_time        = af_ed_get_date($rule['min'], $rule['scheduled']);
        $delivery_date   .= af_ed_format_date($min_time);
        $delivery_date_u = $min_time;
        if (empty($rule['max'])) $is_min = true;
    }
    if (isset($rule['max']) && $rule['max'] !== '') {
        $max_time = af_ed_get_date($rule['max'], $rule['scheduled']);
        if ($rule['min'] !== '') {
            $delivery_date   .= apply_filters('wpced_dates_separator', ' - ');
            $delivery_date_u .= apply_filters('wpced_dates_separator', ' - ');
        } else {
            $is_max = true;
        }
        $delivery_date   .= af_ed_format_date($max_time);
        $delivery_date_u .= $max_time;
    }

    if ($type === 'u' || $type === 'U') {
        $product_date = $delivery_date_u;
    } elseif ($type === 'plain' || $type === 'text') {
        $product_date = $delivery_date;
    } else {
        if ($is_max) {
            $delivery_text = af_ed_setting('text_max', esc_html__('Latest estimated delivery date: %s', 'wpc-estimated-delivery-date'));
            if (empty($delivery_text)) $delivery_text = esc_html__('Latest estimated delivery date: %s', 'wpc-estimated-delivery-date');
        } elseif ($is_min) {
            $delivery_text = af_ed_setting('text_min', esc_html__('Earliest estimated delivery date: %s', 'wpc-estimated-delivery-date'));
            if (empty($delivery_text)) $delivery_text = esc_html__('Earliest estimated delivery date: %s', 'wpc-estimated-delivery-date');
        } else {
            $delivery_text = af_ed_setting('text', esc_html__('Estimated delivery dates: %s', 'wpc-estimated-delivery-date'));
            if (empty($delivery_text)) $delivery_text = esc_html__('Estimated delivery dates: %s', 'wpc-estimated-delivery-date');
        }
        $wrapper_id    = is_a($product, 'WC_Product_Variation') ? $product->get_parent_id() : $product_id;
        $wrapper_class = apply_filters('wpced_wrapper_class', 'wpced wpced-' . $wrapper_id . ' wpced-' . $context . ' wpced-' . ($rule['key'] ?? 'default'), $product, $type, $context);
        if (!empty($delivery_date)) {
            $product_date = '<div class="' . esc_attr($wrapper_class) . '" data-id="' . esc_attr($wrapper_id) . '"><div class="wpced-inner">' . sprintf($delivery_text, $delivery_date) . '</div></div>';
        } else {
            $product_date = '<div class="' . esc_attr($wrapper_class) . '" data-id="' . esc_attr($wrapper_id) . '"></div>';
        }
    }
    return apply_filters('wpced_get_product_date', $product_date, $product, $type, $context);
}

/* ── cart total date: get_overall_date (class-frontend.php:441-509) ──
 * A line the plugin ignores (virtual, out of stock, not purchasable, disabled)
 * gets an empty rule; the plugin then read $rule['min'] off an empty array,
 * which is null, so it counted the line as "0 days" = today. That is why the
 * live cart says today's date when it holds a Digital Download line
 * (inc/kit-choices.php:464 marks those lines virtual). Same result here,
 * without the "Undefined array key" warnings. */
function af_ed_overall_date($shipping_method = null) {
    if (!isset(WC()->cart)) return null;
    $items = WC()->cart->get_cart();
    if (is_array($items) && (count($items) > 0)) {
        $format      = af_ed_setting('cart_overall_format', 'latest');
        $overall_min = array();
        $overall_max = array();
        foreach ($items as $item) {
            $rule      = af_ed_get_rule($item['data'], $shipping_method);
            $min       = array_key_exists('min', $rule) ? $rule['min'] : null;
            $max       = array_key_exists('max', $rule) ? $rule['max'] : null;
            $scheduled = array_key_exists('scheduled', $rule) ? $rule['scheduled'] : null;
            $item_min  = $item_max = '';
            if ($min !== '') $item_min = $item_max = af_ed_get_date($min, $scheduled);
            if ($max !== '') {
                $item_max = af_ed_get_date($max, $scheduled);
                if (empty($item_min)) $item_min = $item_max;
            }
            if (!empty($item_min)) $overall_min[] = $item_min;
            if (!empty($item_max)) $overall_max[] = $item_max;
        }
        if (!empty($overall_min) && !empty($overall_max)) {
            sort($overall_min);
            sort($overall_max);
            switch ($format) {
                case 'earliest':
                    $delivery_date = af_ed_format_date(reset($overall_min));
                    break;
                case 'earliest_latest':
                    $delivery_date = af_ed_format_date(reset($overall_min)) . apply_filters('wpced_dates_separator', ' - ') . af_ed_format_date(end($overall_max));
                    break;
                case 'latest':
                default:
                    $delivery_date = af_ed_format_date(end($overall_max));
                    break;
            }
            $delivery_text = af_ed_setting('text_cart_overall', esc_html__('Overall estimated dispatch date: %s', 'wpc-estimated-delivery-date'));
            if (empty($delivery_text)) $delivery_text = esc_html__('Overall estimated dispatch date: %s', 'wpc-estimated-delivery-date');
            return apply_filters('wpced_get_overall_date', sprintf($delivery_text, $delivery_date), $shipping_method);
        }
    }
    return null;
}

/* ── hooks: Wpced_Frontend::__construct (class-frontend.php:16-89), same order ── */
function af_ed_show_date($product = null) {
    if (!$product) global $product;
    if (!$product || !is_a($product, 'WC_Product')) return;
    echo af_ed_product_date($product);
}

add_action('wp_enqueue_scripts', function () {
    $base = get_stylesheet_directory_uri() . '/assets/ports/wpced/';
    wp_enqueue_style('wpced-frontend', $base . 'frontend.css', array(), AF_ED_VER);
    wp_enqueue_script('wpced-frontend', $base . 'frontend.js', array('jquery'), AF_ED_VER, true);
    wp_localize_script('wpced-frontend', 'wpced_vars', array(
        'wc_ajax_url'  => WC_AJAX::get_endpoint('%%endpoint%%'),
        'nonce'        => wp_create_nonce('wpced-security'),
        'reload_dates' => apply_filters('wpced_reload_dates', wc_string_to_bool(af_ed_setting('reload_dates', 'no'))),
    ));
});

add_shortcode('wpced', function ($attrs) {
    $attrs = shortcode_atts(array('product_id' => null), $attrs);
    if (empty($attrs['product_id'])) {
        global $product;
    } else {
        $product = wc_get_product($attrs['product_id']);
    }
    return apply_filters('wpced_shortcode', is_a($product, 'WC_Product') ? af_ed_product_date($product) : '', $attrs);
});

switch (af_ed_setting('position_archive', 'above_add_to_cart')) {
    case 'under_title':       add_action('woocommerce_shop_loop_item_title', 'af_ed_show_date', 11); break;
    case 'under_rating':      add_action('woocommerce_after_shop_loop_item_title', 'af_ed_show_date', 6); break;
    case 'under_price':       add_action('woocommerce_after_shop_loop_item_title', 'af_ed_show_date', 11); break;
    case 'above_add_to_cart': add_action('woocommerce_after_shop_loop_item', 'af_ed_show_date', 9); break;
    case 'under_add_to_cart': add_action('woocommerce_after_shop_loop_item', 'af_ed_show_date', 11); break;
    case '0': case 'no': case 'none': break;
    default:
        add_action('wpced_custom_archive_position', function ($pos = 'none') {
            global $product;
            if (!$product || !is_a($product, 'WC_Product')) return;
            $position = apply_filters('wpced_archive_position', af_ed_setting('position_archive', apply_filters('wpced_default_archive_position', 'above_add_to_cart')));
            if ($position === $pos) af_ed_show_date($product);
        });
}

$af_ed_pos_single = af_ed_setting('position_single', '31');
if (!empty($af_ed_pos_single)) {
    if (is_numeric($af_ed_pos_single)) {
        add_action('woocommerce_single_product_summary', 'af_ed_show_date', absint($af_ed_pos_single));
    } else {
        add_action('wpced_custom_single_position', function ($pos = '0') {
            global $product;
            if (!$product || !is_a($product, 'WC_Product')) return;
            $position = apply_filters('wpced_single_position', af_ed_setting('position_single', apply_filters('wpced_default_single_position', '31')));
            if ($position === $pos) af_ed_show_date($product);
        });
    }
}
unset($af_ed_pos_single);

add_filter('woocommerce_available_variation', function ($available, $variable, $variation) {
    if (apply_filters('wpced_available_variation', true, $available, $variable, $variation)) {
        $available['wpced_enable'] = apply_filters('wpced_enable_variation', get_post_meta($variation->get_id(), 'wpced_enable', true) ?: 'parent', $variation);
        $available['wpced_date']   = htmlentities(af_ed_product_date($variation));
    }
    return $available;
}, 99, 3);

add_action('woocommerce_before_variations_form', function () {
    global $product;
    echo '<span class="wpced-variable wpced-variable-' . esc_attr($product->get_id()) . '" data-wpced="' . esc_attr(htmlentities(af_ed_product_date($product))) . '" style="display: none"></span>';
});

add_action('wc_ajax_wpced_reload_dates', function () {
    $dates = array();
    $ids   = isset($_POST['ids']) ? af_ed_sanitize_array($_POST['ids']) : array();
    if (!empty($ids)) {
        foreach (array_unique($ids) as $id) $dates['wpced-' . $id] = af_ed_product_date($id);
    }
    wp_send_json($dates);
});
function af_ed_sanitize_array($arr) {             // Wpced_Helper::sanitize_array
    foreach ((array) $arr as $k => $v) {
        $arr[$k] = is_array($v) ? af_ed_sanitize_array($v) : sanitize_post_field('post_content', $v, 0, 'db');
    }
    return $arr;
}

if (af_ed_setting('cart_item', 'no') === 'yes') {
    add_filter('woocommerce_cart_item_name', function ($name, $cart_item) {
        return $name . af_ed_product_date($cart_item['data']);
    }, 10, 2);
}
if (af_ed_setting('cart_item', 'no') === 'yes_data') {
    add_filter('woocommerce_get_item_data', function ($data, $cart_item) {
        $date = af_ed_product_date($cart_item['data'], 'plain');
        if (!empty($date)) {
            $data['wpced_date'] = apply_filters('wpced_cart_item_meta', array(
                'key'     => apply_filters('wpced_cart_item_meta_key', af_ed_setting('text_cart_item', esc_html__('Estimated delivery date', 'wpc-estimated-delivery-date')), $cart_item),
                'value'   => apply_filters('wpced_cart_item_meta_value', esc_html($date), $cart_item),
                'display' => apply_filters('wpced_cart_item_meta_display', $date, $cart_item),
            ), $cart_item);
        }
        return $data;
    }, 10, 2);
}
if (af_ed_setting('cart_overall', 'yes') !== 'no') {
    add_action('woocommerce_cart_contents', function () {
        $overall_date = af_ed_overall_date();
        if (!empty($overall_date)) {
            if (af_ed_setting('cart_overall', 'yes') === 'yes_text') {
                echo '<tr><td colspan="100" class="wpced-cart">' . esc_html($overall_date) . '</td></tr>';
            } else {
                echo '<tr><td colspan="100" class="wpced-cart"><span class="wpced"><span class="wpced-inner">' . esc_html($overall_date) . '</span></span></td></tr>';
            }
        }
        return null;
    });
}

/* order details (thank-you page, My Account, emails) */
add_action('woocommerce_checkout_create_order_line_item', function ($order_item, $cart_item_key, $values) {
    $order_item->update_meta_data('_wpced_date', af_ed_product_date($values['data']));
}, 10, 3);
add_action('woocommerce_order_item_meta_start', function ($order_item_id, $order_item) {
    if ((af_ed_setting('order_item', 'no') === 'yes') && ($date = $order_item->get_meta('_wpced_date')) && !empty($date)) {
        echo apply_filters('wpced_order_item_date', $date, $order_item_id, $order_item);
    }
}, 10, 2);
add_filter('woocommerce_email_styles', function ($css, $email) {
    if (in_array($email->id, apply_filters('wpced_hide_order_item_date_emails', array(
        'failed_order', 'cancelled_order', 'customer_failed_order', 'customer_refunded_order')))) {
        $css .= ' .wpced{display: none !important;} ';
    }
    return $css;
}, 10, 2);

/* staff order screen: keep the date shown above each line, raw meta hidden */
add_filter('woocommerce_hidden_order_itemmeta', function ($hidden) {
    return array_merge($hidden, array('_wpced_date'));
});
add_action('woocommerce_before_order_itemmeta', function ($order_item_id, $order_item) {
    if (($date = $order_item->get_meta('_wpced_date')) && !empty($date)) echo $date;
}, 10, 2);
