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
 * The debug notes are gone, and their removal is the point.
 *
 * They printed the finished SQL, the query's internals and the matched ids
 * straight into the page, and the only thing between a visitor and that output
 * was an X-AF-Probe request header — which is client-supplied, not a secret.
 * Anyone who sent it got the lot back in the HTML. That was a fair trade for an
 * afternoon spent measuring a search that did not work; it is not a fair trade
 * now that it does.
 *
 * What they were for is covered from outside the page instead:
 * check-search-server.yml fetches a real search over the loopback and reports
 * which template rendered, how many product cards are on it, whether the blog
 * sidebar is gone and how many prices are showing.
 *
 * Kept as a no-op so the handful of call sites need no edit and any future one
 * is harmless.
 */
function af_search_debug($msg) {}

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
    // Only published products and posts — never a draft, never a revision,
    // never an attachment, never a product hidden from search.
    return af_search_published_ids($ids);
}

/**
 * Widen the SEARCH clause: everything it already matched, OR one of our ids.
 *
 * ── WHY posts_search AGAIN, AND WHY AT 100000 ─────────────────────────────
 * Three hooks were tried before this one and each failed for a measurable
 * reason. posts_where cannot see the text match: WordPress concatenates the
 * search clause into the query after that filter, so ORing there widened the
 * post-type group while the title match went on excluding everything —
 * "rk0118" matched one id and still returned nothing. posts_clauses cannot
 * reach it either: WordPress does not list the search clause among the pieces
 * that filter may rewrite, so the modified clause was simply discarded, again
 * measured with one id matched and nothing returned.
 *
 * posts_search failed at 10, at 999 and again at 100000: the clause is EMPTY
 * at every one of them, because core built no search clause (the s query var
 * was already emptied) and the title match this site does perform is injected
 * into the WHERE by some other plugin, later still.
 *
 * So the hook is posts_clauses at PHP_INT_MAX, rewriting 'where' — which IS
 * one of the pieces that filter may change, unlike 'search'. By then every
 * other plugin has contributed and the finished condition is there to widen.
 * The debug line reports whether the title match is present in it, so this
 * assumption is checked on every probe rather than trusted.
 *
 * The original is kept intact inside its own group — this can only ever ADD
 * results, which is the one promise this module makes.
 */
/**
 * Widen the finished SQL statement.
 *
 * Every earlier hook was tried and each was measured to be blind to the
 * condition that matters:
 *
 *   posts_search   empty at priority 10, 999 and 100000 alike
 *   posts_where    the text match is not in it (ORing there left rk0118 at 0)
 *   posts_clauses  'search' is not a rewritable piece, and the 'where' piece
 *                  it does expose came back 222 chars with
 *                  text_match_present=no even at PHP_INT_MAX
 *
 * The text match is concatenated into the statement after all of them, so
 * posts_request — the complete SQL, the last thing before the database sees
 * it — is the only place the whole condition exists.
 *
 * The rewrite is deliberately narrow: it finds the WHERE section, wraps it in
 * its own group and ORs the ids beside it. Everything the query already
 * matched still matches.
 */
function af_search_widen_sql($sql, $ids, $posts_table) {
    if (!$ids || strpos($sql, 'WHERE 1=1') === false) return $sql;
    $start = strpos($sql, 'WHERE 1=1') + strlen('WHERE 1=1');
    // The WHERE section ends at the first of these, whichever comes first.
    $end = strlen($sql);
    foreach (array(' GROUP BY ', ' ORDER BY ', ' LIMIT ') as $kw) {
        $at = stripos($sql, $kw, $start);
        if ($at !== false && $at < $end) $end = $at;
    }
    $conditions = substr($sql, $start, $end - $start);
    if (trim($conditions) === '') return $sql;      // nothing to widen
    $widened = ' AND ( ( 1=1 ' . $conditions . ' ) OR ' . $posts_table
             . '.ID IN (' . implode(',', array_map('intval', $ids)) . ') ) ';
    return substr($sql, 0, $start) . $widened . substr($sql, $end);
}

/* ═══════════════════════════════════════════════════════════════════════════
 * SPELLINGS AND OTHER NAMES (Test Run 03, M-03)
 *
 * Measured on the live shop, 23 Sep: "shiv" 28 results and "radah krishna"
 * 112, but "krishan", "krisna", "ganpati" and "buddah" 0 each. Every match
 * above is LIKE '%word%', so a word finds something only if it is spelled
 * exactly as a title or a category spells it, and these are the spellings
 * customers actually type. In the product export of 21 Sep, "krishna" is in
 * 107 titles and categories and "buddha" in 39; "krishan", "krisna",
 * "buddah", "ganpati" and "laxmi" are in none.
 *
 * Two additions, and like everything else here they only ADD results:
 *
 *   other names   a typed word that belongs to a group below (ganpati,
 *                 vinayaka, ganesh, ganesha) also searches the members of
 *                 its group that the catalogue actually uses
 *   misspellings  a typed word that appears in no title and no category is
 *                 matched to the nearest catalogue word: one letter wrong,
 *                 missing, extra or swapped with its neighbour ("buddah"),
 *                 two for words of six letters or more; the first letter has
 *                 to be right. Run-together words ("radhakrishna") and a
 *                 trailing "ji" ("ganeshji") are taken apart the same way.
 *
 * The catalogue's words are read from product titles and product category,
 * tag and attribute names, and kept for twelve hours or until a product or
 * term is saved. What was added is said under the results heading
 * ("Including results for “krishna”"), so no result is unexplained.
 * ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Names that mean the same thing to a customer. Each group is searched as a
 * whole when any member is typed, but only for the members the catalogue
 * uses (af_search_expand() checks), so a spelling no title has costs nothing.
 * Short, ambiguous words (a person's name like Shyam, "hari", "mor") are left
 * out: they would pull in pieces the visitor did not mean.
 */
function af_search_synonyms() {
    return array(
        array('ganesha', 'ganesh', 'ganpati', 'ganapati', 'ganapathi', 'ganapathy', 'ganesa', 'vinayaka', 'vinayak', 'vinayagar', 'pillaiyar', 'gajanan', 'gajanana'),
        array('krishna', 'krishan', 'krisna', 'krsna', 'kanha', 'kanhaiya', 'kanhaiyya', 'murlidhar'),
        array('radha', 'radhe', 'radhika', 'radharani'),
        array('shiva', 'shiv', 'siva', 'shankar', 'shankara', 'mahadev', 'mahadeva', 'bholenath', 'shambhu', 'neelkanth'),
        array('lakshmi', 'laxmi', 'lakshmee', 'laksmi', 'lakhsmi', 'mahalakshmi', 'mahalaxmi'),
        array('saraswati', 'sarasvati', 'saraswathi', 'saraswathy', 'sharada'),
        array('hanuman', 'hanumaan', 'hanumanji', 'bajrang', 'bajrangbali', 'anjaneya', 'anjaneyar', 'maruti', 'maruthi'),
        array('durga', 'durgaa', 'bhavani'),
        array('buddha', 'buddah', 'budha', 'budda', 'bhudda', 'gautama', 'siddhartha'),
        array('vishnu', 'visnu', 'narayana', 'narayan'),
        array('balaji', 'venkateswara', 'venkateshwara', 'venkatesh', 'srinivasa', 'tirupati', 'tirupathi', 'thirupathi'),
        array('murugan', 'muruga', 'kartikeya', 'karthikeya', 'subramanya', 'subramanian', 'skanda', 'shanmukha'),
        array('rama', 'shriram', 'sriram', 'ramachandra'),
        array('sita', 'seeta', 'seetha', 'janaki'),
        array('parvati', 'parvathi', 'parvathy'),
        array('nataraja', 'natraj', 'nataraj'),
        array('jagannath', 'jagannatha', 'jaganath'),
        array('ayyappa', 'ayyappan', 'ayappa'),
        array('meenakshi', 'minakshi'),
        array('tanjore', 'thanjavur', 'tanjavur'),
        array('madhubani', 'madubani', 'mithila'),
        array('pichwai', 'pichhwai', 'pichvai', 'shrinathji', 'srinathji', 'nathdwara'),
        array('murti', 'moorti', 'murthi', 'idol'),
        array('varanasi', 'banaras', 'benares', 'kashi'),
        array('bharatanatyam', 'bharatnatyam', 'bharathanatyam'),
        array('temple', 'mandir'),
        array('elephant', 'elefant', 'haathi', 'hathi'),
        array('peacock', 'mayur', 'mayura'),
        array('lotus', 'kamal', 'padma'),
        array('saibaba', 'sai baba', 'shirdi'),
    );
}

/**
 * Every word of the catalogue, with how often it occurs. Pure, so the tests
 * can feed it any list of titles.
 */
function af_search_build_vocabulary($texts) {
    $v = array();
    foreach ((array) $texts as $t) {
        $t = strtolower(html_entity_decode((string) $t, ENT_QUOTES, 'UTF-8'));
        foreach (preg_split('/[^a-z]+/', $t, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if (strlen($w) >= 3) $v[$w] = isset($v[$w]) ? $v[$w] + 1 : 1;
        }
    }
    return $v;
}

/** The live catalogue's words: product titles and product term names. */
function af_search_vocabulary() {
    static $v = null;
    if ($v !== null) return $v;
    $cached = get_transient('af_search_vocab');
    if (is_array($cached)) return $v = $cached;
    global $wpdb;
    $titles = $wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
    $names  = $wpdb->get_col("SELECT DISTINCT t.name FROM {$wpdb->terms} t"
        . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id"
        . " WHERE tt.taxonomy IN ('product_cat','product_tag') OR tt.taxonomy LIKE 'pa\\_%'");
    $v = af_search_build_vocabulary(array_merge((array) $titles, (array) $names));
    set_transient('af_search_vocab', $v, 12 * HOUR_IN_SECONDS);
    return $v;
}
foreach (array('save_post_product', 'created_term', 'edited_term', 'delete_term') as $af_hook) {
    add_action($af_hook, function () { delete_transient('af_search_vocab'); });
}

/**
 * Letters to change, add, remove or swap with a neighbour to turn $a into $b
 * (the optimal string alignment distance). levenshtein() counts a swap as two,
 * which would put "buddah" as far from "buddha" as from "bud".
 */
function af_search_distance($a, $b) {
    $la = strlen($a); $lb = strlen($b);
    $d = array();
    for ($i = 0; $i <= $la; $i++) $d[$i] = array($i);
    for ($j = 0; $j <= $lb; $j++) $d[0][$j] = $j;
    for ($i = 1; $i <= $la; $i++) {
        for ($j = 1; $j <= $lb; $j++) {
            $cost = ($a[$i - 1] === $b[$j - 1]) ? 0 : 1;
            $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
            if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
            }
        }
    }
    return $d[$la][$lb];
}

/**
 * True when some catalogue word BEGINS with $w: "shiv" (shiva), "tanjor"
 * (tanjore), "landscap". Begins, not contains: "shva" is inside
 * "vishvarupa", and one unrelated piece is not what the visitor asked for.
 */
function af_search_in_vocabulary($w, $vocab) {
    if (isset($vocab[$w])) return true;
    foreach ($vocab as $word => $n) {
        if (strpos((string) $word, $w) === 0) return true;
    }
    return false;
}

/**
 * A word as it sounds, for comparing spellings: ph is f, sh is s, x is ks,
 * doubled letters are single. Without it "elefant" is nearer "elegant" (one
 * letter) than "elephant" (two); with it, laxmi and lakshmi are the same word.
 */
function af_search_sound($w) {
    $w = strtr($w, array(
        'ph' => 'f', 'ck' => 'k', 'sh' => 's', 'th' => 't', 'dh' => 'd', 'bh' => 'b',
        'kh' => 'k', 'gh' => 'g', 'x' => 'ks', 'q' => 'k', 'z' => 's', 'w' => 'v',
        'y' => 'i', 'ee' => 'i', 'oo' => 'u',
    ));
    return preg_replace('/(.)\1+/', '$1', $w);
}

/**
 * The catalogue words to search as well as what was typed.
 *
 * @param array $terms from af_search_terms()
 * @param array $vocab word => count, from af_search_vocabulary()
 * @return array lowercase words or phrases, most useful first, at most eight
 */
function af_search_expand($terms, $vocab) {
    if (!$terms || !$vocab) return array();
    $phrase = strtolower(trim((string) $terms[0]));
    // An art code is an identifier, never a misspelling of a word.
    if (preg_match('/^[a-z]{1,4}[0-9]{2,6}$/', af_search_flatten($phrase))) return array();
    $typed = array_values(array_unique(preg_split('/[^a-z]+/', $phrase, -1, PREG_SPLIT_NO_EMPTY)));
    $out = array();
    $grouped = array();

    // Other names.
    foreach (af_search_synonyms() as $group) {
        $hit = false;
        foreach ($group as $m) {
            if (strpos($m, ' ') !== false ? strpos(' ' . $phrase . ' ', ' ' . $m . ' ') !== false : in_array($m, $typed, true)) { $hit = true; break; }
        }
        if (!$hit) continue;
        foreach ($group as $m) {
            $grouped[$m] = true;
            $words = explode(' ', $m);
            $known = true;
            foreach ($words as $w) if (!isset($vocab[$w])) { $known = false; break; }
            if ($known) $out[] = $m;
        }
    }

    // Misspellings: only for a word the catalogue does not contain anywhere.
    foreach ($typed as $w) {
        if (strlen($w) < 4 || isset($grouped[$w]) || af_search_in_vocabulary($w, $vocab)) continue;

        // "ganeshji", "hanumanji"
        if (substr($w, -2) === 'ji' && strlen($w) >= 6 && af_search_in_vocabulary(substr($w, 0, -2), $vocab)) {
            $out[] = substr($w, 0, -2);
            continue;
        }
        // "radhakrishna", "saibaba": two catalogue words run together.
        $split = false;
        for ($i = 3; $i <= strlen($w) - 3; $i++) {
            $l = substr($w, 0, $i); $r = substr($w, $i);
            if (isset($vocab[$l]) && isset($vocab[$r])) { $out[] = $l . ' ' . $r; $split = true; break; }
        }
        if ($split) continue;

        $max = strlen($w) >= 6 ? 2 : 1;
        $ws = af_search_sound($w);
        $best = array();
        foreach ($vocab as $cand => $n) {
            $cand = (string) $cand;
            if (strlen($cand) < 4 || $cand[0] !== $w[0] || abs(strlen($cand) - strlen($w)) > $max + 1) continue;
            $cs = af_search_sound($cand);
            if (levenshtein($ws, $cs) > $max * 2) continue;        // cheap first cut
            $d = af_search_distance($ws, $cs);
            if ($d > $max) continue;
            // Nearest by sound, then by letters, then the word the catalogue
            // uses most.
            $best[$cand] = array($d, af_search_distance($w, $cand), -$n);
        }
        asort($best);
        $keys = array_keys($best);
        foreach ($keys as $i => $c) {
            // The best, and a second only if it is exactly as near.
            if ($i === 0 || ($i === 1 && $best[$c][0] === $best[$keys[0]][0] && $best[$c][1] === $best[$keys[0]][1])) $out[] = $c;
        }
    }

    $out = array_values(array_unique($out));
    return array_slice($out, 0, 8);
}

/**
 * Published, searchable products (and posts and pages) among $ids. Never a
 * draft, a revision or an attachment, and never a product the shop hides
 * from search.
 */
function af_search_published_ids($ids) {
    global $wpdb;
    $ids = array_values(array_unique(array_map('intval', (array) $ids)));
    if (!$ids) return array();
    $in = implode(',', $ids);
    $ok = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$in})"
        . " AND post_status = 'publish' AND post_type IN ('product','post','page')"
        . " AND ID NOT IN (SELECT vtr.object_id FROM {$wpdb->term_relationships} vtr"
        . " INNER JOIN {$wpdb->term_taxonomy} vtt ON vtt.term_taxonomy_id = vtr.term_taxonomy_id"
        . " INNER JOIN {$wpdb->terms} vt ON vt.term_id = vtt.term_id"
        . " WHERE vtt.taxonomy = 'product_visibility' AND vt.slug = 'exclude-from-search')"
    );
    return array_map('intval', (array) $ok);
}

/**
 * Products whose title, or one of whose category, tag or attribute names, has
 * a word STARTING with one of $words. Word starts, not LIKE '%rama%', which
 * would also find "dramatic" and "panorama".
 */
function af_search_word_ids($words, $limit = 300) {
    global $wpdb;
    $ids = array();
    foreach ((array) $words as $w) {
        $like = '% ' . $wpdb->esc_like($w) . '%';
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
            . " AND CONCAT(' ', REPLACE(REPLACE(LOWER(post_title),'-',' '),'&',' ')) LIKE %s LIMIT %d",
            $like, $limit
        ));
        if ($rows) $ids = array_merge($ids, $rows);
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr"
            . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . " INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
            . " WHERE (tt.taxonomy IN ('product_cat','product_tag') OR tt.taxonomy LIKE 'pa\\_%')"
            . " AND CONCAT(' ', REPLACE(LOWER(t.name),'-',' ')) LIKE %s LIMIT %d",
            $like, $limit
        ));
        if ($rows) $ids = array_merge($ids, $rows);
    }
    return af_search_published_ids($ids);
}

/** What af_search_expand() added to this page's search, for the note. */
function af_search_added($set = null) {
    static $added = array();
    if ($set !== null) $added = $set;
    return $added;
}

/**
 * "Including results for “krishna”." under the results heading, or '' when
 * nothing was added. Words the visitor typed, or that contain what they
 * typed ("ganesha" for "ganesh"), are already their own results and are not
 * listed.
 */
function af_search_added_html($typed) {
    $typed = strtolower((string) $typed);
    $show = array();
    foreach (af_search_added() as $w) {
        if (strpos($typed, $w) !== false) continue;
        $contains = false;
        foreach (preg_split('/[^a-z]+/', $typed, -1, PREG_SPLIT_NO_EMPTY) as $t) {
            if (strlen($t) >= 3 && strpos($w, $t) !== false) { $contains = true; break; }
        }
        if (!$contains) $show[] = '&ldquo;' . esc_html($w) . '&rdquo;';
    }
    if (!$show) return '';
    return '<p class="af-sr-also">Including results for ' . implode(', ', array_slice($show, 0, 4)) . '.</p>'
         . '<style>.af-sr-also{margin:4px 0 12px;font-size:14px;color:#6b6b6b}</style>';
}

add_filter('posts_request', function ($sql, $q) {
    if (af_search_disabled()) return $sql;
    if (!af_search_is_main_search($q)) return $sql;
    $terms = af_search_terms(af_search_query_string($q));
    if (!$terms) return $sql;
    $ids = af_search_matching_ids($terms);
    // Spellings and other names. A failure here must never cost the search
    // it would have returned anyway.
    try {
        $added = af_search_expand($terms, af_search_vocabulary());
        af_search_added($added);
        if ($added) $ids = array_values(array_unique(array_merge($ids, af_search_word_ids($added))));
    } catch (\Throwable $e) {
        af_search_added(array());
    }
    af_search_debug('posts_request: ' . count($ids) . ' id(s) matched'
        . '; text_match_present=' . (strpos($sql, 'post_title LIKE') !== false ? 'yes' : 'no'));
    if (!$ids) return $sql;
    global $wpdb;
    return af_search_widen_sql($sql, $ids, $wpdb->posts);
}, PHP_INT_MAX, 2);
