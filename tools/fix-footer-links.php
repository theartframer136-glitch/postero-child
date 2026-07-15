<?php
/**
 * Fix the Elementor footer template links.
 * The footer's icon-list items exist but point to "#" or the theme demo
 * site (demo2wpopal.b-cdn.net). This maps each item text to its real page
 * and appends the missing items (Reviews & Press, Customize Your Picture,
 * Privacy Policy). Idempotent. Run: wp eval-file tools/fix-footer-links.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

global $wpdb;

/* Map footer item text -> live URL (relative keeps it domain-safe) */
$map = array(
    'about us'              => '/about/',
    'contact us'            => '/contact/',
    'low price guarantee'   => '/low-price-guarantee/',
    'shipping & delivery'   => '/shipping-delivery/',
    'return policy'         => '/returns-exchanges/',
    'refund policy'         => '/refund-policy/',
    'gift cards'            => '/gift-cards/',
    'faqs'                  => '/faqs/',
    'help centre'           => '/help-support/',
    'live chat'             => '/contact/',
    'safe & easy payments'  => '/faqs/',
    'content ethics policy' => '/content-ethics-policy/',
    'terms of use'          => '/terms-conditions/',
    'terms of service'      => '/terms-conditions/',
    'legal imprint'         => '/legal-imprint/',
    'refer a friend'        => '/refer-a-friend/',
    'blogs'                 => '/blog/',
    'wholesale programs'    => '/wholesale-corporate/',
    'affiliates'            => '/affiliates/',
    'artists & creators'    => '/artists/',
    'exhibitions & events'  => '/exhibitions-events/',
    'track your order'      => '/track-your-order/',
);

/* Items to append if absent: anchor-text => [url, list-locator-text] */
$additions = array(
    'Reviews & Press'        => array( '/reviews-press/',          'gift cards' ),    // Customer Service list
    'Customize Your Picture' => array( '/customize-your-picture/', 'gift cards' ),    // Customer Service list
    'Privacy Policy'         => array( '/privacy-policy/',         'legal imprint' ), // Legal list
);

function taf_norm( $t ) {
    $t = html_entity_decode( (string) $t, ENT_QUOTES );
    $t = strtolower( trim( wp_strip_all_tags( $t ) ) );
    return preg_replace( '/\s+/', ' ', $t );
}

/* Find the footer template: the post whose _elementor_data contains the
   footer-only phrase "Wholesale Programs". */
$rows = $wpdb->get_results(
    "SELECT pm.post_id, p.post_type, p.post_title
     FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
     WHERE pm.meta_key = '_elementor_data'
       AND pm.meta_value LIKE '%Wholesale Programs%'
       AND p.post_status = 'publish'"
);
if ( ! $rows ) { echo "ERROR: footer template not found\n"; return; }

foreach ( $rows as $row ) {
    echo "Template #{$row->post_id} ({$row->post_type}) \"{$row->post_title}\"\n";
    $raw  = get_post_meta( $row->post_id, '_elementor_data', true );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) { echo "  SKIP: cannot decode _elementor_data\n"; continue; }

    $fixed = 0; $added = 0; $existing_texts = array();

    $walk = function ( &$elements ) use ( &$walk, &$fixed, &$existing_texts, $map ) {
        foreach ( $elements as &$el ) {
            if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) $walk( $el['elements'] );
            if ( ( $el['widgetType'] ?? '' ) !== 'icon-list' ) continue;
            if ( empty( $el['settings']['icon_list'] ) || ! is_array( $el['settings']['icon_list'] ) ) continue;
            foreach ( $el['settings']['icon_list'] as &$item ) {
                $key = taf_norm( $item['text'] ?? '' );
                $existing_texts[ $key ] = true;
                if ( ! isset( $map[ $key ] ) ) continue;
                $cur = $item['link']['url'] ?? '';
                $new = $map[ $key ];
                // fix dead (#/empty) or demo-site URLs; leave good live URLs alone
                if ( $cur === '' || $cur === '#' || strpos( $cur, 'demo2wpopal' ) !== false ) {
                    if ( ! is_array( $item['link'] ?? null ) ) $item['link'] = array();
                    $item['link']['url'] = $new;
                    $item['link']['is_external'] = '';
                    $item['link']['nofollow'] = '';
                    $fixed++;
                    echo "  FIX  \"" . $item['text'] . "\" -> {$new}\n";
                }
            }
            unset( $item );
        }
        unset( $el );
    };
    $walk( $data );

    /* Append missing items to the right list (located by an anchor text) */
    $append = function ( &$elements ) use ( &$append, &$added, $additions, $existing_texts ) {
        foreach ( $elements as &$el ) {
            if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) $append( $el['elements'] );
            if ( ( $el['widgetType'] ?? '' ) !== 'icon-list' ) continue;
            if ( empty( $el['settings']['icon_list'] ) || ! is_array( $el['settings']['icon_list'] ) ) continue;
            $texts_here = array_map( function ( $i ) { return taf_norm( $i['text'] ?? '' ); }, $el['settings']['icon_list'] );
            foreach ( $additions as $label => $info ) {
                list( $url, $locator ) = $info;
                if ( isset( $existing_texts[ taf_norm( $label ) ] ) ) continue;      // already in footer
                if ( ! in_array( $locator, $texts_here, true ) ) continue;            // wrong list
                $template_item = $el['settings']['icon_list'][0];
                $template_item['_id']  = substr( md5( $label . wp_rand() ), 0, 7 );
                $template_item['text'] = $label;
                $template_item['link'] = array( 'url' => $url, 'is_external' => '', 'nofollow' => '' );
                if ( isset( $template_item['selected_icon'] ) && isset( $el['settings']['icon_list'][0]['selected_icon'] ) ) {
                    $template_item['selected_icon'] = $el['settings']['icon_list'][0]['selected_icon'];
                }
                $el['settings']['icon_list'][] = $template_item;
                $added++;
                echo "  ADD  \"{$label}\" -> {$url}\n";
            }
        }
        unset( $el );
    };
    $append( $data );

    if ( $fixed || $added ) {
        update_post_meta( $row->post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
        echo "  SAVED: {$fixed} fixed, {$added} added\n";
    } else {
        echo "  no changes needed\n";
    }
}

/* Flush Elementor's generated CSS/data cache so the footer re-renders */
if ( class_exists( '\Elementor\Plugin' ) ) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "Elementor cache cleared\n";
}
echo "=== DONE ===\n";
