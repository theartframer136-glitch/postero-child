<?php
/**
 * PRO Features hub — hero, featured highlights, categorized feature grid,
 * Free vs PRO table, CTA.
 *
 * Marketing content only: this lives in the Free plugin, so it must never
 * reference PRO option keys/ids — it only describes features and links out.
 * Styling is class-based (see css/components/pro-features.css); no inline
 * styles so the tab follows the theme tokens in light and dark.
 *
 * @package Click_To_Chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ctc_pricing_url = 'https://holithemes.com/plugins/click-to-chat/pricing/';
$ctc_install_url = 'https://holithemes.com/plugins/click-to-chat/installation-of-click-to-chat-pro-plugin/';

/*
 * Featured flagships — the two headline features that don't sit inside a single
 * category (lead capture / platform integration). Shown as prominent bands so
 * they never get orphaned as one-item groups.
 */
$ctc_pro_featured = array(
	array(
		'icon'  => 'dashicons-forms',
		'title' => 'Greetings Form Filling',
		'desc'  => 'Capture name, email, phone and more before the chat opens — 8 field types including date & international number.',
		'url'   => 'https://holithemes.com/plugins/click-to-chat/greetings-form/',
	),
	// array(
	// 'icon'  => 'dashicons-cart',
	// 'title' => 'WooCommerce',
	// 'desc'  => 'Product- and cart-aware chat with pre-filled messages, tailored to your store.',
	// 'url'   => 'https://holithemes.com/plugins/click-to-chat/woocommerce/',
	// ),
);

/*
 * Feature catalogue grouped by area. Each group renders as a titled section of
 * cards. `url` deep-links to the matching docs page. Kept as data (not markup)
 * so it stays easy to align with the PRO plugin.
 */
$ctc_pro_groups = array(
	array(
		'title' => 'Agents & Availability',
		'icon'  => 'dashicons-businessperson',
		'items' => array(
			array(
				'icon'  => 'dashicons-groups',
				'title' => 'Multi-Agent Support',
				'desc'  => 'Add multiple agents with their own name, avatar, number and availability ranges.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/multi-agent/',
			),
			array(
				'icon'  => 'dashicons-clock',
				'title' => __( 'Business Hours', 'click-to-chat-for-whatsapp' ),
				'desc'  => 'Online/offline scheduling with multiple time slots. Show an offline number and call-to-action when away.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/docs/business-hours-online-offline/',
			),
			array(
				'icon'  => 'dashicons-randomize',
				'title' => 'Random & Sequential Numbers',
				'desc'  => 'Distribute chats across a list of WhatsApp numbers — randomly or in sequence — to balance the load.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/docs/random-number/',
			),
		),
	),
	array(
		'title' => 'Smart Display & Targeting',
		'icon'  => 'dashicons-visibility',
		'items' => array(
			array(
				'icon'  => 'dashicons-admin-site-alt3',
				'title' => 'Country-Based Display',
				'desc'  => 'Show or hide the chat button based on the visitor’s country.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/display/',
			),
			array(
				'icon'  => 'dashicons-calendar-alt',
				'title' => 'Scheduling & Triggers',
				'desc'  => 'Target by day, time and login status — and auto-open the greeting by time delay, scroll depth or element in view.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/display/',
			),
			array(
				'icon'  => 'dashicons-admin-page',
				'title' => 'Page-Level Settings',
				'desc'  => 'Override the style, number or greeting per page — plus fixed / absolute position types.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/change-values-at-page-level/',
			),
		),
	),
	array(
		'title' => 'Analytics & Tracking',
		'icon'  => 'dashicons-chart-line',
		'items' => array(
			array(
				'icon'  => 'dashicons-chart-bar',
				'title' => __( 'Google Ads Conversion', 'click-to-chat-for-whatsapp' ),
				'desc'  => 'Fire a Google Ads conversion when a visitor clicks to chat.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/google-ads-conversion/',
			),
			array(
				'icon'  => 'dashicons-facebook',
				'title' => 'Meta Conversion API',
				'desc'  => 'Send server-side Facebook events for reliable conversion tracking.',
				// todo: point at a dedicated Meta Conversion API docs page once available.
				'url'   => 'https://holithemes.com/plugins/click-to-chat/',
			),
			array(
				'icon'  => 'dashicons-cloud',
				'title' => 'Webhooks & Dynamic Variables',
				'desc'  => 'Send chat events anywhere with dynamic variables like {url}, cookie values and UTM parameters.',
				'url'   => 'https://holithemes.com/plugins/click-to-chat/webhook/',
			),
		),
	),
);

/*
 * Free vs PRO comparison. `free` / `pro` are booleans; `free_note` optionally
 * qualifies a partial Free capability (e.g. "Basic").
 */
$ctc_compare_rows = array(
	array(
		'label'     => 'WhatsApp chat button & widget',
		'free'      => true,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Styles & visual customization',
		'free'      => true,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Greetings dialog',
		'free'      => true,
		'free_note' => 'Basic',
		'pro'       => true,
	),
	array(
		'label'     => 'Form filling & lead capture',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Multi-agent support',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Business hours & availability',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Country & schedule targeting',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Random / sequential numbers',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Google Ads & Meta conversion tracking',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	array(
		'label'     => 'Webhooks & dynamic variables',
		'free'      => false,
		'free_note' => '',
		'pro'       => true,
	),
	// array(
	// 'label'     => 'WooCommerce integration',
	// 'free'      => false,
	// 'free_note' => '',
	// 'pro'       => true,
	// ),
	// array(
	// 'label'     => 'Page-level settings',
	// 'free'      => false,
	// 'free_note' => 'Basic',
	// 'pro'       => true,
	// ),
);
?>
<div class="ctc-pro">

	<!-- Hero -->
	<div class="ctc-pro-hero">
		<span class="ctc-pro-eyebrow"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'PRO', 'click-to-chat-for-whatsapp' ); ?></span>
		<h2 class="ctc-pro-hero-title">Turn more visitors into WhatsApp conversations</h2>
		<p class="ctc-pro-hero-sub">Unlock multi-agent routing, lead-capture forms, business hours and conversion tracking — everything you need to engage visitors and grow your business.</p>
		<div class="ctc-pro-hero-actions">
			<a href="<?php echo esc_url( $ctc_pricing_url ); ?>" target="_blank" rel="noopener" class="ctc-pro-btn ctc-pro-btn-primary">
				View Pricing & Get PRO
				<span class="dashicons dashicons-arrow-right-alt"></span>
			</a>
			<?php // <a href="#pro-features/ctc-pro-compare"  class="ctc-pro-btn ctc-pro-btn-ghost">Compare Free vs PRO</a> ?>
			<a href="#ctc-pro-compare"  class="ctc-pro-btn ctc-pro-btn-ghost">Compare Free vs PRO</a>
		</div>
		<ul class="ctc-pro-trust">
			<li><span class="dashicons dashicons-shield-alt"></span> 14-day money-back guarantee</li>
			<!-- <li><span class="dashicons dashicons-update"></span> 1 year of updates & support</li> -->
			<!-- <li><span class="dashicons dashicons-yes-alt"></span> Works alongside the Free plugin</li> -->
		</ul>
	</div>

	<!-- Featured flagships -->
	<!-- <div class="ctc-pro-featured">
		<?php foreach ( $ctc_pro_featured as $ctc_feat ) { ?>
			<a class="ctc-pro-highlight" href="<?php echo esc_url( $ctc_feat['url'] ); ?>" target="_blank" rel="noopener">
				<span class="ctc-pro-highlight-icon"><span class="dashicons <?php echo esc_attr( $ctc_feat['icon'] ); ?>"></span></span>
				<span class="ctc-pro-highlight-body">
					<span class="ctc-pro-highlight-title">
						<?php echo esc_html( $ctc_feat['title'] ); ?>
						<span class="ctc-pro-card-arrow dashicons dashicons-arrow-right-alt"></span>
					</span>
					<span class="ctc-pro-highlight-desc"><?php echo esc_html( $ctc_feat['desc'] ); ?></span>
				</span>
			</a>
		<?php } ?>
	</div> -->

	<!-- Feature groups -->
	<!-- <?php foreach ( $ctc_pro_groups as $ctc_group ) { ?>
		<section class="ctc-pro-section">
			<h3 class="ctc-pro-section-title">
				<span class="dashicons <?php echo esc_attr( $ctc_group['icon'] ); ?>"></span>
				<?php echo esc_html( $ctc_group['title'] ); ?>
			</h3>
			<div class="ctc-pro-grid">
				<?php foreach ( $ctc_group['items'] as $ctc_item ) { ?>
					<a class="ctc-pro-card" href="<?php echo esc_url( $ctc_item['url'] ); ?>" target="_blank" rel="noopener">
						<span class="ctc-pro-card-icon"><span class="dashicons <?php echo esc_attr( $ctc_item['icon'] ); ?>"></span></span>
						<span class="ctc-pro-card-body">
							<span class="ctc-pro-card-title">
								<?php echo esc_html( $ctc_item['title'] ); ?>
								<span class="ctc-pro-card-arrow dashicons dashicons-arrow-right-alt"></span>
							</span>
							<span class="ctc-pro-card-desc"><?php echo esc_html( $ctc_item['desc'] ); ?></span>
						</span>
					</a>
				<?php } ?>
			</div>
		</section>
	<?php } ?> -->

	<!-- Comparison -->
	<section class="ctc-pro-section" id="ctc-pro-compare">
		<h3 class="ctc-pro-section-title">
			<span class="dashicons dashicons-editor-table"></span>
			Free vs PRO
		</h3>
		<div class="ctc-pro-compare-wrap">
			<table class="ctc-pro-compare">
				<thead>
					<tr>
						<th class="ctc-pro-compare-feature">Feature</th>
						<th>Free</th>
						<th class="ctc-pro-compare-pro"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'PRO', 'click-to-chat-for-whatsapp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ctc_compare_rows as $ctc_row ) { ?>
						<tr>
							<td class="ctc-pro-compare-feature"><?php echo esc_html( $ctc_row['label'] ); ?></td>
							<td>
								<?php if ( ! empty( $ctc_row['free_note'] ) ) { ?>
									<span class="ctc-pro-compare-note"><?php echo esc_html( $ctc_row['free_note'] ); ?></span>
								<?php } elseif ( $ctc_row['free'] ) { ?>
									<span class="dashicons dashicons-yes ctc-pro-yes" aria-label="Included"></span>
								<?php } else { ?>
									<span class="dashicons dashicons-minus ctc-pro-no" aria-label="Not included"></span>
								<?php } ?>
							</td>
							<td>
								<?php if ( $ctc_row['pro'] ) { ?>
									<span class="dashicons dashicons-yes ctc-pro-yes ctc-pro-yes-gold" aria-label="Included"></span>
								<?php } else { ?>
									<span class="dashicons dashicons-minus ctc-pro-no" aria-label="Not included"></span>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</section>

	<!-- Closing CTA -->
	<div class="ctc-pro-cta-band">
		<div class="ctc-pro-cta-copy">
			<h3>Ready to do more with WhatsApp?</h3>
			<p>Join thousands of businesses using Click to Chat PRO. Backed by a 14-day money-back guarantee.</p>
		</div>
		<div class="ctc-pro-cta-actions">
			<a href="<?php echo esc_url( $ctc_pricing_url ); ?>" target="_blank" rel="noopener" class="ctc-pro-btn ctc-pro-btn-primary">
				View Pricing & Get PRO
				<span class="dashicons dashicons-external"></span>
			</a>
			<a href="<?php echo esc_url( $ctc_install_url ); ?>" target="_blank" rel="noopener" class="ctc-pro-cta-link">
				Installation guide
			</a>
		</div>
	</div>

</div>
