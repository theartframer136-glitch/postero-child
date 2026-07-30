<?php
/**
 * Address validation  —  requirements §11
 *
 * Catches the address mistakes that actually cause failed deliveries:
 * malformed postcodes, a postcode that belongs to a different state, a
 * street line with no number, and unusable phone numbers. Everything is
 * checked offline — no third-party lookup, nothing to go down or expire.
 */
if (!defined('ABSPATH')) exit;

/** US ZIP prefix (first 3 digits) ranges => state. Unknown prefixes are allowed. */
function af_zip_state_ranges() {
    return array(
        array('005','005','NY'), array('006','009','PR'), array('010','027','MA'),
        array('028','029','RI'), array('030','038','NH'), array('039','049','ME'),
        array('050','059','VT'), array('060','069','CT'), array('070','089','NJ'),
        array('100','149','NY'), array('150','196','PA'), array('197','199','DE'),
        array('200','200','DC'), array('201','201','VA'), array('202','205','DC'),
        array('206','219','MD'), array('220','246','VA'), array('247','268','WV'),
        array('270','289','NC'), array('290','299','SC'), array('300','319','GA'),
        array('320','349','FL'), array('350','369','AL'), array('370','385','TN'),
        array('386','397','MS'), array('398','399','GA'), array('400','427','KY'),
        array('430','459','OH'), array('460','479','IN'), array('480','499','MI'),
        array('500','528','IA'), array('530','549','WI'), array('550','567','MN'),
        array('570','577','SD'), array('580','588','ND'), array('590','599','MT'),
        array('600','629','IL'), array('630','658','MO'), array('660','679','KS'),
        array('680','693','NE'), array('700','714','LA'), array('716','729','AR'),
        array('730','749','OK'), array('750','799','TX'), array('800','816','CO'),
        array('820','831','WY'), array('832','838','ID'), array('840','847','UT'),
        array('850','865','AZ'), array('870','884','NM'), array('885','885','TX'),
        array('889','898','NV'), array('900','961','CA'), array('967','968','HI'),
        array('970','979','OR'), array('980','994','WA'), array('995','999','AK'),
    );
}

/** Canadian postal first letter => province. */
function af_ca_postal_provinces() {
    return array(
        'A' => array('NL'), 'B' => array('NS'), 'C' => array('PE'), 'E' => array('NB'),
        'G' => array('QC'), 'H' => array('QC'), 'J' => array('QC'),
        'K' => array('ON'), 'L' => array('ON'), 'M' => array('ON'), 'N' => array('ON'), 'P' => array('ON'),
        'R' => array('MB'), 'S' => array('SK'), 'T' => array('AB'), 'V' => array('BC'),
        'X' => array('NT', 'NU'), 'Y' => array('YT'),
    );
}

/** Which state a US ZIP belongs to, or '' when we cannot say. */
function af_state_for_zip($zip) {
    $zip = preg_replace('/[^0-9]/', '', (string) $zip);
    if (strlen($zip) < 5) return '';
    $p = substr($zip, 0, 3);
    foreach (af_zip_state_ranges() as $r) {
        if ($p >= $r[0] && $p <= $r[1]) return $r[2];
    }
    return '';
}

/** Tidy postcodes into the form the carriers expect. */
add_filter('woocommerce_format_postcode', function($postcode, $country) {
    $pc = strtoupper(trim((string) $postcode));
    if ($country === 'CA') {
        $bare = preg_replace('/[^A-Z0-9]/', '', $pc);
        if (strlen($bare) === 6) return substr($bare, 0, 3) . ' ' . substr($bare, 3);
    }
    if ($country === 'US') {
        $bare = preg_replace('/[^0-9]/', '', $pc);
        if (strlen($bare) === 9) return substr($bare, 0, 5) . '-' . substr($bare, 5);
        if (strlen($bare) === 5) return $bare;
    }
    return $pc;
}, 10, 2);

/**
 * Does the cart contain a piece too large for a PO Box / parcel locker?
 * Sizes come from the pricing engine labels, e.g. "3×5 ft (36×60 in)".
 */
function af_cart_has_oversized() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        if (empty($item['af_size'])) continue;
        if (preg_match('/(\d+(?:\.\d+)?)\s*[x×X]\s*(\d+(?:\.\d+)?)/u', $item['af_size'], $m)) {
            if (max((float) $m[1], (float) $m[2]) >= 4) return true;
        }
    }
    return false;
}

add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    $ship_diff = !empty($data['ship_to_different_address']);

    $sets = array(
        'billing' => array('label' => 'Billing'),
    );
    if ($ship_diff) $sets['shipping'] = array('label' => 'Shipping');

    foreach ($sets as $prefix => $meta) {
        $country = isset($data[$prefix . '_country'])  ? $data[$prefix . '_country']  : '';
        $state   = isset($data[$prefix . '_state'])    ? $data[$prefix . '_state']    : '';
        $zip     = isset($data[$prefix . '_postcode']) ? $data[$prefix . '_postcode'] : '';
        $addr    = isset($data[$prefix . '_address_1'])? $data[$prefix . '_address_1']: '';
        $label   = $meta['label'];

        if ($country === 'US') {
            if ($zip !== '' && !preg_match('/^\d{5}(-\d{4})?$/', trim($zip))) {
                $errors->add('validation', sprintf('%s ZIP code should be 5 digits (or ZIP+4, like 90210-1234).', $label));
            } else {
                $expect = af_state_for_zip($zip);
                if ($expect && $state && strtoupper($state) !== $expect) {
                    $errors->add('validation', sprintf(
                        '%s ZIP %s belongs to %s, not %s — please check the state and ZIP.',
                        $label, esc_html($zip), esc_html($expect), esc_html(strtoupper($state))
                    ));
                }
            }
        } elseif ($country === 'CA') {
            $pc = strtoupper(preg_replace('/\s+/', '', (string) $zip));
            if ($pc !== '' && !preg_match('/^[ABCEGHJ-NPRSTVXY]\d[A-Z]\d[A-Z]\d$/', $pc)) {
                $errors->add('validation', sprintf('%s postal code should look like K1A 0B1.', $label));
            } else {
                $map = af_ca_postal_provinces();
                $ltr = substr($pc, 0, 1);
                if ($ltr && isset($map[$ltr]) && $state && !in_array(strtoupper($state), $map[$ltr], true)) {
                    $errors->add('validation', sprintf(
                        '%s postal code %s belongs to %s — please check the province.',
                        $label, esc_html($pc), esc_html(implode('/', $map[$ltr]))
                    ));
                }
            }
        }

        // A street line with no number is the classic undeliverable address.
        if (in_array($country, array('US', 'CA'), true) && $addr !== '' && !preg_match('/\d/', $addr)) {
            $errors->add('validation', sprintf(
                '%s street address needs a building or house number.', $label
            ));
        }

        // Large canvases cannot go to a PO Box or parcel locker.
        if ($addr !== '' && af_cart_has_oversized()
            && preg_match('/\b(p\.?\s*o\.?\s*box|post\s*office\s*box|parcel\s*locker)\b/i', $addr)) {
            $errors->add('validation', sprintf(
                '%s address is a PO Box. Your order includes a piece 4 ft or larger, which couriers can only deliver to a street address.', $label
            ));
        }
    }

    // Phone: enough digits to actually be dialled.
    if (!empty($data['billing_phone'])) {
        $digits = preg_replace('/\D/', '', $data['billing_phone']);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $errors->add('validation', 'Billing phone number does not look complete — we need it for delivery updates.');
        }
    }
}, 10, 2);

/** Live formatting hints while they type. */
add_action('woocommerce_after_checkout_form', function() {
    ?>
    <script>
    (function(){
      function tidy(e){
        var el = e.target;
        if (!el || !el.id) return;
        if (!/_postcode$/.test(el.id)) return;
        var cField = document.getElementById(el.id.replace('_postcode','_country'));
        var country = cField ? cField.value : '';
        if (country === 'CA') {
          var b = el.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
          el.value = b.length > 3 ? b.slice(0,3) + ' ' + b.slice(3,6) : b;
        } else if (country === 'US') {
          var d = el.value.replace(/[^0-9]/g,'');
          el.value = d.length > 5 ? d.slice(0,5) + '-' + d.slice(5,9) : d;
        }
      }
      document.addEventListener('blur', tidy, true);
    })();
    </script>
    <?php
});
