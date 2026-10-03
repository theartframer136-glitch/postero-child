<?php

if (! defined('ABSPATH'))
	exit;


/**
 * @var bool   $hide_slider_arrows
 **/

?>

</div><!--swiper-wrapper-->
<!-- If we need pagination -->
<div class="swiper-pagination"></div>

<?php if (!$hide_slider_arrows) : ?>
<!-- If we need navigation buttons -->
<div class="slider-prev-next-wrapper">
    <div class="grwp-swiper-button-next"></div>
    <div class="grwp-swiper-button-prev"></div>
</div>
<?php endif; ?>

</div> <!--swiper-->
