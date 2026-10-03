<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The live settings each plugin port must reproduce (read while the plugins
 * are still active). Read-only. Every value goes through a mask: anything
 * under a key naming a token, key, secret, password or code, and any long
 * opaque string, is printed as [masked].
 *
 * Run: wp eval-file tools/diag-port-data.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb, $wp_filter;
$P = $wpdb->prefix;

function af_pd_mask($v, $k = '') {
    if (is_array($v) || is_object($v)) { $o = array(); foreach ((array) $v as $kk => $vv) $o[$kk] = af_pd_mask($vv, (string) $kk); return $o; }
    if (!is_string($v)) return $v;
    if ($k !== '' && preg_match('/token|secret|passw|api_?key|bypass|auth|license|nonce|salt|serp_?api/i', $k)) return '[masked, ' . strlen($v) . ' chars]';
    return preg_replace('/[A-Za-z0-9_\-]{40,}/', '[masked]', $v);
}
function af_pd_json($v) { return json_encode(af_pd_mask($v), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
function af_pd_opts($like) {
    global $wpdb;
    foreach ($wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT 120", $like, '%transient%', 'woosw_list_%')) as $n) {
        echo "  option $n = " . af_pd_json(maybe_unserialize(get_option($n))) . "\n";
    }
}
function af_pd_hooks($re) {
    global $wp_filter;
    foreach ($wp_filter as $tag => $h) { if (!preg_match($re, $tag) || !($h instanceof WP_Hook)) continue;
        foreach ($h->callbacks as $prio => $cbs) foreach ($cbs as $id => $cb) {
            $f = $cb['function']; $file = '';
            try { if ($f instanceof Closure) $file = (new ReflectionFunction($f))->getFileName(); elseif (is_array($f)) $file = (new ReflectionMethod($f[0], $f[1]))->getFileName(); elseif (is_string($f) && function_exists($f)) $file = (new ReflectionFunction($f))->getFileName(); } catch (Throwable $e) {}
            echo "  hook $tag @$prio " . (is_string($f) ? $f : (is_array($f) ? (is_object($f[0]) ? get_class($f[0]) : $f[0]) . '::' . $f[1] : 'closure')) . ' in ' . preg_replace('#^.*/wp-content/#', '', (string) $file) . "\n";
        }
    }
}
function af_pd_elementor_widgets($type) {
    global $wpdb;
    foreach ($wpdb->get_results($wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s", '%' . $wpdb->esc_like('"' . $type . '"') . '%')) as $r) {
        $walk = function ($els) use (&$walk, $r, $type) { foreach ((array) $els as $el) { if (!is_array($el)) continue;
            if (($el['widgetType'] ?? '') === $type) echo "  #{$r->post_id} " . get_post_type($r->post_id) . '/' . get_post_status($r->post_id) . " element {$el['id']}: " . af_pd_json($el['settings'] ?? array()) . "\n";
            if (!empty($el['elements'])) $walk($el['elements']); } };
        $walk(json_decode($r->meta_value, true));
    }
}

echo "=== MAS BRANDS ===\n";
$tax = get_option('mas_wc_brands_brand_taxonomy', '(missing)');
echo "  brand_taxonomy: " . var_export($tax, true) . " | plugin_styles: " . var_export(get_option('mas_wc_brands_plugin_styles', '(missing)'), true) . " | WC core product_brand: " . (taxonomy_exists('product_brand') ? 'registered' : 'no') . "\n";
if ($tax && $tax !== '(missing)' && taxonomy_exists($tax)) { $ts = get_terms(array('taxonomy' => $tax, 'hide_empty' => false)); echo "  terms: " . count($ts) . "\n"; foreach (array_slice($ts, 0, 30) as $t) echo "    {$t->term_id} {$t->slug} count={$t->count} thumbnail_id=" . get_term_meta($t->term_id, 'thumbnail_id', true) . "\n"; }
foreach (array('mas_wc_brands_brand_description', 'mas_wc_brands_brand_thumbnails') as $b) echo "  widget_$b: " . json_encode(get_option("widget_$b")) . "\n";
foreach ((array) get_option('sidebars_widgets') as $sb => $ids) if (is_array($ids)) foreach ($ids as $id) if (strpos($id, 'mas_wc_brands_') === 0) echo "  in sidebar $sb: $id\n";
foreach (array('postero-all-author', 'postero-artists', 'postero-brand') as $w) af_pd_elementor_widgets($w);

echo "\n=== WPC SMART MESSAGES ===\n";
foreach ($wpdb->get_results("SELECT ID, post_status, post_date FROM {$P}posts WHERE post_type='wpc_smart_message' ORDER BY post_date DESC") as $r) {
    $meta = array(); foreach (get_post_meta($r->ID) as $k => $v) if (strpos($k, 'wpcsm_') === 0) $meta[$k] = maybe_unserialize($v[0]);
    echo "  #{$r->ID} {$r->post_status} \"" . get_the_title($r->ID) . "\" activate=" . ($meta['wpcsm_activate'] ?? '?') . " location=" . json_encode($meta['wpcsm_location'] ?? null) . " | content: " . mb_substr(preg_replace('/\s+/', ' ', get_post_field('post_content', $r->ID, 'raw')), 0, 200) . "\n";
    echo "     meta: " . json_encode(af_pd_mask($meta), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}
if (class_exists('Wpcsm_Frontend') && method_exists('Wpcsm_Frontend', 'instance')) { try { echo "  ACTIVE (plugin's own selection): " . implode(',', wp_list_pluck((array) Wpcsm_Frontend::instance()->get_messages(), 'ID')) . "\n"; } catch (Throwable $e) { echo "  (active list failed: " . $e->getMessage() . ")\n"; } }

echo "\n=== YITH FEATURED VIDEO ===\n";
foreach ($wpdb->get_col("SELECT post_id FROM {$P}postmeta WHERE meta_key='_video_url' AND meta_value<>''") as $id) {
    $p = function_exists('wc_get_product') ? wc_get_product($id) : null; $img = get_post_meta($id, '_video_image_url', true); $url = get_post_meta($id, '_video_url', true);
    echo "  #$id " . get_post_status($id) . ' ' . get_permalink($id) . "\n     url=$url parsed=" . json_encode(function_exists('ywcfav_video_type_by_url') ? ywcfav_video_type_by_url($url) : 'n/a') . " _video_image_url=$img (" . (get_post_status($img) ?: 'MISSING') . ") thumb_id=" . ($p ? $p->get_image_id() : '-') . ' gallery=' . ($p ? implode(',', $p->get_gallery_image_ids()) : '-') . "\n";
}
echo "  aspect=" . get_option('ywcfav_aspectratio') . " placeholder=" . get_option('ywcfav_video_placeholder_id') . " zoom=" . var_export(apply_filters('woocommerce_single_product_zoom_enabled', get_theme_support('wc-product-gallery-zoom')), true) . " WP_DEBUG=" . var_export(defined('WP_DEBUG') && WP_DEBUG, true) . " SCRIPT_DEBUG=" . var_export(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG, true) . "\n";
af_pd_opts('ywcfav%');

echo "\n=== WPC SMART QUICK VIEW ===\n";
af_pd_opts('woosq%');
echo "  woocommerce_cart_redirect_after_add=" . get_option('woocommerce_cart_redirect_after_add') . " | variable products=" . (function_exists('wc_get_products') ? count(wc_get_products(array('type' => 'variable', 'limit' => -1, 'return' => 'ids'))) : '?') . "\n";
af_pd_hooks('/woosq|woosc_disable_security_check/');

echo "\n=== WPC SMART WISHLIST ===\n";
af_pd_opts('woosw%');
echo "  lists stored: " . $wpdb->get_var("SELECT COUNT(*) FROM {$P}options WHERE option_name LIKE 'woosw_list_%'") . " | one list value (format): " . af_pd_json(maybe_unserialize($wpdb->get_var("SELECT option_value FROM {$P}options WHERE option_name LIKE 'woosw_list_%' AND option_value <> '' AND option_value <> 'a:0:{}' LIMIT 1"))) . "\n";
foreach ($wpdb->get_results("SELECT meta_key, COUNT(*) n FROM {$P}usermeta WHERE meta_key LIKE 'woosw%' GROUP BY meta_key") as $r) echo "  usermeta {$r->meta_key}: {$r->n}\n";
af_pd_hooks('/woosw/');

echo "\n=== HEADER FOOTER CODE MANAGER: snippet rules ===\n";
if ($wpdb->get_var("SHOW TABLES LIKE '{$P}hfcm_scripts'")) foreach ($wpdb->get_results("SELECT script_id, name, location, display_on, device_type, status, ex_pages, ex_posts, s_pages, s_posts, s_custom_posts, s_categories, s_tags, lp_count, snippet_type FROM {$P}hfcm_scripts") as $r) echo "  " . json_encode($r) . "\n";

echo "\n=== CONTACT FORM 7 ===\n";
foreach ($wpdb->get_results("SELECT ID, post_title, post_status, post_content FROM {$P}posts WHERE post_type='wpcf7_contact_form'") as $r) {
    echo "  #{$r->ID} {$r->post_status} \"{$r->post_title}\" hash=" . get_post_meta($r->ID, '_hash', true) . "\n";
    foreach (array('_form', '_mail', '_mail_2', '_messages', '_additional_settings', '_locale') as $k) echo "     $k: " . af_pd_json(get_post_meta($r->ID, $k, true)) . "\n";
}
af_pd_elementor_widgets('postero-contactform');
af_pd_opts('wpcf7%');

echo "\n=== GOOGLE REVIEWS EMBED ===\n";
af_pd_opts('grwp%'); af_pd_opts('google_reviews%');
foreach ($wpdb->get_col("SELECT option_name FROM {$P}options WHERE (option_name LIKE '\\_transient\\_grwp%' OR option_name LIKE '\\_transient\\_google%' OR option_name LIKE '%reviews_cache%') LIMIT 20") as $n) echo "  transient $n (" . strlen((string) get_option($n)) . " b): " . mb_substr(af_pd_json(maybe_unserialize(get_option($n))), 0, 1500) . "\n";

echo "\n=== INSTAGRAM FEED (insta-gallery) ===\n";
af_pd_opts('insta_gallery%');
foreach ($wpdb->get_col("SELECT option_name FROM {$P}options WHERE option_name LIKE '\\_transient\\_%insta%' OR option_name LIKE '\\_transient\\_%qligg%' LIMIT 20") as $n) echo "  transient $n (" . strlen((string) get_option($n)) . " b)\n";

echo "\n=== DYNAMIC VISIBILITY (elements carrying its rules) ===\n";
foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$P}postmeta WHERE meta_key = '_elementor_data' AND (meta_value LIKE '%enabled_visibility%' OR meta_value LIKE '%dce_visibility%')") as $r) {
    $walk = function ($els) use (&$walk, $r) { foreach ((array) $els as $el) { if (!is_array($el)) continue; $s = (array) ($el['settings'] ?? array());
        $hit = array_filter($s, function ($k) { return strpos($k, 'dce_') === 0 || strpos($k, 'enabled_visibility') === 0; }, ARRAY_FILTER_USE_KEY);
        if ($hit) echo "  #{$r->post_id} " . get_post_type($r->post_id) . " \"" . get_the_title($r->post_id) . "\" element {$el['id']} ({$el['elType']}" . (isset($el['widgetType']) ? ':' . $el['widgetType'] : '') . "): " . af_pd_json($hit) . "\n";
        if (!empty($el['elements'])) $walk($el['elements']); } };
    $walk(json_decode($r->meta_value, true));
}

echo "\n=== LANGUAGE SWITCHER / TRANSPOSH ===\n";
echo "  cfxlsft_options: " . af_pd_json(get_option('cfxlsft_options')) . "\n";
$tp = (array) get_option('transposh_options'); echo "  transposh: default=" . ($tp['default_language'] ?? '?') . " viewable=" . ($tp['viewable_languages'] ?? '?') . "\n";

echo "\n=== CODE SNIPPETS: execution facts ===\n";
echo "  CODE_SNIPPETS_FILE=" . (defined('CODE_SNIPPETS_FILE') ? 'defined' : 'no') . " code_snippets()=" . (function_exists('code_snippets') ? 'yes' : 'no') . " CODE_SNIPPETS_VERSION=" . (defined('CODE_SNIPPETS_VERSION') ? CODE_SNIPPETS_VERSION : '-') . "\n";
echo "\n=== END PORT DATA ===\n";
