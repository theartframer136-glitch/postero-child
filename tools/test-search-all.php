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

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
