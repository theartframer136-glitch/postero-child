<?php
/**
 * License tab for the free plugin. Shown only while Premium Addons Pro is not active.
 *
 * @package PremiumAddons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Includes\Helper_Functions;

$offer_label  = __( '30% OFF', 'premium-addons-for-elementor' );
$get_pro_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'free-license-page', 'wp-dash', 'get-pro', 'free' );
$compare_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/', 'free-license-page', 'wp-dash', 'compare', 'free' );
$account_link = Helper_Functions::get_campaign_link( 'https://clients.leap13.com/', 'free-license-page', 'wp-dash', 'account', 'free' );

?>

<div class="pa-section-content">
	<div class="row">
		<div class="col-full">
			<div id="pa-license-settings" class="pa-settings-tab">

				<div class="pa-section-info-wrap">
					<div class="pa-section-info">
						<div class="pa-license-heading">
							<h4><?php esc_html_e( 'Extend the free version with Pro', 'premium-addons-for-elementor' ); ?></h4>
							<span class="pa-license-offer">
								<?php
								/* translators: %s: offer label, e.g. 30% OFF. */
								echo esc_html( sprintf( __( '%s new licenses', 'premium-addons-for-elementor' ), $offer_label ) );
								?>
							</span>
						</div>
						<p><?php esc_html_e( 'Premium Addons Pro installs alongside the free plugin and unlocks the Pro widgets, the container add-ons and the Premium Templates library, with priority support.', 'premium-addons-for-elementor' ); ?></p>
						<ul class="pa-license-list pa-license-checks">
							<li><svg class="pa-license-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg><?php esc_html_e( 'Pro widgets and container add-ons in the editor', 'premium-addons-for-elementor' ); ?></li>
							<li><svg class="pa-license-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg><?php esc_html_e( 'Premium Templates library, in Elementor and through the MCP tools', 'premium-addons-for-elementor' ); ?></li>
							<li><svg class="pa-license-check" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg><?php esc_html_e( 'White labeling and priority support', 'premium-addons-for-elementor' ); ?></li>
						</ul>
					</div>

					<div class="pa-section-info-cta">
						<a class="button pa-btn" href="<?php echo esc_url( $get_pro_link ); ?>" target="_blank"><?php esc_html_e( 'Get Premium Addons Pro', 'premium-addons-for-elementor' ); ?></a>
						<a class="pa-license-secondary" href="<?php echo esc_url( $compare_link ); ?>" target="_blank"><?php esc_html_e( 'Compare free and Pro', 'premium-addons-for-elementor' ); ?></a>
					</div>
				</div>

				<div class="pa-section-info-wrap">
					<div class="pa-section-info">
						<h4><?php esc_html_e( 'Already have a license?', 'premium-addons-for-elementor' ); ?></h4>
						<ol class="pa-license-list">
							<li>
								<?php
								printf(
									/* translators: %s: link to the customer account. */
									esc_html__( 'Download the Pro plugin from %s → Downloads.', 'premium-addons-for-elementor' ),
									'<a href="' . esc_url( $account_link ) . '" target="_blank">' . esc_html__( 'your account', 'premium-addons-for-elementor' ) . '</a>'
								);
								?>
							</li>
							<li><?php esc_html_e( 'Upload it under Plugins → Add Plugin → Upload Plugin, then activate it.', 'premium-addons-for-elementor' ); ?></li>
							<li><?php esc_html_e( 'Come back to this tab: the license key field appears here.', 'premium-addons-for-elementor' ); ?></li>
						</ol>
					</div>

					<div class="pa-section-info-cta">
						<span class="pa-section-info-label"><?php esc_html_e( 'Your key is in the same account under License Keys.', 'premium-addons-for-elementor' ); ?></span>
					</div>
				</div>

			</div>
		</div>
	</div>
</div> <!-- End Section Content -->
