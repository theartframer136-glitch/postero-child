<?php
/**
 * Fatal recorder.
 *
 * Product pages are answering HTTP 500 with WordPress's critical-error page,
 * and wp-content/debug.log holds nothing newer than a week ago — so the fatals
 * are not being logged anywhere we can read. This records them ourselves:
 * message, file, line and the URL that triggered it, to its own small file.
 *
 * Deliberately tiny and dependency-free — it runs on every request, so it must
 * cost nothing until something actually dies.
 */
if (!defined('ABSPATH')) exit;

function af_fatal_log_path() { return WP_CONTENT_DIR . '/af-fatals.log'; }

register_shutdown_function(function() {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) return;
    $line = sprintf("[%s] %s | %s:%d | URL: %s\n",
        gmdate('Y-m-d H:i:s'),
        preg_replace('/\s+/', ' ', $e['message']),
        $e['file'], $e['line'],
        isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'cli');
    $path = af_fatal_log_path();
    // keep it small: a fatal on every request must not fill the disk
    if (file_exists($path) && filesize($path) > 256 * 1024) @unlink($path);
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
});
