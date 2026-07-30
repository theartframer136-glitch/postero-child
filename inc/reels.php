<?php
/**
 * Reels commerce  —  requirements §10
 *
 * Short vertical videos as a selling surface: each reel carries the products
 * shown in it, so a visitor goes from watching to buying in one tap.
 *
 *   • af_reel post type — clips managed like any other content
 *   • product tagging   — the products featured, rendered as shoppable chips
 *   • Instagram sync    — pulls your reels via the Instagram Graph API once a
 *                         token is saved; without one, manual upload is the
 *                         fallback (the spec marks the API as dependent)
 *   • analytics         — views and product clicks per reel, so you can see
 *                         which videos actually sell
 */
if (!defined('ABSPATH')) exit;

// ── post type ────────────────────────────────────────────────────────
add_action('init', function() {
    register_post_type('af_reel', array(
        'labels' => array(
            'name'          => 'Reels',
            'singular_name' => 'Reel',
            'add_new_item'  => 'Add New Reel',
            'edit_item'     => 'Edit Reel',
        ),
        'public'              => true,
        'show_in_menu'        => true,
        'menu_icon'           => 'dashicons-video-alt3',
        'menu_position'       => 27,
        'supports'            => array('title', 'thumbnail'),
        'has_archive'         => false,
        'rewrite'             => array('slug' => 'reel'),
        'exclude_from_search' => true,
    ));
}, 9);

/** Videos come from three places; work out which this URL is. */
function af_reel_video_kind($url) {
    if (preg_match('#youtube\.com/(shorts/|watch\?v=)([\w-]{6,})#', $url, $m)) return array('youtube', $m[2]);
    if (preg_match('#youtu\.be/([\w-]{6,})#', $url, $m))                        return array('youtube', $m[1]);
    if (preg_match('#instagram\.com/(reel|p)/([\w-]+)#', $url, $m))             return array('instagram', $m[2]);
    if (preg_match('#\.(mp4|webm|mov)(\?|$)#i', $url))                          return array('file', $url);
    return array('unknown', '');
}

// ── admin: video + tagged products ───────────────────────────────────
add_action('add_meta_boxes', function() {
    add_meta_box('af_reel_box', 'Reel video & tagged products', function($post) {
        wp_nonce_field('af_reel_save', 'af_reel_nonce');
        $video = get_post_meta($post->ID, '_af_reel_video', true);
        $tags  = (array) get_post_meta($post->ID, '_af_reel_products', true);
        echo '<p><label><strong>Video URL</strong> — a YouTube Short, an Instagram reel link, or a direct .mp4<br>';
        echo '<input type="url" name="af_reel_video" style="width:100%" value="' . esc_attr($video) . '" placeholder="https://youtube.com/shorts/… or https://instagram.com/reel/…"></label></p>';

        echo '<p><strong>Tagged products</strong> — the pieces shown in this reel:</p>';
        echo '<select name="af_reel_products[]" multiple size="10" style="width:100%">';
        $products = get_posts(array('post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC'));
        foreach ($products as $p) {
            echo '<option value="' . (int) $p->ID . '"' . (in_array($p->ID, array_map('intval', $tags), true) ? ' selected' : '') . '>'
               . esc_html($p->post_title) . '</option>';
        }
        echo '</select><p style="color:#646970;">Hold Ctrl / Cmd to pick several.</p>';

        $views  = (int) get_post_meta($post->ID, '_af_reel_views', true);
        $clicks = (int) get_post_meta($post->ID, '_af_reel_clicks', true);
        if ($views || $clicks) {
            printf('<p><strong>Analytics:</strong> %d views · %d product clicks · %s%% CTR</p>',
                $views, $clicks, $views ? number_format($clicks / $views * 100, 1) : '0');
        }
    }, 'af_reel', 'normal', 'high');
});

add_action('save_post_af_reel', function($post_id) {
    if (!isset($_POST['af_reel_nonce']) || !wp_verify_nonce(wp_unslash($_POST['af_reel_nonce']), 'af_reel_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, '_af_reel_video', esc_url_raw(wp_unslash($_POST['af_reel_video'] ?? '')));
    $ids = array_values(array_filter(array_map('absint', (array) ($_POST['af_reel_products'] ?? array()))));
    update_post_meta($post_id, '_af_reel_products', $ids);
});

// ── admin: analytics columns ─────────────────────────────────────────
add_filter('manage_af_reel_posts_columns', function($cols) {
    $cols['af_views']  = 'Views';
    $cols['af_clicks'] = 'Product clicks';
    $cols['af_ctr']    = 'CTR';
    $cols['af_src']    = 'Source';
    return $cols;
});
add_action('manage_af_reel_posts_custom_column', function($col, $post_id) {
    $views  = (int) get_post_meta($post_id, '_af_reel_views', true);
    $clicks = (int) get_post_meta($post_id, '_af_reel_clicks', true);
    if ($col === 'af_views')  echo number_format($views);
    if ($col === 'af_clicks') echo number_format($clicks);
    if ($col === 'af_ctr')    echo $views ? esc_html(number_format($clicks / $views * 100, 1)) . '%' : '—';
    if ($col === 'af_src')    echo get_post_meta($post_id, '_af_reel_ig_id', true) ? 'Instagram' : 'Manual';
}, 10, 2);

// ── analytics: the browser reports views and clicks ──────────────────
function af_reel_track_handler() {
    check_ajax_referer('af_reel_track', 'nonce');
    $id   = absint($_POST['reel'] ?? 0);
    $what = ($_POST['what'] ?? '') === 'click' ? '_af_reel_clicks' : '_af_reel_views';
    if (!$id || get_post_type($id) !== 'af_reel') wp_send_json_error();
    // plain meta increment — a few lost races are fine for trend data
    update_post_meta($id, $what, (int) get_post_meta($id, $what, true) + 1);
    wp_send_json_success();
}
add_action('wp_ajax_af_reel_track',        'af_reel_track_handler');
add_action('wp_ajax_nopriv_af_reel_track', 'af_reel_track_handler');

// ── Instagram sync (API dependent — manual upload is the fallback) ───
add_action('init', function() {
    if (!wp_next_scheduled('af_reels_ig_sync')) wp_schedule_event(time() + 900, 'hourly', 'af_reels_ig_sync');
});

add_action('af_reels_ig_sync', function() {
    $token = trim((string) get_option('af_reels_ig_token', ''));
    if ($token === '') return;                       // no credentials yet — manual mode
    $r = wp_remote_get(add_query_arg(array(
        'fields'       => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        'limit'        => 25,
        'access_token' => $token,
    ), 'https://graph.instagram.com/me/media'), array('timeout' => 30));
    if (is_wp_error($r)) { update_option('af_reels_ig_last', 'error: ' . $r->get_error_message(), false); return; }
    $body = json_decode(wp_remote_retrieve_body($r), true);
    if (empty($body['data'])) {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : 'no media returned';
        update_option('af_reels_ig_last', 'error: ' . $msg, false);
        return;
    }
    $made = 0;
    foreach ($body['data'] as $m) {
        if (($m['media_type'] ?? '') !== 'VIDEO') continue;
        $exists = get_posts(array('post_type' => 'af_reel', 'post_status' => 'any', 'meta_key' => '_af_reel_ig_id', 'meta_value' => $m['id'], 'fields' => 'ids', 'posts_per_page' => 1));
        if ($exists) continue;
        $title = trim(wp_trim_words((string) ($m['caption'] ?? ''), 10, '…'));
        $pid = wp_insert_post(array(
            'post_type'   => 'af_reel',
            'post_status' => 'draft',                // a human tags products, then publishes
            'post_title'  => $title !== '' ? $title : 'Instagram reel ' . $m['id'],
        ));
        if (is_wp_error($pid) || !$pid) continue;
        update_post_meta($pid, '_af_reel_ig_id', sanitize_text_field($m['id']));
        update_post_meta($pid, '_af_reel_video', esc_url_raw($m['permalink'] ?? ($m['media_url'] ?? '')));
        $made++;
    }
    update_option('af_reels_ig_last', sprintf('ok: %d new reel(s) at %s UTC', $made, gmdate('Y-m-d H:i')), false);
});

// settings box on the Reels list screen
add_action('admin_notices', function() {
    $s = get_current_screen();
    if (!$s || $s->id !== 'edit-af_reel' || !current_user_can('manage_options')) return;
    if (isset($_POST['af_reels_ig_nonce']) && wp_verify_nonce(wp_unslash($_POST['af_reels_ig_nonce']), 'af_reels_ig')) {
        update_option('af_reels_ig_token', sanitize_text_field(wp_unslash($_POST['af_reels_ig_token'] ?? '')), false);
        if (!empty($_POST['af_reels_ig_now'])) do_action('af_reels_ig_sync');
        echo '<div class="notice notice-success"><p>Saved.</p></div>';
    }
    $last = get_option('af_reels_ig_last', 'never run');
    echo '<div class="notice" style="padding:14px 16px;">';
    echo '<form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
    wp_nonce_field('af_reels_ig', 'af_reels_ig_nonce');
    echo '<strong>Instagram sync</strong>';
    echo '<input type="password" name="af_reels_ig_token" placeholder="Instagram Graph API access token" style="min-width:320px;" value="' . esc_attr(get_option('af_reels_ig_token', '')) . '">';
    echo '<button class="button">Save</button>';
    echo '<button class="button" name="af_reels_ig_now" value="1">Save &amp; sync now</button>';
    echo '<span style="color:#646970;">Hourly once a token is set — synced reels arrive as drafts for product tagging. Last: ' . esc_html($last) . '</span>';
    echo '</form></div>';
});

// ── front end: [af_reels] — vertical shoppable video cards ───────────
function af_reels_render($atts = array()) {
    $atts  = shortcode_atts(array('limit' => 8, 'title' => 'Shop Through Videos'), $atts, 'af_reels');
    $reels = get_posts(array('post_type' => 'af_reel', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['limit']));
    if (!$reels) return '';

    $nonce = wp_create_nonce('af_reel_track');
    ob_start();
    ?>
    <section class="af-reels" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
      <?php if ($atts['title']) : ?><h2 class="af-reels-title"><?php echo esc_html($atts['title']); ?></h2><?php endif; ?>
      <div class="af-reels-row">
      <?php foreach ($reels as $reel) :
          $video = get_post_meta($reel->ID, '_af_reel_video', true);
          list($kind, $ref) = af_reel_video_kind($video);
          if ($kind === 'unknown') continue;
          $poster = get_the_post_thumbnail_url($reel->ID, 'large');
          if (!$poster && $kind === 'youtube') $poster = 'https://i.ytimg.com/vi/' . rawurlencode($ref) . '/hqdefault.jpg';
          $tags = array_filter(array_map('wc_get_product', (array) get_post_meta($reel->ID, '_af_reel_products', true)));
          ?>
        <article class="af-reel" data-reel="<?php echo (int) $reel->ID; ?>" data-kind="<?php echo esc_attr($kind); ?>" data-ref="<?php echo esc_attr($ref); ?>">
          <div class="af-reel-stage" <?php if ($poster) echo 'style="background-image:url(' . esc_url($poster) . ')"'; ?>>
            <button type="button" class="af-reel-play" aria-label="Play video">▶</button>
          </div>
          <h3 class="af-reel-name"><?php echo esc_html(get_the_title($reel)); ?></h3>
          <?php if ($tags) : ?>
          <div class="af-reel-tags">
            <?php foreach (array_slice($tags, 0, 3) as $p) : ?>
              <a class="af-reel-tag" href="<?php echo esc_url(get_permalink($p->get_id())); ?>" data-click="1">
                <?php echo wp_kses_post($p->get_image('thumbnail')); ?>
                <span><b><?php echo esc_html(wp_trim_words($p->get_name(), 5, '…')); ?></b>
                <em><?php echo wp_kses_post(wc_price(wc_get_price_to_display($p))); ?></em></span>
              </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
    </section>
    <style>
    .af-reels{max-width:1220px;margin:34px auto;padding:0 16px;}
    .af-reels-title{font-size:26px;margin:0 0 16px;}
    .af-reels-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;}
    .af-reel-stage{position:relative;aspect-ratio:9/16;border-radius:14px;overflow:hidden;background:#111 center/cover no-repeat;}
    .af-reel-stage iframe,.af-reel-stage video{position:absolute;inset:0;width:100%;height:100%;border:0;object-fit:cover;}
    .af-reel-play{position:absolute;inset:0;margin:auto;width:56px;height:56px;border-radius:50%;border:0;cursor:pointer;
      background:rgba(255,255,255,.92);color:#1a1a1a;font-size:19px;box-shadow:0 6px 18px rgba(0,0,0,.35);transition:transform .15s;}
    .af-reel-play:hover{transform:scale(1.08);}
    .af-reel-name{font-size:14px;margin:10px 2px 6px;line-height:1.35;}
    .af-reel-tags{display:flex;flex-direction:column;gap:6px;}
    .af-reel-tag{display:flex;gap:8px;align-items:center;border:1px solid #ece4cf;border-radius:10px;padding:6px 8px;text-decoration:none;background:#fffdf8;}
    .af-reel-tag:hover{border-color:#c9a84c;}
    .af-reel-tag img{width:34px;height:34px;border-radius:7px;object-fit:cover;flex:0 0 auto;}
    .af-reel-tag b{display:block;font-size:12px;color:#1a1a1a;line-height:1.3;}
    .af-reel-tag em{font-style:normal;font-size:12px;color:#a8872e;font-weight:700;}
    </style>
    <script>
    (function(){
      document.querySelectorAll('.af-reels').forEach(function(sec){
        if (sec.dataset.init) return; sec.dataset.init = '1';
        var ajax = sec.dataset.ajax, nonce = sec.dataset.nonce;
        function track(id, what){
          var b = new URLSearchParams();
          b.set('action','af_reel_track'); b.set('nonce',nonce); b.set('reel',id); b.set('what',what);
          (navigator.sendBeacon && navigator.sendBeacon(ajax, b)) ||
            fetch(ajax,{method:'POST',credentials:'same-origin',body:b}).catch(function(){});
        }
        sec.addEventListener('click', function(e){
          var tag = e.target.closest('.af-reel-tag');
          if (tag) { track(tag.closest('.af-reel').dataset.reel, 'click'); return; }
          var play = e.target.closest('.af-reel-play');
          if (!play) return;
          var card = play.closest('.af-reel'), stage = card.querySelector('.af-reel-stage');
          var kind = card.dataset.kind, ref = card.dataset.ref;
          // one player at a time — put every other card back to its poster
          sec.querySelectorAll('.af-reel-stage iframe,.af-reel-stage video').forEach(function(v){
            var st = v.closest('.af-reel-stage'); v.remove();
            var btn = document.createElement('button'); btn.type='button'; btn.className='af-reel-play'; btn.textContent='▶';
            st.appendChild(btn);
          });
          play.remove();
          if (kind === 'youtube') {
            var f = document.createElement('iframe');
            f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(ref) + '?autoplay=1&playsinline=1&rel=0';
            f.allow = 'autoplay; encrypted-media; picture-in-picture';
            f.allowFullscreen = true;
            stage.appendChild(f);
          } else if (kind === 'instagram') {
            var f2 = document.createElement('iframe');
            f2.src = 'https://www.instagram.com/reel/' + encodeURIComponent(ref) + '/embed/';
            f2.allow = 'autoplay; encrypted-media';
            stage.appendChild(f2);
          } else {
            var v2 = document.createElement('video');
            v2.src = ref; v2.controls = true; v2.autoplay = true; v2.playsInline = true; v2.muted = false;
            stage.appendChild(v2);
          }
          track(card.dataset.reel, 'view');
        });
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('af_reels', 'af_reels_render');

// ── the /reels/ page, same pattern as the other custom pages ─────────
add_action('template_redirect', function() {
    $req = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    if ($req !== 'reels') return;
    status_header(200);
    get_header();
    echo '<div style="padding:30px 0 50px;">';
    $out = af_reels_render(array('limit' => 24, 'title' => 'Shop Through Videos'));
    echo $out !== '' ? $out : '<div style="max-width:900px;margin:40px auto;text-align:center;color:#6b6250;">'
        . '<h1>Shop Through Videos</h1><p>Reels are on their way — check back soon.</p></div>';
    echo '</div>';
    get_footer();
    exit;
}, 1);
