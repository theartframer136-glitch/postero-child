<?php
/**
 * Custom 404  (requirements §4 — error pages)
 * A dead end that sells: search, the main collections, and a way home.
 */
get_header();
$cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 6, 'parent' => 0));
?>
<div class="af-404">
  <p class="af-404-code">404</p>
  <h1>This wall is empty.</h1>
  <p class="af-404-sub">The page you were looking for has moved, sold out, or never hung here.
     The art, however, is very much still around.</p>
  <form class="af-404-search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="search" name="s" placeholder="Search artworks…" aria-label="Search">
    <input type="hidden" name="post_type" value="product">
    <button type="submit">Search</button>
  </form>
  <?php if ($cats && !is_wp_error($cats)): ?>
  <div class="af-404-cats">
    <?php foreach ($cats as $c): if (in_array($c->slug, array('uncategorized', 'frame-colors', 'sizes'), true)) continue; ?>
      <a href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="af-404-actions">
    <a class="af-404-btn solid" href="<?php echo esc_url(home_url('/')); ?>">Back to Home</a>
    <a class="af-404-btn" href="<?php echo esc_url(home_url('/shop/')); ?>">Browse the Shop</a>
    <a class="af-404-btn" href="<?php echo esc_url(home_url('/try-on-wall/')); ?>">Try Art on Your Wall</a>
  </div>
</div>
<style>
.af-404{max-width:760px;margin:0 auto;text-align:center;padding:70px 16px 90px;}
.af-404-code{font-size:15px;font-weight:800;letter-spacing:.35em;color:#a8872e;margin:0 0 8px;}
.af-404 h1{font-size:40px;margin:0 0 12px;letter-spacing:-.5px;}
.af-404-sub{color:#6b6250;font-size:15.5px;line-height:1.7;margin:0 auto 26px;max-width:520px;}
.af-404-search{display:flex;gap:8px;max-width:440px;margin:0 auto 22px;}
.af-404-search input[type=search]{flex:1;padding:13px 15px;border:1.5px solid #e2d9c4;border-radius:11px;font-size:14px;background:#fffdf8;}
.af-404-search input[type=search]:focus{outline:none;border-color:#c9a84c;}
.af-404-search button{padding:13px 22px;border:0;border-radius:11px;background:#1a1a1a;color:#fff;font-weight:700;font-size:13px;cursor:pointer;}
.af-404-cats{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:0 0 28px;}
.af-404-cats a{padding:8px 15px;border:1.5px solid #e2d9c4;border-radius:999px;background:#fffdf8;color:#5a5140;
  font-size:12.5px;font-weight:700;text-decoration:none;}
.af-404-cats a:hover{border-color:#1a1a1a;background:#1a1a1a;color:#fff;}
.af-404-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
.af-404-btn{padding:13px 22px;border:1.5px solid #e2d9c4;border-radius:11px;background:#fffdf8;color:#5a5140;
  font-weight:700;font-size:13.5px;text-decoration:none;}
.af-404-btn:hover{border-color:#c9a84c;color:#5a5140;}
.af-404-btn.solid{background:#1a1a1a;border-color:#1a1a1a;color:#fff;}
.af-404-btn.solid:hover{background:#000;color:#fff;}
</style>
<?php get_footer(); ?>
