<?php
/**
 * Review enhancements  (requirements §7/§13, audit item 20)
 *
 *  • star distribution — a 5→1 histogram at the top of the reviews tab
 *  • photo uploads — reviewers can attach a photo; shown in the review and
 *    collected into a gallery strip above the list
 *  • verified badge — WooCommerce's "(verified owner)" restyled as a badge
 */
if (!defined('ABSPATH')) exit;

// ── photo upload on the review form ──────────────────────────────
add_filter('woocommerce_product_review_comment_form_args', function($args) {
    $args['comment_field'] .= '<p class="comment-form-af-photo"><label for="af_review_photo">Add a photo <span style="color:#8a8170;">(optional — your wall, your unboxing)</span></label>'
        . '<input type="file" id="af_review_photo" name="af_review_photo" accept="image/jpeg,image/png,image/webp"></p>';
    return $args;
});

// the comment form must be multipart for the file to arrive
add_action('wp_footer', function() {
    if (!function_exists('is_product') || !is_product()) return;
    echo '<script>(function(){var f=document.getElementById("commentform");if(f){f.setAttribute("enctype","multipart/form-data");}})();</script>';
}, 63);

add_action('comment_post', function($comment_id) {
    if (empty($_FILES['af_review_photo']['name'])) return;
    $comment = get_comment($comment_id);
    if (!$comment || get_post_type($comment->comment_post_ID) !== 'product') return;

    $file = $_FILES['af_review_photo'];
    if (!empty($file['error']) || $file['size'] > 8 * 1024 * 1024) return;
    $check = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
    if (empty($check['ext']) || !in_array($check['ext'], array('jpg', 'jpeg', 'png', 'webp'), true)) return;
    $probe = @getimagesize($file['tmp_name']);
    if (!$probe) return;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $att = media_handle_upload('af_review_photo', 0, array('post_title' => 'Review photo'));
    if (!is_wp_error($att)) {
        // private: reachable by URL in the review, but not listed publicly
        wp_update_post(array('ID' => $att, 'post_status' => 'private'));
        update_comment_meta($comment_id, 'af_review_photo', (int) $att);
    }
});

// show the photo inside its review
add_filter('comment_text', function($text, $comment) {
    if (!$comment || get_post_type($comment->comment_post_ID) !== 'product') return $text;
    $att = (int) get_comment_meta($comment->comment_ID, 'af_review_photo', true);
    if (!$att) return $text;
    $url   = wp_get_attachment_image_url($att, 'medium');
    $full  = wp_get_attachment_image_url($att, 'large');
    if (!$url) return $text;
    return $text . '<p class="af-rev-photo"><a href="' . esc_url($full ?: $url) . '" target="_blank" rel="noopener">'
        . '<img src="' . esc_url($url) . '" alt="Customer photo" loading="lazy"></a></p>';
}, 10, 2);

// ── histogram + photo strip, injected at the top of #reviews ─────
add_action('wp_footer', function() {
    if (!function_exists('is_product') || !is_product()) return;
    $product = af_wc_product();
    if (!$product || !comments_open($product->get_id())) return;
    $total = (int) $product->get_review_count();
    if ($total < 1) return;

    $dist = array(5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0);
    $photos = array();
    $comments = get_comments(array('post_id' => $product->get_id(), 'status' => 'approve', 'type' => 'review', 'number' => 200));
    if (!$comments) $comments = get_comments(array('post_id' => $product->get_id(), 'status' => 'approve', 'number' => 200));
    foreach ($comments as $c) {
        $r = (int) get_comment_meta($c->comment_ID, 'rating', true);
        if ($r >= 1 && $r <= 5) $dist[$r]++;
        $att = (int) get_comment_meta($c->comment_ID, 'af_review_photo', true);
        if ($att && count($photos) < 8) {
            $u = wp_get_attachment_image_url($att, 'thumbnail');
            if ($u) $photos[] = array($u, wp_get_attachment_image_url($att, 'large') ?: $u);
        }
    }
    $rated = array_sum($dist);
    if (!$rated) return;
    $avg = (float) $product->get_average_rating();
    ?>
    <template id="af-revsum-tpl">
      <div class="af-revsum">
        <div class="af-revsum-score">
          <strong><?php echo esc_html(number_format($avg, 1)); ?></strong>
          <span>★★★★★</span>
          <small><?php echo (int) $total; ?> review<?php echo $total === 1 ? '' : 's'; ?></small>
        </div>
        <div class="af-revsum-bars">
          <?php foreach ($dist as $stars => $n): $pct = round($n / $rated * 100); ?>
          <div class="af-revsum-row"><em><?php echo (int) $stars; ?>★</em>
            <i><b style="width:<?php echo (int) $pct; ?>%"></b></i><u><?php echo (int) $n; ?></u></div>
          <?php endforeach; ?>
        </div>
        <?php if ($photos): ?>
        <div class="af-revsum-photos">
          <?php foreach ($photos as $ph): ?>
            <a href="<?php echo esc_url($ph[1]); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url($ph[0]); ?>" alt="Customer photo" loading="lazy"></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </template>
    <style>
    .af-revsum{display:flex;gap:22px;flex-wrap:wrap;align-items:center;border:1px solid #ece4cf;background:#fffdf8;
      border-radius:14px;padding:16px 18px;margin:0 0 22px;}
    .af-revsum-score{text-align:center;min-width:90px;}
    .af-revsum-score strong{display:block;font-size:30px;color:#1a1a1a;line-height:1;}
    .af-revsum-score span{color:#e0a52e;font-size:13px;letter-spacing:2px;}
    .af-revsum-score small{display:block;color:#8a8170;font-size:11.5px;margin-top:2px;}
    .af-revsum-bars{flex:1 1 220px;min-width:200px;display:flex;flex-direction:column;gap:4px;}
    .af-revsum-row{display:flex;align-items:center;gap:8px;font-size:12px;color:#6b6250;}
    .af-revsum-row em{font-style:normal;width:24px;}
    .af-revsum-row i{flex:1;height:8px;border-radius:99px;background:#eee6d2;overflow:hidden;font-style:normal;display:block;}
    .af-revsum-row b{display:block;height:100%;background:#e0a52e;border-radius:99px;}
    .af-revsum-row u{text-decoration:none;width:26px;text-align:right;}
    .af-revsum-photos{display:flex;gap:8px;flex-wrap:wrap;width:100%;}
    .af-revsum-photos img{width:62px;height:62px;object-fit:cover;border-radius:9px;border:1px solid #ece4cf;}
    .af-rev-photo img{max-width:180px;border-radius:10px;border:1px solid #ece4cf;margin-top:6px;}
    .woocommerce-review__verified{background:#eefaf3;color:#1e8b56;font-style:normal;font-size:10.5px;font-weight:700;
      padding:2px 8px;border-radius:999px;margin-left:6px;}
    </style>
    <script>
    (function(){
      var tpl = document.getElementById('af-revsum-tpl');
      var host = document.querySelector('#reviews');
      if (!tpl || !host) return;
      host.insertBefore(tpl.content.cloneNode(true), host.firstChild);
    })();
    </script>
    <?php
}, 64);
