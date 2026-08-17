<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * The stretcher-bar products in full: every price, including variations.
 *
 * The catalogue sweep showed most of these with a blank price, which for a
 * VARIABLE product simply means the price lives on its variations. Print each
 * product's type, price range and every variation with its own attributes and
 * price, so the DIY kit can be priced from what the store already sells rather
 * than from numbers invented here.
 *
 * Read-only. Run: wp eval-file tools/diag-stretcher-bars.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!function_exists('wc_get_product')) { echo "WooCommerce not loaded\n"; return; }

echo "=== STRETCHER BARS AND FRAME KITS: FULL PRICING ===\n";

$cats = array('canvas-stretcher-bars', 'diy-canvas-stretcher-bars', 'diy-floating-frames',
              'art-accessories', 'wooden-frames', 'premium-aluminium-frames', 'framed-white-canvas');
$seen = array();

foreach ($cats as $slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term || is_wp_error($term)) { printf("\n-- %s: category not found --\n", $slug); continue; }
    $ids = get_posts(array('post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => 40, 'fields' => 'ids', 'no_found_rows' => true,
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'term_id',
            'terms' => (int) $term->term_id, 'include_children' => false))));
    printf("\n-- %s (%d products) --\n", $term->name, count($ids));
    foreach ($ids as $pid) {
        if (isset($seen[$pid])) { printf("  #%-6d (listed above)\n", $pid); continue; }
        $seen[$pid] = true;
        $p = wc_get_product($pid);
        if (!$p) continue;
        printf("\n  #%-6d %s\n", $pid, mb_strimwidth($p->get_name(), 0, 76, '…'));
        printf("      type: %-10s price: %-12s regular: %-10s stock: %s\n",
            $p->get_type(),
            $p->get_price() === '' ? '(none)' : ('$' . $p->get_price()),
            $p->get_regular_price() === '' ? '(none)' : ('$' . $p->get_regular_price()),
            $p->get_stock_status());
        $dims = trim(sprintf('%s x %s x %s', $p->get_length(), $p->get_width(), $p->get_height()), ' x');
        if ($dims !== '' && $dims !== 'x x') printf("      dims: %s   weight: %s\n", $dims, $p->get_weight());
        if ($p->is_type('variable')) {
            $vars = $p->get_available_variations();
            printf("      %d variations:\n", count($vars));
            foreach (array_slice($vars, 0, 25) as $v) {
                $attrs = array();
                foreach ((array) $v['attributes'] as $k => $val) {
                    $attrs[] = str_replace('attribute_', '', $k) . '=' . $val;
                }
                printf("          %-56s $%s\n",
                    mb_strimwidth(implode(', ', $attrs), 0, 56), $v['display_price']);
            }
        }
    }
}

echo "\n=== DONE ===\n";
