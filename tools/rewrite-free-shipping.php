<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Replace the free-shipping claims that live in the DATABASE, which is where
 * most of them are: Elementor designs (the homepage badge titles are Elementor
 * widgets, not theme code, which is why editing the theme never changed them),
 * page content, and product descriptions.
 *
 * THE SHOP DOES NOT SHIP FREE (owner, 2026-09-10 and again 2026-10-14: never
 * say "free shipping" anywhere). The wording comes from af_shipping_copy(), so
 * this tool and the theme always say the same thing.
 *
 * How it matches. The first version compared exact strings and missed most of
 * what was stored: Elementor keeps its text as JSON, where "&" may be "&amp;"
 * or "&", a line break is the two characters "\r\n" and an em dash is
 * "—"; pages wrap words in <b>; and the same sentence appears in three
 * capitalisations. The rules below are regular expressions built from the
 * sentences as the site writes them, tolerant of those encodings, applied
 * longest first, and closed by one generic rule for any "free shipping" /
 * "free delivery" left over, which keeps the original capitalisation.
 *
 * How it writes. Straight to the table with $wpdb->update(), NOT
 * wp_update_post(): WP-CLI has no logged-in user, so wp_update_post() runs the
 * content through kses and strips <style>, <script>, iframes and builder
 * attributes along with the wording (that is what the 5 Oct restore undid).
 * Every post touched keeps a one-time backup of its original content and
 * Elementor data in postmeta (_af_shipping_copy_backup, _af_elementor_backup),
 * so tools/restore-shipping-copy.php reverses the whole operation by hand.
 * Elementor's per-post caches are deleted so the old words cannot be served
 * from them, and the page cache is purged.
 *
 * Idempotent: a second run reports 0 changed.
 *
 * Run: wp eval-file tools/rewrite-free-shipping.php --allow-root
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Run via wp eval-file\n"); exit(1); }
global $wpdb;

$copy  = function_exists('af_shipping_copy') ? af_shipping_copy()
       : array('label'=>'Shipping Throughout the USA','short'=>'Shipping cost shown at checkout',
               'blurb'=>'Delivered anywhere in the USA — shipping cost is shown at checkout before you pay.','cost'=>'','free'=>false);
if (!empty($copy['free'])) { echo "af_shipping_cost says shipping is free: nothing to rewrite.\n"; return; }
$label = $copy['label'];                        // Shipping Throughout the USA
$short = $copy['short'];                        // Shipping cost shown at checkout
$blurb = $copy['blurb'];                        // Delivered anywhere in the USA — ...
$every = 'We ship to every state in the USA.';
$card  = 'We ship to every state in the USA — the shipping cost for your size and destination is shown at checkout before you pay.';

// ── pattern fragments ──
// whitespace, including the two-character JSON escapes "\r" "\n" and &nbsp;
if (!defined('AF_RW_WS')) define('AF_RW_WS', '(?:\s|\\\\[rnt]|&nbsp;|\x{00a0})+');
if (!defined('AF_RW_AMP')) define('AF_RW_AMP', '(?:&amp;|&|\\\\u0026amp;|\\\\u0026)');
if (!defined('AF_RW_DASH')) define('AF_RW_DASH', '(?:—|–|-|&mdash;|&ndash;|\\\\u2014|\\\\u2013)');
if (!defined('AF_RW_SL')) define('AF_RW_SL', '(?:\\\\)?/');                 // "/" or the JSON "\/"
if (!defined('AF_RW_B')) define('AF_RW_B', '(?:<(?:b|strong)>)?');
if (!defined('AF_RW_BC')) define('AF_RW_BC', '(?:<(?:\\\\)?/(?:b|strong)>)?');

/** A sentence as the site writes it → a regex body tolerant of the encodings above. */
if (!function_exists('af_rw_pat')) {
function af_rw_pat($s) {
    $p = preg_quote($s, '~');
    $p = str_replace('\\/', AF_RW_SL, $p);
    $p = str_replace('&', AF_RW_AMP, $p);
    $p = str_replace('—', AF_RW_DASH, $p);
    $p = preg_replace('/ +/', AF_RW_WS, $p);
    return $p;
}
}
if (!function_exists('af_rw_rx')) {
function af_rw_rx($body) { return '~' . $body . '~iu'; }
}

// left = what the site says today, right = what it should say. Longest first
// so a broad phrase never eats a specific one. A replacement may be a closure
// (receives the match) when the result depends on the original capitalisation.
$STATES = 'Delaware, Pennsylvania, Maryland, New Jersey';
$rules = array(
    // product FAQ answer (Elementor + template): original, and the form the
    // first pass left behind ("We offer shipping throughout the USA across…")
    array(af_rw_rx('(?:' . af_rw_pat('We offer free delivery across major cities and serviceable locations in the USA.') . '|'
                    . af_rw_pat('We offer shipping throughout the USA across major cities and serviceable locations in the USA.') . ')'
                    . '(?:' . AF_RW_WS . ')?' . af_rw_pat('Free delivery is available in cities such as ' . $STATES . ' & nearby areas.')),
          $every . ' ' . $blurb),
    array(af_rw_rx(af_rw_pat('We offer free delivery across major cities and serviceable locations in the USA, including ' . $STATES . ' & nearby areas.')),
          $every . ' ' . $blurb),
    array(af_rw_rx(af_rw_pat('Free delivery is available in cities such as ' . $STATES . ' & nearby areas.')), $every . ' ' . $blurb),
    array(af_rw_rx(af_rw_pat('Free delivery is available in ' . $STATES . ' & nearby areas.')),               $every . ' ' . $blurb),
    array(af_rw_rx(af_rw_pat('We offer shipping throughout the USA across major cities and serviceable locations in the USA.')), $every),
    array(af_rw_rx(af_rw_pat('Which areas are eligible for free delivery?')), 'Which areas do you ship to?'),
    // contact page card and lead
    array(af_rw_rx(af_rw_pat('Free delivery: ' . $STATES . ' & nearby areas. Nationwide shipping available.')),
          'Shipping to every state in the USA — cost shown at checkout.'),
    array(af_rw_rx(af_rw_pat('Free delivery: ' . $STATES . ' & nearby areas.')), ucfirst($short) . '.'),
    array(af_rw_rx(af_rw_pat('Our studio operates from Delaware, USA — with free delivery across ' . $STATES . ' and nearby areas.')),
          'Our studio operates from Delaware, USA, and ships to every state. ' . $blurb),
    // legal imprint, about page
    array(af_rw_rx(af_rw_pat('We serve customers across the United States with free delivery in ' . $STATES . ' and nearby areas.')),
          'We serve customers across the United States. ' . $blurb),
    array(af_rw_rx(af_rw_pat('we serve customers nationwide with free shipping, secure checkout')),
          'we serve customers nationwide with secure checkout'),
    // shipping page card: "<h3>Free Delivery</h3><p>Free across … <b>states</b> and nearby areas.</p>"
    array(af_rw_rx(af_rw_pat('<h3>Free Delivery</h3><p>Free across major serviceable US locations — including ') . AF_RW_B
                    . af_rw_pat($STATES) . AF_RW_BC . af_rw_pat(' and nearby areas.</p>')),
          '<h3>' . $label . '</h3><p>' . $card . '</p>'),
    array(af_rw_rx(af_rw_pat('Free across major serviceable US locations — including ') . AF_RW_B . af_rw_pat($STATES) . AF_RW_BC . af_rw_pat(' and nearby areas.')),
          $card),
    array(af_rw_rx(af_rw_pat('Free across major serviceable US locations')), 'We ship to every state in the USA'),
    array(af_rw_rx(af_rw_pat('Will It Be Free For You?')), 'Check Delivery to Your ZIP'),
    // about page bullet: "<strong>Free USA Shipping</strong> — on premium canvas art to serviceable locations."
    array(af_rw_rx(af_rw_pat(' — on premium canvas art to serviceable locations.')), ' — ' . lcfirst($short) . '.'),
    array(af_rw_rx(af_rw_pat('Free shipping options across our delivery areas')), 'Shipping throughout the USA — cost shown at checkout'),
    // product Elementor blocks and descriptions
    array(af_rw_rx(af_rw_pat('Get free shipping over $65') . '\.?'), $short . '.'),
    // the homepage badge strip and its popup (Elementor HTML widget)
    array(af_rw_rx(af_rw_pat('Free Shipping Available!')), $label),
    array(af_rw_rx(af_rw_pat('Free Delivery In:')), 'We Deliver Across the USA, Including:'),
    array(af_rw_rx(af_rw_pat('Fast, safe, and reliable delivery with on-time assurance') . '\.?'), $blurb),
    // anything left: "free [US|USA] shipping|delivery", keeping the capitalisation
    array(af_rw_rx('\bfree' . AF_RW_WS . '(?:usa?' . AF_RW_WS . ')?(?:shipping|delivery)\b'), function ($m) use ($label) {
        $w = $m[0];
        if (preg_match('/^Free\s+(?:USA?\s+)?[SD]/u', $w)) return $label;                  // Free Shipping / Free USA Shipping
        if ($w[0] === 'F') return 'Shipping throughout the USA';                           // Free delivery …
        return 'shipping throughout the USA';                                              // … free shipping …
    }),
);

/**
 * Apply the rules. In JSON mode (Elementor data) every replacement is passed
 * through wp_json_encode so quotes, slashes and non-ASCII characters are
 * stored the way Elementor stores them. Returns null when nothing changed.
 */
if (!function_exists('af_rw_apply')) {
function af_rw_apply($text, $rules, $json, &$hits) {
    if (!is_string($text) || $text === '') return null;
    $before = $text;
    foreach ($rules as $rule) {
        list($rx, $to) = $rule;
        $enc = function ($s) use ($json) { return $json ? trim(wp_json_encode($s), '"') : $s; };
        if (is_callable($to)) {
            $text = preg_replace_callback($rx, function ($m) use ($to, $enc, &$hits) { $hits++; return $enc($to($m)); }, $text);
        } else {
            $text = preg_replace($rx, strtr($enc($to), array('\\' => '\\\\', '$' => '\$')), $text, -1, $n);
            $hits += (int) $n;
        }
        if ($text === null) { $text = $before; echo "  (regex error on a rule: " . preg_last_error_msg() . ")\n"; }
    }
    return $text === $before ? null : $text;
}
}

/** Does the text still say it anywhere? (what the verifier counts) */
if (!function_exists('af_rw_still')) {
function af_rw_still($text) {
    return (bool) preg_match('~\bfree' . AF_RW_WS . '(?:usa?' . AF_RW_WS . ')?(?:shipping|delivery)\b|will it be free~iu', (string) $text);
}
}

echo "=== REWRITE FREE-SHIPPING CLAIMS (DB CONTENT) ===\n";
printf("  new label : %s\n  new line  : %s\n\n", $label, $short);

$changed_posts = 0; $changed_meta = 0; $total_hits = 0; $unmatched = array(); $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
$like = "(LOWER(%s) LIKE '%%free shipping%%' OR LOWER(%s) LIKE '%%free delivery%%' OR LOWER(%s) LIKE '%%free usa shipping%%'
          OR LOWER(%s) LIKE '%%free us shipping%%' OR LOWER(%s) LIKE '%%will it be free%%' OR LOWER(%s) LIKE '%%serviceable us locations%%')";

// ── 1. post content and excerpt (pages, products, posts) ──
$ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
      WHERE post_status IN ('publish','draft','private','pending','future')
        AND post_type NOT IN ('revision','attachment','elementor_library')
        AND (" . sprintf($like, 'post_content', 'post_content', 'post_content', 'post_content', 'post_content', 'post_content') . "
          OR " . sprintf($like, 'post_excerpt', 'post_excerpt', 'post_excerpt', 'post_excerpt', 'post_excerpt', 'post_excerpt') . ")
      LIMIT 500");
foreach ($ids as $pid) {
    $post = get_post($pid);
    if (!$post) continue;
    $hits = 0;
    $new_content = af_rw_apply($post->post_content, $rules, false, $hits);
    $new_excerpt = af_rw_apply($post->post_excerpt, $rules, false, $hits);
    if ($new_content === null && $new_excerpt === null) { $unmatched[] = (int) $pid; continue; }
    if (!get_post_meta($pid, '_af_shipping_copy_backup', true)) {
        update_post_meta($pid, '_af_shipping_copy_backup', wp_slash(wp_json_encode(array(
            'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'when' => gmdate('c'),
        ))));
    }
    $set = array('post_modified' => $now, 'post_modified_gmt' => $now_gmt);
    if ($new_content !== null) $set['post_content'] = $new_content;
    if ($new_excerpt !== null) $set['post_excerpt'] = $new_excerpt;
    // straight to the table: no kses, no hooks, bytes stored as written
    $ok = $wpdb->update($wpdb->posts, $set, array('ID' => (int) $pid));
    if ($ok === false) { echo "  FAILED #{$pid}: " . $wpdb->last_error . "\n"; continue; }
    clean_post_cache((int) $pid);
    $changed_posts++; $total_hits += $hits;
    printf("  content  #%-6d %-9s %s (%d)%s\n", $pid, $post->post_type,
           mb_strimwidth(get_the_title($pid), 0, 46, '…'), $hits,
           af_rw_still($new_content === null ? $post->post_content : $new_content) ? '  << still says it, see below' : '');
}

// ── 2. Elementor designs in postmeta ──
// _elementor_data is JSON; every phrase here is display text, never markup or
// a key, so replacing inside the string is safe. Batched until exhausted; rows
// that hold a phrase this map cannot replace are remembered and excluded, so
// the loop always makes progress.
$touched = array();
$seen = array();
$guard = 0;
while (true) {
    $skip = $seen ? (' AND meta_id NOT IN (' . implode(',', array_map('intval', $seen)) . ')') : '';
    $batch = $wpdb->get_results(
        "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
          WHERE meta_key = '_elementor_data'
            AND " . sprintf($like, 'meta_value', 'meta_value', 'meta_value', 'meta_value', 'meta_value', 'meta_value') . "
            {$skip}
          LIMIT 100");
    if (!$batch) break;
    foreach ($batch as $row) {
        $seen[] = (int) $row->meta_id;
        $hits = 0;
        $new = af_rw_apply($row->meta_value, $rules, true, $hits);
        if ($new === null) { $unmatched[] = (int) $row->post_id; continue; }
        if (json_decode($new) === null && json_decode($row->meta_value) !== null) {
            echo "  SKIPPED elementor #{$row->post_id}: the rewritten design would not parse\n"; continue;
        }
        if (!get_post_meta($row->post_id, '_af_elementor_backup', true)) {
            update_post_meta($row->post_id, '_af_elementor_backup', wp_slash($row->meta_value));
        }
        $ok = $wpdb->update($wpdb->postmeta, array('meta_value' => $new), array('meta_id' => (int) $row->meta_id));
        if ($ok === false) { echo "  FAILED meta #{$row->meta_id}: " . $wpdb->last_error . "\n"; continue; }
        $touched[] = (int) $row->post_id;
        $changed_meta++; $total_hits += $hits;
        printf("  elementor #%-6d %s (%d)\n", $row->post_id, mb_strimwidth(get_the_title($row->post_id), 0, 46, '…'), $hits);
    }
    if (++$guard > 60) { echo "  (batch guard reached — re-run to continue)\n"; break; }
}

// ── 3. caches that would keep serving the old words ──
// Elementor renders widgets into _elementor_element_cache and CSS into
// _elementor_css per post; clear them for every design touched, and for any
// cached render that still carries the claim.
$cache_rows = $wpdb->get_col(
    "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
      WHERE meta_key IN ('_elementor_element_cache','_elementor_css')
        AND (LOWER(meta_value) LIKE '%free shipping%' OR LOWER(meta_value) LIKE '%free delivery%' OR LOWER(meta_value) LIKE '%free usa shipping%')");
foreach (array_unique(array_merge($touched, array_map('intval', $cache_rows))) as $cpid) {
    delete_post_meta($cpid, '_elementor_element_cache');
    delete_post_meta($cpid, '_elementor_css');
    clean_post_cache($cpid);
}
if ($touched || $cache_rows) echo "  elementor render caches cleared on " . count(array_unique(array_merge($touched, array_map('intval', $cache_rows)))) . " post(s)\n";

if (!empty($unmatched)) {
    $u = array_unique($unmatched);
    echo "  places holding a phrase these rules do not cover: " . count($u) . "\n";
    foreach (array_slice($u, 0, 8) as $up) echo "    #{$up} " . get_the_title($up) . "\n";
}

echo "\n  posts changed:      {$changed_posts}\n";
echo "  elementor changed:  {$changed_meta}\n";
echo "  phrases replaced:   {$total_hits}\n";

if ($changed_posts || $changed_meta || $cache_rows) {
    if (class_exists('\\Elementor\\Plugin')) {
        try { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "  elementor css cache cleared\n"; }
        catch (\Throwable $e) { echo "  (elementor cache clear skipped: " . $e->getMessage() . ")\n"; }
    }
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
    if (function_exists('af_mk_flush_feeds')) af_mk_flush_feeds();
    if (function_exists('rocket_clean_domain')) rocket_clean_domain();
    do_action('litespeed_purge_all');
    wp_cache_flush();
    echo "  product transients, feeds and page cache flushed\n";
}

// ── 4. what is left (the same count tools/diag-free-shipping.php reports) ──
$left = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts}
      WHERE post_status IN ('publish','draft','private','pending','future')
        AND post_type NOT IN ('revision','attachment')
        AND (LOWER(post_content) LIKE '%free shipping%' OR LOWER(post_content) LIKE '%free delivery%')")
      + (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta}
      WHERE meta_key IN ('_elementor_data','_elementor_element_cache')
        AND (LOWER(meta_value) LIKE '%free shipping%' OR LOWER(meta_value) LIKE '%free delivery%')");
echo "  rows still saying free shipping/delivery: {$left}\n";
echo "=== DONE ===\n";
