<?php
/**
 * About page  —  /about/ rendered in the site's own design language
 * (the cream/gold system the AR pages, blog hub and artist profiles use),
 * with live store facts instead of demo copy.
 */
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function() {
    $req = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    if ($req !== 'about' && $req !== 'about-us') return;

    $contact = af_studio_contact();
    $email = $contact['email'];
    $phone = $contact['phone'];
    $wa    = $contact['wa'];

    $products = (int) wp_count_posts('product')->publish;
    $parent   = get_term_by('slug', 'direct-from-artists', 'product_cat');
    $artists  = $parent ? count(get_terms(array('taxonomy' => 'product_cat', 'parent' => $parent->term_id, 'hide_empty' => false, 'fields' => 'ids'))) : 0;
    $sizes    = function_exists('af_pricing_config') ? count(af_pricing_config()['sizes']) : 14;

    $cats = array();
    foreach (array(
        'digital-canvas-prints' => array('🖼', 'Digital Canvas Prints', 'Spiritual, cultural and modern art on gallery-grade canvas.'),
        'framed-canvases'       => array('🪟', 'Framed Canvases', 'Aluminium, fibre and floating frames in four finishes.'),
        'direct-from-artists'   => array('🎨', 'Direct from Artists', 'Original works, printed and framed by our studio.'),
        'personalised-prints'   => array('�personal', 'Personalised Prints', 'Your photos, framed like art — see Frame The Moment.'),
        'banners-signage'       => array('🏢', 'Banners & Signage', 'Corporate signage and large-format printing.'),
        'digital-downloads'     => array('⤓', 'Digital Downloads', 'Instant files for printing at home.'),
    ) as $slug => $def) {
        $t = get_term_by('slug', $slug, 'product_cat');
        if (!$t) continue;
        $link = get_term_link($t);
        if (is_wp_error($link)) continue;
        $cats[] = array($def[0] === '�personal' ? '📸' : $def[0], $def[1], $def[2], $link);
    }

    status_header(200);
    get_header();
    ?>
    <div class="af-about">
      <header class="af-about-hero">
        <p class="af-about-kicker">About The Art Framer</p>
        <h1>Walls are empty promises.<br>We keep them.</h1>
        <p class="af-about-lede">The Art Framer crafts premium canvas wall art, framed prints and custom décor —
           devotional, cultural and modern pieces made with archival, fade-resistant inks,
           framed by hand, and shipped safely to your door across the USA and Canada.</p>
      </header>

      <div class="af-about-stats">
        <div><strong><?php echo (int) $products; ?>+</strong><span>artworks in the collection</span></div>
        <div><strong><?php echo max(1, (int) $artists); ?></strong><span>artists represented</span></div>
        <div><strong><?php echo (int) $sizes; ?></strong><span>sizes, up to 6 ft</span></div>
        <div><strong>4</strong><span>frame styles &amp; finishes</span></div>
      </div>

      <section class="af-about-story">
        <div>
          <h2>Born from a love of art and craft</h2>
          <p>We started with a simple belief: meaningful art should be accessible. From Radha Krishna and
             Seven Horses canvases rooted in tradition, to abstracts, landscapes and personalised prints,
             every piece is produced to order in our Delaware studio — never warehoused, never faded stock.</p>
          <p>Each print is made with pigment inks rated for decades, stretched over kiln-dried bars or
             finished in your choice of aluminium, fibre or floating frames, corner-protected, and
             checked by a person before it ships.</p>
        </div>
        <div>
          <h2>Try before you hang</h2>
          <p>We built tools most galleries don't have: preview any artwork on a photo of
             <em>your own wall</em> — true to scale — or point your camera at the wall and see it live.
             Your own photographs can be framed the same way.</p>
          <p class="af-about-ctas">
            <a class="af-about-btn solid" href="<?php echo esc_url(home_url('/try-on-wall/')); ?>">Try art on your wall</a>
            <a class="af-about-btn" href="<?php echo esc_url(home_url('/frame-the-moment/')); ?>">Frame your moment</a>
          </p>
        </div>
      </section>

      <?php if ($cats): ?>
      <h2 class="af-about-sub">What we make</h2>
      <div class="af-about-cats">
        <?php foreach ($cats as $c): ?>
          <a class="af-about-cat" href="<?php echo esc_url($c[3]); ?>">
            <span class="af-about-ico"><?php echo esc_html($c[0]); ?></span>
            <strong><?php echo esc_html($c[1]); ?></strong>
            <small><?php echo esc_html($c[2]); ?></small>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <section class="af-about-promise">
        <h2 class="af-about-sub">Our promise</h2>
        <div class="af-about-vals">
          <div><span>🚚</span><strong>Free US shipping</strong><small>Rolled in tubes or flat in corner-protected crates — sized to survive the journey.</small></div>
          <div><span>🎯</span><strong>Archival quality</strong><small>Fade-resistant pigment inks and gallery-grade canvas on every piece.</small></div>
          <div><span>🔒</span><strong>Secure checkout</strong><small>Card, PayPal and wallet payments with fraud screening on every order.</small></div>
          <div><span>💬</span><strong>Real support</strong><small>Phone, email and WhatsApp — a person answers, usually within the hour.</small></div>
        </div>
      </section>

      <section class="af-about-contact">
        <h2>Talk to the studio</h2>
        <p>Custom sizes, bulk and corporate orders, trade enquiries — or just advice on what fits your wall.</p>
        <p class="af-about-ctas">
          <a class="af-about-btn solid" href="https://wa.me/<?php echo esc_attr($wa); ?>" target="_blank" rel="noopener">WhatsApp us</a>
          <a class="af-about-btn" href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
          <a class="af-about-btn" href="tel:+16104707280"><?php echo esc_html($phone); ?></a>
        </p>
        <p class="af-about-fine">The Art Framer · Delaware, USA · <a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact page</a></p>
      </section>
    </div>

    <style>
    .af-about{max-width:1120px;margin:0 auto;padding:48px 16px 80px;}
    .af-about-kicker{color:#a8872e;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;margin:0 0 10px;text-align:center;}
    .af-about-hero h1{font-size:44px;line-height:1.15;letter-spacing:-1px;text-align:center;margin:0 0 16px;color:#1a1a1a;}
    .af-about-lede{max-width:720px;margin:0 auto;text-align:center;color:#5a5140;font-size:16.5px;line-height:1.7;}
    .af-about-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:40px 0 46px;}
    .af-about-stats div{background:#fffdf8;border:1px solid #ece4cf;border-radius:16px;padding:22px 16px;text-align:center;}
    .af-about-stats strong{display:block;font-size:30px;color:#1a1a1a;letter-spacing:-.5px;}
    .af-about-stats span{color:#8a8170;font-size:12.5px;}
    .af-about-story{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:46px;}
    .af-about-story>div{background:#fffdf8;border:1px solid #ece4cf;border-radius:18px;padding:28px 30px;}
    .af-about-story h2{margin:0 0 12px;font-size:22px;color:#1a1a1a;}
    .af-about-story p{color:#5a5140;font-size:14.5px;line-height:1.75;margin:0 0 12px;}
    .af-about-sub{font-size:24px;margin:0 0 18px;text-align:center;color:#1a1a1a;}
    .af-about-cats{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:46px;}
    .af-about-cat{background:#fffdf8;border:1px solid #ece4cf;border-radius:15px;padding:20px 18px;text-decoration:none;
      display:flex;flex-direction:column;gap:6px;transition:transform .15s,box-shadow .15s;}
    .af-about-cat:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(70,54,26,.12);}
    .af-about-ico{font-size:22px;}
    .af-about-cat strong{color:#1a1a1a;font-size:15px;}
    .af-about-cat small{color:#8a8170;font-size:12.5px;line-height:1.5;}
    .af-about-vals{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:46px;}
    .af-about-vals div{background:#fffdf8;border:1px solid #ece4cf;border-radius:15px;padding:20px 18px;}
    .af-about-vals span{font-size:22px;display:block;margin-bottom:6px;}
    .af-about-vals strong{display:block;color:#1a1a1a;font-size:14.5px;margin-bottom:4px;}
    .af-about-vals small{color:#8a8170;font-size:12.5px;line-height:1.55;}
    .af-about-contact{background:#1a1a1a;border-radius:20px;padding:38px 30px;text-align:center;}
    .af-about-contact h2{color:#fff;margin:0 0 8px;font-size:24px;}
    .af-about-contact p{color:#cbc2ac;font-size:14px;margin:0 0 16px;}
    .af-about-ctas{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
    .af-about-btn{display:inline-block;padding:12px 20px;border-radius:11px;border:1.5px solid #d9cfb4;background:#fffdf8;
      color:#5a5140;font-weight:700;font-size:13.5px;text-decoration:none;}
    .af-about-btn:hover{border-color:#c9a84c;color:#5a5140;}
    .af-about-btn.solid{background:#c9a84c;border-color:#c9a84c;color:#fff;}
    .af-about-btn.solid:hover{background:#a8872e;color:#fff;}
    .af-about-contact .af-about-btn{background:transparent;color:#e8dfc8;border-color:#5a5140;}
    .af-about-contact .af-about-btn.solid{background:#25a366;border-color:#25a366;color:#fff;}
    .af-about-fine{margin-top:16px !important;font-size:12px !important;color:#8a8170 !important;}
    .af-about-fine a{color:#c9a84c;}
    @media(max-width:760px){.af-about-hero h1{font-size:31px;}.af-about-story{grid-template-columns:1fr;}}
    </style>
    <?php
    get_footer();
    exit;
}, 3);
