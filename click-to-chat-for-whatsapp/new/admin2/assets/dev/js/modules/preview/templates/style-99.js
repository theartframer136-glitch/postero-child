/**
 * Preview Template — Style 99 (own image / GIF)
 *
 * JS port of new/inc/styles/style-99.php. Note: the desktop image option key
 * is 's99_dekstop_img_url' (typo preserved from the DB schema).
 */
import { escapeAttr } from '../../core/Utils.js';

export default function renderStyle99 ( ctx ) {
	const opt = ( key, fallback ) => {
		const value = ctx.value( 'ht_ctc_s99', key );
		return ( value === '' || value === undefined || value === null ) ? fallback : value;
	};

	const callToAction = ctx.cta || '';

	let img;
	let imgCss = '';

	if ( ctx.device === 'mobile' ) {
		img = ctx.value( 'ht_ctc_s99', 's99_mobile_img_url' ) || '';
		imgCss += `height: ${opt( 's99_mobile_img_height', '40px' )}; `;
		const width = opt( 's99_mobile_img_width', '40px' );
		if ( width !== '' ) { imgCss += `width: ${width}; `; }
	} else {
		img = ctx.value( 'ht_ctc_s99', 's99_dekstop_img_url' ) || '';
		imgCss += `height: ${opt( 's99_desktop_img_height', '50px' )}; `;
		const width = opt( 's99_desktop_img_width', '50px' );
		if ( width !== '' ) { imgCss += `width: ${width}; `; }
	}

	// fallback image
	if ( img === '' ) {
		img = `${ctx.pluginUrl}new/inc/assets/img/whatsapp-logo.svg`;
	}

	return `<img class="own-img ctc_s_99 ctc_cta" title="${escapeAttr( callToAction )}" ` +
		`src="${escapeAttr( img )}" style="${escapeAttr( imgCss )}" ` +
		`alt="${escapeAttr( callToAction )}">`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// - add safeUrl to the existing ../../core/Utils.js import
// import { normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-99.php:
// - Preserve the misspelled s99_dekstop_img_url database key.
// - Missing height/width keys use device defaults. Explicit empty height also
//   uses its default, but explicit empty width omits the width declaration.
// - Validate the selected device URL; rejected/empty values use the bundled
//   WhatsApp SVG, matching ctc_sanitize_url plus PHP fallback behavior.
// - Preserve blank CTA text, the style-99 ID, analytics class, and filename alt.
// - Filename extraction mirrors PHP pathinfo without URL-decoding; fall back
//   to CTA only when no filename can be determined.
// export default function renderStyle99 ( ctx ) {
// 	const callToAction = String( ctx.cta ?? '' );

// 	const dimensionCss = ( heightKey, widthKey, heightDefault, widthDefault ) => {
// 		const rawHeight = ctx.value( 'ht_ctc_s99', heightKey );
// 		let height = rawHeight === undefined || rawHeight === null ?
// 			heightDefault :
// 			normalizeCssLength( rawHeight, heightKey );
// 		if ( height === '' ) {
// 			height = heightDefault;
// 		}

// 		const rawWidth = ctx.value( 'ht_ctc_s99', widthKey );
// 		const width = rawWidth === undefined || rawWidth === null ?
// 			widthDefault :
// 			normalizeCssLength( rawWidth, widthKey );

// 		let css = `height: ${height}; `;
// 		if ( width !== '' ) {
// 			css += `width: ${width}; `;
// 		}
// 		return css;
// 	};

// 	let rawImg;
// 	let imgCss = '';

// 	if ( ctx.device === 'mobile' ) {
// 		rawImg = ctx.value( 'ht_ctc_s99', 's99_mobile_img_url' );
// 		imgCss = dimensionCss(
// 			's99_mobile_img_height',
// 			's99_mobile_img_width',
// 			'40px',
// 			'40px'
// 		);
// 	} else {
// 		rawImg = ctx.value( 'ht_ctc_s99', 's99_dekstop_img_url' );
// 		imgCss = dimensionCss(
// 			's99_desktop_img_height',
// 			's99_desktop_img_width',
// 			'50px',
// 			'50px'
// 		);
// 	}

// 	rawImg = String( rawImg ?? '' ).trim();
// 	const validatedImg = rawImg === '' ? '' : safeUrl( rawImg );
// 	let img = validatedImg === '#' && rawImg !== '#' ? '' : validatedImg;
// 	if ( img === '' ) {
// 		img = `${ctx.pluginUrl}new/inc/assets/img/whatsapp-logo.svg`;
// 	}

// 	let filename = '';
// 	try {
// 		const base = img.split( '/' ).pop() || '';
// 		const extensionIndex = base.lastIndexOf( '.' );
// 		filename = extensionIndex === -1 ? base : base.slice( 0, extensionIndex );
// 	} catch ( e ) {
// 		filename = '';
// 	}
// 	if ( filename === '' ) {
// 		filename = callToAction;
// 	}

// 	const html = `<img class="own-img ctc-analytics ctc_s_99 ctc_cta" title="${escapeAttr( callToAction )}" id="style-99" src="${escapeAttr( img )}" style="${escapeAttr( imgCss )}" alt="${escapeAttr( filename )}">`;

// 	return { html };
// }
