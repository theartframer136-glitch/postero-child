<?php
/* AF-WEB-GUARD */ if (PHP_SAPI !== 'cli' && !(defined('WP_CLI') && WP_CLI)) { http_response_code(403); exit('Forbidden'); }
/**
 * Put the shared-code products' own pictures where they can actually be looked at.
 *
 * The collection book gives every artwork a page with its code printed on it, so
 * the 167 products sitting under a shared code can be corrected by comparing
 * picture to picture. The obstacle is plumbing, not method: the book is
 * readable from here, the shop's images are not — they live behind the site,
 * which this side cannot reach.
 *
 * So the pictures come out through the deploy log instead. Each shared code
 * gets one contact sheet — every product under that code, side by side, each
 * tile stamped with its product id — encoded as base64 JPEG and printed in
 * fixed-width chunks. Decoding it is one command; after that the artwork is on
 * screen next to the book page and the match is a matter of looking.
 *
 * One sheet per code, deliberately: the products that must be told apart are
 * exactly the ones on the same sheet.
 *
 * Read-only. It writes no file on the server and changes no product.
 *
 * Run: wp eval-file tools/diag-product-contact-sheets.php --allow-root
 * Env: AF_SHEET_TILE   (default 220) — tile size in px
 *      AF_SHEET_COLS   (default 6)   — tiles per row
 *      AF_SHEET_Q      (default 55)  — JPEG quality
 *      AF_SHEET_GROUPS (default 0)   — stop after N groups, 0 = all
 *      AF_SHEET_ONLY   (default '')  — only this code, e.g. "TA 04"
 *      AF_SHEET_CODES  (default '')  — only these codes, comma separated
 *      AF_SHEET_SCOPE  (default shared) — shared | unique | nocode | all
 *      AF_SHEET_SKIP   (default 0)   — skip this many groups (batching)
 *      AF_SHEET_TAKE   (default 0)   — draw at most this many groups, 0 = no cap
 *
 * AF_SHEET_CODES exists because the whole set does not survive the trip. The
 * log is fetched through an API that truncates a very long one, and 167 tiles
 * of base64 is long enough to lose the first two thirds — the sheets that came
 * back were the alphabetical tail. Asking for the codes still undecided keeps
 * the payload small enough to arrive intact, and avoids redrawing the ones
 * already read.
 *
 * SCOPE exists because a code being unique is not the same as it being right.
 * 215 products carry a code no other product has, so nothing ever flagged them —
 * but an unflagged code can still sit on the wrong picture, and until those
 * pictures have been looked at nobody can say the catalogue is correct. scope
 * unique draws them; scope nocode draws the products carrying no code at all.
 * SKIP and TAKE exist for the same reason AF_SHEET_CODES does: the whole set
 * does not survive one log, so it comes out a batch at a time.
 *
 * NOT WIRED INTO THE DEPLOY BY DEFAULT. All 47 sheets have been read, and a
 * step that prints a megabyte of base64 on every deploy buries every check that
 * runs before it — the log is truncated from the front, so the verifiers simply
 * vanish. It is kept here to be run on demand instead:
 *
 *   wp eval-file wp-content/themes/postero-child/tools/diag-product-contact-sheets.php --allow-root
 *
 * or, for one group, AF_SHEET_CODES='LS 04' in front of that. Add it back as a
 * deploy step only for as long as pictures are actually needed, and always with
 * AF_SHEET_CODES naming a short list.
 */
if ( ! defined( 'ABSPATH' ) ) { fwrite( STDERR, "Run via wp eval-file\n" ); exit(1); }

$TILE   = max( 120, (int) ( getenv( 'AF_SHEET_TILE' ) ?: 220 ) );
$COLS   = max( 1, (int) ( getenv( 'AF_SHEET_COLS' ) ?: 6 ) );
$QUAL   = min( 95, max( 30, (int) ( getenv( 'AF_SHEET_Q' ) ?: 55 ) ) );
$MAXG   = (int) ( getenv( 'AF_SHEET_GROUPS' ) ?: 0 );
$ONLY   = strtoupper( trim( (string) getenv( 'AF_SHEET_ONLY' ) ) );

$CODES = array();
foreach ( explode( ',', (string) getenv( 'AF_SHEET_CODES' ) ) as $c ) {
	$c = strtoupper( preg_replace( '/\s+/', ' ', trim( $c ) ) );
	if ( $c !== '' ) { $CODES[ $c ] = true; }
}

echo "=== CONTACT SHEETS: THE PRODUCTS UNDER A SHARED ART CODE ===\n";

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	echo "GD is not available on this server, so no sheet can be drawn.\n=== DONE ===\n";
	return;
}

/**
 * A file on disk for this attachment, at a size worth looking at but not the
 * full master — the masters are 1600px and loading 167 of them would cost far
 * more memory than the job needs.
 */
function af_cs_source_path( $att_id ) {
	$full = get_attached_file( $att_id );
	if ( ! $full || ! file_exists( $full ) ) { return ''; }
	$meta = wp_get_attachment_metadata( $att_id );
	if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) { return $full; }

	$dir  = dirname( $full );
	$best = ''; $best_w = PHP_INT_MAX;
	foreach ( $meta['sizes'] as $s ) {
		if ( empty( $s['file'] ) || empty( $s['width'] ) ) { continue; }
		$w = (int) $s['width'];
		if ( $w < 300 || $w >= $best_w ) { continue; }
		$p = $dir . '/' . $s['file'];
		if ( file_exists( $p ) ) { $best = $p; $best_w = $w; }
	}
	return $best !== '' ? $best : $full;
}

function af_cs_load( $path ) {
	$info = @getimagesize( $path );
	if ( ! $info ) { return null; }
	switch ( $info[2] ) {
		case IMAGETYPE_JPEG: return @imagecreatefromjpeg( $path );
		case IMAGETYPE_PNG:  return @imagecreatefrompng( $path );
		case IMAGETYPE_GIF:  return @imagecreatefromgif( $path );
		case IMAGETYPE_WEBP: return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : null;
	}
	return null;
}

$ids = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'orderby'        => 'ID',
	'order'          => 'ASC',
) );

$groups = array(); $spell = array(); $nocode_ids = array();
foreach ( $ids as $pid ) {
	$code = get_post_meta( $pid, '_taf_art_code', true );
	$code = is_string( $code ) ? preg_replace( '/\s+/', ' ', trim( $code ) ) : '';
	if ( $code === '' ) { $nocode_ids[] = $pid; continue; }
	$key = strtoupper( $code );
	if ( ! isset( $spell[ $key ] ) ) { $spell[ $key ] = $code; }
	$groups[ $key ][] = $pid;
}
$SCOPE = strtolower( trim( (string) getenv( 'AF_SHEET_SCOPE' ) ) );
if ( $SCOPE === '' ) { $SCOPE = 'shared'; }
$SKIP  = max( 0, (int) getenv( 'AF_SHEET_SKIP' ) );
$TAKE  = max( 0, (int) getenv( 'AF_SHEET_TAKE' ) );

if ( $SCOPE === 'unique' ) {
	$shared = array_filter( $groups, function ( $p ) { return count( $p ) === 1; } );
} elseif ( $SCOPE === 'nocode' ) {
	// Products carrying no code at all, gathered under one heading so they can
	// be looked at the same way as everything else.
	$shared = array();
	if ( $nocode_ids ) { $shared['(NO CODE)'] = $nocode_ids; $spell['(NO CODE)'] = '(no code)'; }
} elseif ( $SCOPE === 'all' ) {
	if ( $nocode_ids ) { $groups['(NO CODE)'] = $nocode_ids; $spell['(NO CODE)'] = '(no code)'; }
	$shared = $groups;
} else {
	$shared = array_filter( $groups, function ( $p ) { return count( $p ) > 1; } );
}
uksort( $shared, 'strcmp' );

// A group of one still has to be split into sheets of a readable size, and a
// hundred single-product sheets would be a hundred base64 blobs. So in the
// unique and nocode scopes the products are packed into sheets of COLS*4.
if ( $SCOPE === 'unique' || $SCOPE === 'nocode' ) {
	$flat = array();
	foreach ( $shared as $k => $pids ) { foreach ( $pids as $pid ) { $flat[] = $pid; } }
	sort( $flat );
	$per = max( 1, $COLS * 4 );
	$shared = array(); $spell = array();
	foreach ( array_chunk( $flat, $per ) as $i => $chunk ) {
		$name = sprintf( 'BATCH %02d', $i + 1 );
		$shared[ $name ] = $chunk; $spell[ $name ] = $name;
	}
}

if ( $SKIP || $TAKE ) {
	$shared = array_slice( $shared, $SKIP, $TAKE > 0 ? $TAKE : null, true );
}

echo "scope: {$SCOPE}  |  groups to draw: " . count( $shared ) . "\n";
if ( $CODES ) { echo "drawing only: " . implode( ', ', array_keys( $CODES ) ) . "\n"; }
echo "tile: {$TILE}px  |  columns: {$COLS}  |  quality: {$QUAL}\n";
echo "Each sheet below is one JPEG, split into B64 lines. To rebuild them:\n";
echo "  grep '^B64 ' log | awk '{print \$2\" \"\$3}' | ...  (id then chunk)\n";

$drawn = 0; $skipped = 0; $tiles_total = 0;

foreach ( $shared as $key => $pids ) {
	if ( $ONLY !== '' && $key !== $ONLY ) { continue; }
	if ( $CODES && ! isset( $CODES[ $key ] ) ) { continue; }
	if ( $MAXG > 0 && $drawn >= $MAXG ) { break; }

	$slug = preg_replace( '/[^A-Za-z0-9]+/', '_', $key );
	$n    = count( $pids );
	$cols = min( $COLS, $n );
	$rows = (int) ceil( $n / $cols );
	$pad  = 6;
	$cap  = 16;   // room under each tile for the product id
	$W    = $cols * ( $TILE + $pad ) + $pad;
	$H    = $rows * ( $TILE + $cap + $pad ) + $pad;

	$sheet = imagecreatetruecolor( $W, $H );
	$white = imagecolorallocate( $sheet, 255, 255, 255 );
	$grey  = imagecolorallocate( $sheet, 150, 150, 150 );
	$ink   = imagecolorallocate( $sheet, 20, 20, 20 );
	imagefilledrectangle( $sheet, 0, 0, $W, $H, $white );

	$listed = array();
	$i = 0;
	foreach ( $pids as $pid ) {
		$col = $i % $cols;
		$row = (int) floor( $i / $cols );
		$x   = $pad + $col * ( $TILE + $pad );
		$y   = $pad + $row * ( $TILE + $cap + $pad );
		$i++;

		imagerectangle( $sheet, $x, $y, $x + $TILE, $y + $TILE, $grey );

		$att = get_post_thumbnail_id( $pid );
		$src = $att ? af_cs_source_path( $att ) : '';
		$img = $src !== '' ? af_cs_load( $src ) : null;

		if ( $img ) {
			$sw = imagesx( $img ); $sh = imagesy( $img );
			$scale = min( $TILE / max( 1, $sw ), $TILE / max( 1, $sh ) );
			$dw = max( 1, (int) round( $sw * $scale ) );
			$dh = max( 1, (int) round( $sh * $scale ) );
			imagecopyresampled( $sheet, $img,
				$x + (int) ( ( $TILE - $dw ) / 2 ), $y + (int) ( ( $TILE - $dh ) / 2 ),
				0, 0, $dw, $dh, $sw, $sh );
			imagedestroy( $img );
			$tiles_total++;
		} else {
			imagestring( $sheet, 3, $x + 8, $y + (int) ( $TILE / 2 ), 'no image', $grey );
		}

		imagestring( $sheet, 3, $x + 2, $y + $TILE + 3, '#' . $pid, $ink );
		$title = html_entity_decode( wp_strip_all_tags( get_the_title( $pid ) ) );
		$listed[] = sprintf( '    #%-7d %s', $pid, mb_substr( $title, 0, 72 ) );
	}

	ob_start();
	imagejpeg( $sheet, null, $QUAL );
	$bytes = ob_get_clean();
	imagedestroy( $sheet );

	if ( ! $bytes ) { $skipped++; continue; }

	echo "\n--- SHEET {$spell[$key]} ({$n} products, {$cols}x{$rows}, " . round( strlen( $bytes ) / 1024 ) . " KB) ---\n";
	echo implode( "\n", $listed ) . "\n";

	$b64 = base64_encode( $bytes );
	foreach ( str_split( $b64, 200 ) as $chunk ) {
		echo "B64 {$slug} {$chunk}\n";
	}
	echo "B64END {$slug}\n";
	$drawn++;
}

echo "\nsheets drawn: {$drawn}  |  tiles with a picture: {$tiles_total}";
if ( $skipped ) { echo "  |  sheets that failed to encode: {$skipped}"; }
echo "\n=== DONE ===\n";
