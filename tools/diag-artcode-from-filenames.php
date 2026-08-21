<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Can the right art code be recovered from the image files themselves?
 *
 * The source artwork was renamed with its art code before upload, so a
 * product's image filename may still carry the code that belongs to THAT
 * picture — which is exactly what the 167 products sitting under a shared
 * code need. This reads those filenames and reports what it finds. It writes
 * no codes and changes no product: a filename is evidence, not proof, and a
 * wrong code would travel onto invoices.
 *
 * Two sections:
 *   A) Calibration — of the products whose code is already its own, how often
 *      does the filename contain that same code? That number says how much
 *      the filenames can be trusted at all.
 *   B) The shared-code products — every code-shaped token found in each one's
 *      filenames, and whether it differs from the code it currently shares.
 *
 * A CSV is written in the import format with the candidate pre-filled, so the
 * list can be checked and corrected rather than typed from scratch. It is
 * deliberately NOT named artcode-import.csv — nothing here is applied until
 * a person renames it.
 *
 * Run: wp eval-file tools/diag-artcode-from-filenames.php --allow-root
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

echo "=== ART CODES FROM IMAGE FILENAMES ===\n";

/** Every image filename attached to a product: featured first, then gallery. */
function af_afn_files( $pid ) {
	$out   = array();
	$thumb = get_post_thumbnail_id( $pid );
	if ( $thumb ) {
		$f = get_post_meta( $thumb, '_wp_attached_file', true );
		if ( $f ) { $out[] = basename( (string) $f ); }
	}
	$gal = get_post_meta( $pid, '_product_image_gallery', true );
	if ( $gal ) {
		foreach ( array_slice( array_filter( explode( ',', $gal ) ), 0, 6 ) as $gid ) {
			$f = get_post_meta( (int) $gid, '_wp_attached_file', true );
			if ( $f ) { $out[] = basename( (string) $f ); }
		}
	}
	return $out;
}

/**
 * Code-shaped tokens in a filename: two or three letters then one to three
 * digits — RK 01, rk-01, RK01, TA_04. WordPress's own size suffixes
 * (800x985) and its de-duplication counters are not codes, so anything that
 * looks like a dimension is dropped first.
 */
function af_afn_candidates( $file ) {
	$name = preg_replace( '/\.[a-z0-9]+$/i', '', (string) $file );
	$name = preg_replace( '/\b\d{2,5}\s*[x×]\s*\d{2,5}\b/i', ' ', $name );   // 800x985
	$name = preg_replace( '/[^A-Za-z0-9]+/', ' ', $name );
	$out  = array();
	if ( preg_match_all( '/\b([A-Za-z]{2,3})\s?0?(\d{1,3})\b/', $name, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $hit ) {
			$letters = strtoupper( $hit[1] );
			if ( in_array( $letters, array( 'JPG', 'PNG', 'IMG', 'DSC', 'WEB', 'SCE' ), true ) ) { continue; }
			$out[] = $letters . ' ' . str_pad( $hit[2], 2, '0', STR_PAD_LEFT );
		}
	}
	return array_values( array_unique( $out ) );
}

function af_afn_norm( $s ) { return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) ); }

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$groups = array(); $spell = array(); $code_of = array();
foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? preg_replace( '/\s+/', ' ', trim( $code ) ) : '';
	if ( $code === '' ) { continue; }
	$key = strtoupper( $code );
	if ( ! isset( $spell[ $key ] ) ) { $spell[ $key ] = $code; }
	$groups[ $key ][] = $pid;
	$code_of[ $pid ]  = $code;
}
$shared = array_filter( $groups, function ( $p ) { return count( $p ) > 1; } );
$unique = array_filter( $groups, function ( $p ) { return count( $p ) === 1; } );

// ── A) How much can a filename be trusted? ──────────────────────────────────
$hit = 0; $miss = 0; $nofile = 0; $samples = array();
foreach ( $unique as $key => $pids ) {
	$pid  = $pids[0];
	$want = af_afn_norm( $code_of[ $pid ] );
	$files = af_afn_files( $pid );
	if ( ! $files ) { $nofile++; continue; }
	$found = false;
	foreach ( $files as $f ) {
		if ( $want !== '' && strpos( af_afn_norm( $f ), $want ) !== false ) { $found = true; break; }
	}
	if ( $found ) { $hit++; } else { $miss++; }
	if ( count( $samples ) < 6 ) {
		$samples[] = sprintf( '  [%s] #%d  code %-7s  %s', $found ? 'MATCH' : ' no  ', $pid,
			$code_of[ $pid ], mb_substr( implode( ' | ', $files ), 0, 90 ) );
	}
}
$checked = $hit + $miss;
echo "\n--- A) calibration: products whose code is already their own ---\n";
echo "checked: {$checked}  (no image: {$nofile})\n";
echo "  filename contains that product's own code: {$hit}\n";
echo "  it does not:                               {$miss}\n";
if ( $checked > 0 ) {
	$pct = round( $hit * 100 / $checked );
	echo "  --> filenames carry the code {$pct}% of the time.\n";
	echo ( $pct >= 60
		? "      High enough to be worth reading as evidence, still to be checked.\n"
		: "      TOO LOW to trust. Treat section B as a hint only.\n" );
}
if ( $samples ) { echo implode( "\n", $samples ) . "\n"; }

// ── B) What do the shared-code products' own filenames say? ─────────────────
echo "\n--- B) the products under a shared code ---\n";
$csv        = array( 'product_id,new_art_code,current_shared_code,candidate_from_filename,title' );
$suggested  = 0; $nothing = 0; $agrees = 0; $total = 0;

foreach ( $shared as $key => $pids ) {
	$base = $spell[ $key ];
	foreach ( $pids as $pid ) {
		$total++;
		$files = af_afn_files( $pid );
		$cands = array();
		foreach ( $files as $f ) {
			foreach ( af_afn_candidates( $f ) as $c ) { $cands[ $c ] = true; }
		}
		$cands = array_keys( $cands );
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );

		// A candidate is only interesting when it is NOT the code already shared —
		// one that agrees tells us nothing new about which product owns it.
		$different = array();
		foreach ( $cands as $c ) {
			if ( af_afn_norm( $c ) !== af_afn_norm( $base ) ) { $different[] = $c; }
		}

		$pick = '';
		if ( count( $different ) === 1 ) { $pick = $different[0]; $suggested++; }
		elseif ( ! $cands ) { $nothing++; }
		elseif ( ! $different ) { $agrees++; }

		echo sprintf( "  %-9s #%-7d %-22s %s\n", $base, $pid,
			( $cands ? implode( ',', array_slice( $cands, 0, 3 ) ) : '—' ),
			mb_substr( $title, 0, 52 ) );

		$csv[] = $pid . ',"' . str_replace( '"', '""', $pick ) . '","'
		       . str_replace( '"', '""', $base ) . '","'
		       . str_replace( '"', '""', implode( ' ', $cands ) ) . '","'
		       . str_replace( '"', '""', $title ) . '"';
	}
}

echo "\nof {$total} products under a shared code:\n";
echo "  one clear candidate from the filename: {$suggested}\n";
echo "  filename only repeats the shared code: {$agrees}\n";
echo "  no code-shaped text in the filename:   {$nothing}\n";
echo "  (the rest had several candidates and need a person to choose)\n";

$up  = wp_get_upload_dir();
$dir = $up['basedir'] . '/taf-reports';
if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
if ( file_put_contents( $dir . '/artcode-candidates.csv', implode( "\n", $csv ) ) !== false ) {
	echo "\ncandidates: " . $up['baseurl'] . "/taf-reports/artcode-candidates.csv\n";
	echo "  Nothing here has been applied. Check each row against the picture, fix\n";
	echo "  what is wrong, then save it as artcode-import.csv to apply it.\n";
}
