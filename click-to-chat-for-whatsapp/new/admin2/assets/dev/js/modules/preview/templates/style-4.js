import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { logoIcon } from '../icons.js';

export default function renderStyle4 ( ctx ) {
	const opt = ( key, fallback ) => {
		const value = ctx.value( 'ht_ctc_s4', key );
		return ( value === '' || value === undefined || value === null ) ? fallback : value;
	};

	const textColor = opt( 's4_text_color', '#000000' );
	const bgColor = opt( 's4_bg_color', '#e4e4e4' );
	const imgUrl = ctx.value( 'ht_ctc_s4', 's4_img_url' ) || '';
	const imgPosition = opt( 's4_img_position', 'left' );
	const imgSize = opt( 's4_img_size', '32px' );

	const callToAction = ctx.cta || 'WhatsApp us';

	let margin, order;
	if ( imgPosition === 'left' ) {
		margin = '0 8px 0 -12px;';
		order = '0';
	} else {
		margin = '0 -12px 0 8px;';
		order = '1';
	}

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';

	const chipCss = [
		'display:flex;',
		'justify-content: center;',
		'align-items: center;',
		`background-color:${bgColor};`,
		`color:${textColor};`,
		'padding:0 12px;',
		'border-radius:25px;',
		'font-size:13px;',
		'line-height:32px;',
		rtlCss,
	].join( ' ' );
	const chipSvgCss = `margin:${margin}order:${order};`;
	const chipImgCss = `margin:${margin}order:${order};height:${imgSize};` +
		`width:${imgSize};border-radius:50%`;
	const svgCss = `pointer-events:none; display: block; height:${imgSize}; width:${imgSize};`;

	let img;
	if ( imgUrl === '' ) {
		img = `<span class="s4_img" style="${escapeAttr( chipSvgCss )}">${logoIcon( imgSize, 'chat-s4', svgCss )}</span>`;
	} else {
		img = `<img class="s4_img" style="${escapeAttr( chipImgCss )}" ` +
			`src="${escapeAttr( imgUrl )}" alt="${escapeAttr( callToAction )}">`;
	}

	return `<div class="ctc_chip ctc_s_4 ctc_nb" style="${escapeAttr( chipCss )}" ` +
		`data-nb_top="-10px" data-nb_right="-10px">
		${img}
		<span class="ctc_cta">${escapeHTML( callToAction )}</span>
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// - add safeUrl to the existing ../../core/Utils.js import
// - import { escapeCssValue, normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-4.php:
// - Preserve explicit empty text/background colors and image position.
// - Image size is different: absent or explicitly empty both become 32px.
// - Style 4 intentionally changes a blank CTA to "WhatsApp us".
// - Validate live image URLs before src output; escapeAttr alone does not
//   reject javascript:/data: schemes like PHP esc_url/esc_url_raw does.
// - Use ctx.isRtl and the feature type; PreviewManager currently lacks
//   ctx.type and feature-specific group/share settings.
// - PHP puts ctc-analytics on the outer chip only.
// - The -10px notification offsets are correct here, but PreviewManager
//   currently ignores them and hardcodes the preview badge to -11px.
// export default function renderStyle4 ( ctx ) {
// 	const rawTextColor = ctx.value( 'ht_ctc_s4', 's4_text_color' ) ?? '#000000';
// 	const rawBgColor = ctx.value( 'ht_ctc_s4', 's4_bg_color' ) ?? '#e4e4e4';
// 	const textColor = escapeCssValue( rawTextColor );
// 	const bgColor = escapeCssValue( rawBgColor );
// 	const imgPosition = ctx.value( 'ht_ctc_s4', 's4_img_position' ) ?? 'left';

// 	const rawImgUrl = String( ctx.value( 'ht_ctc_s4', 's4_img_url' ) ?? '' ).trim();
// 	const validatedImgUrl = rawImgUrl === '' ? '' : safeUrl( rawImgUrl );
// 	const imgUrl = validatedImgUrl === '#' && rawImgUrl !== '#' ? '' : validatedImgUrl;

// 	const rawImgSize = ctx.value( 'ht_ctc_s4', 's4_img_size' ) ?? '';
// 	let imgSize = normalizeCssLength( rawImgSize, 's4_img_size' );
// 	if ( imgSize === '' ) {
// 		imgSize = '32px';
// 	}

// 	let callToAction = String( ctx.cta ?? '' );
// 	if ( callToAction === '' ) {
// 		callToAction = 'WhatsApp us';
// 	}

// 	let margin, order;
// 	if ( imgPosition === 'left' ) {
// 		margin = '0 8px 0 -12px;';
// 		order = '0';
// 	} else {
// 		margin = '0 -12px 0 8px;';
// 		order = '1';
// 	}

// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';

// 	const chipCss = `display:flex;justify-content: center;align-items: center;background-color:${bgColor};color:${textColor};padding:0 12px;border-radius:25px;font-size:13px;line-height:32px;${rtlCss} `;
// 	const chipSvgCss = `margin:${margin}order:${order};`;
// 	const chipImgCss = `margin:${margin}order:${order};height:${imgSize};width:${imgSize};border-radius:50%`;
// 	const svgCss = `pointer-events:none; display: block; height:${imgSize}; width:${imgSize};`;

// 	let img;
// 	if ( imgUrl === '' ) {
// 		const ctcType = `${ctx.type || 'chat'}-s4`;
// 		img = `<span class="s4_img" style="${escapeAttr( chipSvgCss )}">${logoIcon( imgSize, ctcType, svgCss )}</span>`;
// 	} else {
// 		img = `<img class="s4_img" style="${escapeAttr( chipImgCss )}" src="${escapeAttr( imgUrl )}" alt="${escapeAttr( callToAction )}">`;
// 	}

// 	const html = `<div class="ctc_chip ctc-analytics ctc_s_4 ctc_nb" style="${escapeAttr( chipCss )}" data-nb_top="-10px" data-nb_right="-10px">
// 	${img}
// 	<span class="ctc_cta">${escapeHTML( callToAction )}</span>
// </div>`;

// 	return { html };
// }
