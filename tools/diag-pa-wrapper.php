<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/** Read-only. The opening tag of every wrapper-link element on the home page, as rendered now (run with and without --skip-plugins=premium-addons-for-elementor). */
if (!defined('ABSPATH')) exit(1);
$GLOBALS['wp_query'] = new WP_Query(array('page_id' => 75)); $GLOBALS['wp_the_query'] = $GLOBALS['wp_query']; if (have_posts()) the_post();
$html = \Elementor\Plugin::instance()->frontend->get_builder_content(75, true);
echo 'PA plugin loaded: ' . (defined('PREMIUM_ADDONS_VERSION') ? 'yes' : 'no') . ', port: ' . (defined('AF_PA_PORT_VERSION') ? 'yes' : 'no') . "\n";
foreach (array('293f9b2', '58b2213', 'd005868', 'b627341', 'eb2186c') as $id) {
    if (preg_match('/<[a-z]+[^>]*data-id="' . $id . '"[^>]*>/', $html, $m)) echo "$id: " . preg_replace('/\s+/', ' ', $m[0]) . "\n"; else echo "$id: (not found)\n";
}
