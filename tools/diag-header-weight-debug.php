<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Why inc/header-weight.php leaves nothing out: renders the header here and logs what it sees. */
if (!defined('ABSPATH')) exit;
echo 'module loaded: ' . (function_exists('af_hw_active') ? 'yes' : 'NO') . "\n";
echo 'template ids: ' . (function_exists('af_hw_template_ids') ? implode(',', af_hw_template_ids()) : '-') . "\n";
echo 'devices: ' . (function_exists('af_home_elementor_devices') ? implode(',', af_home_elementor_devices()) : '-') . "\n";
$seen = 0;
add_action('elementor/frontend/before_render', function ($el) use (&$seen) {
    if ($seen++ > 400) return;
    $doc = \Elementor\Plugin::$instance->documents->get_current();
    $id = $el->get_id();
    if (in_array($id, array('cf35096', '44760a9', 'ac86c44', '81c88cb', 'c598fa8', '07f187b', '4d02281'), true) || $seen < 4) {
        $hide = array(); foreach (af_home_elementor_devices() as $d) if ($el->get_settings('hide_' . $d)) $hide[] = $d;
        echo "  before_render {$el->get_name()} #$id doc=" . ($doc ? $doc->get_main_id() : 'none') . ' active=' . (af_hw_active() ? 'y' : 'n') . ' own-hide=' . implode(',', $hide) . ' stack=' . implode(',', array_keys(array_filter(af_hw_stack()))) . "\n";
    }
}, 0);
add_filter('elementor/frontend/widget/should_render', function ($s, $el) {
    if (in_array($el->get_id(), array('cf35096', '44760a9', 'ac86c44', '81c88cb', 'c598fa8', '07f187b', '4d02281'), true)) echo "  should_render #{$el->get_id()} => " . var_export($s, true) . "\n";
    return $s;
}, 99, 2);
$_SERVER['REQUEST_URI'] = '/cart/';
$GLOBALS['wp']->main();
ob_start(); if (function_exists('hfe_render_header')) hfe_render_header(); $h = ob_get_clean();
printf("header rendered here: %d KB; not sent: %s\n", strlen($h) / 1024, implode(' ', af_hw_not_sent()));
