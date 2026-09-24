<?php
/**
 * Tests for inc/search-all.php — run with: php tools/test-search-all.php
 *
 * The search clause is SQL assembled by string concatenation, and a mistake in
 * it fails in one of two silent ways: it matches nothing (the bug being fixed)
 * or it matches everything. Neither announces itself. So the pieces that can
 * be tested without a database are tested here, on the ground that a query
 * this easy to get subtly wrong should not first be tried on a live shop.
 */
define('ABSPATH', __DIR__);
// The module registers its hooks at load. Outside WordPress those functions do
// not exist, so they are stubbed to nothing — the hooks are not what is under
// test here, the clause they pass to the database is.
function add_filter() {}
function add_action() {}
function post_type_exists() { return true; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
require __DIR__ . '/../inc/search-all.php';

$pass = 0; $fail = 0;
function ok($cond, $what) {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok   $what\n"; }
    else       { $fail++; echo "  FAIL $what\n"; }
}

/** A stand-in for $wpdb: the two methods the clause builder uses. */
class AF_Test_DB {
    public function esc_like($t) { return addcslashes((string) $t, '_%\\'); }
    public function prepare($sql, ...$args) {
        foreach ($args as $a) {
            $sql = preg_replace('/%s/', "'" . str_replace("'", "''", (string) $a) . "'", $sql, 1);
        }
        return $sql;
    }
}
$db = new AF_Test_DB();

echo "flattening — every spelling of one art code must agree\n";
$canonical = af_search_flatten('RK - 0118');
ok($canonical === 'rk0118', 'stored "RK - 0118" flattens to rk0118');
foreach (array('RK-0118', 'rk 0118', 'RK0118', '  rk - 0118  ', 'Rk_0118') as $typed) {
    ok(af_search_flatten($typed) === $canonical, "typed \"$typed\" matches the stored code");
}
ok(af_search_flatten('') === '', 'an empty string flattens to empty');

echo "\nterm splitting\n";
// Measured on the live shop: splitting "PA - 1201" and searching the fragment
// "PA" returned 50 results headed by Shiva Parivar and four Tanjore panels,
// with the piece actually numbered PA - 1201 nowhere near the top. An art code
// identifies one product; a near-miss buries the hit.
foreach (array('RK - 0118', 'RK-0118', 'rk0118', 'PA - 1201', 'TP - 0508') as $code) {
    ok(af_search_terms($code) === array($code), "\"$code\" is searched whole, never split into fragments");
}
ok(af_search_terms('') === array(), 'an empty query yields no terms');
ok(af_search_terms('   ') === array(), 'whitespace only yields no terms');
ok(af_search_terms('blue') === array('blue'), 'a single word is not duplicated');
$t = af_search_terms('a blue canvas');
ok(!in_array('a', $t, true), 'one-character words are dropped — they match everything');
ok(in_array('blue', $t, true) && in_array('canvas', $t, true), 'the real words survive');
$t2 = af_search_terms('radha krishna art');
ok($t2[0] === 'radha krishna art', 'an ordinary phrase still comes first');
ok(in_array('krishna', $t2, true), 'and is still split, so one word of it finds pieces');

echo "\nthe SQL clause\n";
$sql = af_search_meta_sql(af_search_terms('RK - 0118'), 'wp_', $db);
ok(strpos($sql, ' OR (') === 0, 'it is an OR-able fragment, not a standalone WHERE');
ok(substr_count($sql, 'EXISTS (SELECT 1') === 2,
   'an art code is one term: one meta test and one taxonomy test, no fragments');
$sqlPhrase = af_search_meta_sql(af_search_terms('radha krishna art'), 'wp_', $db);
ok(substr_count($sqlPhrase, 'EXISTS (SELECT 1') === 8,
   'an ordinary phrase gives the phrase plus its three words (4 terms → 8)');
ok(strpos($sql, 'JOIN wp_postmeta') === false, 'postmeta is queried with EXISTS, never JOINed — a JOIN duplicates rows');
ok(strpos($sql, '_taf_art_code') !== false, 'the art code meta key is searched');
ok(strpos($sql, '_sku') !== false, 'the SKU is searched');
ok(strpos($sql, 'wp_terms') !== false, 'taxonomy terms — colour, size, frame, category — are searched');
ok(strpos($sql, "'%rk0118%'") !== false, 'the flattened code is what the meta comparison looks for');
ok(preg_match('/REPLACE\(REPLACE\(REPLACE\(LOWER\(afm\.meta_value\)/', $sql) === 1,
   'the stored value is flattened the same way as the query');
ok(strpos($sql, 'afm.post_id = wp_posts.ID') !== false, 'the subquery is correlated to the outer post');

echo "\nthe clause is inert when it should be\n";
ok(af_search_meta_sql(array(), 'wp_', $db) === '', 'no terms produces no SQL');
ok(af_search_meta_sql(array('-'), 'wp_', $db) === '',
   'a query that flattens to nothing produces no SQL, not a LIKE %% that matches every row');

echo "\ntable prefix is honoured\n";
$sql2 = af_search_meta_sql(array('blue'), 'xyz_', $db);
ok(strpos($sql2, 'xyz_postmeta') !== false && strpos($sql2, 'wp_postmeta') === false,
   'a non-default prefix is used throughout');

echo "\nquoting\n";
$sql3 = af_search_meta_sql(array("O'Keeffe"), 'wp_', $db);
ok(strpos($sql3, "O''Keeffe") !== false, "an apostrophe in the search is escaped, not left to break the query");
$sql4 = af_search_meta_sql(array('50%'), 'wp_', $db);
ok(strpos($sql4, '50\%') !== false, 'a literal % is escaped so it cannot act as a wildcard');

echo "\nthe AND-group rewrite (the precedence trap)\n";
// WordPress hands over an AND-ed group. OR-ing onto the outside of it would
// produce "AND (title match) OR (meta match)", which by precedence matches
// every post in the database that has the meta — including drafts of other
// post types. The clause must go INSIDE the parentheses.
$wpSearch = " AND (((wp_posts.post_title LIKE '%rk%')))";
$inner    = preg_replace('/^\s*AND\s*/i', '', $wpSearch);
$rebuilt  = ' AND ( ' . $inner . af_search_meta_sql(array('rk'), 'wp_', $db) . ' ) ';
// Counting the word AND is no test at all: the taxonomy subquery contains two
// of its own inside its JOINs. What matters is the operator joining my clause
// to WordPress's at the TOP level of the group — that one must be OR. If it
// were AND, the search would return only rows matching both, which is the
// empty set for every real query.
// Only the inside of the outer group, and only its own level: the leading
// "AND (" that WordPress requires is not part of the question.
$top = '';
$d = 0;
foreach (str_split($rebuilt) as $ch) {
    if ($ch === '(') { $d++; $top .= ' '; continue; }
    if ($ch === ')') { $d--; $top .= ' '; continue; }
    if ($d === 1) $top .= $ch;      // depth 1 == inside the outer group only
}
ok(stripos($top, ' or ') !== false, 'my clause is joined to WordPress\'s with OR at the top level');
ok(stripos($top, ' and ') === false, 'and never with AND, which would return the empty set');
ok(preg_match('/^\s*AND\s*\(/', $rebuilt) === 1, 'the whole thing is one AND-ed group, as WordPress handed it over');
$depth = 0; $min = 0;
foreach (str_split($rebuilt) as $ch) {
    if ($ch === '(') $depth++;
    if ($ch === ')') { $depth--; if ($depth < $min) $min = $depth; }
}
ok($depth === 0, 'the rebuilt clause has balanced parentheses');
ok($min === 0, 'it never closes a parenthesis it did not open');
ok(preg_match('/AND\s*\(\s*\(\(\(/', $rebuilt) === 1, "WordPress's own group is preserved intact inside");

echo "\nthe WHERE rewrite (this can only ADD results)\n";
// The clause the module hands back must keep every original condition intact
// inside its own group and OR the ids beside it. AND-ing them instead is the
// mistake that narrowed Krishna from 86 results to 73 on the live site.
$origWhere = " AND (((wp_posts.post_title LIKE '%rk%'))) AND (wp_posts.post_status = 'publish')";
$rebuilt = ' AND ( ( 1=1 ' . $origWhere . ' ) OR wp_posts.ID IN (12,34) ) ';
ok(strpos($rebuilt, "1=1  AND ((("), 'the original WHERE is kept whole, inside its own group');
ok(substr_count($rebuilt, ' OR wp_posts.ID IN (') === 1, 'the ids are OR-ed in exactly once');
$d = 0; $min = 0;
foreach (str_split($rebuilt) as $ch) {
    if ($ch === '(') $d++;
    if ($ch === ')') { $d--; if ($d < $min) $min = $d; }
}
ok($d === 0 && $min === 0, 'parentheses balance, and none closes before it opens');
ok(preg_match('/^\s*AND\s*\(/', $rebuilt) === 1, 'it is still one AND-ed group, as posts_where requires');

echo "\nwidening the finished SQL (the only hook that sees the whole condition)\n";
$sql = "SELECT SQL_CALC_FOUND_ROWS wp_posts.ID FROM wp_posts WHERE 1=1"
     . " AND (((wp_posts.post_title LIKE '%rk0118%')))"
     . " AND (wp_posts.post_status = 'publish')"
     . " ORDER BY wp_posts.post_date DESC LIMIT 0, 10";
$out = af_search_widen_sql($sql, array(12, 34), 'wp_posts');
ok(strpos($out, "post_title LIKE '%rk0118%'") !== false, 'the original text match survives');
ok(strpos($out, 'OR wp_posts.ID IN (12,34)') !== false, 'the matching ids are OR-ed beside it');
ok(strpos($out, 'ORDER BY wp_posts.post_date DESC LIMIT 0, 10') !== false,
   'ORDER BY and LIMIT are left outside the rewrite, where they belong');
ok(preg_match('/IN \(12,34\)\s*\)\s*ORDER BY/', $out) === 1, 'the group closes before ORDER BY, not after');
$d = 0; $min = 0;
foreach (str_split($out) as $ch) {
    if ($ch === '(') $d++;
    if ($ch === ')') { $d--; if ($d < $min) $min = $d; }
}
ok($d === 0 && $min === 0, 'parentheses balance');
ok(af_search_widen_sql($sql, array(), 'wp_posts') === $sql, 'no ids leaves the SQL untouched');
ok(af_search_widen_sql('SELECT 1', array(5), 'wp_posts') === 'SELECT 1',
   'a statement with no WHERE is left alone rather than mangled');
// A GROUP BY must bound the rewrite too, or it would be swallowed into the group.
$g = "SELECT a FROM wp_posts WHERE 1=1 AND (x=1) GROUP BY wp_posts.ID ORDER BY b LIMIT 5";
$og = af_search_widen_sql($g, array(7), 'wp_posts');
ok(preg_match('/IN \(7\)\s*\)\s*GROUP BY/', $og) === 1, 'GROUP BY also ends the WHERE section');
// Ids are forced to integers: a search term can never reach the SQL this way.
$inj = af_search_widen_sql($sql, array("1); DROP TABLE wp_posts;--"), 'wp_posts');
ok(strpos($inj, 'DROP TABLE') === false && strpos($inj, 'IN (1)') !== false,
   'ids are cast to integers, so nothing but a number reaches the statement');

echo "\nspellings and other names (M-03)\n";
// Real titles from the product export of 21 Sep, a few of each subject the
// report searched for, plus the traps: "Elegant" (one letter from "elefant"),
// "Vishvarupa" (which contains "shva") and "Kalki" (a real word, one letter
// from "Kali").
$vocab = af_search_build_vocabulary(array(
    'Radha Krishna Flute Canvas Wall Art', 'Krishna Raas Leela Pichwai Print', 'Lord Ganesha Gold Foil Canvas',
    'Ganesh Murti Canvas Wall Art', 'Golden Buddha Meditation Canvas', 'Lakshmi Blessing Canvas Print',
    'Lord Shiva Mahadev Canvas', 'Elegant Elephant Family Canvas', 'Vishvarupa Canvas Wall Art',
    'Sai Baba Portrait Canvas', 'Kalki Avatar Canvas', 'Kali Maa Canvas', 'Hindu Deities', 'Wildlife',
));
$ex = function ($q) use ($vocab) { return af_search_expand(af_search_terms($q), $vocab); };
ok(af_search_distance('buddah', 'buddha') === 1, 'two letters swapped are one mistake, not two');
ok(af_search_distance('krishan', 'krishna') === 1, '"krishan" is one swap from "krishna"');
ok(af_search_distance('kitten', 'sitting') === 3 && af_search_distance('abc', 'abc') === 0, 'the distance is the usual one otherwise');
ok(af_search_sound('laxmi') === af_search_sound('lakshmi'), 'laxmi and lakshmi sound the same');
ok(af_search_sound('elefant') === af_search_sound('elephant'), 'elefant and elephant sound the same');
foreach (array('krishan' => 'krishna', 'krisna' => 'krishna', 'buddah' => 'buddha', 'budha' => 'buddha',
               'ganpati' => 'ganesha', 'vinayaka' => 'ganesha', 'laxmi' => 'lakshmi', 'laksmi' => 'lakshmi',
               'radah krishna' => 'radha', 'elefant' => 'elephant', 'lord shva' => 'shiva',
               'ganeshji' => 'ganesh', 'radhakrishna' => 'radha krishna', 'saibaba' => 'sai baba') as $typed => $want) {
    ok(in_array($want, $ex($typed), true), "\"$typed\" also searches \"$want\"");
}
ok(!in_array('elegant', $ex('elefant'), true), '"elefant" is not taken for "elegant", the nearer spelling but not the nearer sound');
ok($ex('kalki') === array(), '"kalki" is a real word and is left alone, though "kali" is one letter away');
ok($ex('krishna') === array('krishna'), 'a word the catalogue uses gets no guesses, only its own group');
// "wildlif" begins a catalogue word, so the search already finds it.
foreach (array('dinosaur', 'blue', 'canvas', 'RK - 0118', 'rk0118', 'PA-1201', '', 'wildlif') as $typed) {
    $got = $ex($typed);
    ok($got === array(), '"' . $typed . '" adds nothing' . ($got ? ' (got ' . implode(', ', $got) . ')' : ''));
}
ok(af_search_expand(af_search_terms('krishan'), array()) === array(), 'an empty catalogue adds nothing');
$all = array();
foreach (array('krishan', 'ganpati', '<script>', "o'keeffe", 'radhakrishna', 'tirupathi') as $typed) $all = array_merge($all, $ex($typed));
ok((bool) $all && !preg_grep('/[^a-z ]/', $all), 'what is added is only ever lowercase catalogue words, so it is safe to print');
ok(count($ex('ganpati vinayaka laxmi krishan buddah elefant radhakrishna saibaba lord shva')) <= 8, 'never more than eight additions');

echo "\nthe note under the heading\n";
af_search_added(array('krishna'));
$h = af_search_added_html('krishan');
ok(strpos($h, 'Including results for &ldquo;krishna&rdquo;.') !== false, '"krishan" says it included "krishna"');
af_search_added(array('ganesha', 'ganesh'));
ok(af_search_added_html('ganesh') === '', 'nothing is listed that the visitor typed, or that contains it');
af_search_added(array('shiva', 'mahadev'));
$h = af_search_added_html('shiv');
ok(strpos($h, 'mahadev') !== false && strpos($h, 'shiva') === false, '"shiv" lists mahadev but not shiva, which it already finds');
af_search_added(array());
ok(af_search_added_html('dinosaur') === '', 'no additions, no note');

echo "\none spelling per name in the note (measured after the first deploy)\n";
// The live tags spell some pieces "Budha" and "Ganpati". Searching "buddha"
// then said "Including results for “budha”", which reads as the shop
// suggesting a typo, and "ganesha" said "…“ganpati”". Every spelling is still
// searched; the note names only the one the catalogue uses most.
$live = af_search_build_vocabulary(array(
    'Golden Buddha Meditation Canvas', 'Buddha Among Pink Lotuses', 'Buddha Bodhi Leaf', 'Budha',
    'Lord Ganesha Gold Foil Canvas', 'Ganesha Dawn Silhouette', 'Ganpati', 'Ganesh Murti Canvas',
));
$say = function ($q) use ($live) {
    af_search_expand(af_search_terms($q), $live, $h);
    af_search_added($h);
    return html_entity_decode(strip_tags(preg_replace('/<style.*$/s', '', af_search_added_html($q))), ENT_QUOTES, 'UTF-8');
};
ok(in_array('budha', af_search_expand(af_search_terms('buddha'), $live), true), '"buddha" still searches the pieces spelled "Budha"');
ok($say('buddha') === '', '"buddha" says nothing about "budha"');
ok($say('ganesha') === '', '"ganesha" says nothing about "ganpati"');
ok($say('buddah') === 'Including results for “buddha”.', '"buddah" names "buddha" only, not "budha" (got: ' . $say('buddah') . ')');
ok($say('ganpati') === 'Including results for “ganesha”.', '"ganpati" names "ganesha" only (got: ' . $say('ganpati') . ')');
af_search_expand(af_search_terms('krishan'), $vocab, $h);
ok($h === array('krishna'), 'a misspelling names its one correction');
af_search_expand(af_search_terms('radah krishna'), $vocab, $h);
ok(in_array('radha', $h, true), '"radah krishna" names "radha"');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
