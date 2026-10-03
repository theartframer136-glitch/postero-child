<?php

if (! defined('ABSPATH'))
	exit;
?>

<!-- Slider main container -->
<div class="swiper reviews_embedder_slider <?php echo esc_attr( $arrows_class ?? 'grwp-arrows-below' ); ?>" data-grwp-marquee="<?php echo ! empty( $marquee_active ) ? '1' : '0'; ?>">
	<!-- Additional required wrapper -->
	<div class="swiper-wrapper">
