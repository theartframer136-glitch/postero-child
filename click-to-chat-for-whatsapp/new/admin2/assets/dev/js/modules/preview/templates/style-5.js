import { escapeAttr, escapeHTML } from '../../core/Utils.js';

export default function renderStyle5 ( ctx ) {
	const opt = ( key, fallback ) => {
		const value = ctx.value( 'ht_ctc_s5', key );
		return ( value === '' || value === undefined || value === null ) ? fallback : value;
	};

	let line1 = ctx.value( 'ht_ctc_s5', 's5_line_1' ) || '';
	const line2 = ctx.value( 'ht_ctc_s5', 's5_line_2' ) || '';
	const line1Color = opt( 's5_line_1_color', '#000000' );
	const line2Color = opt( 's5_line_2_color', '#000000' );
	const backgroundColor = opt( 's5_background_color', '#ffffff' );
	const borderColor = opt( 's5_border_color', '#dddddd' );
	let img = ctx.value( 'ht_ctc_s5', 's5_img' ) || '';
	const imgHeight = opt( 's5_img_height', '70px' );
	const imgWidth = opt( 's5_img_width', '70px' );
	const contentHeight = opt( 's5_content_height', '70px' );
	const contentWidth = opt( 's5_content_width', '270px' );
	const imgPosition = opt( 's5_img_position', 'right' );

	const callToAction = ctx.cta || '';

	// default image - if user not added any image
	if ( img === '' ) {
		img = `${ctx.pluginUrl}new/inc/assets/img/new_style8.jpg`;
	}

	if ( line1 === '' ) {
		line1 = callToAction;
	}

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
	const ctaStyle = `display: -ms-flexbox;display: -webkit-flex; display: flex;${rtlCss} `;

	let imgStyle = `height: ${imgHeight}; width: ${imgWidth}; z-index: 1; `;
	if ( imgPosition === 'right' ) {
		imgStyle += 'order: 1;';
	}

	let contentStyle = 'flex-direction: column; justify-content: center; align-items: center; box-sizing: border-box;';
	contentStyle += ` background-color: ${backgroundColor}; border: 1px solid ${borderColor}; ` +
		`height: ${contentHeight}; width: ${contentWidth};  `;
	if ( imgPosition === 'right' ) {
		contentStyle += 'margin-right: -4px;';
	} else if ( imgPosition === 'left' ) {
		contentStyle += 'margin-left: -4px;';
	}

	const cssCode = '.ht-ctc-style-5 .s5_img{box-shadow:2px 5px 10px rgba(0,0,0,.5)}.ht-ctc-style-5 .s5_content{box-shadow:2px 5px 10px rgba(0,0,0,.5);border-radius:5px}.ht-ctc-style-5 .s5_content span{padding:5px;overflow:hidden}.ht-ctc-style-5 .s5_content .heading{font-size:20px}.ht-ctc-style-5 .s5_content .description{font-size:12px}.ht-ctc-style-5 .s5_content.right{animation:1s s5_translate_right}.ht-ctc-style-5 .s5_content.left{animation:1s s5_translate_left}@keyframes s5_translate_right{0%{transform:translateX(55px)}100%{transform:translateX(0)}}@keyframes s5_translate_left{0%{transform:translateX(-55px)}100%{transform:translateX(0)}}';

	const output = '.ht-ctc-style-5 .s5_content{display:none}' +
		`.ht-ctc-style-5 .s5_cta:hover .s5_content{display:flex}${cssCode}`;

	return `<style>${output}</style>
	<div class="ht-ctc-style-5 ctc_s_5" style="cursor: pointer;">
		<div class="s5_cta" style="${escapeAttr( ctaStyle )}">
			<img class="s5_img" src="${escapeAttr( img )}" style="${escapeAttr( imgStyle )}" ` +
				`alt="${escapeAttr( callToAction )}">
			<div class="s5_content ctc_cta_stick ${escapeAttr( imgPosition )}" ` +
				`style="${escapeAttr( contentStyle )}">
				<span class="heading ctc_cta" style="color: ${escapeAttr( line1Color )}">` +
					`${escapeHTML( line1 )}</span>
				<span class="description" style="color: ${escapeAttr( line2Color )}">` +
					`${escapeHTML( line2 )}</span>
			</div>
		</div>
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// - add safeUrl to the existing ../../core/Utils.js import
// - import { escapeCssValue, normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-5.php:
// - Preserve explicit empty text, color, and image-position values.
// - Normalize all four live dimensions using their PHP sanitizer defaults.
// - Validate a custom image URL before src output; rejected URLs use the
//   plugin's default image, matching the saved result after ctc_sanitize_url.
// - Empty line 1 falls back to the current CTA, but Style 5 itself does not
//   assign fallback CTA text.
// - Use ctx.isRtl only and restore PHP's five ctc-analytics classes.
// - PHP forces image position left for Woo pages. PreviewManager does not
//   expose calling_from, so Woo-specific parity remains a context limitation.
// export default function renderStyle5 ( ctx ) {
// 	let line1 = ctx.value( 'ht_ctc_s5', 's5_line_1' ) ?? '';
// 	const line2 = ctx.value( 'ht_ctc_s5', 's5_line_2' ) ?? '';
// 	const line1Color = escapeCssValue( ctx.value( 'ht_ctc_s5', 's5_line_1_color' ) ?? '#000000' );
// 	const line2Color = escapeCssValue( ctx.value( 'ht_ctc_s5', 's5_line_2_color' ) ?? '#000000' );
// 	const backgroundColor = escapeCssValue( ctx.value( 'ht_ctc_s5', 's5_background_color' ) ?? '#ffffff' );
// 	const borderColor = escapeCssValue( ctx.value( 'ht_ctc_s5', 's5_border_color' ) ?? '#dddddd' );

// 	const imgHeight = normalizeCssLength( ctx.value( 'ht_ctc_s5', 's5_img_height' ), 's5_img_height' );
// 	const imgWidth = normalizeCssLength( ctx.value( 'ht_ctc_s5', 's5_img_width' ), 's5_img_width' );
// 	const contentHeight = normalizeCssLength( ctx.value( 'ht_ctc_s5', 's5_content_height' ), 's5_content_height' );
// 	const contentWidth = normalizeCssLength( ctx.value( 'ht_ctc_s5', 's5_content_width' ), 's5_content_width' );

// 	const imgPosition = ctx.value( 'ht_ctc_s5', 's5_img_position' ) ?? 'right';
// 	const callToAction = String( ctx.cta ?? '' );

// 	const defaultImg = `${ctx.pluginUrl}new/inc/assets/img/new_style8.jpg`;
// 	const rawImg = String( ctx.value( 'ht_ctc_s5', 's5_img' ) ?? '' ).trim();
// 	const validatedImg = rawImg === '' ? '' : safeUrl( rawImg );
// 	let img = validatedImg === '#' && rawImg !== '#' ? '' : validatedImg;
// 	if ( img === '' ) {
// 		img = defaultImg;
// 	}

// 	if ( line1 === '' ) {
// 		line1 = callToAction;
// 	}

// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
// 	const ctaStyle = `display: -ms-flexbox;display: -webkit-flex; display: flex;${rtlCss} `;

// 	let imgStyle = `height: ${imgHeight}; width: ${imgWidth}; z-index: 1; `;
// 	if ( imgPosition === 'right' ) {
// 		imgStyle += 'order: 1;';
// 	}

// 	let contentStyle = 'flex-direction: column; justify-content: center; align-items: center; box-sizing: border-box;';
// 	contentStyle += ` background-color: ${backgroundColor}; border: 1px solid ${borderColor}; height: ${contentHeight}; width: ${contentWidth};  `;
// 	if ( imgPosition === 'right' ) {
// 		contentStyle += 'margin-right: -4px;';
// 	} else if ( imgPosition === 'left' ) {
// 		contentStyle += 'margin-left: -4px;';
// 	}

// 	const cssCode = '.ht-ctc-style-5 .s5_img{box-shadow:2px 5px 10px rgba(0,0,0,.5)}.ht-ctc-style-5 .s5_content{box-shadow:2px 5px 10px rgba(0,0,0,.5);border-radius:5px}.ht-ctc-style-5 .s5_content span{padding:5px;overflow:hidden}.ht-ctc-style-5 .s5_content .heading{font-size:20px}.ht-ctc-style-5 .s5_content .description{font-size:12px}.ht-ctc-style-5 .s5_content.right{animation:1s s5_translate_right}.ht-ctc-style-5 .s5_content.left{animation:1s s5_translate_left}@keyframes s5_translate_right{0%{transform:translateX(55px)}100%{transform:translateX(0)}}@keyframes s5_translate_left{0%{transform:translateX(-55px)}100%{transform:translateX(0)}}';
// 	const output = `.ht-ctc-style-5 .s5_content{display:none}.ht-ctc-style-5 .s5_cta:hover .s5_content{display:flex}${cssCode}`;

// 	const html = `<style>${output}</style>
// 	<div class="ht-ctc-style-5 ctc-analytics ctc_s_5" style="cursor: pointer;">
// 		<div class="s5_cta" style="${escapeAttr( ctaStyle )}">
// 			<img class="s5_img ctc-analytics" src="${escapeAttr( img )}" style="${escapeAttr( imgStyle )}" alt="${escapeAttr( callToAction )}">
// 			<div class="s5_content ctc-analytics ctc_cta_stick ${escapeAttr( imgPosition )}" style="${escapeAttr( contentStyle )}">
// 				<span class="heading ctc-analytics ctc_cta" style="color: ${escapeAttr( line1Color )}">${escapeHTML( line1 )}</span>
// 				<span class="description ctc-analytics" style="color: ${escapeAttr( line2Color )}">${escapeHTML( line2 )}</span>
// 			</div>
// 		</div>
// 	</div>`;

// 	return { html };
// }
