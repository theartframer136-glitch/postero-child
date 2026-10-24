<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Tests the pure parts of inc/home-hero.php with WordPress stubbed:
 *     php tools/test-home-hero.php
 */
define('ABSPATH', '/nowhere/');
function add_action() {} function add_filter() {}
require __DIR__ . '/../inc/home-hero.php';

$pass = 0; $fail = 0;
function same($label, $got, $want) { global $pass, $fail;
    if ($got === $want) { $pass++; echo "  OK    $label\n"; }
    else { $fail++; echo "  FAIL  $label\n        got  " . var_export($got, true) . "\n        want " . var_export($want, true) . "\n"; } }

// The site's breakpoints, as Elementor reported them on 24 Oct 2026.
$r = af_hero_device_ranges(array('mobile' => 767, 'mobile_extra' => 880, 'tablet' => 1024, 'tablet_extra' => 1200, 'laptop' => 1366));
$all = array('mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop', 'desktop');

echo "=== which screens ===\n";
same('nothing hidden: every screen', af_hero_media($r, array()), 'all');
same('hidden on phones only', af_hero_media($r, array('mobile')), '(min-width: 768px)');
same('hidden on phones and small tablets', af_hero_media($r, array('mobile', 'mobile_extra')), '(min-width: 881px)');
same('shown on phones and small tablets only', af_hero_media($r, array('tablet', 'tablet_extra', 'laptop', 'desktop')), '(max-width: 880px)');
same('shown on tablets only', af_hero_media($r, array('mobile', 'mobile_extra', 'laptop', 'desktop')), '(min-width: 881px) and (max-width: 1200px)');
same('two separate ranges', af_hero_media($r, array('tablet', 'tablet_extra')), '(max-width: 880px), (min-width: 1201px)');
same('hidden everywhere: none', af_hero_media($r, $all), '');
same('only the two default breakpoints', af_hero_media(af_hero_device_ranges(array('mobile' => 767, 'tablet' => 1024)), array('mobile')), '(min-width: 768px)');
same('widescreen starts at its own value', af_hero_media(af_hero_device_ranges(array('mobile' => 767, 'widescreen' => 2400)), array('widescreen')), '(max-width: 2399px)');

echo "\n=== first slides ===\n";
$slides = function ($id, $urls) { $s = array(); foreach ($urls as $u) $s[] = array('background_image' => array('url' => $u)); return array('id' => $id, 'elType' => 'widget', 'widgetType' => 'slides', 'settings' => array('slides' => $s)); };
$data = array(
    array('id' => 'a', 'elType' => 'container', 'settings' => array('hide_mobile' => 'hidden-mobile', 'hide_mobile_extra' => 'hidden-mobile_extra'),
          'elements' => array($slides('w1', array('https://x.us/Banner-1-1.webp', 'https://x.us/BAnner-2-1.webp')))),
    array('id' => 'b', 'elType' => 'container', 'settings' => array('hide_desktop' => 'hidden-desktop', 'hide_laptop' => 'hidden-laptop', 'hide_tablet_extra' => 'hidden-tablet_extra', 'hide_tablet' => 'hidden-tablet'),
          'elements' => array($slides('w2', array('https://x.us/banner-1.webp')))),
    array('id' => 'c', 'elType' => 'container', 'settings' => array_fill_keys(array_map(function ($d) { return 'hide_' . $d; }, $all), 'yes'),
          'elements' => array($slides('w3', array('https://x.us/hidden.webp')))),
);
same('one per slideshow, the first slide, for the screens it shows on', af_hero_first_slides($data, $r), array(
    array('https://x.us/Banner-1-1.webp', '(min-width: 881px)'),
    array('https://x.us/banner-1.webp', '(max-width: 880px)'),
));
same('a slideshow with no picture is skipped', af_hero_first_slides(array($slides('w', array())), $r), array());
same('a widget\'s own setting counts too', af_hero_first_slides(array(array('id' => 'w', 'widgetType' => 'slides', 'settings' => array('hide_mobile' => 'x', 'slides' => array(array('background_image' => array('url' => 'u')))))), $r), array(array('u', '(min-width: 768px)')));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
