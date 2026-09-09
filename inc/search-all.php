<?php
if (!defined('ABSPATH')) exit;
/**
 * Search that finds a product by anything printed on its card.
 *
 * Owner, 2026-09-09, with a recording: searching "RK - 0118" — an art code
 * shown on every product card on the site — returns "Nothing Found".
 *
 * ── WHY IT FINDS NOTHING ──────────────────────────────────────────────────
 * Two separate reasons, and fixing either alone still returns nothing.
 *
 * 1. WordPress search reads the post title, excerpt and content. That is all
 *    it has ever read. The art code is not in any of them: it lives in post
 *    meta under _taf_art_code, printed onto the card by af_get_art_code().
 *    Neither are the SKU, the colour, the size or the frame — attributes are
 *    taxonomy terms, which search also never consults. So the one identifier
 *    a customer is most likely to type is the one identifier the search
 *    engine cannot see.
 *
 * 2. The search box posts to /?s=... with no post type, so it searches posts
 *    and pages. Products were never in the result set to begin with.
 *
 * ── AND WHY AN EXACT CODE STILL WOULD NOT MATCH ───────────────────────────
 * "RK - 0118" is stored as "RK - 0118", but customers type "RK-0118",
 * "rk 0118" and "RK0118". Comparing those literally fails on the spacing
 * alone. Both sides are therefore flattened — case, spaces, hyphens and
 * underscores removed — before they are compared, so every one of those
 * spellings finds the same piece.
 *
 * ── SCOPE ─────────────────────────────────────────────────────────────────
 * This only ever ADDS matches: the clause is OR-ed onto the search WordPress
 * already built, so nothing that used to be found stops being found. It runs
 * on the main front-end search query and nowhere else — not in the admin, not
 * on any other archive, not in a secondary query some widget runs.
 */

/**
 * Flatten a string for comparison: lowercase, and no spaces, hyphens or
 * underscores. "RK - 0118", "rk-0118" and "RK0118" all become "rk0118".
 */
function af_search_flatten($s) {
    return preg_replace('/[\s\-_]+/u', '', strtolower((string) $s));
}

/**
 * The words worth searching for, from a raw query string.
 *
 * The whole phrase comes first — "blue canvas" should prefer the piece whose
 * attribute is literally that — followed by the individual words, so a
 * two-word search still finds a piece matching one of them. Words of one
 * character are dropped: they match nearly everything and rank nothing.
 */
function af_search_terms($q) {
    $q = trim(preg_replace('/\s+/u', ' ', (string) $q));
    if ($q === '') return array();

    // An art code is searched WHOLE and never split. "PA - 1201" split into
    // its parts searched for "PA", which appears in the name of half the
    // catalogue's terms — measured on the live shop, it returned 50 results
    // headed by Shiva Parivar and four Tanjore panels, none of them the piece
    // asked for. A code is an identifier: the visitor wants that one product,
    // and a near-miss is worse than nothing because it buries the hit.
    if (preg_match('/^[a-z]{1,4}[0-9]{2,6}$/', af_search_flatten($q))) {
        return array($q);
    }

    $out = array($q);
    foreach (explode(' ', $q) as $w) {
        // Three characters, not two. Short fragments match nearly every term
        // in a catalogue this size and rank nothing usefully.
        if (mb_strlen($w) > 2 && !in_array($w, $out, true)) $out[] = $w;
    }
    return $out;
}

/**
 * The SQL that matches meta and taxonomy terms, as an OR-able fragment.
 *
 * EXISTS subqueries rather than JOINs, deliberately: a JOIN onto postmeta
 * returns one row per matching meta row, so a product matching on both its
 * SKU and its art code would appear in the results twice. EXISTS asks only
 * whether a match exists.
 *
 * @param array  $terms  from af_search_terms()
 * @param string $prefix the table prefix ($wpdb->prefix)
 * @param object $db     anything with esc_like() and prepare(); $wpdb in life
 * @return string SQL beginning with " OR (", or '' when there is nothing to add
 */
function af_search_meta_sql($terms, $prefix, $db) {
    if (!$terms) return '';
    $meta_keys = "'_taf_art_code','_sku','_af_sku_artcode'";
    $bits = array();
    foreach ($terms as $t) {
        $like  = '%' . $db->esc_like($t) . '%';
        // The flattened comparison is what actually catches an art code typed
        // with different spacing, so it is applied to the meta values.
        $flat  = af_search_flatten($t);
        if ($flat === '') continue;
        $fLike = '%' . $db->esc_like($flat) . '%';

        $bits[] = $db->prepare(
            "EXISTS (SELECT 1 FROM {$prefix}postmeta afm"
            . " WHERE afm.post_id = {$prefix}posts.ID"
            . " AND afm.meta_key IN ({$meta_keys})"
            . " AND REPLACE(REPLACE(REPLACE(LOWER(afm.meta_value),' ',''),'-',''),'_','') LIKE %s)",
            $fLike
        );
        // Colour, size, frame and category all live here: attributes are
        // taxonomy terms, and search has never looked at a taxonomy.
        $bits[] = $db->prepare(
            "EXISTS (SELECT 1 FROM {$prefix}term_relationships aftr"
            . " INNER JOIN {$prefix}term_taxonomy aftt ON aftt.term_taxonomy_id = aftr.term_taxonomy_id"
            . " INNER JOIN {$prefix}terms aft ON aft.term_id = aftt.term_id"
            . " WHERE aftr.object_id = {$prefix}posts.ID AND aft.name LIKE %s)",
            $like
        );
    }
    if (!$bits) return '';
    return ' OR (' . implode(' OR ', $bits) . ') ';
}

/**
 * The words the visitor typed.
 *
 * $q->get('s') is the obvious source and on this site it comes back EMPTY on a
 * real page load, while the same query reports is_search() true and its SQL
 * plainly contains the term. Measured 2026-09-09 by recording every call:
 *
 *   posts_search callback ran 9 time(s) | called main=1 search=1 s=""
 *
 * So this module found nothing to search for and returned early every single
 * time — on the live site only. Under WP-CLI the query var is present, which
 * is why every probe reported the clause working and every page did not.
 *
 * Something on this stack empties that variable between parsing the request
 * and running the query. Rather than hunt it through forty plugins, the term
 * is taken from the query when it is there and from the request itself when it
 * is not; the two agree whenever both exist.
 */
function af_search_query_string($q) {
    $s = (string) $q->get('s');
    if ($s !== '') return $s;
    if (isset($_GET['s'])) return (string) wp_unslash($_GET['s']);
    if (function_exists('get_search_query')) return (string) get_search_query(false);
    return '';
}

/** True when this is the one query whose results the visitor is looking at. */
function af_search_is_main_search($q) {
    return $q->is_main_query() && $q->is_search() && !is_admin();
}

/**
 * A way to turn this module off for one request, and a way to see what it did.
 *
 * The rendered search page finds nothing while the same terms in WP_Query find
 * plenty, which points at this module rather than away from it — and the only
 * honest way to settle that is to run the same request with the module inert
 * and compare. ?afsearch=off does that. It changes nothing for anyone who does
 * not pass it.
 */
function af_search_disabled() {
    return isset($_GET['afsearch']) && $_GET['afsearch'] === 'off';
}

/**
 * A note in the HTML saying what happened, for requests from the server itself.
 *
 * Restricted to the loopback address deliberately: the finished SQL is useful
 * to a diagnostic and to nobody else, so it is never rendered for a visitor.
 */
function af_search_debug($msg) {
    static $on = null;
    if ($on === null) {
        // A request HEADER, not the client address. The loopback check goes
        // through LiteSpeed, which rewrites REMOTE_ADDR, so the address test
        // silently disabled this and left the run with nothing to read. A
        // header no visitor's browser sends is both reliable and private.
        $on = !empty($_SERVER['HTTP_X_AF_PROBE']);
    }
    if ($on) echo "\n<!-- af-search: " . str_replace('--', '- -', (string) $msg) . " -->\n";
}

/**
 * What the MAIN query actually was, printed for a probe request.
 *
 * The rendered page finds nothing where the same terms in WP_Query find
 * plenty, and turning this module off changes neither — so the question is no
 * longer about this module, it is "what query did the page actually run".
 * This prints it: the post types asked for, how many were found, and the SQL.
 */
/**
 * The loop's state at the moment the template asks have_posts().
 *
 * The parent template is the ordinary one — if ( have_posts() ) render the
 * loop, else render "Nothing Found" — and it takes the else branch while the
 * query holds ten posts. Only two things do that: the loop was already walked
 * to its end by something earlier in the request, or post_count disagrees with
 * the posts array. current_post and post_count tell them apart.
 */
add_action('template_redirect', function () {
    if (empty($_SERVER['HTTP_X_AF_PROBE'])) return;
    $q = isset($GLOBALS['wp_query']) ? $GLOBALS['wp_query'] : null;
    if (!$q) return;
    add_action('wp_footer', function () use ($q) {
        af_search_debug('AT TEMPLATE TIME: suppress_filters=' . var_export($q->get('suppress_filters'), true)
            . ' post_count=' . (int) $q->post_count
            . ' current_post=' . (int) $q->current_post
            . ' posts_in_array=' . count((array) $q->posts)
            . ' have_posts_would_be=' . (($q->current_post + 1 < $q->post_count) ? 'TRUE' : 'FALSE'));
    }, 98);
}, 1);

add_action('wp_footer', function () {
    if (empty($_SERVER['HTTP_X_AF_PROBE'])) return;
    $q = isset($GLOBALS['wp_query']) ? $GLOBALS['wp_query'] : null;
    if (!$q) { af_search_debug('main query: none'); return; }
    $pt = $q->get('post_type');
    af_search_debug('MAIN is_search=' . ($q->is_search() ? 1 : 0)
        . ' s="' . (string) $q->get('s') . '"'
        . ' post_type=' . (is_array($pt) ? implode('+', $pt) : (string) $pt)
        . ' found=' . (int) $q->found_posts
        . ' posts=' . count((array) $q->posts));
    af_search_debug('SQL ' . preg_replace('/\s+/', ' ', (string) $q->request));
    $trace = isset($GLOBALS['af_search_trace']) ? (array) $GLOBALS['af_search_trace'] : array();
    af_search_debug('posts_search callback ran ' . count($trace) . ' time(s)'
        . ($trace ? ' | ' . implode(' | ', array_slice($trace, 0, 3)) : ' - NEVER INVOKED'));
}, 99);

/*
 * THE posts_search FILTER IS GONE, and its removal is the fix.
 *
 * It was the obvious hook and it is useless on this site. Measured at both
 * priority 10 and 999: WordPress hands it an EMPTY clause ("wp_search=0
 * chars") while the finished query plainly contains a title match, because
 * something on this stack builds that match elsewhere. With nothing to widen,
 * the clause it added became " AND (1=0 OR mine) " — a restriction. That is
 * what held Krishna at 73 results when plain WordPress finds 86.
 *
 * af_search_matching_ids() and the posts_where filter below do the work now.
 * af_search_meta_sql() is kept because the server-side probe and the tests
 * still exercise it as the reference for how a match is defined.
 */   // 999: LAST. Measured — see below.

/*
 * WHY 999 AND NOT 10.
 *
 * At priority 10 this filter received an EMPTY search clause from WordPress
 * (measured on the live page: "clause=745 chars wp_search=0 chars") while the
 * finished SQL plainly contained a title match. Something else on this stack
 * builds that match after core does, which is the same thing that empties the
 * s query var.
 *
 * OR-ing onto an empty string produced " AND (1=0 OR mine) ", a restriction
 * rather than an addition: "Krishna" fell from 86 results to 73, the ones that
 * matched the title AND the meta. Exactly the opposite of the intent, which is
 * that this module can only ever ADD matches.
 *
 * Running last means the other clause has already been built, so there is
 * something real to widen.
 */

/**
 * Put products into the results at all.
 *
 * The theme's search form posts to /?s=... with no post type, so the query
 * defaults to posts and pages and a product could never appear however well
 * it matched. Products are ADDED to that list rather than replacing it, so
 * blog posts and pages remain findable exactly as before.
 */
add_action('pre_get_posts', function ($q) {
    if (af_search_disabled()) return;
    if (!af_search_is_main_search($q)) return;
    if (!post_type_exists('product')) return;

    $pt = $q->get('post_type');
    // An explicit single-type search (?s=x&post_type=product) is the visitor
    // being specific; leave it alone.
    if (!empty($pt) && $pt !== 'any') return;

    $q->set('post_type', array('product', 'post', 'page'));

    // posts_* filters are skipped entirely when a query carries
    // suppress_filters, while pre_get_posts still runs — which is exactly the
    // shape of what was measured on the live site: the post types this
    // function sets came through, and the search clause added by the
    // posts_search filter did not appear in the SQL at all. A search query has
    // no business suppressing filters; whatever sets it, this unsets it for
    // this one query.
    $q->set('suppress_filters', false);
}, 20);

/**
 * A search URL renders the search. It does not bounce to the front page.
 *
 * Measured 2026-09-09: every query — art codes, "Krishna", "Blue" — answered
 * with one redirect to https://theartframer.us/ and the homepage's own 956KB
 * of markup. The visitor types a search and arrives back where they started,
 * which reads as "search is broken" no matter how well the query itself works.
 *
 * That is redirect_canonical(), WordPress's tidy-up pass. It rewrites URLs it
 * believes are non-canonical, and it makes that judgement from query variables
 * that a search legitimately alters — the post types above among them. On a
 * search results page there is nothing for it to usefully do and one clear way
 * for it to go wrong, so it is switched off there and left alone everywhere
 * else.
 */
add_filter('redirect_canonical', function ($redirect) {
    return is_search() ? false : $redirect;
}, 10, 1);

/**
 * The ids of every product this search should ALSO find.
 *
 * ── WHY IDS AND NOT A CLAUSE IN posts_search ──────────────────────────────
 * Because on this site posts_search is a dead end. Measured twice, at
 * priority 10 and again at 999: WordPress hands this filter an EMPTY search
 * clause ("wp_search=0 chars") while the finished query plainly contains a
 * title match. Something on this stack builds that match outside posts_search
 * — the same something that empties the s query var — so there is never
 * anything there to widen, and OR-ing onto the empty string produced
 * "AND (1=0 OR mine)", which narrowed Krishna from 86 results to 73.
 *
 * So the match is computed directly and OR-ed into the WHERE by id. Slower in
 * principle, irrelevant in practice: one indexed meta query and one taxonomy
 * query against a 400-product catalogue, on a page that is not cached anyway.
 */
function af_search_matching_ids($terms, $limit = 300) {
    global $wpdb;
    if (!$terms) return array();
    $ids = array();
    foreach ($terms as $t) {
        $flat = af_search_flatten($t);
        if ($flat === '') continue;

        // Art code, SKU: compared with spacing, case and punctuation removed,
        // so "RK - 0118", "rk-0118" and "RK0118" all reach the same piece.
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta}"
            . " WHERE meta_key IN ('_taf_art_code','_sku','_af_sku_artcode')"
            . " AND REPLACE(REPLACE(REPLACE(LOWER(meta_value),' ',''),'-',''),'_','') LIKE %s"
            . " LIMIT %d",
            '%' . $wpdb->esc_like($flat) . '%', $limit
        ));
        if ($rows) $ids = array_merge($ids, $rows);

        // Colour, size, frame, category: attribute values are taxonomy terms,
        // which WordPress search has never consulted.
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . " INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
            . " WHERE t.name LIKE %s LIMIT %d",
            '%' . $wpdb->esc_like($t) . '%', $limit
        ));
        if ($rows) $ids = array_merge($ids, $rows);
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) return array();

    // Only published products and posts — never a draft, never a revision,
    // never an attachment.
    $in = implode(',', $ids);
    $ok = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$in})"
        . " AND post_status = 'publish' AND post_type IN ('product','post','page')"
    );
    return array_map('intval', (array) $ok);
}

/**
 * Widen the WHERE: everything it already matched, OR one of our ids.
 *
 * The original conditions are kept intact inside their own group — this can
 * only ever ADD results, which is the one promise this module makes.
 */
add_filter('posts_where', function ($where, $q) {
    if (af_search_disabled()) return $where;
    if (!af_search_is_main_search($q)) return $where;
    $terms = af_search_terms(af_search_query_string($q));
    if (!$terms) return $where;
    $ids = af_search_matching_ids($terms);
    af_search_debug('posts_where: ' . count($ids) . ' id(s) matched by code, sku or attribute');
    if (!$ids) return $where;
    global $wpdb;
    // $where arrives beginning with " AND ...", so "( 1=1 $where )" is a valid
    // group and the whole thing stays one AND-ed condition.
    return ' AND ( ( 1=1 ' . $where . ' ) OR ' . $wpdb->posts . '.ID IN ('
         . implode(',', $ids) . ') ) ';
}, 9999, 2);
