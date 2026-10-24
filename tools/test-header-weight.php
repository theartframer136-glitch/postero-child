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

// The site's devices (Elementor reported them on 24 Oct 2026).
$dev = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop');
$hide = function (array $devs) { $s = array(); foreach ($devs as $d) $s['hide_' . $d] = 'hidden-' . $d; return $s; };
$w = function ($id, $type, $settings = array()) { return array('id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings); };
$c = function ($id, $settings, $children) { return array('id' => $id, 'elType' => 'container', 'settings' => $settings, 'elements' => $children); };
$rest = array('tablet', 'tablet_extra', 'laptop', 'desktop');
$tree = array(
    $c('phones-off', $hide(array('mobile', 'mobile_extra')), array(
        $w('menu2', 'postero-nav-menu', $hide($rest)),       // section hides phones, itself the rest: never shown
        $w('menu1', 'postero-nav-menu'),                      // shown on bigger screens
        $w('code', 'shortcode', $hide($rest)),                // never shown, but a shortcode: always sent
        $w('canvas', 'postero-menu-canvas', $hide($rest)),    // never shown, but carries the side menu: always sent
        $c('inner', $hide(array('desktop', 'laptop')), array(
            $w('logo2', 'site-logo', $hide(array('tablet', 'tablet_extra'))),  // three layers together: never shown
            $w('logo1', 'site-logo'),                                          // tablets still see it
        )),
    )),
    $w('search', 'postero-search', $hide($dev)),              // its own switches alone
);
echo "=== which widgets nobody can see ===\n";
same('found from the settings, counting the sections around them', af_hw_hidden_widget_ids($tree, $dev, af_hw_safe_types()), array('menu2', 'logo2', 'search'));
same('no devices known: nothing', af_hw_hidden_widget_ids($tree, array(), af_hw_safe_types()), array());
same('hidden everywhere needs every device', af_hw_hidden_everywhere(array('mobile' => true), $dev), false);

echo "\n=== only plain display widgets ===\n";
foreach (array('postero-nav-menu', 'postero-search', 'site-logo', 'icon-list', 'social-icons') as $t) same("$t may be left out", in_array($t, af_hw_safe_types(), true), true);
foreach (array('shortcode', 'html', 'template', 'postero-menu-canvas', 'postero-product-currency', 'postero-header-group') as $t) same("$t is always sent", in_array($t, af_hw_safe_types(), true), false);

echo "\n=== taking a widget out of the page ===\n";
$wid = function ($id, $inner) { return '<div class="elementor-element elementor-element-' . $id . ' elementor-widget" data-id="' . $id . '" data-element_type="widget" data-widget_type="x.default"><div class="elementor-widget-container">' . $inner . '</div></div>'; };
$page = '<html><body><header id="masthead"><div class="e-con">' . $wid('aaa', '<ul><li><div>deep</div></li></ul>') . $wid('bbb', 'kept') . '</div></header><main>' . $wid('aaa', 'not the header') . '</main><footer itemtype="x" id="colophon">' . $wid('ccc', 'f') . '</footer></body></html>';
$out = af_hw_filter_page($page, array('aaa', 'ccc'));
same('the header copy is gone, nested divs and all', strpos($out, 'deep'), false);
same('its neighbour stays, whole', strpos($out, $wid('bbb', 'kept')) !== false, true);
same('the header\'s own wrapper still closes', strpos($out, '<div class="e-con">' . $wid('bbb', 'kept') . '</div></header>') !== false, true);
same('the same id outside the header and footer is not touched', strpos($out, 'not the header') !== false, true);
same('the footer copy is gone too', strpos($out, $wid('ccc', 'f')), false);
same('a note says what was left out', strpos($out, '<!-- af-header-weight: not sent, hidden at every width: aaa ccc -->') !== false, true);
same('a widget holding a script is never cut', af_hw_strip_widget($wid('sss', '<script>var a="<div>";</script>'), 'sss'), $wid('sss', '<script>var a="<div>";</script>'));
same('an id not on the page: unchanged, no note', af_hw_filter_page($page, array('zzz')), $page);
same('an unclosed widget is left alone', af_hw_strip_widget('<header id="masthead"><div data-id="u" data-element_type="widget"><div>', 'u'), '<header id="masthead"><div data-id="u" data-element_type="widget"><div>');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
