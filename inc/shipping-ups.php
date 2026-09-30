<?php
/**
 * US delivery charged the way UPS charges it.
 *
 * Owner's instruction (30 Sep 2026): the USA delivery charge follows the UPS
 * calculation. So the number at checkout is built exactly as UPS builds a
 * UPS Ground charge from the studio in ZIP 19707:
 *
 *   1. ZONE       the destination ZIP's UPS zone, 2 (nearest) to 8 (farthest),
 *                 Alaska and Hawaii apart. UPS publishes a zone chart per
 *                 origin ZIP; until that chart is loaded (tools/setup-ups-
 *                 zones.php) the zone comes from the straight-line miles that
 *                 inc/shipping-distance.php already knows, using UPS's own
 *                 mileage bands.
 *   2. PARCELS    what actually leaves the studio: tubes and crates, grouped
 *                 the way the studio packs them (see af_ups_parcels).
 *   3. WEIGHT     per parcel, the greater of real weight and dimensional
 *                 weight (L × W × H ÷ 139), rounded UP to the next pound —
 *                 UPS bills whole pounds.
 *   4. BASE RATE  the UPS Ground daily rate for that zone and that weight,
 *                 from the rate card.
 *   5. SURCHARGES per parcel, the ones a canvas parcel meets: Residential,
 *                 Additional Handling (a side over 48 in, a second side over
 *                 30 in, over 10,368 cu in, or over 50 lb), Large Package
 *                 (length + girth over 130 in, length over 96 in, over 17,280
 *                 cu in or over 110 lb — with UPS's 90 lb minimum billable
 *                 weight), Over Maximum Limits.
 *   6. FUEL       the Ground fuel surcharge, a percentage on the base rate
 *                 and on those surcharges, which is where UPS applies it.
 *
 * Two sources for the figures, in this order:
 *
 *   LIVE   if UPS API credentials are set (options af_ups_client_id,
 *          af_ups_client_secret, af_ups_account) the UPS Rating API is asked
 *          for the real quote and its answer is used, cached for six hours.
 *          That is the exact UPS calculation, negotiated rates included.
 *
 *   TABLE  otherwise, or whenever the API fails, the published-rate model in
 *          this file. Its rate card is SEEDED from the 2026 UPS Ground daily
 *          rates as published (the 1, 10 and 20 lb zone 2 and zone 8 points
 *          are the published figures; the other cells are fitted between
 *          them) and its surcharges from the 2026 UPS rate guide. Every
 *          figure is an option, so the owner corrects any of them against a
 *          UPS invoice without a deploy — see af_ups_settings() for the keys:
 *
 *            wp option update af_ups_fuel_pct 22.5
 *            wp option update af_ups_surcharges '{"residential":6.50}' --format=json
 *            wp option update af_ups_rate_card '{"1":[11.99,12.35,...],"2":[...]}' --format=json
 *
 * Not modelled: the Delivery Area Surcharge (a ZIP list UPS licenses, not
 * publishes) and peak-season demand surcharges. Both are covered by the live
 * API path, and by af_ups_adjust_pct for the table path.
 *
 * tools/verify-distance-shipping.php prints the whole calculation for sample
 * ZIPs and parcels so a number can be held against a UPS quote.
 */
if (!defined('ABSPATH')) exit;

/* ───────────────────────── settings ───────────────────────── */

/** One place for every tunable, with the option that overrides it. */
function af_ups_settings() {
    static $s = null;
    if ($s !== null) return $s;

    $sur = get_option('af_ups_surcharges');
    if (is_string($sur)) $sur = json_decode($sur, true);
    if (!is_array($sur)) $sur = array();

    $defaults = array(
        // UPS Ground 2026 rate guide, per package
        'residential'      => 6.50,    // Residential Surcharge, Ground
        'ah_dimension'     => 24.00,   // Additional Handling — dimensions
        'ah_weight'        => 34.50,   // Additional Handling — over 50 lb actual
        'lps'              => array(   // Large Package Surcharge, by zone
            2 => 219.50, 3 => 239.50, 4 => 239.50, 5 => 273.00,
            6 => 273.00, 7 => 286.00, 8 => 286.00,
        ),
        'lps_residential_extra' => 0.00, // added to LPS on a residential stop
        'lps_min_lbs'      => 90,      // LPS minimum billable weight
        'over_max'         => 1150.00, // Over Maximum Limits
        'akhi_factor'      => 1.90,    // Alaska / Hawaii: zone 8 rate × this
    );
    $s = array_merge($defaults, $sur);
    if (isset($sur['lps']) && is_array($sur['lps'])) $s['lps'] = $sur['lps'] + $defaults['lps'];

    // the ones that move often get their own option
    $fuel = get_option('af_ups_fuel_pct', '');
    $s['fuel_pct'] = ($fuel === '' || $fuel === false) ? 24.0 : (float) $fuel;   // week of 7 Sep 2026
    $adj = get_option('af_ups_adjust_pct', '');
    $s['adjust_pct'] = ($adj === '' || $adj === false) ? 0.0 : (float) $adj;     // negotiated discount: -15 for 15% off
    $res = get_option('af_ups_residential', '');
    $s['residential_default'] = ($res === '' || $res === false) ? true : in_array(strtolower((string) $res), array('1', 'yes', 'true'), true);
    $fb = get_option('af_ups_fallback_zone', '');
    $s['fallback_zone'] = ($fb === '' || $fb === false) ? 5 : max(2, min(8, (int) $fb));

    return apply_filters('af_ups_settings', $s);
}

/* ───────────────────────── zones ───────────────────────── */

/** UPS Ground mileage bands: upper bound in miles for zones 2..7; 8 is beyond. */
function af_ups_zone_bands() {
    return apply_filters('af_ups_zone_bands', array(
        2 => 150, 3 => 300, 4 => 600, 5 => 1000, 6 => 1400, 7 => 1800,
    ));
}

/**
 * ZIP3 → zone, from the UPS zone chart for origin 197 once it is loaded
 * (option af_ups_zones, written by tools/setup-ups-zones.php). Empty until then.
 */
function af_ups_zone_overrides() {
    static $z = null;
    if ($z !== null) return $z;
    $o = get_option('af_ups_zones');
    if (is_string($o)) $o = json_decode($o, true);
    return $z = (is_array($o) ? $o : array());
}

/**
 * The zone for a destination. 2..8 for the 48 states, 9 for Alaska, Hawaii
 * and the territories (priced off zone 8, see af_ups_base_rate), null for a
 * non-US address.
 */
function af_ups_zone($zip, $state = '', $country = 'US') {
    if ($country && $country !== 'US') return null;
    $digits = preg_replace('/\D/', '', (string) $zip);
    $zip3   = strlen($digits) >= 3 ? substr($digits, 0, 3) : '';
    $state  = strtoupper((string) $state);

    if (in_array($state, array('AK', 'HI', 'PR', 'VI', 'GU', 'AS', 'MP'), true)) return 9;
    if ($zip3 !== '') {
        $n = (int) $zip3;
        if (($n >= 995 && $n <= 999) || $n === 967 || $n === 968 || ($n >= 6 && $n <= 9)) return 9;
        $ov = af_ups_zone_overrides();
        if (isset($ov[$zip3])) return max(2, min(9, (int) $ov[$zip3]));
    }

    $miles = (function_exists('af_zip_distance_miles') && defined('AF_SHIP_ORIGIN_ZIP'))
           ? af_zip_distance_miles(AF_SHIP_ORIGIN_ZIP, $digits) : null;
    return af_ups_zone_for_miles($miles);
}

function af_ups_zone_for_miles($miles) {
    if ($miles === null) return af_ups_settings()['fallback_zone'];
    foreach (af_ups_zone_bands() as $zone => $max) {
        if ($miles <= $max) return (int) $zone;
    }
    return 8;
}

/* ───────────────────────── rate card ───────────────────────── */

/**
 * UPS Ground daily rates, $ per package: weight (lb) => [zone 2 … zone 8].
 * Rows may be sparse; af_ups_base_rate interpolates between them, so pasting
 * UPS's full 1–150 lb table into af_ups_rate_card makes every lookup exact.
 */
function af_ups_rate_card() {
    static $card = null;
    if ($card !== null) return $card;

    $o = get_option('af_ups_rate_card');
    if (is_string($o)) $o = json_decode($o, true);
    $rows = array();
    if (is_array($o)) {
        foreach ($o as $w => $r) {
            if (is_array($r) && count($r) === 7) $rows[(int) $w] = array_map('floatval', array_values($r));
        }
    }
    if (!$rows) {
        // 2026 UPS Ground daily rates. Published points: 1 lb 11.99 / 15.03,
        // 10 lb 15.91 / 26.29, 20 lb 19.20 / 43.33 (zone 2 / zone 8). The rest
        // is fitted; replace with the guide's own rows via af_ups_rate_card.
        $rows = array(
              1 => array( 11.99,  12.35,  12.78,  13.36,  13.87,  14.42,  15.03),
              5 => array( 13.85,  14.61,  15.50,  16.71,  17.79,  18.93,  20.20),
             10 => array( 15.91,  17.16,  18.61,  20.58,  22.35,  24.21,  26.29),
             20 => array( 19.20,  22.10,  25.47,  30.06,  34.16,  38.50,  43.33),
             30 => array( 24.10,  28.71,  34.08,  41.38,  47.91,  54.82,  62.50),
             50 => array( 32.60,  40.75,  50.25,  63.16,  74.70,  86.92, 100.50),
             70 => array( 41.20,  52.70,  66.11,  84.31, 100.60, 117.84, 137.00),
            100 => array( 58.50,  74.28,  92.69, 117.68, 140.03, 163.70, 190.00),
            150 => array( 89.00, 111.32, 137.36, 172.70, 204.32, 237.80, 275.00),
        );
    }
    ksort($rows);
    return $card = apply_filters('af_ups_rate_card', $rows);
}

/** Whether the card in force is the owner's or the seeded one. */
function af_ups_rate_card_source() {
    $o = get_option('af_ups_rate_card');
    return $o ? 'af_ups_rate_card option (owner-set)' : 'seeded from the 2026 published daily rates';
}

/** The Ground base rate for a zone and a billable weight in whole pounds. */
function af_ups_base_rate($zone, $lbs) {
    $lbs  = max(1, (int) ceil((float) $lbs));
    $card = af_ups_rate_card();
    $col  = ($zone >= 9) ? 6 : max(0, min(6, (int) $zone - 2));

    $below = null; $above = null;
    foreach ($card as $w => $row) {
        if ($w <= $lbs) $below = array($w, $row[$col]);
        if ($w >= $lbs) { $above = array($w, $row[$col]); break; }
    }
    if ($below && $above && $below[0] === $above[0]) {
        $rate = $below[1];
    } elseif ($below && $above) {
        $rate = $below[1] + ($above[1] - $below[1]) * ($lbs - $below[0]) / ($above[0] - $below[0]);
    } elseif ($below) {
        // past the last row: continue at the last row's per-pound slope
        $ws = array_keys($card);
        $n  = count($ws);
        $slope = ($n > 1) ? ($card[$ws[$n - 1]][$col] - $card[$ws[$n - 2]][$col]) / ($ws[$n - 1] - $ws[$n - 2]) : 1.0;
        $rate = $below[1] + $slope * ($lbs - $below[0]);
    } else {
        $rate = $above ? $above[1] : 0.0;
    }
    if ($zone >= 9) $rate *= (float) af_ups_settings()['akhi_factor'];
    return round($rate, 2);
}

/* ───────────────────────── parcels ───────────────────────── */

/** UPS bills whole pounds, on the greater of real and dimensional weight. */
function af_ups_billable_lbs($l, $w, $h, $actual) {
    $dim = ($l > 0 && $w > 0 && $h > 0 && function_exists('af_ship_dim_weight'))
         ? (float) af_ship_dim_weight($l, $w, $h) : 0.0;
    return max(1, (int) ceil(max((float) $actual, $dim)));
}

/**
 * The parcels an order ships as: [ [l, w, h, actual, billable, method, pieces], … ].
 *
 * Grouping follows what the studio packs (DEF-03 in shipping-distance.php):
 * a tube holds several rolled prints without growing; a crate takes a few
 * pieces stacked and gets deeper. Here one more rule from UPS: a parcel must
 * stay inside UPS's maximum (165 in length + girth), so a crate stops
 * stacking before it crosses that line rather than becoming an Over Maximum
 * parcel that UPS may refuse.
 *
 * Downloads and virtual lines ship nothing and are not parcels.
 */
function af_ups_parcels($package) {
    $groups = array();
    $loose  = array();

    foreach ((array) (isset($package['contents']) ? $package['contents'] : array()) as $item) {
        $product = isset($item['data']) ? $item['data'] : null;
        if (!$product) continue;
        if ((method_exists($product, 'is_virtual') && $product->is_virtual())
         || (method_exists($product, 'is_downloadable') && $product->is_downloadable()
             && !$product->needs_shipping())) {
            continue;
        }
        if (method_exists($product, 'needs_shipping') && !$product->needs_shipping()) continue;
        $qty = isset($item['quantity']) ? max(1, (int) $item['quantity']) : 1;

        $pkg = null;
        if (!empty($item['af_size']) && function_exists('af_ship_package')) {
            $pkg = af_ship_package($item['af_size'], isset($item['af_frame']) ? $item['af_frame'] : '');
        }
        if (!$pkg || empty($pkg['method'])) {
            // no dimensions to reason about: one parcel per piece at the
            // product's own weight and dimensions, whatever they are
            $w = (float) ($product->get_weight() ? $product->get_weight() : 5);
            $l = (float) $product->get_length(); $wd = (float) $product->get_width(); $h = (float) $product->get_height();
            for ($i = 0; $i < $qty; $i++) {
                $loose[] = array('l' => $l, 'w' => $wd, 'h' => $h, 'actual' => max(1.0, $w),
                                 'method' => 'other', 'pieces' => 1);
            }
            continue;
        }
        $key = $pkg['method'] . '|' . round((float) $pkg['l'], 1)
             . '|' . round((float) $pkg['w'], 1) . '|' . round((float) $pkg['h'], 1);
        if (!isset($groups[$key])) $groups[$key] = array('pkg' => $pkg, 'qty' => 0);
        $groups[$key]['qty'] += $qty;
    }

    $parcels = $loose;
    foreach ($groups as $g) {
        $pkg    = $g['pkg'];
        $left   = (int) $g['qty'];
        $method = $pkg['method'];
        $cap    = function_exists('af_ship_parcel_capacity') ? af_ship_parcel_capacity($method) : 1;
        $unit   = max(1.0, (float) $pkg['weight']);
        $l = (float) $pkg['l']; $w = (float) $pkg['w']; $h0 = (float) $pkg['h'];

        // a crate may not stack past UPS's maximum size
        if ($method === 'crate' && $l > 0 && $w > 0 && $h0 > 0) {
            while ($cap > 1 && ($l + 2 * ($w + $h0 * $cap)) > 165) $cap--;
        }
        $parcel_n = 0;
        while ($left > 0) {
            if (++$parcel_n > 200) {   // DEF-05 belt: never spin on an absurd quantity
                $parcels[] = array('l' => $l, 'w' => $w, 'h' => $h0, 'actual' => $unit * $left,
                                   'method' => $method, 'pieces' => $left);
                break;
            }
            $n     = min($cap, $left);
            $left -= $n;
            $h = ($method === 'crate') ? $h0 * $n : $h0;
            $parcels[] = array('l' => $l, 'w' => $w, 'h' => $h, 'actual' => $unit * $n,
                               'method' => $method, 'pieces' => $n);
        }
    }

    foreach ($parcels as &$p) {
        $p['billable'] = af_ups_billable_lbs($p['l'], $p['w'], $p['h'], $p['actual']);
    }
    unset($p);
    return $parcels;
}

/* ───────────────────────── the charge ───────────────────────── */

/**
 * One parcel's UPS Ground charge, itemised. $zone 2..9; $residential bool.
 */
function af_ups_parcel_charge($p, $zone, $residential) {
    $s    = af_ups_settings();
    $dims = array((float) $p['l'], (float) $p['w'], (float) $p['h']);
    rsort($dims);
    list($long, $second, $third) = $dims;
    $girth  = 2 * ($second + $third);
    $volume = $long * $second * $third;
    $actual = (float) $p['actual'];
    $lbs    = (int) $p['billable'];
    $zcol   = ($zone >= 9) ? 8 : max(2, min(8, (int) $zone));

    $lines = array();
    $over_max = ($long > 108) || ($long + $girth > 165) || ($actual > 150);
    $large    = ($long > 96) || ($long + $girth > 130) || ($volume > 17280) || ($actual > 110);
    $ah_dim   = ($long > 48) || ($second > 30) || ($volume > 10368);
    $ah_wt    = ($actual > 50);

    if ($over_max || $large) {
        $lbs = max($lbs, (int) $s['lps_min_lbs']);
    }
    $base = af_ups_base_rate($zone, $lbs);
    $lines[] = array('Ground base rate', $base);

    if ($over_max) {
        $lines[] = array('Over Maximum Limits', (float) $s['over_max']);
    } elseif ($large) {
        $lps = isset($s['lps'][$zcol]) ? (float) $s['lps'][$zcol] : (float) end($s['lps']);
        if ($residential) $lps += (float) $s['lps_residential_extra'];
        $lines[] = array('Large Package Surcharge', $lps);
    } elseif ($ah_dim || $ah_wt) {
        // UPS applies one Additional Handling charge per package, the higher
        $ah = max($ah_dim ? (float) $s['ah_dimension'] : 0, $ah_wt ? (float) $s['ah_weight'] : 0);
        $lines[] = array('Additional Handling', $ah);
    }
    if ($residential) $lines[] = array('Residential Surcharge', (float) $s['residential']);

    $subtotal = 0.0;
    foreach ($lines as $ln) $subtotal += $ln[1];
    $fuel = round($subtotal * (float) $s['fuel_pct'] / 100, 2);
    $lines[] = array(sprintf('Fuel surcharge %.2f%%', (float) $s['fuel_pct']), $fuel);

    return array(
        'zone'     => $zone,
        'billable' => $lbs,
        'lines'    => $lines,
        'total'    => round($subtotal + $fuel, 2),
        'flags'    => array_keys(array_filter(array(
            'over_max' => $over_max, 'large' => $large, 'ah_dim' => $ah_dim, 'ah_wt' => $ah_wt))),
    );
}

/**
 * The delivery charge for a WooCommerce shipping package, or null when there
 * is nothing physical to deliver.
 *
 * @return array|null  cost, zone, parcels (each with its charge), source
 */
function af_ups_quote($package) {
    $dest    = isset($package['destination']) ? $package['destination'] : array();
    $country = isset($dest['country']) ? $dest['country'] : 'US';
    $zip     = isset($dest['postcode']) ? $dest['postcode'] : '';
    $state   = isset($dest['state']) ? $dest['state'] : '';

    $parcels = af_ups_parcels($package);
    if (!$parcels) return null;

    $zone = af_ups_zone($zip, $state, $country);
    if ($zone === null) $zone = 8;   // this method only sits in US zones; be safe if it is ever elsewhere
    $residential = (bool) apply_filters('af_ups_residential', af_ups_settings()['residential_default'], $package);

    $quote = array('zone' => $zone, 'residential' => $residential, 'parcels' => array(), 'source' => 'table');
    $total = 0.0;
    foreach ($parcels as $p) {
        $p['charge'] = af_ups_parcel_charge($p, $zone, $residential);
        $total += $p['charge']['total'];
        $quote['parcels'][] = $p;
    }
    $adj = (float) af_ups_settings()['adjust_pct'];
    if ($adj) $total = $total * (1 + $adj / 100);
    $quote['cost'] = round(max(0, $total), 2);

    // the real thing, when the studio's UPS account is connected
    if (af_ups_api_enabled() && $country === 'US' && strlen(preg_replace('/\D/', '', $zip)) >= 5) {
        $live = af_ups_api_rate($parcels, $zip, $state, $residential);
        if ($live !== null) {
            $quote['table_cost'] = $quote['cost'];
            $quote['cost']   = round($live, 2);
            $quote['source'] = 'api';
        }
    }
    return apply_filters('af_ups_quote', $quote, $package);
}

/* ───────────────────────── live UPS Rating API ───────────────────────── */

function af_ups_api_enabled() {
    return (bool) (get_option('af_ups_client_id') && get_option('af_ups_client_secret'));
}

function af_ups_api_base() {
    return (get_option('af_ups_api_env', 'production') === 'test')
        ? 'https://wwwcie.ups.com' : 'https://onlinetools.ups.com';
}

/** OAuth client-credentials token, cached for its lifetime less a margin. */
function af_ups_api_token() {
    $cached = get_transient('af_ups_token');
    if ($cached) return $cached;
    $res = wp_remote_post(af_ups_api_base() . '/security/v1/oauth/token', array(
        'timeout' => 15,
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode(get_option('af_ups_client_id') . ':' . get_option('af_ups_client_secret')),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ),
        'body' => 'grant_type=client_credentials',
    ));
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        error_log('af_ups: token request failed: ' . (is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res)));
        return null;
    }
    $j = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($j['access_token'])) return null;
    $ttl = isset($j['expires_in']) ? max(60, (int) $j['expires_in'] - 120) : 3000;
    set_transient('af_ups_token', $j['access_token'], $ttl);
    return $j['access_token'];
}

/**
 * Ask UPS for the Ground charge of these parcels to this address. Returns the
 * total in dollars, or null on any failure so the table takes over. Answers
 * are cached for six hours per (parcels, ZIP, residential).
 */
function af_ups_api_rate($parcels, $zip, $state, $residential) {
    $zip5 = substr(preg_replace('/\D/', '', (string) $zip), 0, 5);
    $shape = array();
    foreach ($parcels as $p) {
        $shape[] = array(round($p['l'], 1), round($p['w'], 1), round($p['h'], 1), round($p['actual'], 1));
    }
    $key = 'af_ups_q_' . md5(wp_json_encode(array($shape, $zip5, $state, $residential, get_option('af_ups_account'))));
    $hit = get_transient($key);
    if ($hit !== false) return ($hit === 'fail') ? null : (float) $hit;

    $token = af_ups_api_token();
    if (!$token) { set_transient($key, 'fail', 600); return null; }

    $account = (string) get_option('af_ups_account');
    $origin  = (string) get_option('af_ups_shipper_zip', defined('AF_SHIP_ORIGIN_ZIP') ? AF_SHIP_ORIGIN_ZIP : '19707');
    $pkgs = array();
    foreach ($parcels as $p) {
        $pkgs[] = array(
            'PackagingType' => array('Code' => '02'),
            'Dimensions'    => array(
                'UnitOfMeasurement' => array('Code' => 'IN'),
                'Length' => (string) max(1, (int) ceil($p['l'])),
                'Width'  => (string) max(1, (int) ceil($p['w'])),
                'Height' => (string) max(1, (int) ceil($p['h'])),
            ),
            'PackageWeight' => array(
                'UnitOfMeasurement' => array('Code' => 'LBS'),
                'Weight' => (string) max(1, (int) ceil($p['actual'])),
            ),
        );
    }
    $ship_to = array('Address' => array('PostalCode' => $zip5, 'CountryCode' => 'US'));
    if ($state) $ship_to['Address']['StateProvinceCode'] = strtoupper($state);
    if ($residential) $ship_to['Address']['ResidentialAddressIndicator'] = 'Y';

    $shipper = array('Address' => array('PostalCode' => $origin, 'StateProvinceCode' => 'DE', 'CountryCode' => 'US'));
    if ($account) $shipper['ShipperNumber'] = $account;

    $body = array('RateRequest' => array(
        'Request'  => array('RequestOption' => 'Rate'),
        'Shipment' => array(
            'Shipper'  => $shipper,
            'ShipTo'   => $ship_to,
            'ShipFrom' => array('Address' => $shipper['Address']),
            'Service'  => array('Code' => '03'),          // UPS Ground
            'Package'  => $pkgs,
            'ShipmentRatingOptions' => array('NegotiatedRatesIndicator' => $account ? 'Y' : 'N'),
        ),
    ));
    $res = wp_remote_post(af_ups_api_base() . '/api/rating/v2409/Rate', array(
        'timeout' => 15,
        'headers' => array(
            'Authorization'  => 'Bearer ' . $token,
            'Content-Type'   => 'application/json',
            'transId'        => substr(md5(uniqid('', true)), 0, 32),
            'transactionSrc' => 'theartframer',
        ),
        'body' => wp_json_encode($body),
    ));
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        error_log('af_ups: rate request failed: ' . (is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res)));
        set_transient($key, 'fail', 600);
        return null;
    }
    $j  = json_decode(wp_remote_retrieve_body($res), true);
    $rs = isset($j['RateResponse']['RatedShipment']) ? $j['RateResponse']['RatedShipment'] : null;
    if (isset($rs[0])) $rs = $rs[0];
    $amount = null;
    if (isset($rs['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'])) {
        $amount = (float) $rs['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'];
    } elseif (isset($rs['TotalCharges']['MonetaryValue'])) {
        $amount = (float) $rs['TotalCharges']['MonetaryValue'];
    }
    if ($amount === null || $amount <= 0) { set_transient($key, 'fail', 600); return null; }
    set_transient($key, (string) $amount, 6 * HOUR_IN_SECONDS);
    return $amount;
}

/* ───────────────────────── one charge, not two ───────────────────────── */

// The cart used to add its own "Oversize handling" fee (inc/shipping.php) on
// top of whatever the delivery method charged. Additional Handling and the
// Large Package Surcharge are that same cost, priced by UPS, and they are now
// inside the delivery line — so the separate fee would bill the size twice.
add_filter('af_ship_surcharge_table', '__return_empty_array', 20);
