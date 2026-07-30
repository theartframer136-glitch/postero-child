<?php
/**
 * Blog & Knowledge Hub  (requirements §4)
 *
 * The site already carries long-form posts; what was missing was a home for
 * them. /blog/ becomes the hub: a featured latest article, a card grid with
 * reading times, category chips and search — in the site's own styling.
 */
if (!defined('ABSPATH')) exit;

function af_blog_reading_time($post) {
    $words = str_word_count(wp_strip_all_tags($post->post_content));
    return max(1, (int) round($words / 200));
}

add_action('template_redirect', function() {
    $req = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    if ($req !== 'blog') return;
    // The hub owns /blog/ outright. A WordPress Posts page configured here
    // would otherwise render the theme's default archive — bare titles, no
    // topics, no reading times — which is exactly what §4 asks us to replace.

    $paged = max(1, absint($_GET['bpage'] ?? 1));
    $cat   = isset($_GET['topic']) ? sanitize_key(wp_unslash($_GET['topic'])) : '';
    $args  = array('post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $paged);
    if ($cat) $args['category_name'] = $cat;
    $q     = new WP_Query($args);
    $hero  = null;
    if ($paged === 1 && !$cat && $q->have_posts()) { $hero = $q->posts[0]; }
    $cats  = get_categories(array('hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 10));

    status_header(200);
    get_header();
    ?>
    <div class="af-hub">
      <header class="af-hub-head">
        <h1>Blog &amp; Knowledge Hub</h1>
        <p>Buying guides, care advice and ideas for living with art — from the studio.</p>
        <form class="af-hub-search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
          <input type="search" name="s" placeholder="Search articles…" aria-label="Search articles">
          <button type="submit">Search</button>
        </form>
        <?php if ($cats): ?>
        <nav class="af-hub-cats" aria-label="Topics">
          <a class="af-hub-cat<?php echo $cat ? '' : ' on'; ?>" href="<?php echo esc_url(home_url('/blog/')); ?>">All topics</a>
          <?php foreach ($cats as $c): if ($c->slug === 'uncategorized') continue; ?>
            <a class="af-hub-cat<?php echo $cat === $c->slug ? ' on' : ''; ?>"
               href="<?php echo esc_url(add_query_arg('topic', $c->slug, home_url('/blog/'))); ?>">
              <?php echo esc_html($c->name); ?> <span><?php echo (int) $c->count; ?></span></a>
          <?php endforeach; ?>
        </nav>
        <?php endif; ?>
      </header>

      <?php if ($hero): $himg = get_the_post_thumbnail_url($hero, 'large'); ?>
      <a class="af-hub-hero" href="<?php echo esc_url(get_permalink($hero)); ?>">
        <div class="af-hub-hero-img" <?php if ($himg) echo 'style="background-image:url(' . esc_url($himg) . ')"'; ?>></div>
        <div class="af-hub-hero-body">
          <span class="af-hub-badge">Latest</span>
          <h2><?php echo esc_html(get_the_title($hero)); ?></h2>
          <p><?php echo esc_html(wp_trim_words(get_the_excerpt($hero) ?: $hero->post_content, 32, '…')); ?></p>
          <small><?php echo esc_html(get_the_date('', $hero)); ?> · <?php echo (int) af_blog_reading_time($hero); ?> min read</small>
        </div>
      </a>
      <?php endif; ?>

      <?php if ($q->have_posts()): ?>
      <div class="af-hub-grid">
        <?php foreach ($q->posts as $p):
            if ($hero && $p->ID === $hero->ID) continue;
            $img = get_the_post_thumbnail_url($p, 'medium_large');
            $pc  = get_the_category($p->ID); ?>
          <a class="af-hub-card" href="<?php echo esc_url(get_permalink($p)); ?>">
            <div class="af-hub-card-img" <?php if ($img) echo 'style="background-image:url(' . esc_url($img) . ')"'; ?>></div>
            <div class="af-hub-card-body">
              <?php if ($pc && $pc[0]->slug !== 'uncategorized'): ?><em><?php echo esc_html($pc[0]->name); ?></em><?php endif; ?>
              <h3><?php echo esc_html(get_the_title($p)); ?></h3>
              <small><?php echo esc_html(get_the_date('', $p)); ?> · <?php echo (int) af_blog_reading_time($p); ?> min read</small>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($q->max_num_pages > 1): ?>
      <nav class="af-hub-pages">
        <?php if ($paged > 1): ?><a href="<?php echo esc_url(add_query_arg(array_filter(array('topic' => $cat, 'bpage' => $paged - 1)), home_url('/blog/'))); ?>">← Newer</a><?php endif; ?>
        <span>Page <?php echo (int) $paged; ?> of <?php echo (int) $q->max_num_pages; ?></span>
        <?php if ($paged < $q->max_num_pages): ?><a href="<?php echo esc_url(add_query_arg(array_filter(array('topic' => $cat, 'bpage' => $paged + 1)), home_url('/blog/'))); ?>">Older →</a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php else: ?>
        <p style="text-align:center;color:#6b6250;padding:40px 0;">No articles here yet — check back soon.</p>
      <?php endif; wp_reset_postdata(); ?>
    </div>

    <style>
    .af-hub{max-width:1220px;margin:0 auto;padding:40px 16px 70px;}
    .af-hub-head{text-align:center;margin-bottom:30px;}
    .af-hub-head h1{font-size:38px;margin:0 0 6px;letter-spacing:-.5px;}
    .af-hub-head p{color:#6b6250;margin:0 0 18px;font-size:15px;}
    .af-hub-search{display:flex;gap:8px;max-width:440px;margin:0 auto 16px;}
    .af-hub-search input{flex:1;padding:12px 14px;border:1.5px solid #e2d9c4;border-radius:11px;font-size:14px;background:#fffdf8;}
    .af-hub-search input:focus{outline:none;border-color:#c9a84c;}
    .af-hub-search button{padding:12px 20px;border:0;border-radius:11px;background:#1a1a1a;color:#fff;font-weight:700;font-size:13px;cursor:pointer;}
    .af-hub-cats{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;}
    .af-hub-cat{padding:8px 14px;border:1.5px solid #e2d9c4;border-radius:999px;background:#fffdf8;color:#5a5140;
      font-size:12.5px;font-weight:700;text-decoration:none;}
    .af-hub-cat span{color:#a8872e;}
    .af-hub-cat.on,.af-hub-cat:hover{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}
    .af-hub-cat.on span,.af-hub-cat:hover span{color:#e8c766;}
    .af-hub-hero{display:grid;grid-template-columns:1.2fr 1fr;gap:0;border:1px solid #ece4cf;border-radius:18px;overflow:hidden;
      background:#fffdf8;text-decoration:none;margin-bottom:28px;min-height:280px;}
    .af-hub-hero-img{background:#e9e4d8 center/cover no-repeat;min-height:220px;}
    .af-hub-hero-body{padding:30px 32px;display:flex;flex-direction:column;justify-content:center;gap:10px;}
    .af-hub-badge{align-self:flex-start;background:#c9a84c;color:#fff;font-size:10.5px;font-weight:800;letter-spacing:.06em;
      text-transform:uppercase;padding:4px 10px;border-radius:999px;}
    .af-hub-hero-body h2{margin:0;font-size:24px;line-height:1.3;color:#1a1a1a;}
    .af-hub-hero-body p{margin:0;color:#5a5140;font-size:14px;line-height:1.6;}
    .af-hub-hero-body small{color:#8a8170;font-size:12px;}
    .af-hub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
    .af-hub-card{border:1px solid #ece4cf;border-radius:15px;overflow:hidden;background:#fffdf8;text-decoration:none;
      display:flex;flex-direction:column;transition:transform .15s,box-shadow .15s;}
    .af-hub-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(70,54,26,.12);}
    .af-hub-card-img{aspect-ratio:16/9;background:#e9e4d8 center/cover no-repeat;}
    .af-hub-card-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:6px;}
    .af-hub-card-body em{font-style:normal;color:#a8872e;font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;}
    .af-hub-card-body h3{margin:0;font-size:16px;line-height:1.4;color:#1a1a1a;}
    .af-hub-card-body small{color:#8a8170;font-size:12px;}
    .af-hub-pages{display:flex;justify-content:center;align-items:center;gap:18px;margin-top:30px;font-size:13.5px;}
    .af-hub-pages a{color:#a8872e;font-weight:700;text-decoration:none;}
    .af-hub-pages span{color:#8a8170;}
    @media(max-width:760px){.af-hub-hero{grid-template-columns:1fr;}.af-hub-head h1{font-size:29px;}}
    </style>
    <?php
    get_footer();
    exit;
}, 1);
