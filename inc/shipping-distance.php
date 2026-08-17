<?php
/**
 * Distance-based delivery from the studio: ZIP 19707, Hockessin, Delaware.
 *
 * The owner's requirement: orders ship FROM 19707 and the delivery charge is
 * calculated by how far the customer's address is from there. No external
 * rate API is involved — the charge comes from a distance tier table the
 * owner controls, so it can be tuned to real carrier prices at any time
 * without code changes.
 *
 * How the distance is known: a one-time table of every US ZIP code's centre
 * point (latitude/longitude, US Census ZCTA data, public domain) is loaded by
 * tools/setup-zip-distance.php. At checkout the straight-line (haversine)
 * miles between 19707 and the customer's ZIP pick the tier.
 *
 * The tiers ship with DEFAULTS the owner has not confirmed yet. They live in
 * one option so correcting them is a single update, no deploy:
 *   wp option update af_distance_tiers '[{"mi":15,"cost":15}, ...]' --format=json
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
 * Zone bands by distance from the studio. Carriers price on TWO things — how
 * far the parcel goes AND how big/heavy it is — so a flat per-distance charge
 * was wrong: it billed $65 to ship a rolled print that a carrier moves for a
 * fraction of that, and would have undercharged a large framed crate.
 *
 * Each band therefore carries a handling base plus a per-pound rate, applied
 * to the parcel's BILLABLE weight (the greater of real weight and dimensional
 * weight, which is how every carrier bills). A rolled tube is light and small
 * and lands cheap; a 4 ft framed crate is bulky and lands dear, automatically.
 *
 * STILL PLACEHOLDERS until the owner supplies carrier quotes. One option to
 * change, no deploy:
 *   wp option update af_distance_tiers '[{"mi":50,"base":8,"per_lb":0.6}, ...]' --format=json
 */
function af_distance_tiers() {
    $t = get_option('af_distance_tiers');
    if (is_string($t)) $t = json_decode($t, true);
    if (is_array($t) && $t) return $t;
    return array(
        array('mi' => 50,    'base' => 8,  'per_lb' => 0.60, 'label' => 'Local / regional'),
        array('mi' => 150,   'base' => 9,  'per_lb' => 0.90, 'label' => 'Extended region'),
        array('mi' => 500,   'base' => 10, 'per_lb' => 1.30, 'label' => 'East coast / near Midwest'),
        array('mi' => 1000,  'base' => 11, 'per_lb' => 1.70, 'label' => 'Midwest / South'),
        array('mi' => 1800,  'base' => 12, 'per_lb' => 2.10, 'label' => 'Mountain / South West'),
        array('mi' => 99999, 'base' => 14, 'per_lb' => 2.60, 'label' => 'West coast / AK / HI'),
    );
}

/** The band a distance falls into; the last band when the ZIP is unknown. */
function af_distance_band($miles) {
    $tiers = af_distance_tiers();
    if ($miles === null) {
        $i = (int) get_option('af_distance_fallback_band', 2);
        return isset($tiers[$i]) ? $tiers[$i] : end($tiers);
    }
    foreach ($tiers as $t) {
        if ($miles <= (float) $t['mi']) return $t;
    }
    return end($tiers);
}

/**
 * What actually has to travel, and what it weighs to a carrier.
 *
 * Digital downloads travel by email: they add nothing here, so a download-only
 * order is never charged delivery — that was the $65 on an $80 download.
 */
function af_distance_package_weight($package) {
    $lbs = 0.0;
    $physical = 0;
    foreach ((array) $package['contents'] as $item) {
        $product = isset($item['data']) ? $item['data'] : null;
        if (!$product) continue;
        // a download or a virtual line ships nothing
        if ((method_exists($product, 'is_virtual') && $product->is_virtual())
         || (method_exists($product, 'is_downloadable') && $product->is_downloadable()
             && !$product->needs_shipping())) {
            continue;
        }
        if (method_exists($product, 'needs_shipping') && !$product->needs_shipping()) continue;
        $physical++;
        $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;

        $w = null;
        if (!empty($item['af_size']) && function_exists('af_ship_package')) {
            $pkg = af_ship_package($item['af_size'], isset($item['af_frame']) ? $item['af_frame'] : '');
            if ($pkg && function_exists('af_ship_billable_weight')) {
                $w = (float) af_ship_billable_weight($pkg);
            }
        }
        if ($w === null) {
            $w = (float) ($product->get_weight() ? $product->get_weight() : 5);
        }
        $lbs += max(1.0, $w) * max(1, $qty);
    }
    return array($lbs, $physical);
}

/** Legacy helper kept for the verifier: flat cost for a distance. */
function af_distance_rate($miles, $lbs = 12.0) {
    $b = af_distance_band($miles);
    $base   = isset($b['base'])   ? (float) $b['base']   : 10.0;
    $per_lb = isset($b['per_lb']) ? (float) $b['per_lb'] : 1.5;
    return round($base + $per_lb * max(1.0, (float) $lbs), 2);
}

add_action('woocommerce_shipping_init', function () {
    if (!class_exists('WC_Shipping_Method') || class_exists('AF_Distance_Shipping')) return;

    class AF_Distance_Shipping extends WC_Shipping_Method {
        public function __construct($instance_id = 0) {
            $this->id                 = 'af_distance';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = 'Distance-based delivery (from ZIP 19707)';
            $this->method_description = 'Charges by distance from the Hockessin, DE studio to the '
                                      . 'customer ZIP. Tiers: option af_distance_tiers.';
            $this->supports           = array('shipping-zones', 'instance-settings',
                                              'instance-settings-modal');
            $this->instance_form_fields = array(
                'title' => array(
                    'title'   => 'Label shown at checkout',
                    'type'    => 'text',
                    'default' => 'Delivery',
                ),
            );
            $this->title = $this->get_option('title', 'Delivery');
        }

        public function calculate_shipping($package = array()) {
            $dest    = isset($package['destination']) ? $package['destination'] : array();
            $country = isset($dest['country']) ? $dest['country'] : '';
            $zip     = isset($dest['postcode']) ? $dest['postcode'] : '';

            list($lbs, $physical) = af_distance_package_weight($package);
            // nothing physical in the basket: a download-only order pays no
            // delivery at all, so no rate is offered
            if ($physical < 1 || $lbs <= 0) return;

            $miles = ($country === 'US') ? af_zip_distance_miles(AF_SHIP_ORIGIN_ZIP, $zip) : null;
            $band  = af_distance_band($miles);
            $base   = isset($band['base'])   ? (float) $band['base']   : 10.0;
            $per_lb = isset($band['per_lb']) ? (float) $band['per_lb'] : 1.5;
            $cost   = round($base + $per_lb * $lbs, 2);

            $label = $this->title;
            if ($miles !== null) {
                $label .= sprintf(' (~%d mi from Hockessin, DE)', (int) round($miles));
            }

            $this->add_rate(array(
                'id'      => $this->get_rate_id(),
                'label'   => $label,
                'cost'    => $cost,
                'package' => $package,
            ));
        }
    }
});

add_filter('woocommerce_shipping_methods', function ($methods) {
    $methods['af_distance'] = 'AF_Distance_Shipping';
    return $methods;
});
