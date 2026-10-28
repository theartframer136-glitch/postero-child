<?php
/**
 * Templates Search Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

?>

<label for="elementor-template-library-filter-text" class="elementor-screen-only"><?php echo esc_html( __( 'Search Templates:', 'premium-addons-for-elementor' ) ); ?></label>
<input id="elementor-template-library-filter-text" placeholder="<?php echo esc_attr__( 'Search By Template ID', 'premium-addons-for-elementor' ); ?>">
<i class="eicon-search" aria-hidden="true"></i>
