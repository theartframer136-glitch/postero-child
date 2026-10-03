<?php
/**
 * Header Footer Code Manager -> theme code.
 *
 * The plugin held one active snippet on the live site (read 3 Oct 2026):
 * #1 "Google Analytics", the GA4 tag G-F3PB80TT6Y, in the head of every page
 * on every device. Printed exactly as the plugin printed it (its comment
 * markers, the snippet as stored with Windows line endings, in wp_head, not
 * in feeds), and, like it, outside the cookie-consent gate that
 * inc/analytics.php applies to the theme's own analytics: what visitors'
 * browsers receive does not change.
 *
 * Only while the plugin is off.
 */
if (!defined('ABSPATH')) exit;
if (class_exists('NNR_HFCM')) return;

add_action('wp_head', function () {
    if (is_feed()) return;
    echo "<!-- HFCM by 99 Robots - Snippet # 1: Google Analytics -->\n"
        . '<!-- Google tag (gtag.js) -->' . "\r\n" . '<script async src="https://www.googletagmanager.com/gtag/js?id=G-F3PB80TT6Y"></script>' . "\r\n" . '<script>' . "\r\n" . '  window.dataLayer = window.dataLayer || [];' . "\r\n" . '  function gtag(){dataLayer.push(arguments);}' . "\r\n" . '  gtag(\'js\', new Date());' . "\r\n" . '' . "\r\n" . '  gtag(\'config\', \'G-F3PB80TT6Y\');' . "\r\n" . '</script>'
        . "\n<!-- /end HFCM by 99 Robots -->\n";
});
