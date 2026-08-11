<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Prove audit items 13-20 on the live site: GA4 wiring, download hardening,
 * live chat, sales counts, artist profiles, custom 404, terms pages, review
 * enhancements. Read-only.
 * Run: wp eval-file tools/verify-polish.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
$fail = 0;
function af_pv($label, $ok, &$fail, $extra = '') {
    printf("  %-46s %s%s\n", $label, $ok ? 'OK' : 'FAILED  <<<', $extra ? '  ' . $extra : '');
    if (!$ok) $fail++;
}
function af_pv_get($url) {
    $a = array('timeout'=>60,'sslverify'=>false,'headers'=>array('User-Agent'=>'AF-Verify'));
    for ($i=0;$i<3;$i++) {
        $r = wp_remote_get($url,$a);
        if (!is_wp_error($r) && !in_array((int) wp_remote_retrieve_response_code($r), array(508,503,429), true)) return $r;
        sleep(3);
    }
    return $r;
}

echo "=== 13. GA4 / GTM ===\n";
af_pv('module loaded',      function_exists('af_ga4_id'), $fail);
af_pv('settings registered',(bool) has_action('admin_init'), $fail);
$id = af_ga4_id();
af_pv('measurement ID state', true, $fail, $id !== '' ? $id : 'not set yet — silent until saved in Settings → General');
if ($id !== '') {
    $r = af_pv_get(home_url('/?nocache=' . time()));
    if (!is_wp_error($r)) {
        $b = wp_remote_retrieve_body($r);
        af_pv('tag ships consent-gated', strpos($b, 'googletagmanager.com/gtag') !== false
            && strpos($b, 'data-consent="analytics"') !== false, $fail);
    }
}

echo "\n=== 14. DIGITAL DOWNLOADS ===\n";
global $wpdb;
$dl = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID
    WHERE p.post_type='product' AND m.meta_key='_downloadable' AND m.meta_value='yes'");
$lim = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_download_limit' AND meta_value NOT IN ('','-1')");
$exp = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_download_expiry' AND meta_value NOT IN ('','-1')");
af_pv('downloadable products found', (int) $dl > 0, $fail, "{$dl}");
af_pv('download limits set',  (int) $lim >= (int) $dl, $fail, "{$lim} products");
af_pv('download expiry set',  (int) $exp >= (int) $dl, $fail, "{$exp} products");
af_pv('watermarks enabled',   get_option('af_wm_enabled') === 'yes', $fail);
af_pv('watermark gate is category-based', function_exists('af_wm_applies'), $fail);
// the modal must fetch its preview from the endpoint, never reuse the card image
$hp = af_pv_get(home_url('/?nocache=' . time()));
if (!is_wp_error($hp)) {
    $hb = wp_remote_retrieve_body($hp);
    af_pv('dd modal uses preview endpoint', strpos($hb, 'af_dd_preview') !== false, $fail);
    af_pv('dd modal image shield ships',    strpos($hb, 'af-dd-shield') !== false, $fail);
    af_pv('image save-guard ships',         strpos($hb, 'dragstart') !== false, $fail);
}
// the endpoint itself: watermarked URL, and never the raw master
$probe = get_posts(array('post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>1,
    'meta_query'=>array(array('key'=>'_thumbnail_id','compare'=>'EXISTS'))));
if ($probe) {
    $r = af_pv_get(admin_url('admin-ajax.php') . '?action=af_dd_preview&pid=' . $probe[0]);
    if (!is_wp_error($r)) {
        $j = json_decode(wp_remote_retrieve_body($r), true);
        $u = isset($j['data']['url']) ? $j['data']['url'] : '';
        $master = wp_get_attachment_url(get_post_thumbnail_id($probe[0]));
        af_pv('preview endpoint answers', !empty($j['success']) && $u !== '', $fail,
              !empty($j['data']['wm']) ? 'watermarked' : 'small fallback');
        af_pv('endpoint never serves the master', $u !== '' && $u !== $master, $fail);
    }
}

$home = af_pv_get(home_url('/?nocache=' . time()));
$hb = is_wp_error($home) ? '' : wp_remote_retrieve_body($home);

echo "\n=== 15. ON-SITE CHATBOT ===\n";
af_pv('launcher ships',      strpos($hb, 'af-chat-bub') !== false, $fail);
af_pv('quick topics',        strpos($hb, 'af-chat-chips') !== false, $fail);
af_pv('message form',        strpos($hb, 'af-chat-form') !== false, $fail);
af_pv('email fallback',      strpos($hb, 'af-chat-alt') !== false, $fail);
af_pv('conversation thread', strpos($hb, 'af-chat-thread') !== false, $fail);
// the panel sets display:flex, which outranks the browser's [hidden] rule —
// without this rule the close button fires and the panel stays on screen
af_pv('close button can actually hide the panel',
      strpos($hb, '.af-chat-panel[hidden]') !== false, $fail);
// scope this to the chatbot's own script — the floating quick-access panel
// legitimately links to WhatsApp, and testing the whole page flagged that
$bot_js = '';
if (preg_match('#af_bot_reply.{0,4000}#s', $hb, $bm)) $bot_js = $bm[0];
af_pv('chatbot answers on-site (no wa.me hand-off)', $bot_js !== '' && strpos($bot_js, 'wa.me') === false, $fail);
af_pv('reply endpoint',      (bool) has_action('wp_ajax_nopriv_af_bot_reply'), $fail);
af_pv('question log table',  (bool) $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '" . af_bot_table() . "'"), $fail);
// the brain must actually route real questions
$cases = array('what sizes do you have'=>'sizes','do you have gold frames'=>'frames',
               'how long does delivery take'=>'shipping','can you frame my own photo'=>'custom',
               'i want to talk to a human'=>'human');
$bad = array();
foreach ($cases as $q => $want) { if (af_bot_match($q) !== $want) $bad[] = $q . ' -> ' . (af_bot_match($q) ?: 'none'); }
af_pv('intent routing (5 samples)', !$bad, $fail, $bad ? implode('; ', $bad) : 'all correct');
// and it must answer a live request end to end
$r = wp_remote_post(admin_url('admin-ajax.php'), array('timeout'=>45,'sslverify'=>false,
    'body'=>array('action'=>'af_bot_reply','nonce'=>wp_create_nonce('af_bot'),'msg'=>'what sizes do you offer?')));
if (!is_wp_error($r)) {
    $j = json_decode(wp_remote_retrieve_body($r), true);
    af_pv('live reply', !empty($j['success']) && !empty($j['data']['reply']), $fail,
          !empty($j['data']['reply']) ? mb_strimwidth(str_replace("\n",' ',$j['data']['reply']),0,60,'…') : '');
}
// The bot used to publish get_option('admin_email') as "the studio" — a
// personal admin address handed to every customer who asked for a human.
$admin_addr = get_option('admin_email');
$studio     = af_studio_contact();
af_pv('studio contact is not the WP admin account', $studio['email'] !== $admin_addr, $fail, $studio['email']);
af_pv('widget publishes the studio address', strpos($hb, $studio['email']) !== false, $fail);
af_pv('widget never leaks the admin address',
      $admin_addr && $studio['email'] !== $admin_addr ? strpos($hb, $admin_addr) === false : true, $fail);
// and the same must hold for the two replies that name a human
foreach (array('i want to talk to a human' => 'human hand-off',
               'zzqq nonsense question xyzzy' => 'fallback') as $probe => $what) {
    $pr = wp_remote_post(admin_url('admin-ajax.php'), array('timeout'=>45,'sslverify'=>false,
        'body'=>array('action'=>'af_bot_reply','nonce'=>wp_create_nonce('af_bot'),'msg'=>$probe)));
    if (is_wp_error($pr)) continue;
    $pj  = json_decode(wp_remote_retrieve_body($pr), true);
    $txt = isset($pj['data']['reply']) ? $pj['data']['reply'] : '';
    af_pv("{$what} gives the studio address", strpos($txt, $studio['email']) !== false, $fail);
    af_pv("{$what} hides the admin address",
          $admin_addr && $studio['email'] !== $admin_addr ? strpos($txt, $admin_addr) === false : true, $fail);
}

// ── shipping wording must be one claim, stated once ──
echo "\n=== SHIPPING WORDING ===\n";
$ship = af_shipping_copy();
af_pv('copy helper exists', function_exists('af_shipping_copy'), $fail, $ship['label']);
af_pv('no free-shipping claim in the copy',
      stripos($ship['label'] . $ship['short'] . $ship['blurb'], 'free') === false, $fail);
af_pv('badge label ships to the page', strpos($hb, $ship['label']) !== false, $fail);
af_pv('homepage makes no free-shipping promise',
      stripos($hb, 'Free Shipping across the USA') === false, $fail);
// the bot must not contradict the page
$sr = wp_remote_post(admin_url('admin-ajax.php'), array('timeout'=>45,'sslverify'=>false,
    'body'=>array('action'=>'af_bot_reply','nonce'=>wp_create_nonce('af_bot'),'msg'=>'how much is shipping?')));
if (!is_wp_error($sr)) {
    $sj = json_decode(wp_remote_retrieve_body($sr), true);
    $stxt = isset($sj['data']['reply']) ? $sj['data']['reply'] : '';
    af_pv('chatbot says the same as the page',
          $stxt !== '' && stripos($stxt, 'shipping is free') === false, $fail,
          mb_strimwidth(str_replace("\n", ' ', $stxt), 0, 58, '…'));
}

echo "\n=== 16. SALES COUNT ON CARDS ===\n";
af_pv('hook registered', function_exists('af_sales_count_min'), $fail);
$sold = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='total_sales' AND CAST(meta_value AS UNSIGNED) >= " . (int) af_sales_count_min());
af_pv('products over threshold', true, $fail, "{$sold} would show a badge (threshold " . af_sales_count_min() . ")");
if ((int) $sold > 0) {
    $terms = get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'number'=>1,'orderby'=>'count','order'=>'DESC'));
    if ($terms && !is_wp_error($terms)) {
        $r2 = af_pv_get(add_query_arg('nocache', time(), get_term_link($terms[0])));
        if (!is_wp_error($r2)) af_pv('badge CSS ships', strpos(wp_remote_retrieve_body($r2), 'af-sold') !== false, $fail);
    }
}

echo "\n=== 17. ARTIST PROFILES ===\n";
$parent = get_term_by('slug', 'direct-from-artists', 'product_cat');
if (!$parent) { echo "  (no Direct from Artists category)\n"; }
else {
    $kids = get_terms(array('taxonomy'=>'product_cat','parent'=>$parent->term_id,'hide_empty'=>false,'number'=>1));
    af_pv('artist categories exist', $kids && !is_wp_error($kids), $fail, $kids ? $kids[0]->slug : '');
    if ($kids && !is_wp_error($kids)) {
        $r3 = af_pv_get(home_url('/artists/' . $kids[0]->slug . '/?nocache=' . time()));
        if (!is_wp_error($r3)) {
            $b3 = wp_remote_retrieve_body($r3);
            $code = (int) wp_remote_retrieve_response_code($r3);
            af_pv('profile page responds 200', $code === 200, $fail, "HTTP {$code}");
            af_pv('profile renders (real page or virtual)', strpos($b3, 'af-artist') !== false || strpos($b3, 'taf-') !== false, $fail);
        }
    }
}

echo "\n=== 18. CUSTOM 404 ===\n";
$r4 = af_pv_get(home_url('/definitely-not-a-page-' . time() . '/'));
if (!is_wp_error($r4)) {
    $b4 = wp_remote_retrieve_body($r4);
    af_pv('returns HTTP 404', (int) wp_remote_retrieve_response_code($r4) === 404, $fail, 'HTTP ' . wp_remote_retrieve_response_code($r4));
    af_pv('designed page ships', strpos($b4, 'af-404') !== false, $fail);
    af_pv('search + category links', strpos($b4, 'af-404-search') !== false && strpos($b4, 'af-404-cats') !== false, $fail);
}

echo "\n=== 19. TERMS PAGES ===\n";
foreach (array('terms-of-use' => 'Terms of Use', 'terms-of-service' => 'Terms of Service') as $slug => $label) {
    $pg = get_page_by_path($slug);
    af_pv($label . ' page exists', $pg && $pg->post_status === 'publish', $fail, $pg ? '#' . $pg->ID : '');
}

echo "\n=== 20. REVIEW ENHANCEMENTS ===\n";
af_pv('photo upload field hooked', (bool) has_filter('woocommerce_product_review_comment_form_args'), $fail);
af_pv('photo save handler',        (bool) has_action('comment_post'), $fail);
$prod = $wpdb->get_var("SELECT comment_post_ID FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID
    WHERE c.comment_approved='1' AND p.post_type='product' ORDER BY c.comment_ID DESC LIMIT 1");
if ($prod) {
    $r5 = af_pv_get(add_query_arg('nocache', time(), get_permalink((int) $prod)));
    if (!is_wp_error($r5)) {
        $b5 = wp_remote_retrieve_body($r5);
        af_pv('histogram ships on reviewed product', strpos($b5, 'af-revsum') !== false, $fail);
        af_pv('verified badge styling ships',        strpos($b5, 'woocommerce-review__verified') !== false, $fail);
    }
} else {
    echo "  (no approved product reviews yet — histogram appears with the first one)\n";
}


echo "\n=== BROCHURE (view, not download) ===\n";
// The card button says View Brochure and carries no download attribute; the
// server must cooperate by serving the PDF inline, or the browser downloads
// it anyway and the label lies.
if (function_exists('taf_brochure_url')) {
    $bu = taf_brochure_url();
    $br = wp_remote_get($bu, array('timeout'=>45,'sslverify'=>false,
        'headers'=>array('User-Agent'=>'AF-Verify','Range'=>'bytes=0-2047')));
    if (is_wp_error($br)) { af_pv('brochure fetch', false, $fail, $br->get_error_message()); }
    else {
        $bc = (int) wp_remote_retrieve_response_code($br);
        $ct = (string) wp_remote_retrieve_header($br, 'content-type');
        $cd = (string) wp_remote_retrieve_header($br, 'content-disposition');
        af_pv('brochure answers', in_array($bc, array(200, 206), true), $fail, "HTTP {$bc}");
        af_pv('served as application/pdf', stripos($ct, 'pdf') !== false, $fail, $ct);
        af_pv('not forced to download', stripos($cd, 'attachment') === false, $fail, $cd !== '' ? $cd : '(no disposition header)');
    }
    // and no surface still forces the download client-side
    $hb2 = isset($hb) ? $hb : '';
    af_pv('no download attribute ships anywhere', strpos($hb2, 'download="TheArtFramer') === false, $fail);
    // the three-button spec must reach the browser: View Brochure label, the
    // gold-outline card style, the icon-text gap and both hover colours
    af_pv('cards say View Brochure', strpos($hb2, 'View Brochure') !== false, $fail);
    // Match the rule's PROPERTIES, not one exact byte sequence: the page is
    // minified in transit, so an exact-string probe fails against perfectly
    // correct CSS — the same brittleness that made the pricing probe miss a
    // unicode-escaped multiplication sign.
    $card_rule = '';
    if (preg_match('/\.taf-broch--card\s*\{([^}]*)\}/', $hb2, $cm)) $card_rule = $cm[1];
    // CSS minifiers rewrite `background:transparent` to the shorter but
    // equivalent `background:0 0` (and `none` / `rgba(0,0,0,0)` are the same
    // paint), so accept every spelling of "no fill" rather than the one I
    // happened to type. The page is minified in transit — the previous probe
    // reported a failure against CSS that renders exactly as intended.
    $need = array(
        // Every way a minifier can write "no fill": the keyword, none, the
        // 0 0 shorthand, rgba with zero alpha, and hex WITH zero alpha —
        // #fff0 is what this site's minifier actually emits, which the log
        // only revealed once the probe started printing the served rule.
        'transparent background' => '/background(-color)?\s*:\s*(transparent|none|0\s+0|rgba\([^)]*,\s*0(\.0+)?\s*\)|#[0-9a-f]{3}0\b|#[0-9a-f]{6}00\b)/i',
        'gold 1.5px outline'     => '/border\s*:\s*1\.5px\s+solid\s+#c9a84c/i',
        'square corners'         => '/border-radius\s*:\s*0/i',
    );
    $missing = array();
    foreach ($need as $what => $re) { if (!preg_match($re, $card_rule)) $missing[] = $what; }
    af_pv('brochure card style is outline-on-transparent',
          $card_rule !== '' && !$missing, $fail,
          $card_rule === '' ? 'rule not found in page'
            : ($missing ? 'missing ' . implode(', ', $missing) . ' | served rule: ' . mb_strimwidth($card_rule, 0, 160, '…')
                        : 'all three'));
    af_pv('cart hover colour ships (#8b6a2b)', strpos($hb2, '#8b6a2b') !== false, $fail);
    af_pv('icon-text gap ships (8px)', strpos($hb2, "'gap', '8px'") !== false || strpos($hb2, 'gap:8px') !== false, $fail);
    // the carousels ship .add-to-cart-btn (hyphens) — the selector the first
    // gap fix missed entirely, which is why nothing changed on Trending Today
    af_pv('hyphen add-to-cart-btn is covered',
          strpos($hb2, '.add-to-cart-btn') !== false, $fail);
    af_pv('cart label cannot wrap under the icon',
          (bool) preg_match('/add-to-cart-btn[^{]*\{[^}]*white-space:nowrap/s', $hb2), $fail);
}

echo "\n=== RESULT: " . ($fail ? "{$fail} PROBLEM(S)" : "ALL CHECKS PASSED") . " ===\n";
