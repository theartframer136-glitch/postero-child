<?php

/**
 * Upsell modal shown on the plugin's start screen (free version only).
 *
 * The markup is rendered hidden on the start screen; upsell-modal.js opens it
 * unless it was already closed today (tracked in the browser's localStorage).
 */
class GRWP_Upsell_Modal {

    public function __construct() {
        add_action( 'admin_footer', array( $this, 'render' ) );
    }

    /**
     * Only on the plugin's start screen, for users who can manage the plugin.
     *
     * @return bool
     */
    private function is_start_screen() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        return 'google-reviews' === $page && current_user_can( 'manage_options' );
    }

    /**
     * Plan columns. Filterable so prices/features can be adjusted without editing markup.
     *
     * @return array
     */
    private function get_plans() {
        $plans = array(
            array(
                'name'     => __( 'Free', 'embedder-for-google-reviews' ),
                'plan'     => __( 'Current plan', 'embedder-for-google-reviews' ),
                'price'    => '$0',
                'per'      => '',
                'note'     => __( 'Free forever', 'embedder-for-google-reviews' ),
                'featured' => false,
                'features' => array(
                    array( true, __( 'Up to 20 reviews', 'embedder-for-google-reviews' ) ),
                    array( true, __( 'Slider & grid layouts', 'embedder-for-google-reviews' ) ),
                    array( true, __( '8 designs', 'embedder-for-google-reviews' ) ),
                    array( true, __( 'Shortcode for any page builder', 'embedder-for-google-reviews' ) ),
                    array( false, __( 'Pull ALL reviews', 'embedder-for-google-reviews' ) ),
                    array( false, __( 'Premium designs', 'embedder-for-google-reviews' ) ),
                    array( false, __( 'Filter reviews by rating & keywords', 'embedder-for-google-reviews' ) ),
                ),
                'cta'      => '',
                'cta_url'  => '',
            ),
            array(
                'name'     => __( 'PRO', 'embedder-for-google-reviews' ),
                'plan'     => __( 'Recommended', 'embedder-for-google-reviews' ),
                'price'    => '$5.49',
                'per'      => __( '/ month', 'embedder-for-google-reviews' ),
                'note'     => __( '14-day free trial', 'embedder-for-google-reviews' ),
                'featured' => true,
                'features' => array(
                    array( true, '<strong>' . __( 'Pull ALL reviews', 'embedder-for-google-reviews' ) . '</strong> <span class="rem-feat__sub">' . __( 'instead of only 20', 'embedder-for-google-reviews' ) . '</span>' ),
                    array( true, '<strong>' . __( 'Premium designs', 'embedder-for-google-reviews' ) . '</strong>' ),
                    array( true, '<strong>' . __( 'Filter reviews by rating & keywords', 'embedder-for-google-reviews' ) . '</strong>' ),
                    array( true, __( 'Slider & grid layouts', 'embedder-for-google-reviews' ) ),
                    array( true, __( 'Shortcode for any page builder', 'embedder-for-google-reviews' ) ),
                    array( true, __( 'Automatic weekly review updates', 'embedder-for-google-reviews' ) ),
                    array( true, __( 'More customization options', 'embedder-for-google-reviews' ) ),
                ),
                'cta'      => __( 'Start free trial', 'embedder-for-google-reviews' ),
                'cta_url'  => grwp_fs()->get_trial_url(),
            ),
        );

        return apply_filters( 'grwp_upsell_modal_plans', $plans );
    }

    /**
     * Optional logo: drop a logo.png / logo.svg into dist/images to show it in the header.
     *
     * @return string
     */
    private function get_logo_url() {
        foreach ( array( 'logo.svg', 'logo.png' ) as $file ) {
            if ( file_exists( GR_BASE_PATH . 'dist/images/' . $file ) ) {
                return GR_PLUGIN_DIR_URL . 'dist/images/' . $file;
            }
        }

        return '';
    }

    /**
     * Modal markup, rendered hidden into the footer of the start screen.
     */
    public function render() {
        if ( ! $this->is_start_screen() ) {
            return;
        }

        $logo        = $this->get_logo_url();
        $plans       = $this->get_plans();
        $pricing_url = grwp_fs()->get_upgrade_url();
        $allowed     = array(
            'strong' => array(),
            'span'   => array( 'class' => array() ),
        );
        ?>
        <div class="rem-overlay" id="grwp-upsell-modal" aria-hidden="true">
            <div class="rem-modal" role="dialog" aria-modal="true" aria-labelledby="grwp-upsell-title">

                <div class="rem-head">
                    <?php if ( $logo ) : ?>
                        <img src="<?php echo esc_url( $logo ); ?>" alt="">
                    <?php endif; ?>
                    <span class="rem-head__name" id="grwp-upsell-title">ReviewsEmbedder</span>
                    <button type="button" class="rem-close" data-rem-close aria-label="<?php esc_attr_e( 'Close', 'embedder-for-google-reviews' ); ?>">&times;</button>
                </div>

                <div class="rem-cols">
                    <?php foreach ( $plans as $plan ) : ?>
                        <div class="rem-col<?php echo ! empty( $plan['featured'] ) ? ' rem-col--featured' : ''; ?>">
                            <h3 class="rem-col__name"><?php echo esc_html( $plan['name'] ); ?></h3>
                            <div class="rem-col__plan"><?php echo esc_html( $plan['plan'] ); ?></div>

                            <?php if ( '' !== $plan['price'] ) : ?>
                                <div class="rem-price">
                                    <span class="rem-price__val"><?php echo esc_html( $plan['price'] ); ?></span>
                                    <?php if ( '' !== $plan['per'] ) : ?>
                                        <span class="rem-price__per"><?php echo esc_html( $plan['per'] ); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="rem-price__note"><?php echo esc_html( $plan['note'] ); ?></div>

                            <ul class="rem-feats">
                                <?php foreach ( $plan['features'] as $feature ) : ?>
                                    <li class="rem-feat<?php echo $feature[0] ? '' : ' rem-feat--off'; ?>">
                                        <?php if ( $feature[0] ) : ?>
                                            <svg class="rem-feat__icon" viewBox="0 0 16 16" aria-hidden="true"><path fill="#1898d0" d="M6.2 11.6 2.6 8l1.2-1.2 2.4 2.4 6-6L13.4 4.4z"/></svg>
                                        <?php else : ?>
                                            <svg class="rem-feat__icon" viewBox="0 0 16 16" aria-hidden="true"><path fill="#c3c9cd" d="M4.4 3.2 8 6.8l3.6-3.6 1.2 1.2L9.2 8l3.6 3.6-1.2 1.2L8 9.2l-3.6 3.6-1.2-1.2L6.8 8 3.2 4.4z"/></svg>
                                        <?php endif; ?>
                                        <span><?php echo wp_kses( $feature[1], $allowed ); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ( ! empty( $plan['cta'] ) ) : ?>
                                <a class="rem-buy" href="<?php echo esc_url( $plan['cta_url'] ); ?>"><?php echo esc_html( $plan['cta'] ); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="rem-foot">
                    <strong><?php esc_html_e( 'Show every review your customers left.', 'embedder-for-google-reviews' ); ?></strong>
                    <?php
                    printf(
                        /* translators: %s: link to the pricing page */
                        esc_html__( 'Compare all plans and prices on the %s.', 'embedder-for-google-reviews' ),
                        '<a href="' . esc_url( $pricing_url ) . '">' . esc_html__( 'pricing page', 'embedder-for-google-reviews' ) . '</a>'
                    );
                    ?>
                </div>

            </div>
        </div>
        <?php
    }

}
