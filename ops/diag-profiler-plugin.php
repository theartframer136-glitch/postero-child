<?php
/**
 * Plugin Name: AF Request Profiler (temporary diagnostic)
 * Description: Installed for ~15 minutes by the diagnose-cpu workflow to attribute CPU: logs one line per PHP-reaching request (cache HITs never reach PHP, so every line here is real PHP cost). Self-expires 2 hours after install, caps its file at 30MB, and the workflow deactivates and deletes it when the window closes.
 */
if ( PHP_SAPI === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) { return; }
if ( @filemtime( __FILE__ ) + 7200 < time() ) { return; } // self-expire even if cleanup never runs

register_shutdown_function( function () {
    $lf = WP_CONTENT_DIR . '/uploads/af-req-log.txt';
    $sz = @filesize( $lf );
    if ( $sz !== false && $sz > 30 * 1024 * 1024 ) { return; }

    $dur = microtime( true ) - (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
    if ( defined( 'DOING_CRON' ) && DOING_CRON )      { $type = 'cron'; }
    elseif ( defined( 'DOING_AJAX' ) && DOING_AJAX )  { $type = 'ajax'; }
    elseif ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { $type = 'rest'; }
    elseif ( is_admin() )                              { $type = 'admin'; }
    else                                               { $type = 'page'; }

    $line = sprintf(
        "%s\t%.3f\t%d\t%s\t%s\t%s\t%s\t%s\n",
        gmdate( 'H:i:s' ),
        $dur,
        (int) ( memory_get_peak_usage( true ) / 1048576 ),
        $type,
        $_SERVER['REMOTE_ADDR'] ?? '-',
        $_SERVER['REQUEST_METHOD'] ?? '-',
        substr( (string) ( $_SERVER['REQUEST_URI'] ?? '-' ), 0, 160 ),
        substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '-' ), 0, 110 )
    );
    @file_put_contents( $lf, $line, FILE_APPEND | LOCK_EX );
} );
