<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Which header/footer templates Header Footer Elementor (plugin or theme port) picks for a visitor and for a logged-in customer, and the size of the rendered header. */
if (!defined('ABSPATH')) exit(1);
echo 'HFE plugin active: ' . (in_array('header-footer-elementor/header-footer-elementor.php', (array) get_option('active_plugins'), true) ? 'yes' : 'no') . ', theme port: ' . (defined('AF_HFE_PORT') ? 'yes' : 'no') . "\n";
$customer = get_users(array('role' => 'customer', 'number' => 1, 'fields' => 'ID'));
foreach (array('visitor' => 0, 'customer' => $customer ? (int) $customer[0] : 0) as $label => $uid) {
    wp_set_current_user($uid);
    $GLOBALS['wp_query'] = new WP_Query(array('page_id' => (int) get_option('page_on_front')));
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    // the target-rule class caches per request; clear it so each user is resolved afresh
    foreach (array('Astra_Target_Rules_Fields', 'HFE\\Lib\\Astra_Target_Rules_Fields') as $cls) {
        if (!class_exists($cls)) continue;
        $r = new ReflectionClass($cls);
        foreach (array('current_page_type' => null, 'current_page_data' => array()) as $prop => $val) {
            if ($r->hasProperty($prop)) { $p = $r->getProperty($prop); $p->setAccessible(true); $p->setValue(null, $val); }
        }
    }
    $h = function_exists('get_hfe_header_id') ? get_hfe_header_id() : 'n/a';
    $f = function_exists('get_hfe_footer_id') ? get_hfe_footer_id() : 'n/a';
    $b = function_exists('hfe_get_before_footer_id') ? hfe_get_before_footer_id() : 'n/a';
    ob_start(); if (function_exists('hfe_render_header')) hfe_render_header(); $html = ob_get_clean();
    echo "$label (user $uid): header=$h footer=$f before-footer=$b rendered-header=" . strlen($html) . " bytes\n";
}
