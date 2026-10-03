<?php
$style  = filter_input( INPUT_POST, 'style', FILTER_UNSAFE_RAW );
$folder = filter_input( INPUT_POST, 'folder', FILTER_UNSAFE_RAW );
$basic  = filter_input( INPUT_POST, 'basic', FILTER_UNSAFE_RAW );

$css = (int) 1 === $basic ? wp_remote_get( __DIR__ . "/../assets/styles/basic-$folder.css" ) : wp_remote_get( __DIR__ . "/../assets/styles/$folder/$style" );

echo esc_html( $css );
