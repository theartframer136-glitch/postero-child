<?php
/**
 * Cart, compare, quick view and wishlist — in the rating row, icons only.
 *
 * All four controls already exist on every product card; they are rendered by
 * the theme and by the WPC plugins. What they lacked was somewhere sensible to
 * be. Measured on the live listing, they sat twenty pixels BELOW a box with
 * overflow:hidden (div.product-transition, 300px tall) waiting for a hover
 * that slid them up — so they were clipped out of existence, while reporting a
 * perfect 38x38 box and full opacity to every check. That is why they appeared
 * to be missing rather than misplaced.
 *
 * They now live in the card's rating row, to the right of the stars: one line,
 * icons only, each naming itself on hover. That row already exists
 * (div.product-action holding div.count-review), it is inside the caption
 * rather than the picture, and nothing there clips.
 *
 * The move is done in JavaScript and the layout written inline with priority.
 * That is not a flourish: this theme sets these positions inline itself, and a
 * stylesheet — however specific, however many !importants — measurably loses
 * to it. Four separate faults in this codebase have now come down to the same
 * thing.
 *
 * Nothing outside the product card is touched.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
    if (is_admin()) return;
    if (!function_exists('is_shop')) return;
    // The cart and wishlist "You may also like" rows print the same cards and
    // were outside this list, so their four buttons stayed as the theme leaves
    // them: floating over the artwork on hover instead of inline beside the
    // stars.
    if (!(is_shop() || is_product_taxonomy() || is_product() || is_front_page() || is_search()
          || (function_exists('af_cards_secondary_page') && af_cards_secondary_page()))) return;
    ?>
    <link rel="stylesheet" id="af-card-actions" href="<?php echo esc_url(af_static_url('card-actions.css')); ?>" data-no-optimize="1">
    <script id="af-card-actions-icons" src="<?php echo esc_url(af_static_url('card-actions-icons.js')); ?>" data-no-optimize="1"></script>
    <?php
}, 62);
