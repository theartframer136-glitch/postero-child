<?php
/**
 * Whole-banner click-through for the homepage "Discover Original Works of
 * Artists" section. The Elementor data now carries real links on the button
 * and images (tools/link-artist-banner.php); this makes the ENTIRE banner a
 * click target — heading, empty space, collage gaps — the way visitors in
 * the recording expected it to behave. Real links inside keep working
 * normally (and middle-click/new-tab still comes from them).
 */
if (!defined('ABSPATH')) exit;

add_action('wp_footer', function() {
    if (is_admin() || !is_front_page()) return;
    $term = get_term_by('slug', 'direct-from-artists', 'product_cat');
    $dest = $term ? get_term_link($term) : home_url('/product-category/direct-from-artists/');
    if (is_wp_error($dest)) $dest = home_url('/product-category/direct-from-artists/');
    ?>
    <script>
    (function(){
      var DEST = <?php echo wp_json_encode($dest); ?>;
      function findBanner(){
        var nodes = document.querySelectorAll('h1,h2,h3,h4,.elementor-heading-title');
        for (var i = 0; i < nodes.length; i++) {
          if (/discover\s+original\s+works/i.test(nodes[i].textContent || '')) {
            return nodes[i].closest('.elementor-section, .e-con, section') || nodes[i].parentElement;
          }
        }
        // the text may be baked into the banner artwork itself — find it by alt
        var imgs = document.images;
        for (var j = 0; j < imgs.length; j++) {
          var alt = (imgs[j].alt || '') + ' ' + (imgs[j].src || '');
          if (/discover[-_\s]?original|works[-_\s]?of[-_\s]?artists?/i.test(alt)) {
            return imgs[j].closest('.elementor-section, .e-con, section') || imgs[j].parentElement;
          }
        }
        return null;
      }
      function arm(){
        var banner = findBanner();
        if (!banner || banner.dataset.afLinked) return;
        banner.dataset.afLinked = '1';
        banner.style.cursor = 'pointer';
        banner.setAttribute('role', 'link');
        banner.setAttribute('aria-label', 'Discover original works of artists');
        banner.addEventListener('click', function(e){
          if (e.target.closest('a, button, input, select')) return;   // real controls win
          window.location.href = DEST;
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arm);
      else arm();
      setTimeout(arm, 800);       // Elementor sometimes hydrates late
    })();
    </script>
    <?php
}, 66);
