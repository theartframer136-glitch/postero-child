<?php
/**
 * Click to Chat (WhatsApp) -> retired, with the one side effect kept.
 *
 * The plugin printed a floating WhatsApp bubble on every page, but the theme
 * hides it everywhere (functions.php, "A second, plugin-supplied WhatsApp
 * bubble sits in the same corner as ours": .ht-ctc.ht-ctc-chat {display:none
 * !important}) and shows its own button in the quick-access panel. Live
 * settings read 3 Oct 2026: style 3, "Join Our Community", no shortcode in
 * use, no WooCommerce product/shop button. So no visitor ever saw it; without
 * the plugin the hidden markup and its two files simply are not sent.
 *
 * Kept: the plugin made shortcodes run inside text widgets (widget_text ->
 * do_shortcode); a text widget relying on that keeps working.
 *
 * Only while the plugin is off.
 */
if (!defined('ABSPATH')) exit;
if (defined('HT_CTC_VERSION')) return;

add_filter('widget_text', 'do_shortcode');
