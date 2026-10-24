<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the pure parts of inc/header-weight.php with WordPress stubbed:
 *     php tools/test-header-weight.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
function apply_filters($h, $v) { return $v; }
require __DIR__ . '/../inc/header-weight.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }
class El { public $s; function __construct($s) { $this->s = $s; } function get_settings($k) { return isset($this->s[$k]) ? $this->s[$k] : ''; } }

// The site's devices (Elementor reported them on 24 Oct 2026).
$dev = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop');
$section_hides_phones = af_hw_merge_hidden(array(), new El(array('hide_mobile' => 'hidden-mobile', 'hide_mobile_extra' => 'hidden-mobile_extra')), $dev);
$widget_hides_rest = new El(array('hide_desktop' => 'hidden-desktop', 'hide_laptop' => 'hidden-laptop', 'hide_tablet' => 'hidden-tablet', 'hide_tablet_extra' => 'hidden-tablet_extra'));

echo "=== hidden by its section and its own switches together ===\n";
same('section hides phones + widget hides the rest = hidden everywhere', af_hw_hidden_everywhere(af_hw_merge_hidden($section_hides_phones, $widget_hides_rest, $dev), $dev), true);
same('section hides phones only = shown on bigger screens', af_hw_hidden_everywhere(af_hw_merge_hidden($section_hides_phones, new El(array()), $dev), $dev), false);
same('one width left showing = shown', af_hw_hidden_everywhere(af_hw_merge_hidden($section_hides_phones, new El(array('hide_desktop' => 'x', 'hide_laptop' => 'x', 'hide_tablet' => 'x')), $dev), $dev), false);
same('its own switches alone can hide it everywhere', af_hw_hidden_everywhere(af_hw_merge_hidden(array(), new El(array_fill_keys(array_map(function ($d) { return 'hide_' . $d; }, $dev), 'x')), $dev), $dev), true);
same('no devices known: never hidden', af_hw_hidden_everywhere(array('mobile' => true), array()), false);
same('a parent\'s hiding is kept, not undone, by a child', af_hw_merge_hidden($section_hides_phones, new El(array()), $dev), $section_hides_phones);

echo "\n=== only plain display widgets ===\n";
foreach (array('postero-nav-menu', 'postero-search', 'site-logo', 'icon-list', 'social-icons') as $t) same("$t may be left out", in_array($t, af_hw_safe_types(), true), true);
foreach (array('shortcode', 'html', 'template', 'postero-menu-canvas', 'postero-product-currency', 'postero-header-group') as $t) same("$t is always sent", in_array($t, af_hw_safe_types(), true), false);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
