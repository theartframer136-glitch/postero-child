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
 * The owner's tier table. DEFAULTS ARE PLACEHOLDERS chosen to be plausible for
 * a large framed canvas (ground shipping, packed) — they are NOT researched
 * carrier prices and the owner should replace them after checking real quotes.
 */
function af_distance_tiers() {
    $t = get_option('af_distance_tiers');
    if (is_string($t)) $t = json_decode($t, true);
    if (is_array($t) && $t) return $t;
    return array(
        array('mi' => 15,    'cost' => 15,  'label' => 'Local (Hockessin & nearby)'),
        array('mi' => 50,    'cost' => 25,  'label' => 'Regional (DE, SE PA, NJ, MD)'),
        array('mi' => 150,   'cost' => 45,  'label' => 'Extended region'),
        array('mi' => 500,   'cost' => 65,  'label' => 'East / Midwest'),
        array('mi' => 1500,  'cost' => 85,  'label' => 'Most of the continental US'),
        array('mi' => 99999, 'cost' => 105, 'label' => 'Far West / AK / HI'),
    );
}

/** Cost for a distance; unknown ZIPs use the owner-settable fallback. */
function af_distance_rate($miles) {
    if ($miles === null) {
        $f = get_option('af_distance_fallback', '');
        return $f === '' ? 65.0 : (float) $f;
    }
    foreach (af_distance_tiers() as $t) {
        if ($miles <= (float) $t['mi']) return (float) $t['cost'];
    }
    $last = end(af_distance_tiers());
    return (float) $last['cost'];
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

            $miles = ($country === 'US') ? af_zip_distance_miles(AF_SHIP_ORIGIN_ZIP, $zip) : null;
            $cost  = af_distance_rate($miles);
            if ($cost === null) return;

            $label = $this->title;
            if ($miles !== null) {
                $label .= sprintf(' (~%d mi from our Hockessin, DE studio)', (int) round($miles));
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
