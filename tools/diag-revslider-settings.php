<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. Slider Revolution's load settings and its sliders. */
if (!defined('ABSPATH')) exit;
$gs = get_option('revslider-global-settings', '');
if (!is_array($gs)) $gs = json_decode((string) $gs, true);
echo 'allinclude: ' . var_export(isset($gs['allinclude']) ? $gs['allinclude'] : '(unset)', true) . "\n";
echo 'includeids: ' . var_export(isset($gs['includeids']) ? $gs['includeids'] : '(unset)', true) . "\n";
echo 'rev-slider-widget active: ' . var_export((bool) is_active_widget(false, false, 'rev-slider-widget', true), true) . "\n";
global $wpdb;
foreach ($wpdb->get_results("SELECT id, alias, title FROM {$wpdb->prefix}revslider_sliders") as $s) echo "slider #{$s->id} {$s->alias} ({$s->title})\n";
foreach (array('rev_slider', 'sr7', 'slider_revolution') as $n) {
    $l = '%' . $wpdb->esc_like($n) . '%';
    foreach ($wpdb->get_results($wpdb->prepare("SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status IN ('publish','private') LIMIT 10", $l)) as $p) echo "  content '$n': #{$p->ID} {$p->post_type} {$p->post_status} {$p->post_title}\n";
    foreach ($wpdb->get_results($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_elementor_data' AND meta_value LIKE %s LIMIT 10", $l)) as $p) echo "  elementor '$n': #{$p->post_id} " . get_post_type($p->post_id) . ' ' . get_post_status($p->post_id) . ' ' . get_the_title($p->post_id) . "\n";
}
