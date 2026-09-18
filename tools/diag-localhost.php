<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Eight homepage links point at http://localhost/wordpress/postero/shop/.
 * Posts, post meta and the slider tables were already searched and came up
 * empty, so search EVERY text column of EVERY table. Read-only.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }
global $wpdb;
// JSON storage (Slider Revolution, Elementor) escapes slashes, so the same
// address is stored as localhost\/wordpress\/postero and a LIKE on the plain
// form finds nothing. Search both spellings.
$needles = array( 'localhost/wordpress/postero', 'localhost\\/wordpress\\/postero' );
$needle = $needles[0];
$tables = $wpdb->get_col( 'SHOW TABLES' );
$hits = 0;
echo "searching " . count( $tables ) . " tables for '{$needle}'\n\n";
foreach ( $tables as $t ) {
    $cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$t}`" );
    $pk = null;
    foreach ( $cols as $c ) if ( $c->Key === 'PRI' ) { $pk = $c->Field; break; }
    foreach ( $cols as $c ) {
        if ( ! preg_match( '/char|text|blob|json/i', $c->Type ) ) continue;
        $n = 0;
        foreach ( $needles as $nd ) $n += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE `{$c->Field}` LIKE %s", '%' . $wpdb->esc_like( $nd ) . '%' ) );
        if ( ! $n ) continue;
        $needle = $needles[1];  // context extraction below: prefer the escaped form where it matched
        foreach ( $needles as $nd ) if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE `{$c->Field}` LIKE %s", '%' . $wpdb->esc_like( $nd ) . '%' ) ) ) { $needle = $nd; break; }
        $hits += $n;
        printf( "  %-40s %-28s %d row(s)\n", $t, $c->Field, $n );
        $sel = $pk ? "`{$pk}`, " : '';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$sel}`{$c->Field}` AS v FROM `{$t}` WHERE `{$c->Field}` LIKE %s OR `{$c->Field}` LIKE %s LIMIT 3", '%' . $wpdb->esc_like( $needles[0] ) . '%', '%' . $wpdb->esc_like( $needles[1] ) . '%' ), ARRAY_A );
        foreach ( $rows as $r ) {
            $v = (string) $r['v']; $pos = strpos( $v, $needle );
            $ctx = substr( $v, max( 0, $pos - 90 ), 220 );
            printf( "      %s%s\n", $pk ? "{$pk}={$r[$pk]}  " : '', str_replace( array( "\n", "\r" ), ' ', $ctx ) );
            // for wp_options, the name is the useful key
            if ( $t === $wpdb->options && isset( $r[ $pk ] ) ) {
                $name = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_id=%d", $r[ $pk ] ) );
                echo "      option_name = {$name}\n";
            }
            if ( $t === $wpdb->postmeta && isset( $r[ $pk ] ) ) {
                $m = $wpdb->get_row( $wpdb->prepare( "SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_id=%d", $r[ $pk ] ) );
                if ( $m ) echo "      post_id={$m->post_id}  meta_key={$m->meta_key}  (" . get_post_type( $m->post_id ) . ': ' . get_the_title( $m->post_id ) . ")\n";
            }
        }
    }
}
echo "\ntotal matching rows across the database: {$hits}\n";
if ( ! $hits ) echo "NOT in the database at all — so it is in a theme or plugin file. See the file grep above.\n";
