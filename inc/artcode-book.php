<?php
/**
 * The Master Brochure's own numbering, and how to read a product's art code
 * against it.
 *
 * ── What changed in the book ────────────────────────────────────────────────
 *
 * The brochure used to label a page with its section prefix and a number
 * counted inside that section: RK 01, LI 32, HD 15. It now prints the section's
 * own number as well:
 *
 *     RK - 0101      section 01 (Radha Krishna), first page of it
 *     LI - 1932      section 19 (Living Room), thirty-second page of it
 *
 * That is the whole of the change as far as a reader is concerned. It matters
 * to us for two reasons. The obvious one: every product carries the code the
 * book prints, so every product's code is now out of date. The second is
 * easier to miss — the new numbering is CONTIGUOUS, and the old was not.
 *
 * ── The two gaps, and why they move everything after them ───────────────────
 *
 * Two labels existed in the old book's numbering but had no page: TP 04 and
 * HD 14. Both were confirmed absent by reading the pages either side of them
 * (page 128 is TP 03 and page 129 was TP 05; page 166 was HD 13 and page 167
 * was HD 15). The new numbering has no gaps, so from each gap onwards a page's
 * number is one lower than the label it used to carry:
 *
 *     TP 05  ->  TP - 0504        HD 15  ->  HD - 0814
 *     TP 16  ->  TP - 0515        HD 28  ->  HD - 0827
 *
 * So this is NOT a matter of pasting the section number onto the front of the
 * old one. Fifteen Tirupati pages and fourteen Hindu Deities pages shift, and
 * a product on TP 05 that were given TP - 0505 would be pointing at somebody
 * else's painting.
 *
 * Every other section maps straight across, and every one of the twenty-one
 * was checked page by page against the design rather than assumed: the first
 * and last page of each section was read out of Canva and matched to the page
 * numbers recorded in docs/brochure-sections.md when the book was first read.
 * The pages themselves did not move — all 358 of them are where they were, and
 * only their labels were rewritten.
 *
 * ── Two labels that no longer name anything ─────────────────────────────────
 *
 * TP 04 and HD 14 are refused rather than mapped, because they never named a
 * page. A product carrying one is left exactly as it is and reported, on the
 * same reasoning the audit uses throughout: a code that points nowhere should
 * be recorded as wrong and looked at, not quietly turned into a code that
 * points at the wrong picture.
 *
 * @see docs/brochure-sections.md   which section occupies which pages
 * @see tools/renumber-artcodes.php the pass that writes the new codes
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The book, section by section, in the order it prints them.
 *
 *   no        the section number the new codes carry
 *   name      what the contents page calls it
 *   pages     the first and last Canva page of the section, 1-indexed
 *   count     how many pages it has — and so the highest number a new code
 *             can carry inside it
 *   absent    labels the OLD numbering used that never had a page
 *
 * 'count' plus 'absent' is everything needed in both directions: the old
 * numbering ran from 1 to count + the number of absent labels below the top.
 */
function af_artcode_book() {
	static $book = null;
	if ( $book !== null ) { return $book; }

	$book = array(
		'RK' => array( 'no' =>  1, 'name' => 'Radha Krishna',   'pages' => array(   5,  95 ), 'count' => 91, 'absent' => array() ),
		'LG' => array( 'no' =>  2, 'name' => 'Lakshmi Ganesha', 'pages' => array(  96,  98 ), 'count' =>  3, 'absent' => array() ),
		'LS' => array( 'no' =>  3, 'name' => 'Lord Shiva',      'pages' => array(  99, 113 ), 'count' => 15, 'absent' => array() ),
		'SH' => array( 'no' =>  4, 'name' => 'Seven Horses',    'pages' => array( 114, 125 ), 'count' => 12, 'absent' => array() ),
		'TP' => array( 'no' =>  5, 'name' => 'Tirupati Balaji', 'pages' => array( 126, 140 ), 'count' => 15, 'absent' => array( 4 ) ),
		'MG' => array( 'no' =>  6, 'name' => 'Murugan',         'pages' => array( 141, 144 ), 'count' =>  4, 'absent' => array() ),
		'LR' => array( 'no' =>  7, 'name' => 'Lord Rama',       'pages' => array( 145, 153 ), 'count' =>  9, 'absent' => array() ),
		'HD' => array( 'no' =>  8, 'name' => 'Hindu Deities',   'pages' => array( 154, 180 ), 'count' => 27, 'absent' => array( 14 ) ),
		'LB' => array( 'no' =>  9, 'name' => 'Buddha',          'pages' => array( 181, 193 ), 'count' => 13, 'absent' => array() ),
		'SA' => array( 'no' => 10, 'name' => 'Sikh Art',        'pages' => array( 194, 196 ), 'count' =>  3, 'absent' => array() ),
		'SN' => array( 'no' => 11, 'name' => 'Swaminarayan',    'pages' => array( 197, 197 ), 'count' =>  1, 'absent' => array() ),
		'PA' => array( 'no' => 12, 'name' => 'Pichwai Art',     'pages' => array( 198, 198 ), 'count' =>  1, 'absent' => array() ),
		'IC' => array( 'no' => 13, 'name' => 'Indian Culture',  'pages' => array( 199, 202 ), 'count' =>  4, 'absent' => array() ),
		'LC' => array( 'no' => 14, 'name' => 'Landscapes',      'pages' => array( 203, 212 ), 'count' => 10, 'absent' => array() ),
		'SL' => array( 'no' => 15, 'name' => 'Still Life',      'pages' => array( 213, 235 ), 'count' => 23, 'absent' => array() ),
		'VA' => array( 'no' => 16, 'name' => 'Vaastu Art',      'pages' => array( 236, 239 ), 'count' =>  4, 'absent' => array() ),
		'WL' => array( 'no' => 17, 'name' => 'Wildlife',        'pages' => array( 240, 258 ), 'count' => 19, 'absent' => array() ),
		'KR' => array( 'no' => 18, 'name' => 'Kids Room',       'pages' => array( 259, 277 ), 'count' => 19, 'absent' => array() ),
		'LI' => array( 'no' => 19, 'name' => 'Living Room',     'pages' => array( 278, 321 ), 'count' => 44, 'absent' => array() ),
		'AA' => array( 'no' => 20, 'name' => 'Abstract Art',    'pages' => array( 322, 340 ), 'count' => 19, 'absent' => array() ),
		'TA' => array( 'no' => 21, 'name' => 'Travel Art',      'pages' => array( 341, 344 ), 'count' =>  4, 'absent' => array() ),
	);
	return $book;
}

/**
 * The section a prefix names, or null. Case-insensitive, because codes have
 * been typed by hand in several places.
 */
function af_artcode_section( $prefix ) {
	$book = af_artcode_book();
	$key  = strtoupper( trim( (string) $prefix ) );
	return isset( $book[ $key ] ) ? $book[ $key ] : null;
}

/**
 * Pull a code apart into prefix, digits and whatever trails it.
 *
 * The trailing part is not decoration: the Gold Foiled & UV importer copies a
 * product and gives the copy its source's code with '-GF' on the end, so
 * "HD 15-GF" is a real code in the catalogue and has to survive renumbering as
 * "HD - 0814-GF". Anything else trailing is kept untouched for the same reason
 * — this file's job is the number, not to decide what the rest means.
 *
 * Returns array( prefix, digits, suffix ) or null if it is not a code at all.
 */
function af_artcode_split( $code ) {
	$s = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
	if ( $s === '' ) { return null; }
	if ( ! preg_match( '/^([A-Za-z]{2})\s*-?\s*(\d{1,4})(.*)$/', $s, $m ) ) { return null; }
	return array(
		'prefix' => strtoupper( $m[1] ),
		'digits' => $m[2],
		'suffix' => trim( $m[3] ),
	);
}

/**
 * How the book writes page $n of the section with this prefix: "HD - 0814".
 */
function af_artcode_book_label( $prefix, $n, $suffix = '' ) {
	$sec = af_artcode_section( $prefix );
	if ( ! $sec ) { return ''; }
	return sprintf( '%s - %02d%02d', strtoupper( $prefix ), $sec['no'], (int) $n ) . $suffix;
}

/**
 * A product's art code as the book writes it today, or '' if the code names no
 * page of the book.
 *
 * Accepts what the catalogue actually holds — "RK 01", "rk 01", "RK-01",
 * "HD 15-GF" — and what this pass writes, "RK - 0101", which it returns
 * unchanged. That last part is what makes the renumbering safe to run on every
 * deploy: a code already in the book's shape maps to itself.
 *
 * '' is returned, and nothing guessed, for:
 *   - a prefix that is not a section of the book (a typo, or a section that was
 *     renamed out of it: Landscapes was LS and is LC, Living Room was LR and
 *     is LI, and products still carry the old ones)
 *   - a number past the end of its section (LR 32, when Lord Rama has 9 pages)
 *   - TP 04 and HD 14, which the old numbering used and the book never had
 */
function af_artcode_book_code( $code ) {
	$parts = af_artcode_split( $code );
	if ( ! $parts ) { return ''; }

	$sec = af_artcode_section( $parts['prefix'] );
	if ( ! $sec ) { return ''; }

	$digits = $parts['digits'];

	// Already the book's own shape: four digits whose first two are this
	// section's number and whose last two land inside it.
	if ( strlen( $digits ) === 4 ) {
		$s = (int) substr( $digits, 0, 2 );
		$n = (int) substr( $digits, 2, 2 );
		if ( $s === $sec['no'] && $n >= 1 && $n <= $sec['count'] ) {
			return af_artcode_book_label( $parts['prefix'], $n, $parts['suffix'] );
		}
		// Four digits that are not this section's — fall through and read them
		// as an old label, which will almost certainly be out of range and be
		// refused. Better that than silently accepting another section's number.
	}

	// An old label, counted inside the section with the gaps still in it.
	$label   = (int) $digits;
	$absent  = $sec['absent'];
	$old_max = $sec['count'] + count( $absent );

	if ( $label < 1 || $label > $old_max ) { return ''; }
	if ( in_array( $label, $absent, true ) ) { return ''; }

	$shift = 0;
	foreach ( $absent as $gap ) {
		if ( $gap < $label ) { $shift++; }
	}

	return af_artcode_book_label( $parts['prefix'], $label - $shift, $parts['suffix'] );
}

/**
 * Why a code could not be read against the book — one short phrase, for the
 * reports. Only meaningful when af_artcode_book_code() returned ''.
 */
function af_artcode_book_refusal( $code ) {
	$parts = af_artcode_split( $code );
	if ( ! $parts ) { return 'not shaped like an art code'; }

	$sec = af_artcode_section( $parts['prefix'] );
	if ( ! $sec ) { return $parts['prefix'] . ' is not a section of the book'; }

	$label   = (int) $parts['digits'];
	$absent  = $sec['absent'];
	$old_max = $sec['count'] + count( $absent );

	if ( in_array( $label, $absent, true ) ) {
		return $parts['prefix'] . ' ' . str_pad( $label, 2, '0', STR_PAD_LEFT ) . ' never had a page';
	}
	return $sec['name'] . ' has ' . $sec['count'] . ' pages, numbered up to '
	     . $old_max . ' in the old book';
}
