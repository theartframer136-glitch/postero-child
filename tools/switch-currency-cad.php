<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Switch storefront currencies to USD + CAD (spec requirement).
 * Removes INR from the FOX/WOOCS switcher, adds CAD, keeps USD as base.
 * Idempotent. Run: wp eval-file tools/switch-currency-cad.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== Switch currencies to USD + CAD ===\n\n";

// FOX (formerly WOOCS) stores its currency list in the 'woocs' option,
// keyed by currency code. Some builds use 'woocs_currencies'.
$option_keys = array( 'woocs', 'woocs_currencies' );
$updated_any = false;

foreach ( $option_keys as $key ) {
    $currencies = get_option( $key, null );
    if ( ! is_array( $currencies ) || empty( $currencies ) ) {
        echo "SKIP  option '{$key}' — not an array or empty\n";
        continue;
    }

    echo "FOUND option '{$key}' with: " . implode( ', ', array_keys( $currencies ) ) . "\n";
    $changed = false;

    if ( isset( $currencies['INR'] ) ) {
        unset( $currencies['INR'] );
        echo "  - removed INR\n";
        $changed = true;
    }

    if ( ! isset( $currencies['CAD'] ) ) {
        // Clone USD entry as a structural template so we match this WOOCS
        // build's exact field set, then override the CAD-specific fields.
        $template = isset( $currencies['USD'] ) && is_array( $currencies['USD'] )
            ? $currencies['USD'] : array();
        $cad = array_merge( $template, array(
            'name'        => 'CAD',
            'rate'        => 1.37, // fixed rate; enable auto-update in WOOCS settings later
            'symbol'      => 'CA$',
            'position'    => 'left',
            'is_etalon'   => 0,
            'description' => 'Canadian Dollar',
            'hide_cents'  => isset( $template['hide_cents'] ) ? $template['hide_cents'] : 0,
            'flag'        => 'ca.png',
            'decimals'    => isset( $template['decimals'] ) ? $template['decimals'] : 2,
        ) );
        $currencies['CAD'] = $cad;
        echo "  - added CAD (rate 1.37, symbol CA$)\n";
        $changed = true;
    }

    if ( isset( $currencies['USD'] ) && is_array( $currencies['USD'] ) ) {
        if ( empty( $currencies['USD']['is_etalon'] ) ) {
            $currencies['USD']['is_etalon'] = 1;
            echo "  - marked USD as base (etalon)\n";
            $changed = true;
        }
    }

    if ( $changed ) {
        update_option( $key, $currencies );
        echo "  UPDATED '{$key}' -> " . implode( ', ', array_keys( $currencies ) ) . "\n";
        $updated_any = true;
    } else {
        echo "  no changes needed for '{$key}'\n";
    }
}

// Keep default at USD
if ( get_option( 'woocs_default_currency', '' ) !== 'USD' ) {
    update_option( 'woocs_default_currency', 'USD' );
    echo "SET   woocs_default_currency = USD\n";
}

// WMC (Villatheme Multi Currency), if present: mirror the same change
$wmc = get_option( 'wmc_options', null );
if ( is_array( $wmc ) && ! empty( $wmc ) ) {
    if ( isset( $wmc['currency'] ) && is_array( $wmc['currency'] ) ) {
        $codes = $wmc['currency'];
        $idx   = array_search( 'INR', $codes, true );
        if ( $idx !== false ) {
            $codes[ $idx ] = 'CAD';
            $wmc['currency'] = $codes;
            // parallel arrays used by WMC: rate/decimals/custom symbol
            if ( isset( $wmc['currency_rate'][ $idx ] ) )    $wmc['currency_rate'][ $idx ] = 1.37;
            if ( isset( $wmc['currency_decimals'][ $idx ] ) ) $wmc['currency_decimals'][ $idx ] = 2;
            update_option( 'wmc_options', $wmc );
            echo "UPDATED wmc_options: INR -> CAD\n";
            $updated_any = true;
        }
    }
}

echo $updated_any ? "\n=== DONE — currencies now USD + CAD ===\n"
                  : "\n=== DONE — nothing to change (already USD + CAD?) ===\n";
