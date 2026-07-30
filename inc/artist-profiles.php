<?php
/**
 * Artist profile pages  (requirements §4, audit item 17)
 *
 * /artists/ already lists artists (child categories of Direct from Artists),
 * and its cards link to /artists/<slug>/ — pages that mostly did not exist.
 * Any missing profile now renders virtually from the category itself: the
 * artist's image and bio, their catalogue, and a way back. A real page with
 * that slug, if one is ever written, quietly takes precedence.
 */
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function() {
    $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    if (!preg_match('#^artists/([a-z0-9-]+)$#', $path, $m)) return;
    if (get_page_by_path('artists/' . $m[1])) return;          // a real page wins

    $term = get_term_by('slug', $m[1], 'product_cat');
    if (!$term || is_wp_error($term)) return;                  // let WP 404 it
    $parent = get_term_by('slug', 'direct-from-artists', 'product_cat');
    if (!$parent || (int) $term->parent !== (int) $parent->term_id) return;

    $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
    $img      = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '';
    $desc     = trim($term->description) ?: ('Original artworks by ' . $term->name . ', printed and framed by The Art Framer.');
    $products = wc_get_products(array('status' => 'publish', 'limit' => 12, 'category' => array($term->slug)));
    $archive  = get_term_link($term);

    status_header(200);
    get_header();
    ?>
    <div class="af-artist">
      <div class="af-artist-hero">
        <?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($term->name); ?>"><?php endif; ?>
        <div>
          <p class="af-artist-kicker">Artist Profile</p>
          <h1><?php echo esc_html($term->name); ?></h1>
          <p class="af-artist-bio"><?php echo esc_html($desc); ?></p>
          <p class="af-artist-meta"><?php echo (int) $term->count; ?> artwork<?php echo (int) $term->count === 1 ? '' : 's'; ?> in the collection</p>
          <?php if (!is_wp_error($archive)): ?><a class="af-artist-cta" href="<?php echo esc_url($archive); ?>">Shop all by <?php echo esc_html($term->name); ?> →</a><?php endif; ?>
        </div>
      </div>
      <?php if ($products): ?>
      <h2 class="af-artist-sub">Selected works</h2>
      <div class="af-artist-grid">
        <?php foreach ($products as $p): ?>
          <a class="af-artist-card" href="<?php echo esc_url(get_permalink($p->get_id())); ?>">
            <?php echo wp_kses_post($p->get_image('woocommerce_thumbnail')); ?>
            <strong><?php echo esc_html(wp_trim_words($p->get_name(), 7, '…')); ?></strong>
            <em><?php echo wp_kses_post(wc_price(wc_get_price_to_display($p))); ?></em>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p class="af-artist-back"><a href="<?php echo esc_url(home_url('/artists/')); ?>">← All artists</a></p>
    </div>
    <style>
    .af-artist{max-width:1220px;margin:0 auto;padding:40px 16px 70px;}
    .af-artist-hero{display:grid;grid-template-columns:minmax(220px,380px) 1fr;gap:34px;align-items:center;
      border:1px solid #ece4cf;background:#fffdf8;border-radius:18px;padding:28px;margin-bottom:34px;}
    .af-artist-hero img{width:100%;border-radius:14px;object-fit:cover;aspect-ratio:1;}
    .af-artist-kicker{color:#a8872e;font-size:11.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;margin:0 0 6px;}
    .af-artist h1{margin:0 0 10px;font-size:34px;letter-spacing:-.5px;}
    .af-artist-bio{color:#5a5140;font-size:15px;line-height:1.7;margin:0 0 10px;}
    .af-artist-meta{color:#8a8170;font-size:13px;margin:0 0 16px;}
    .af-artist-cta{display:inline-block;background:#1a1a1a;color:#fff;text-decoration:none;font-weight:700;font-size:13.5px;
      padding:12px 20px;border-radius:11px;}
    .af-artist-cta:hover{background:#000;color:#fff;}
    .af-artist-sub{font-size:22px;margin:0 0 16px;}
    .af-artist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:18px;}
    .af-artist-card{border:1px solid #ece4cf;border-radius:14px;background:#fffdf8;padding:12px;text-decoration:none;
      display:flex;flex-direction:column;gap:6px;transition:transform .15s,box-shadow .15s;}
    .af-artist-card:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(70,54,26,.12);}
    .af-artist-card img{width:100%;border-radius:10px;}
    .af-artist-card strong{font-size:13.5px;color:#1a1a1a;line-height:1.4;}
    .af-artist-card em{font-style:normal;color:#a8872e;font-weight:700;font-size:13px;}
    .af-artist-back{margin-top:28px;}
    .af-artist-back a{color:#a8872e;font-weight:700;text-decoration:none;}
    @media(max-width:720px){.af-artist-hero{grid-template-columns:1fr;}}
    </style>
    <?php
    get_footer();
    exit;
}, 2);
