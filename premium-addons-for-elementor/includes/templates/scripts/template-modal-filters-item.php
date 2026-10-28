<?php
/**
 * Template Library Filter Item
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

?>
<label class="premium-template-filter-label">
	<input type="radio" value="{{ slug }}" <# if ( '' === slug ) { #> checked<# } #> name="premium-template-filter">
	<span>{{ title.replace('&amp;', '&') }}</span>
</label>
