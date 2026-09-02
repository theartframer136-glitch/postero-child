<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Write the corrected art codes that have actually been checked against the book.
 *
 * Every row in tools/artcode-corrections.csv was decided by putting the product's
 * own picture next to its page in TheARTFramer Master Brochure and looking at
 * them — not by matching the page's caption. That distinction matters here: the
 * captions have been wrong more than once (the page labelled TA 04 describes a
 * Ganga Aarti and shows a Tanjore throne painting), and three earlier proposals
 * made from wording alone turned out to name the wrong product. So the file
 * carries only what the images agree on, and each row records why.
 *
 * The codes themselves changed under us, which is what makes most of these
 * corrections possible: the Landscapes section used to be LS, colliding with
 * Lord Shiva, and the Living Room section used to be LR, colliding with Lord
 * Rama. Both have been renamed in the book — Landscapes is now LC, Living Room
 * LI — so a landscape sitting on an LS code is no longer half of an unavoidable
 * clash. It simply has the wrong code, and there is a right one to give it.
 *
 * A row whose new_art_code is NONE clears the product's art code instead of
 * setting one. That is the owner's rule for a picture that appears nowhere in
 * the book: better no code at all than a code belonging to a different artwork,
 * because a wrong code travels onto the SKU and onto invoices.
 *
 * DRY RUN BY DEFAULT. Nothing is written unless AF_APPLY=1 is set, so the first
 * run on any deploy prints exactly what it would do and changes nothing.
 *
 * Every write is reversible: the previous code is kept in _af_code_before_fix
 * the first time a product is touched, and never overwritten after that.
 *
 * Run: wp eval-file tools/apply-artcode-corrections.php --allow-root
 * Env: AF_APPLY=1  — actually write (otherwise it only reports)
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$APPLY = getenv( 'AF_APPLY' ) === '1';

/**
 * Every code in this file — and in the catalogue when these rows were written —
 * is in the numbering the book used to print: "LC 09". The book now prints
 * "LC - 1409", and closed two gaps in the process, so both sides are read
 * through inc/artcode-book.php before anything is compared or written.
 *
 * The rows themselves are deliberately left in the old numbering. Each one
 * records a judgement made by putting a product's picture beside a page, and
 * rewriting ninety-five of those by hand into a numbering that may change again
 * would put that reasoning at risk for no gain. Translating on the way out
 * costs nothing and keeps the audit's own record intact.
 *
 * It also stops this pass and tools/renumber-artcodes.php undoing each other
 * once per deploy, which is the failure documented at the top of
 * tools/apply-artcode-batch.php.
 *
 * A code the book does not contain — NONE, or a row still pointing at a page
 * that was renamed away — passes through untouched.
 */
function af_corr_book_code( $code ) {
	$code = preg_replace( '/\s+/', ' ', trim( (string) $code ) );
	if ( $code === '' || ! function_exists( 'af_artcode_book_code' ) ) { return $code; }
	$book = af_artcode_book_code( $code );
	return $book !== '' ? $book : $code;
}

echo "=== ART CODE CORRECTIONS ===\n";
echo $APPLY ? "mode: APPLYING\n" : "mode: dry run — nothing will be written (set AF_APPLY=1 to apply)\n";

$path = __DIR__ . '/artcode-corrections.csv';
if ( ! file_exists( $path ) ) { echo "no corrections file at {$path}\n=== DONE ===\n"; return; }

$fh = fopen( $path, 'r' );
if ( ! $fh ) { echo "could not open {$path}\n=== DONE ===\n"; return; }

$head = fgetcsv( $fh );
$col  = array();
foreach ( (array) $head as $i => $name ) { $col[ strtolower( trim( (string) $name ) ) ] = $i; }
if ( ! isset( $col['product_id'] ) || ! isset( $col['new_art_code'] ) ) {
	echo "the file needs product_id and new_art_code columns\n=== DONE ===\n";
	fclose( $fh ); return;
}

$rows = array();
while ( ( $r = fgetcsv( $fh ) ) !== false ) {
	$pid = isset( $r[ $col['product_id'] ] ) ? (int) $r[ $col['product_id'] ] : 0;
	$new = isset( $r[ $col['new_art_code'] ] ) ? preg_replace( '/\s+/', ' ', trim( (string) $r[ $col['new_art_code'] ] ) ) : '';
	if ( $pid && $new !== '' ) {
		$rows[] = array( 'pid' => $pid, 'new' => $new,
			'why' => isset( $col['why'], $r[ $col['why'] ] ) ? trim( (string) $r[ $col['why'] ] ) : '' );
	}
}
fclose( $fh );
echo "rows in the file: " . count( $rows ) . "\n";

// A code that is about to be written must not already belong to a DIFFERENT
// product, or the correction just moves the clash somewhere else. Checked
// against the live catalogue before anything is written, including the other
// rows of this same file.
$owner = array();
foreach ( get_posts( array(
	'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1, 'fields' => 'ids',
) ) as $pid ) {
	$c = get_post_meta( $pid, '_taf_art_code', true );
	if ( is_string( $c ) && trim( $c ) !== '' ) {
		$owner[ strtoupper( af_corr_book_code( $c ) ) ][] = (int) $pid;
	}
}

$changed = 0; $same = 0; $missing = 0; $clash = 0; $cleared = 0; $shared = 0;

echo "\n--- what happens to each row ---\n";
foreach ( $rows as $row ) {
	$pid = $row['pid'];
	$new = $row['new'];

	// A row may deliberately give two products the same code, but only when it
	// says so. Two listings of the SAME picture must carry the same art code;
	// the SKU letter keeps their SKUs apart. Everything else is a clash and is
	// still refused, because that is nearly always a mistake.
	$share = false;
	if ( stripos( $new, 'SHARE:' ) === 0 ) {
		$share = true;
		$new   = trim( substr( $new, 6 ) );
	}
	$new = af_corr_book_code( $new );
	$key = strtoupper( $new );

	$post = get_post( $pid );
	if ( ! $post || $post->post_type !== 'product' ) {
		printf( "  #%-7d SKIP — no such product\n", $pid );
		$missing++;
		continue;
	}

	$title = mb_substr( html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) ), 0, 44 );
	$now   = (string) get_post_meta( $pid, '_taf_art_code', true );

	if ( $key !== 'NONE' && strcasecmp( af_corr_book_code( $now ), $new ) === 0 ) {
		printf( "  #%-7d already %-8s %s\n", $pid, $new, $title );
		$same++;
		continue;
	}

	// NONE means: this picture is in no book page, so it should carry no code.
	if ( $key === 'NONE' ) {
		if ( $now === '' ) {
			printf( "  #%-7d already has no code   %s\n", $pid, $title );
			$same++;
			continue;
		}
		printf( "  #%-7d %-8s -> (cleared)  %s\n", $pid, $now, $title );
		if ( $row['why'] !== '' ) { echo "            because: " . $row['why'] . "\n"; }
		if ( $APPLY ) {
			if ( get_post_meta( $pid, '_af_code_before_fix', true ) === '' ) {
				update_post_meta( $pid, '_af_code_before_fix', $now );
			}
			delete_post_meta( $pid, '_taf_art_code' );
			$oldkey = strtoupper( af_corr_book_code( $now ) );
			if ( isset( $owner[ $oldkey ] ) ) {
				$owner[ $oldkey ] = array_values( array_diff( $owner[ $oldkey ], array( $pid ) ) );
			}
		}
		$cleared++;
		continue;
	}

	$held = isset( $owner[ $key ] ) ? array_diff( $owner[ $key ], array( $pid ) ) : array();
	if ( $held && ! $share ) {
		printf( "  #%-7d REFUSED — %s already belongs to #%s\n", $pid, $new, implode( ', #', $held ) );
		$clash++;
		continue;
	}
	if ( $held ) {
		printf( "  #%-7d SHARING %-8s with #%s — the same artwork, listed twice\n",
			$pid, $new, implode( ', #', $held ) );
		$shared++;
	}

	printf( "  #%-7d %-8s -> %-8s %s\n", $pid, ( $now !== '' ? $now : '(none)' ), $new, $title );
	if ( $row['why'] !== '' ) { echo "            because: " . $row['why'] . "\n"; }

	if ( $APPLY ) {
		if ( get_post_meta( $pid, '_af_code_before_fix', true ) === '' ) {
			update_post_meta( $pid, '_af_code_before_fix', $now );
		}
		update_post_meta( $pid, '_taf_art_code', $new );
		// keep the in-memory map honest for the rows still to come
		$owner[ $key ][] = $pid;
		$oldkey = strtoupper( af_corr_book_code( $now ) );
		if ( isset( $owner[ $oldkey ] ) ) {
			$owner[ $oldkey ] = array_values( array_diff( $owner[ $oldkey ], array( $pid ) ) );
		}
	}
	$changed++;
}

echo "\n";
printf( "to change: %d  |  codes cleared: %d  |  already correct: %d  |  refused as a clash: %d  |  missing: %d\n",
	$changed, $cleared, $same, $clash, $missing );
if ( $shared ) { printf( "of those, %d deliberately share a code with another listing of the same picture\n", $shared ); }

if ( ! $APPLY ) {
	echo "\nNothing was written. Set AF_APPLY=1 on this step to apply.\n";
} else {
	echo "\nWritten. The SKU pass picks these up on its next run, and the previous\n";
	echo "code of every product touched is kept in _af_code_before_fix.\n";
}
echo "=== DONE ===\n";
