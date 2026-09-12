<?php
/**
 * The Master Brochure's own numbering, and how to read a product's art code
 * against it.
 *
 * ── The book has moved again, and this file no longer describes it ──────────
 *
 * Read out of Canva on 2026-09-12, the brochure is NOT the book the rest of
 * this header describes. Three things changed:
 *
 *   it grew          340 product pages -> 373. Ten sections gained pages:
 *                    LI +7, RK +6, WL +4, LS/SH/HD/KR +3, SA +2, LR/IC +1.
 *   it prints six    LB - 090001, not LB - 0901. Section number, then the page
 *                    number in four digits.
 *   it prints size   the page label now ends in the aspect: LB-090001-3050.
 *
 * The section map below is now today's book, measured page by page: product
 * page N sits at Canva page 4 + N, confirmed against HD-080030 at 196,
 * LB-090001 at 197, LB-090013 at 209, SA-100001 at 210, TA-210004 at 377, and
 * page 378 being the back matter. Every section runs 1..N with no gaps.
 *
 * ── The catalogue moves onto six digits; it does not move pages ─────────────
 *
 * Asked for on 2026-09-12: every product to carry the code the book prints
 * today. So af_artcode_book_label() now writes six digits and the renumbering
 * pass, which runs with AF_APPLY=1 on every deploy, carries the catalogue over
 * on the next push.
 *
 * WHAT THAT IS AND IS NOT. "LB - 0901" and "LB - 090001" are the same section
 * and the same page number; only the padding widens. So this is a reformat. It
 * is emphatically NOT a verification, and it must not be mistaken for one —
 * asked in the same conversation whether the book's 33 new pages went on the
 * end of each section or were slotted in among the old ones, the owner said it
 * was mixed and he is not sure. If a page was inserted, the product on it
 * already named the wrong painting under four digits and names exactly the
 * same wrong painting under six. Widening the number neither causes that nor
 * cures it; only putting the pictures side by side can, and that audit has not
 * been done for this round.
 *
 * SO THE MAP STILL CARRIES TWO COUNTS, and that is the part to leave alone.
 * 'count' is the book as it is today — 373 pages — and is what reporting and
 * matching read. 'legacy' is frozen at the numbering the catalogue was last
 * written against, and it alone bounds what the translation will accept. The
 * consequence is the one that matters: the reformat moves every product's code
 * to six digits WITHOUT letting any product acquire a page it did not already
 * name. A product on no code stays on no code; a product whose code the book
 * refuses stays refused; nothing lands on one of the 33 new pages by
 * arithmetic. Raising 'legacy' to 'count' would break all of that silently,
 * and the deploy would carry it to live SKUs, and to invoices, on the next
 * push.
 *
 * ── What the rest of this header describes: the PREVIOUS renumbering ────────
 *
 * The brochure used to label a page with its section prefix and a number
 * counted inside that section: RK 01, LI 32, HD 15. It then printed the
 * section's own number as well:
 *
 *     RK - 0101      section 01 (Radha Krishna), first page of it
 *     LI - 1932      section 19 (Living Room), thirty-second page of it
 *
 * That is the numbering the catalogue holds today, and the translation below
 * still reads it. It mattered for two reasons. The obvious one: every product
 * carries the code the book prints. The second is easier to miss — that
 * numbering was CONTIGUOUS, and the one before it was not.
 *
 * ── The two gaps, and why they move everything after them ───────────────────
 *
 * Two labels existed in the old book's numbering but had no page: TP 04 and
 * HD 14. Both were confirmed absent by reading the pages either side of them
 * (page 128 is TP 03 and page 129 was TP 05; page 166 was HD 13 and page 167
 * was HD 15). The new numbering has no gaps, so from each gap onwards a page's
 * number is one lower than the label it used to carry:
 *
 *     TP 05  ->  TP - 050004      HD 15  ->  HD - 080014
 *     TP 16  ->  TP - 050015      HD 28  ->  HD - 080027
 *
 * So reading a TWO-digit label is not a matter of pasting the section number
 * onto the front of it. Fifteen Tirupati pages and fourteen Hindu Deities
 * pages shift, and a product on TP 05 given TP - 050005 would be pointing at
 * somebody else's painting. (Widening a FOUR-digit code to six is a different
 * thing entirely and shifts nothing — see the top of this header.)
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
 *   no        the section number the codes carry
 *   name      what the contents page calls it
 *   pages     the first and last Canva page of the section, 1-indexed
 *   count     how many pages the section has IN THE BOOK TODAY
 *   legacy    how many it had when the shop's codes were last written
 *   absent    labels the OLD numbering used that never had a page
 *
 * Why two counts. 'count' is the truth about the book and is what reporting
 * and matching must use. 'legacy' plus 'absent' is what the translation below
 * reads, and it is deliberately frozen at the numbering the catalogue was last
 * written against — see the header for why moving it would rewrite live SKUs
 * on unproven arithmetic.
 */
function af_artcode_book() {
	static $book = null;
	if ( $book !== null ) { return $book; }

	$book = array(
		'RK' => array( 'no' =>  1, 'name' => 'Radha Krishna',   'pages' => array(   5, 101 ), 'count' => 97, 'legacy' => 91, 'absent' => array() ),
		'LG' => array( 'no' =>  2, 'name' => 'Lakshmi Ganesha', 'pages' => array( 102, 104 ), 'count' =>  3, 'legacy' =>  3, 'absent' => array() ),
		'LS' => array( 'no' =>  3, 'name' => 'Lord Shiva',      'pages' => array( 105, 122 ), 'count' => 18, 'legacy' => 15, 'absent' => array() ),
		'SH' => array( 'no' =>  4, 'name' => 'Seven Horses',    'pages' => array( 123, 137 ), 'count' => 15, 'legacy' => 12, 'absent' => array() ),
		'TP' => array( 'no' =>  5, 'name' => 'Tirupati Balaji', 'pages' => array( 138, 152 ), 'count' => 15, 'legacy' => 15, 'absent' => array( 4 ) ),
		'MG' => array( 'no' =>  6, 'name' => 'Murugan',         'pages' => array( 153, 156 ), 'count' =>  4, 'legacy' =>  4, 'absent' => array() ),
		'LR' => array( 'no' =>  7, 'name' => 'Lord Rama',       'pages' => array( 157, 166 ), 'count' => 10, 'legacy' =>  9, 'absent' => array() ),
		'HD' => array( 'no' =>  8, 'name' => 'Hindu Deities',   'pages' => array( 167, 196 ), 'count' => 30, 'legacy' => 27, 'absent' => array( 14 ) ),
		'LB' => array( 'no' =>  9, 'name' => 'Buddha',          'pages' => array( 197, 209 ), 'count' => 13, 'legacy' => 13, 'absent' => array() ),
		'SA' => array( 'no' => 10, 'name' => 'Sikh Art',        'pages' => array( 210, 214 ), 'count' =>  5, 'legacy' =>  3, 'absent' => array() ),
		'SN' => array( 'no' => 11, 'name' => 'Swaminarayan',    'pages' => array( 215, 215 ), 'count' =>  1, 'legacy' =>  1, 'absent' => array() ),
		'PA' => array( 'no' => 12, 'name' => 'Pichwai Art',     'pages' => array( 216, 216 ), 'count' =>  1, 'legacy' =>  1, 'absent' => array() ),
		'IC' => array( 'no' => 13, 'name' => 'Indian Culture',  'pages' => array( 217, 221 ), 'count' =>  5, 'legacy' =>  4, 'absent' => array() ),
		'LC' => array( 'no' => 14, 'name' => 'Landscapes',      'pages' => array( 222, 231 ), 'count' => 10, 'legacy' => 10, 'absent' => array() ),
		'SL' => array( 'no' => 15, 'name' => 'Still Life',      'pages' => array( 232, 254 ), 'count' => 23, 'legacy' => 23, 'absent' => array() ),
		'VA' => array( 'no' => 16, 'name' => 'Vaastu Art',      'pages' => array( 255, 258 ), 'count' =>  4, 'legacy' =>  4, 'absent' => array() ),
		'WL' => array( 'no' => 17, 'name' => 'Wildlife',        'pages' => array( 259, 281 ), 'count' => 23, 'legacy' => 19, 'absent' => array() ),
		'KR' => array( 'no' => 18, 'name' => 'Kids Room',       'pages' => array( 282, 303 ), 'count' => 22, 'legacy' => 19, 'absent' => array() ),
		'LI' => array( 'no' => 19, 'name' => 'Living Room',     'pages' => array( 304, 354 ), 'count' => 51, 'legacy' => 44, 'absent' => array() ),
		'AA' => array( 'no' => 20, 'name' => 'Abstract Art',    'pages' => array( 355, 373 ), 'count' => 19, 'legacy' => 19, 'absent' => array() ),
		'TA' => array( 'no' => 21, 'name' => 'Travel Art',      'pages' => array( 374, 377 ), 'count' =>  4, 'legacy' =>  4, 'absent' => array() ),
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
	// Up to six digits. The book prints six today (LB - 090001); the catalogue
	// still holds four from the previous round (LB - 0901) and, here and there,
	// the two the book printed before that (LB 01). All three have to parse, and
	// the LENGTH is what tells them apart — so this must never be narrowed back
	// to four, or a six-digit code would parse as four with "01" left dangling
	// as a suffix and quietly become a different page.
	if ( ! preg_match( '/^([A-Za-z]{2})\s*-?\s*(\d{1,6})(.*)$/', $s, $m ) ) { return null; }
	return array(
		'prefix' => strtoupper( $m[1] ),
		'digits' => $m[2],
		'suffix' => trim( $m[3] ),
	);
}

/**
 * How the book writes page $n of the section with this prefix: "HD - 080014".
 *
 * Six digits — two for the section, four for the page — which is what the
 * brochure prints today. It printed four until this round ("HD - 0814") and the
 * catalogue is being moved onto six; the page number inside is the same number
 * in both, so the move is a reformat and not a renumbering.
 */
function af_artcode_book_label( $prefix, $n, $suffix = '' ) {
	$sec = af_artcode_section( $prefix );
	if ( ! $sec ) { return ''; }
	return sprintf( '%s - %02d%04d', strtoupper( $prefix ), $sec['no'], (int) $n ) . $suffix;
}

/**
 * How the book prints page $n of this section: "LB - 090001".
 *
 * Differs from af_artcode_book_label() in one way that matters: this accepts
 * any page the book HAS ('count'), including the 33 it gained this round,
 * whereas the writing path stops at 'legacy'. So this is the function for
 * reading and reporting on the book itself — listing its pages, checking
 * coverage — and the other is the one that may touch a product.
 *
 * On the page itself the aspect is appended too ("LB-090001-3050"), which is a
 * property of the page and not of the code, so it is not produced here.
 */
function af_artcode_page_label( $prefix, $n ) {
	$sec = af_artcode_section( $prefix );
	if ( ! $sec ) { return ''; }
	$n = (int) $n;
	if ( $n < 1 || $n > $sec['count'] ) { return ''; }
	return sprintf( '%s - %02d%04d', strtoupper( $prefix ), $sec['no'], $n );
}

/**
 * How many pages the book has today, in total or in one section. The number
 * matching works against — 'legacy' is only for reading the codes the shop
 * currently holds.
 */
function af_artcode_book_pages( $prefix = '' ) {
	$book = af_artcode_book();
	if ( $prefix !== '' ) {
		$sec = af_artcode_section( $prefix );
		return $sec ? (int) $sec['count'] : 0;
	}
	$total = 0;
	foreach ( $book as $sec ) { $total += (int) $sec['count']; }
	return $total;
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
	// Six digits: the shape the book prints today, and what this returns. Maps
	// to itself, which is what lets the renumbering pass run on every deploy
	// without doing anything after the first time.
	if ( strlen( $digits ) === 6 ) {
		$s = (int) substr( $digits, 0, 2 );
		$n = (int) substr( $digits, 2, 4 );
		if ( $s === $sec['no'] && $n >= 1 && $n <= $sec['legacy'] ) {
			return af_artcode_book_label( $parts['prefix'], $n, $parts['suffix'] );
		}
	}

	// Four digits: the shape the catalogue is being moved off. The section and
	// the page number are read out and written back unchanged — only the
	// padding widens, so "LB - 0901" and "LB - 090001" name the same page.
	if ( strlen( $digits ) === 4 ) {
		$s = (int) substr( $digits, 0, 2 );
		$n = (int) substr( $digits, 2, 2 );
		// 'legacy', not 'count': see the header. The book has pages this range
		// does not cover, and admitting them here would start writing codes
		// nobody has checked a picture against.
		if ( $s === $sec['no'] && $n >= 1 && $n <= $sec['legacy'] ) {
			return af_artcode_book_label( $parts['prefix'], $n, $parts['suffix'] );
		}
		// Four digits that are not this section's — fall through and read them
		// as an old label, which will almost certainly be out of range and be
		// refused. Better that than silently accepting another section's number.
	}

	// An old label, counted inside the section with the gaps still in it.
	$label   = (int) $digits;
	$absent  = $sec['absent'];
	$old_max = $sec['legacy'] + count( $absent );

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
	$old_max = $sec['legacy'] + count( $absent );

	if ( in_array( $label, $absent, true ) ) {
		return $parts['prefix'] . ' ' . str_pad( $label, 2, '0', STR_PAD_LEFT ) . ' never had a page';
	}
	return $sec['name'] . ' has ' . $sec['count'] . ' pages in the book today, '
	     . 'and the numbering the shop was last written against went up to '
	     . $old_max;
}
