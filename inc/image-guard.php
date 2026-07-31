<?php
/**
 * Casual-save deterrent for product imagery  (requirements §9)
 *
 * Right-click "Save image as" and drag-to-desktop are blocked on product
 * cards, product galleries and the digital-download modal. This is a
 * deterrent, not cryptography — screenshots always exist — but it closes
 * the exact two-click flow shown in the recording, and the modal now only
 * ever holds a watermarked preview anyway.
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    if (is_admin()) return;
    ?>
    <style>
    li.product img, .product-card img, .woocommerce-product-gallery img,
    .af-dd-imgwrap img, .af-shell-track img{-webkit-user-drag:none;}
    </style>
    <script>
    (function(){
      var GUARD = 'li.product, .product-card, .woocommerce-product-gallery, .af-dd-imgwrap, .af-shell-track';
      document.addEventListener('contextmenu', function(e){
        if (e.target && e.target.tagName === 'IMG' && e.target.closest(GUARD)) e.preventDefault();
      });
      document.addEventListener('dragstart', function(e){
        if (e.target && e.target.tagName === 'IMG' && e.target.closest(GUARD)) e.preventDefault();
      });
    })();
    </script>
    <?php
}, 67);
