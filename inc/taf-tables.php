<?php
/**
 * Give every policy-table cell the name of its column.
 *
 * The four-column tables on the policy pages are unreadable on a phone: at
 * 420px the columns squeeze until the headings run one letter down the page.
 * The fix in custom.css turns each row into a card, and a card only works if
 * every value says what it is — "✓ Free" on its own means nothing.
 *
 * The labels come from the table's own <th> row rather than being written into
 * the page content, so this holds for every .taf-table on the site, including
 * ones added later, and no stored page has to be edited to get it.
 *
 * Server-side: the markup ships with the label already on it, so the cards are
 * correct in the first paint rather than after a script runs. The pages are
 * cached HTML, so this costs one pass over the buffer per cache generation.
 */
if (!defined('ABSPATH')) exit;

add_filter('the_content', function ($content) {
    if (is_admin() || is_feed()) return $content;
    if (strpos($content, 'taf-table') === false) return $content;
    if (strpos($content, 'data-taf-label') !== false) return $content;   // already done

    return preg_replace_callback(
        '#<table\b[^>]*class="[^"]*\btaf-table\b[^"]*"[^>]*>.*?</table>#is',
        function ($m) {
            $table = $m[0];

            // The column names, in order, from the header row.
            if (!preg_match('#<thead\b.*?</thead>#is', $table, $head)) return $table;
            if (!preg_match_all('#<th\b[^>]*>(.*?)</th>#is', $head[0], $th)) return $table;
            $labels = array_map(function ($t) {
                return trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($t)));
            }, $th[1]);
            if (!$labels) return $table;

            // Walk the body rows, numbering cells across each row.
            return preg_replace_callback('#<tbody\b.*?</tbody>#is', function ($b) use ($labels) {
                return preg_replace_callback('#<tr\b[^>]*>.*?</tr>#is', function ($r) use ($labels) {
                    $i = 0;
                    return preg_replace_callback('#<td\b([^>]*)>#i', function ($c) use ($labels, &$i) {
                        $attrs = $c[1];
                        $label = $labels[$i] ?? '';
                        $i++;
                        if ($label === '' || stripos($attrs, 'data-taf-label') !== false) return $c[0];
                        return '<td' . $attrs . ' data-taf-label="' . esc_attr($label) . '">';
                    }, $r[0]);
                }, $b[0]);
            }, $table);
        },
        $content
    );
}, 20);
