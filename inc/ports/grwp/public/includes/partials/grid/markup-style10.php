<?php
if (! defined('ABSPATH'))
    exit;

/**
 * Style-10 grid card markup.
 * Layout: stars + G logo (top) → review text (middle) → avatar + name + date (bottom).
 *
 * @var array  $review
 * @var string $google_svg
 * @var bool   $link_user_profiles
 * @var string $card_hidden_class
 * @var object $this  GRWP_Reviews_Widget_Grid
 */

$path = esc_attr( GR_PLUGIN_DIR_URL );
?>

<div class="g-review<?php echo esc_attr( $card_hidden_class ?? '' ); ?>">

    <!-- ── TOP: stars + Google logo ── -->
    <div class="gr-inner-header">
        <span class="gr-stars">
            <span class="stars-wrapper">
                <?php for ( $i = 1; $i <= intval( $review['rating'] ); $i++ ) : ?>
                    <img src="<?php echo $path; ?>dist/images/svg-star.svg" alt="" />
                <?php endfor; ?>
            </span>
        </span>
        <img src="<?php echo esc_attr( $google_svg ); ?>" alt="" class="gr-google" />
    </div>

    <!-- ── MIDDLE: review text ── -->
    <div class="gr-inner-body">
        <p><?php echo esc_html( $review['text'] ); ?></p>
    </div>

    <!-- ── BOTTOM: avatar + name + date ── -->
    <div class="gr-inner-footer">
        <img
            class="gr-profile"
            src="<?php echo esc_attr( $review['profile_photo_url'] ); ?>"
            width="44"
            height="44"
            alt=""
            data-imgtype="image/png"
            referrerpolicy="no-referrer"
        />
        <div class="gr-author-info">
            <?php if ( $link_user_profiles ) : ?>
                <a href="<?php echo esc_attr( $review['author_url'] ); ?>"
                   target="_blank"
                   class="gr-author-name">
                    <?php echo esc_html( $review['name'] ); ?>
                </a>
            <?php else : ?>
                <span class="gr-author-name"><?php echo esc_html( $review['name'] ); ?></span>
            <?php endif; ?>
            <span class="gr-author-date time"><?php echo esc_html( $review['time'] ); ?></span>
        </div>
    </div>

</div>

