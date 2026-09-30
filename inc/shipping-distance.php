<?php
/**
 * Delivery from the studio: ZIP 19707, Hockessin, Delaware.
 *
 * The owner's requirement: orders ship FROM 19707 and the delivery charge is
 * what UPS charges to carry them. This file knows WHERE the customer is —
 * the straight-line miles from the studio — and registers the WooCommerce
 * shipping method; the pricing itself is the UPS calculation in
 * inc/shipping-ups.php (zone, billable weight, rate card, surcharges, fuel).
 *
 * How the distance is known: a one-time table of every US ZIP code's centre
 * point (latitude/longitude, US Census ZCTA data, public domain) is loaded by
 * tools/setup-zip-distance.php. The miles between 19707 and the customer's
 * ZIP pick the UPS zone until the studio's own UPS zone chart is loaded
 * (tools/setup-ups-zones.php), after which the chart wins.
 *
 * History: until 30 Sep 2026 this file priced by its own distance tiers
 * (option af_distance_tiers, a handling base plus a per-pound rate per band).
 * That option is no longer read; the UPS figures live in the af_ups_*
 * options described in inc/shipping-ups.php.
 */
if (!defined('ABSPATH')) exit;

define('AF_SHIP_ORIGIN_ZIP', '19707');

function af_zip_geo_table() {
    global $wpdb;
    return $wpdb->prefix . 'af_zip_geo';
}

function af_zip_latlng($zip) {
    global $wpdb;
    static $cache = array();
    $zip = substr(preg_replace('/\D/', '', (string) $zip), 0, 5);
    if (strlen($zip) !== 5) return null;
    if (array_key_exists($zip, $cache)) return $cache[$zip];
    $t = af_zip_geo_table();
    $row = $wpdb->get_row($wpdb->prepare("SELECT lat, lng FROM {$t} WHERE zip = %s", $zip));
    return $cache[$zip] = ($row ? array((float) $row->lat, (float) $row->lng) : null);
}

/** Straight-line miles between two US ZIPs; null when either is unknown. */
function af_zip_distance_miles($from, $to) {
    $a = af_zip_latlng($from);
    $b = af_zip_latlng($to);
    if (!$a || !$b) return null;
    $la1 = deg2rad($a[0]); $la2 = deg2rad($b[0]);
    $dla = $la2 - $la1;
    $dlo = deg2rad($b[1] - $a[1]);
    $h = sin($dla / 2) ** 2 + cos($la1) * cos($la2) * sin($dlo / 2) ** 2;
    return 2 * 3958.8 * asin(min(1, sqrt($h)));
}

/**
 * How many pieces share one parcel. A tube holds several rolled prints; a
 * flat crate takes a few pieces stacked, which grows its depth and nothing
 * else. Filterable because these are physical limits the studio knows better
 * than this file does.
 */
function af_ship_parcel_capacity($method) {
    $caps = array('tube' => 5, 'crate' => 4, 'other' => 1);
    $cap  = isset($caps[$method]) ? $caps[$method] : 1;
    return max(1, (int) apply_filters('af_ship_parcel_capacity', $cap, $method));
}

/**
 * Billable pounds across the order's parcels and the count of parcels.
 * Kept for the verifier and for anything that only wants the weight; the
 * parcels themselves (DEF-03: bill for parcels, not for pieces) come from
 * af_ups_parcels() in inc/shipping-ups.php.
 *
 * @return array [ billable pounds, count of parcels ]
 */
function af_distance_package_weight($package) {
    if (!function_exists('af_ups_parcels')) return array(0.0, 0);
    $lbs = 0.0;
    $parcels = af_ups_parcels($package);
    foreach ($parcels as $p) $lbs += (float) $p['billable'];
    return array($lbs, count($parcels));
}

add_action('woocommerce_shipping_init', function () {
    if (!class_exists('WC_Shipping_Method') || class_exists('AF_Distance_Shipping')) return;

    class AF_Distance_Shipping extends WC_Shipping_Method {
        public function __construct($instance_id = 0) {
            // The id stays 'af_distance' so the instances already sitting in
            // the shipping zones keep working; only the pricing behind it moved.
            $this->id                 = 'af_distance';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = 'UPS Ground delivery (from ZIP 19707)';
            $this->method_description = 'Charges what UPS Ground charges from the Hockessin, DE studio '
                                      . 'to the customer address: zone, billable weight, surcharges and '
                                      . 'fuel. Live UPS quote when API credentials are set; otherwise the '
                                      . 'published-rate model in inc/shipping-ups.php (options af_ups_*).';
            $this->supports           = array('shipping-zones', 'instance-settings',
                                              'instance-settings-modal');
            $this->instance_form_fields = array(
                'title' => array(
                    'title'   => 'Label shown at checkout',
                    'type'    => 'text',
                    'default' => 'UPS Ground',
                ),
            );
            $this->title = $this->get_option('title', 'UPS Ground');
        }

        public function calculate_shipping($package = array()) {
            if (!function_exists('af_ups_quote')) return;
            $quote = af_ups_quote($package);
            // nothing physical in the basket: a download-only order pays no
            // delivery at all, so no rate is offered
            if (!$quote || !isset($quote['cost'])) return;

            // What the number is made of travels with the order line, so the
            // owner can hold it against the UPS invoice. The customer sees
            // only the label: the zone and the parcel list are how this
            // method works, not something they ordered.
            $meta = array(
                'UPS zone'    => (string) $quote['zone'],
                'Rate source' => ($quote['source'] === 'api') ? 'UPS Rating API' : 'published-rate table',
                'Parcels'     => count($quote['parcels']),
            );
            $i = 0;
            foreach ($quote['parcels'] as $p) {
                $i++;
                $meta['Parcel ' . $i] = sprintf('%s %dx%dx%d in, %d lb billable, $%.2f',
                    $p['method'], round($p['l']), round($p['w']), round($p['h']),
                    $p['charge']['billable'], $p['charge']['total']);
            }

            $this->add_rate(array(
                'id'        => $this->get_rate_id(),
                'label'     => $this->title,
                'cost'      => (float) $quote['cost'],
                'package'   => $package,
                'meta_data' => $meta,
            ));
        }
    }
});

add_filter('woocommerce_shipping_methods', function ($methods) {
    $methods['af_distance'] = 'AF_Distance_Shipping';
    return $methods;
});
