<?php
/**
 * Customer reviews: the plugin "Customer Reviews for WooCommerce" 5.123.0
 * (customer-reviews-woocommerce), moved into the theme.
 *
 * Settings: only ivole_activation_notice, ivole_siteurl and ivole_version are
 * stored, so every setting is the plugin's default. With the defaults the
 * plugin does NOT replace the WooCommerce reviews tab (no histogram, no AJAX
 * list, no voting, no media upload, no captcha, no Q&A tab, no review
 * reminders, no coupons, no trust badges). What it does do, and what this port
 * does the same way with the plugin's own code (inc/ports/cusrev/, mirroring
 * the plugin's folders; every changed line there is marked "port:"):
 *
 * - Every front-end page: frontend.css + badges.css in the head, frontend.js
 *   (with cr_ajax_object) + colcade.js in the footer, through
 *   enqueue_block_assets. frontend.css also carries un-prefixed .slick-* rules,
 *   a @font-face for "slick" and #reviews .comment_container { position:
 *   relative }, which reach other sliders and the review list.
 * - Product pages: PhotoSwipe + the full cr_ajax_object; the review meta line
 *   comes from the plugin's review-meta.php (adds "(store manager)" for staff)
 *   instead of WooCommerce's; review title / "Featured Review", country flag,
 *   custom-question answers, review tags and review photos and videos (comment
 *   meta ivole_*) around the review text; the lightbox markup when the theme
 *   has no WooCommerce lightbox; CusRev-hosted review photos in the review
 *   structured data; Q&A comments (type cr_qna) kept out of comment queries.
 * - Other singular pages (home page, pages, posts, cart, checkout):
 *   PhotoSwipe, the plugin's CSS/JS and the lightbox markup in the footer.
 * - Checkout: the "Would you like to be invited to review your order?"
 *   checkbox (ivole_customer_consent defaults to 'yes'), saved to the order as
 *   _ivole_cr_consent, including the Klarna and block-checkout variants.
 * - Orders: a private note "CR: a review reminder was cancelled ..." when an
 *   order is refunded or cancelled; the cr_referral_session cookie copied to
 *   new orders.
 * - Everywhere: the cr_tag taxonomy for comments, the ivrating, crsearch and
 *   referral_session query vars, cr_qna typing of replies to questions,
 *   deleting a review's uploaded media with the review, [cusrev_reviews], and
 *   the guest AJAX action cr_auto_download_media_frontend (frontend.js calls it
 *   to copy CusRev-hosted review media into the Media Library).
 *
 * Assets are copied byte for byte to assets/ports/cusrev/ with the plugin's
 * folder layout, under the same handles and version (5.123.0).
 *
 * Left out (no effect with the default settings, or admin only): the admin
 * menus, settings, notices, import/export, diagnostics and product-feed
 * screens and the product-editor GTIN/MPN/brand fields; review reminders
 * (scheduling and sending, the local review forms at /cusrev/..., WhatsApp),
 * coupons, the CusRev REST endpoints (ivole/v1/review, ivole/v1/review-reply),
 * XML feeds and their cron schedule, the shortcodes and blocks other than
 * [cusrev_reviews] (none is used anywhere), the other 12 guest AJAX actions
 * (they serve only the plugin's own review templates and forms, which are
 * off), the email review button, qTranslate, the HPOS declaration and the
 * translations.
 *
 * Settings: the port is built for the defaults above. The few options the kept
 * code reads still work as in the plugin (ivole_disable_lightbox,
 * ivole_customer_consent and its text, ivole_verified_owner); any other
 * ivole_* option has no effect here. To change settings, switch the plugin
 * back on (this file then steps aside again).
 *
 * While the plugin is still active this file does nothing.
 */
defined('ABSPATH') || exit;

if (function_exists('ivole_get_site_url') || class_exists('Ivole', false)) {
    return; // the plugin is active and does the work
}

if (!defined('AF_CUSREV_VERSION')) {
    define('AF_CUSREV_VERSION', '5.123.0'); // Ivole::CR_VERSION
    define('AF_CUSREV_URL', get_stylesheet_directory_uri() . '/assets/ports/cusrev/');
}

require_once __DIR__ . '/cusrev/cusrev.php';
