<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put the distance-based delivery method into the store's shipping zones.
 *
 * Owner's instruction (2026-08-17): delivery is charged by distance from ZIP
 * 19707. So the af_distance method is added to every zone that covers the US
 * (or to the catch-all zone if none does), and any Free Shipping method in
 * those zones is DISABLED — not deleted — because a free-shipping row beside a
 * distance charge would always win and the charge would never apply.
 *
 * Reversible: the ids of the free-shipping instances that were turned off are
 * stored in the af_freeship_disabled option; re-enable them in WooCommerce →
 * Settings → Shipping at any time. Idempotent — a second run changes nothing.
 *
 * Run: wp eval-file tools/setup-distance-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
if (!class_exists('WC_Shipping_Zones')) { echo "WooCommerce not loaded\n"; return; }
global $wpdb;

echo "=== DISTANCE SHIPPING SETUP ===\n";

$zones = WC_Shipping_Zones::get_zones();
$targets = array();
foreach ($zones as $z) {
    $covers_us = false;
    foreach ((array) $z['zone_locations'] as $loc) {
        if ($loc->type === 'country' && $loc->code === 'US') $covers_us = true;
        if ($loc->type === 'state' && strpos($loc->code, 'US:') === 0) $covers_us = true;
    }
    if ($covers_us) $targets[] = WC_Shipping_Zones::get_zone($z['id']);
}
if (!$targets) {
    echo "  no zone covers the US — using the catch-all zone\n";
    $targets[] = WC_Shipping_Zones::get_zone(0);
}

$disabled = get_option('af_freeship_disabled', array());
if (!is_array($disabled)) $disabled = array();

foreach ($targets as $zone) {
    $name = $zone->get_zone_name() ? $zone->get_zone_name() : 'Rest of the world';
    $methods = $zone->get_shipping_methods();
    $has_distance = false;
    foreach ($methods as $m) {
        if ($m->id === 'af_distance') $has_distance = true;
    }
    if (!$has_distance) {
        $iid = $zone->add_shipping_method('af_distance');
        printf("  zone %-22s + af_distance (instance %d)\n", $name, (int) $iid);
    } else {
        printf("  zone %-22s af_distance already present\n", $name);
    }
    foreach ($methods as $m) {
        if ($m->id === 'free_shipping' && $m->enabled === 'yes') {
            $wpdb->update("{$wpdb->prefix}woocommerce_shipping_zone_methods",
                array('is_enabled' => 0),
                array('instance_id' => (int) $m->instance_id));
            $disabled[] = (int) $m->instance_id;
            printf("  zone %-22s free_shipping instance %d DISABLED (kept, not deleted)\n",
                   $name, (int) $m->instance_id);
        }
    }
}
update_option('af_freeship_disabled', array_values(array_unique($disabled)), false);

if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
delete_transient('shipping-transient-version');
// WooCommerce caches computed package rates in session/transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_ship%'");

echo "=== DONE ===\n";
