<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Give the Corporate Printing row the icon every other category row has.
 *
 * The icons are not term thumbnails and not a theme filter: each menu item
 * carries a meta key postero_megamenu_item_data, a blob the parent theme's
 * mega-menu reads, whose "icon" field names a glyph in the theme's own icon
 * font (postero-icon-gift-box, postero-icon-star, and so on). A row created
 * with wp_update_nav_menu_item has no such blob, which is why the new row
 * renders with nothing in front of it.
 *
 * The blob is COPIED from a sibling rather than invented, so every other field
 * in it keeps whatever the theme expects, and only "icon" is changed.
 *
 * Run: DRY=1 wp eval-file tools/fix-menu-icon.php --allow-root
 *      ICON=postero-icon-... wp eval-file tools/fix-menu-icon.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry  = (bool) getenv( 'DRY' );
$SLUG = 'corporate-printing';
$MODEL_SLUG = 'banners-signage';        // the sibling whose blob we copy

$menu  = wp_get_nav_menu_object( 'Category' );
$items = wp_get_nav_menu_items( $menu->term_id );

$term  = get_term_by( 'slug', $SLUG, 'product_cat' );
$model = get_term_by( 'slug', $MODEL_SLUG, 'product_cat' );
if ( ! $term || ! $model ) { echo "ABORT: term not found.\n"; return; }

$ours = null; $model_item = null;
foreach ( $items as $i ) {
    if ( $i->type !== 'taxonomy' || $i->object !== 'product_cat' ) continue;
    if ( (int) $i->object_id === (int) $term->term_id )  $ours = $i;
    if ( (int) $i->object_id === (int) $model->term_id ) $model_item = $i;
}
if ( ! $ours )       { echo "ABORT: no menu row for {$SLUG}.\n"; return; }
if ( ! $model_item ) { echo "ABORT: no menu row for {$MODEL_SLUG} to copy from.\n"; return; }

echo "=== MENU ICON " . ( $dry ? '(DRY RUN)' : '(WRITING)' ) . " ===\n\n";
printf( "  our row   : #%d \"%s\"\n", $ours->ID, html_entity_decode( $ours->title ) );
printf( "  model row : #%d \"%s\"\n\n", $model_item->ID, html_entity_decode( $model_item->title ) );

$raw = get_post_meta( $model_item->ID, 'postero_megamenu_item_data', true );
$blob = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
if ( ! is_array( $blob ) ) { echo "ABORT: could not read the model's postero_megamenu_item_data.\n"; return; }

echo "  the model's blob, in full:\n";
foreach ( $blob as $k => $v ) printf( "      %-28s = %s\n", $k, is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) );

$have = get_post_meta( $ours->ID, 'postero_megamenu_item_data', true );
echo "\n  our row currently has: " . ( $have ? 'a blob' : 'NOTHING — which is why no icon draws' ) . "\n";

// Every icon name actually present in the theme's icon font, so the choice is
// made from what exists rather than from a guess at a naming convention.
$icons = array();
foreach ( array( get_template_directory() . '/assets/css/postero-icon.css',
                 get_template_directory() . '/assets/fonts/icons/postero-icon.css',
                 get_template_directory() . '/style.css' ) as $css ) {
    if ( ! file_exists( $css ) ) continue;
    if ( preg_match_all( '/\.(postero-icon-[a-z0-9\-]+):before/i', file_get_contents( $css ), $m ) ) {
        foreach ( $m[1] as $n ) $icons[ $n ] = 1;
    }
}
if ( ! $icons ) {
    foreach ( glob( get_template_directory() . '/assets/css/*.css' ) as $css ) {
        if ( preg_match_all( '/\.(postero-icon-[a-z0-9\-]+):before/i', file_get_contents( $css ), $m ) ) {
            foreach ( $m[1] as $n ) $icons[ $n ] = 1;
        }
    }
}
$icons = array_keys( $icons );
sort( $icons );
printf( "\n  %d icons exist in the theme font.\n", count( $icons ) );
$likely = array_values( array_filter( $icons, function ( $n ) {
    return preg_match( '/print|paper|file|doc|office|business|card|board|sign|banner|flag|tag|building|briefcase|company|corporate/i', $n );
} ) );
echo "  ones that suit a printing category:\n";
foreach ( $likely as $n ) echo "      {$n}\n";
if ( ! $likely ) { echo "      (none matched; first 40 of all)\n"; foreach ( array_slice( $icons, 0, 40 ) as $n ) echo "      {$n}\n"; }

$want = getenv( 'ICON' ) ?: '';
if ( $want === '' ) {
    foreach ( array( 'postero-icon-print', 'postero-icon-printer', 'postero-icon-file', 'postero-icon-paper',
                     'postero-icon-business-card', 'postero-icon-briefcase', 'postero-icon-building',
                     'postero-icon-board', 'postero-icon-flag', 'postero-icon-tag' ) as $c ) {
        if ( in_array( $c, $icons, true ) ) { $want = $c; break; }
    }
}
if ( $want === '' && $likely ) $want = $likely[0];
if ( $want === '' ) $want = isset( $blob['icon'] ) ? $blob['icon'] : '';

if ( $want === '' ) { echo "\nABORT: could not settle on an icon name.\n"; return; }
printf( "\n  chosen icon: %s%s\n", $want, in_array( $want, $icons, true ) ? '  (exists in the font)' : '  (NOT found in the font — check this)' );

$blob['icon'] = $want;
// The blob describes this row, not the model's, so drop anything that would
// carry the model's own content across.
foreach ( array( 'icon_custom_image', 'icon_custom_image_url' ) as $k ) if ( isset( $blob[ $k ] ) ) $blob[ $k ] = '';

if ( $dry ) {
    echo "\n  WOULD write postero_megamenu_item_data on #{$ours->ID}:\n";
    foreach ( $blob as $k => $v ) printf( "      %-28s = %s\n", $k, is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) );
    echo "\nDry run. Nothing written.\n";
    return;
}

// Write it the way the theme stores it. get_post_meta un-serialises an array
// transparently, so the model came back as an ARRAY and the first attempt wrote
// it back as a JSON STRING. The read-back looked right because the script
// json_decoded its own string, but the theme expects an array and got text, so
// no <i class="menu-icon"> was ever emitted. update_post_meta serialises an
// array by itself; hand it the array.
update_post_meta( $ours->ID, 'postero_megamenu_item_data', wp_slash( $blob ) );
echo "\n  written.\n";
$check = get_post_meta( $ours->ID, 'postero_megamenu_item_data', true );
printf( "  read back: %s, icon = %s\n",
    gettype( $check ),
    ( is_array( $check ) && isset( $check['icon'] ) ) ? $check['icon'] : '(FAILED — not an array)' );
$model_type = gettype( get_post_meta( $model_item->ID, 'postero_megamenu_item_data', true ) );
printf( "  model is : %s   %s\n", $model_type,
    $model_type === gettype( $check ) ? '(same shape — good)' : '(DIFFERENT SHAPE — still wrong)' );

if ( function_exists( 'do_action' ) ) do_action( 'litespeed_purge_all' );
echo "  cache purged.\n";
