<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put Corporate Printing into the Shop by Collection strip.
 *
 * WHAT THE STRIP ACTUALLY IS, measured rather than assumed. It is not a term
 * query: it keeps categories holding nothing (Framed Canvases, Gifts, Direct
 * from Artists) while leaving out Corporate Printing, which holds 33 products.
 * It is not the parent theme's PHP either. It is a hand-written Elementor HTML
 * widget on the front page - id 13b3f41 - whose markup is a literal list of
 * <button class="top-cat-btn" data-cat="..."> elements. Corporate Printing was
 * never typed into it, and nothing else was ever going to put it there.
 *
 * SO THIS EDITS THAT ONE SETTING, AND AS LITTLE AS POSSIBLE.
 * The button goes in directly after Banners & Signage, which is where the
 * category sits in the header menu and the shop sidebar, so the three agree.
 *
 * The edit is a targeted splice into the RAW JSON rather than a decode and
 * re-encode of all 193KB. Re-encoding would rewrite unicode escapes and
 * numeric formatting across a page built by other people, and a diff nobody
 * asked for is a risk nobody needs. The result is checked for valid JSON
 * before it is written, and the original is saved to a file first.
 *
 * Run: DRY=1 wp eval-file tools/add-corporate-tab.php --allow-root
 *      wp eval-file tools/add-corporate-tab.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$dry     = (bool) getenv( 'DRY' );
$SLUG    = 'corporate-printing';
$LABEL   = 'Corporate Printing';
$AFTER   = 'banners-signage';        // the button this one follows
$WIDGET  = '13b3f41';

echo '=== CORPORATE PRINTING -> COLLECTION STRIP ' . ( $dry ? '(DRY RUN)' : '(WRITING)' ) . " ===\n\n";

/* ---- the category has to exist and be worth linking to ---- */
$term = get_term_by( 'slug', $SLUG, 'product_cat' );
if ( ! $term || is_wp_error( $term ) ) { echo "ABORT: no product_cat '{$SLUG}'.\n"; return; }
printf( "  category : #%d \"%s\"  parent=%d  products=%d\n",
    $term->term_id, html_entity_decode( $term->name ), $term->parent, $term->count );
if ( (int) $term->parent !== 0 ) { echo "ABORT: it is not top-level; the strip only shows top-level categories.\n"; return; }

$front = (int) get_option( 'page_on_front' );
if ( ! $front ) { echo "ABORT: no static front page.\n"; return; }
$raw = get_post_meta( $front, '_elementor_data', true );
if ( ! is_string( $raw ) || $raw === '' ) { echo "ABORT: front page has no _elementor_data string.\n"; return; }
printf( "  front page: #%d, _elementor_data %d bytes\n", $front, strlen( $raw ) );

if ( json_decode( $raw, true ) === null ) { echo "ABORT: the stored data is not valid JSON to begin with.\n"; return; }

/* ---- already there? ---- */
if ( strpos( $raw, 'data-cat=\\"' . $SLUG . '\\"' ) !== false
  || strpos( $raw, 'data-cat="' . $SLUG . '"' ) !== false ) {
    echo "\n  The strip already has a Corporate Printing button. Nothing to do.\n";
    return;
}

/* ---- find the Banners & Signage button, in the raw JSON ---- */
// Inside JSON the markup is escaped: data-cat=\"banners-signage\"
$needle = 'data-cat=\\"' . $AFTER . '\\"';
$at = strpos( $raw, $needle );
if ( $at === false ) { echo "ABORT: could not find the {$AFTER} button in the stored markup.\n"; return; }
$close = strpos( $raw, '<\\/button>', $at );
if ( $close === false ) $close = strpos( $raw, '</button>', $at );
if ( $close === false ) { echo "ABORT: found the {$AFTER} button but not its closing tag.\n"; return; }
$endTag = ( strpos( $raw, '<\\/button>', $at ) !== false ) ? '<\\/button>' : '</button>';
$insertAt = $close + strlen( $endTag );

printf( "\n  anchor   : %s button at byte %d, closes at %d\n", $AFTER, $at, $insertAt );
printf( "  context  : ...%s...\n", str_replace( array( "\n", "\r" ), ' ', substr( $raw, max( 0, $at - 60 ), 180 ) ) );

/* ---- the new button, escaped the same way the surrounding markup is ---- */
$button = "\\n\\n<button class=\\\"top-cat-btn\\\" data-cat=\\\"{$SLUG}\\\">\\n{$LABEL}\\n{$endTag}";
printf( "\n  inserting: %s\n", $button );

$new = substr( $raw, 0, $insertAt ) . $button . substr( $raw, $insertAt );

/* ---- it must still be valid JSON, and must actually contain the button ---- */
$check = json_decode( $new, true );
if ( $check === null ) { echo "\nABORT: the edit would leave invalid JSON. Nothing written.\n"; return; }
$found = false;
$walk = function ( $nodes ) use ( &$walk, &$found, $WIDGET, $SLUG ) {
    foreach ( (array) $nodes as $n ) {
        if ( ! is_array( $n ) ) continue;
        if ( ( $n['id'] ?? '' ) === $WIDGET ) {
            $html = $n['settings']['html'] ?? '';
            if ( strpos( $html, 'data-cat="' . $SLUG . '"' ) !== false ) $found = true;
        }
        if ( ! empty( $n['elements'] ) ) $walk( $n['elements'] );
    }
};
$walk( $check );
printf( "  check    : decodes cleanly, and widget %s now carries the button: %s\n",
    $WIDGET, $found ? 'yes' : 'NO' );
if ( ! $found ) { echo "ABORT: the button did not land in widget {$WIDGET}. Nothing written.\n"; return; }

if ( $dry ) { echo "\n  DRY RUN - nothing written.\n"; return; }

/* ---- keep the original where it can be put back ---- */
$backup = WP_CONTENT_DIR . '/uploads/af-elementor-front-' . gmdate( 'Ymd-His' ) . '.json';
@file_put_contents( $backup, $raw );
printf( "\n  original saved to %s\n", $backup );

update_post_meta( $front, '_elementor_data', wp_slash( $new ) );

// Elementor serves a cached CSS/markup bundle per page; a data change it did
// not make itself will not be noticed until that is dropped.
if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "  Elementor cache cleared.\n";
}
delete_transient( 'wc_term_counts' );
do_action( 'litespeed_purge_all' );
echo "  caches cleared.\n";

/* ---- read it back from the database, not from the variable ---- */
$after = get_post_meta( $front, '_elementor_data', true );
printf( "\n  read back: %d bytes, contains the button: %s\n", strlen( $after ),
    strpos( $after, 'data-cat=\\"' . $SLUG . '\\"' ) !== false || strpos( $after, 'data-cat="' . $SLUG . '"' ) !== false ? 'yes' : 'NO' );

echo "\n=== THE STRIP NOW READS ===\n";
if ( preg_match_all( '/data-cat=\\\\?"([a-z0-9-]+)\\\\?"/i', $after, $m ) ) {
    foreach ( $m[1] as $s ) printf( "  %s%s\n", $s, $s === $SLUG ? '   <-- added' : '' );
}
