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

// inc/analytics.php sends the shop's ecommerce events to this same tag when
// no ID is saved in its own setting, so page views are not counted twice.
define('AF_HFCM_GA4_ID', 'G-F3PB80TT6Y');

add_action('wp_head', function () {
    if (is_feed()) return;
    // The tag is added by script rather than as a static <script src>, so the
    // automated visitors af_bot_signal_js() describes (87% of GA4's "users" on
    // 16 Oct) never load it. gtag() always exists, so the ecommerce events
    // inc/analytics.php reports can be called either way; for a bot they go
    // nowhere.
    $bot = function_exists('af_bot_signal_js') ? af_bot_signal_js()
         : 'navigator.webdriver||(screen.width==800&&screen.height==600)||(screen.width==1280&&screen.height==1200)';
    echo "<!-- HFCM by 99 Robots - Snippet # 1: Google Analytics -->\n"
        . '<!-- Google tag (gtag.js) -->' . "\n"
        . '<script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};'
        . 'if(!(' . $bot . ')){var afg=document.createElement("script");afg.async=true;afg.src="https://www.googletagmanager.com/gtag/js?id=G-F3PB80TT6Y";document.head.appendChild(afg);'
        . "gtag('js',new Date());gtag('config','G-F3PB80TT6Y');}</script>"
        . "\n<!-- /end HFCM by 99 Robots -->\n";
});
