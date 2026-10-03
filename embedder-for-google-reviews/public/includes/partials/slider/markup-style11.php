<?php
if (! defined('ABSPATH'))
    exit;

/**
 * Style-11 slider slide markup.
 * Layout: avatar (+ Google badge) & name + 5-star rating (top) → review text
 * (middle) → date (bottom). Stars render on a 5-point scale (filled + empty).
 *
 * @var array  $review
 * @var string $google_svg
 * @var bool   $link_user_profiles
 * @var object $this  GRWP_Reviews_Widget_Slider
 */

$rating = intval( $review['rating'] );
?>

<div class="swiper-slide">
    <div class="g-review">

        <!-- ── TOP: avatar + name (left) and stars (right) ── -->
        <div class="gr-inner-header">
            <div class="gr-author">
                <span class="gr-avatar">
                    <img
                        class="gr-profile"
                        src="<?php echo esc_attr( $review['profile_photo_url'] ); ?>"
                        width="48"
                        height="48"
                        alt=""
                        data-imgtype="image/png"
                        referrerpolicy="no-referrer"
                    />
                    <img src="<?php echo esc_attr( $google_svg ); ?>" alt="" class="gr-google" />
                </span>
                <?php if ( $link_user_profiles ) : ?>
                    <a href="<?php echo esc_attr( $review['author_url'] ); ?>" target="_blank" class="gr-name">
                        <?php echo esc_html( $review['name'] ); ?>
                    </a>
                <?php else : ?>
                    <span class="gr-name"><?php echo esc_html( $review['name'] ); ?></span>
                <?php endif; ?>
            </div>

            <span class="gr-stars">
                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                    <span class="gr-star <?php echo $i <= $rating ? 'gr-star-full' : 'gr-star-empty'; ?>"><?php echo $i <= $rating ? '★' : '☆'; ?></span>
                <?php endfor; ?>
            </span>
        </div>

        <!-- ── MIDDLE: review text ── -->
        <div class="gr-inner-body">
            <p><?php echo esc_html( $review['text'] ); ?></p>
        </div>

        <!-- ── BOTTOM: date ── -->
        <div class="gr-inner-footer">
            <span class="time"><?php echo esc_html( $review['time'] ); ?></span>
        </div>

    </div>
</div>
