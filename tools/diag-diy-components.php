<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Does the store ALREADY sell the DIY kit parts?
 *
 * Before asking the owner for prices, look for what is already priced in the
 * catalogue: structure/stretcher bars, hooks, screws, screwdrivers, hanging
 * wire, and shipping tubes. Also report the option sets the product page
 * offers today (sizes, frame types, frame colours) so the five purchase paths
 * can be built on what exists rather than invented.
 *
 * Read-only. Run: wp eval-file tools/diag-diy-components.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

echo "=== DIY COMPONENTS: WHAT THE STORE ALREADY HAS ===\n";

// ── 1. products whose name suggests a kit part ──
echo "\n-- catalogue search --\n";
$terms = array(
    'structure bar' => 'structure',
    'stretcher'     => 'stretcher',
    'bar'           => 'bar',
    'hook'          => 'hook',
    'screw'         => 'screw',
    'driver'        => 'driver',
    'hanging'       => 'hang',
    'wire'          => 'wire',
    'tube'          => 'tube',
    'pipe'          => 'pipe',
    'kit'           => 'kit',
    'diy'           => 'diy',
);
foreach ($terms as $label => $needle) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title, post_status FROM {$wpdb->posts}
          WHERE post_type IN ('product','product_variation')
            AND post_status IN ('publish','draft','private')
            AND post_title LIKE %s
          LIMIT 8", '%' . $wpdb->esc_like($needle) . '%'));
    if (!$rows) { printf("  %-14s none\n", $label); continue; }
    printf("  %-14s %d found\n", $label, count($rows));
    foreach ($rows as $r) {
        $p = function_exists('wc_get_product') ? wc_get_product($r->ID) : null;
        printf("      #%-6d %-58s %s %s\n", $r->ID,
            mb_strimwidth($r->post_title, 0, 58, '…'),
            $p ? ('$' . $p->get_price()) : '',
            $r->post_status !== 'publish' ? ('[' . $r->post_status . ']') : '');
    }
}

// ── 2. categories that might hold accessories ──
echo "\n-- accessory categories --\n";
foreach ((array) get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false)) as $t) {
    if (is_wp_error($t)) continue;
    if (!preg_match('/accessor|frame|diy|kit|supply|hardware/i', $t->name . ' ' . $t->slug)) continue;
    printf("  %-30s %-28s %d products\n", $t->name, $t->slug, (int) $t->count);
}

// ── 3. what the product page offers today ──
echo "\n-- options the product page offers --\n";
if (function_exists('af_pricing_config')) {
    $cfg = af_pricing_config();
    if (!empty($cfg['sizes'])) {
        echo "  sizes:\n";
        foreach ($cfg['sizes'] as $name => $price) printf("      %-22s $%s\n", $name, $price);
    }
    if (!empty($cfg['frames'])) {
        echo "  frame types:\n";
        foreach ($cfg['frames'] as $name => $fee) printf("      %-22s +$%s\n", $name, $fee);
    }
    if (!empty($cfg['colors'])) {
        echo "  frame colours:\n";
        foreach ($cfg['colors'] as $name => $fee) printf("      %-22s +$%s\n", $name, $fee);
    }
} else {
    echo "  af_pricing_config() not available\n";
}

// ── 4. is anything already marked as a downloadable/digital variant? ──
echo "\n-- digital / downloadable products --\n";
$dl = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}
    WHERE meta_key = '_downloadable' AND meta_value = 'yes'");
$vt = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta}
    WHERE meta_key = '_virtual' AND meta_value = 'yes'");
printf("  downloadable: %d    virtual: %d\n", (int) $dl, (int) $vt);
$sample = $wpdb->get_results("SELECT p.ID, p.post_title FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
    WHERE m.meta_key = '_downloadable' AND m.meta_value = 'yes'
      AND p.post_type = 'product' AND p.post_status = 'publish' LIMIT 3");
foreach ($sample as $s) {
    $p = wc_get_product($s->ID);
    printf("      #%-6d %-50s virtual:%s needs_shipping:%s\n", $s->ID,
        mb_strimwidth($s->post_title, 0, 50, '…'),
        ($p && $p->is_virtual()) ? 'yes' : 'no',
        ($p && $p->needs_shipping()) ? 'yes' : 'no');
}

echo "\n=== DONE ===\n";
