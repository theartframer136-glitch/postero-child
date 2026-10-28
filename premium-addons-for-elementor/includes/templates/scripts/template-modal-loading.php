<?php
/**
 * Templates Loader View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

?>
<div class="elementor-loader-wrapper">
	<div class="elementor-loader">
		<div class="elementor-loader-box"></div>
		<div class="elementor-loader-box"></div>
		<div class="elementor-loader-box"></div>
		<div class="elementor-loader-box"></div>
	</div>
	<div class="elementor-loading-title"><?php echo wp_kses_post( __( 'Loading', 'premium-addons-for-elementor' ) ); ?></div>
</div>
