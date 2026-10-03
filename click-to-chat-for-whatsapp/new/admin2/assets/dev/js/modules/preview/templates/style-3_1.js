/**
 * Preview Template — Style 3 Extend (round logo with background pad)
 *
 * JS port of new/inc/styles/style-3_1.php. Class names, inline CSS, and option
 * defaults must stay in sync with the PHP template.
 */
import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { logoIcon } from '../icons.js';
import { iconCtaBlock, hoverRevealRule } from '../cta.js';
import { escapeCssValue } from '../css.js';

export default function renderStyle31 ( ctx ) {
	let imgSize = ctx.value( 'ht_ctc_s3_1', 's3_img_size' ) || '';
	if ( imgSize === '' ) { imgSize = '40px'; }

	const ctaType = ctx.value( 'ht_ctc_s3_1', 'cta_type' ) || 'hover';
	const textColor = ctx.value( 'ht_ctc_s3_1', 'cta_textcolor' ) || '';
	const bgColor = ctx.value( 'ht_ctc_s3_1', 'cta_bgcolor' ) ?? '#ffffff';
	const fontSize = ctx.value( 'ht_ctc_s3_1', 'cta_font_size' ) || '';

	const callToAction = ctx.cta || 'WhatsApp us';

	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
		ctaType,
		textColor,
		bgColor: bgColor === '' ? '#ffffff' : bgColor,
		fontSize,
		side2: ctx.side2,
		cta: callToAction,
	} );

	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
	const css = `display:flex;justify-content:center;align-items:center;${rtlCss} `;

	// extend
	const padding = ctx.value( 'ht_ctc_s3_1', 's3_padding' ) || '';
	const padBgColor = ctx.value( 'ht_ctc_s3_1', 's3_bg_color' ) || '#25D366';
	const padBgColorHover = ctx.value( 'ht_ctc_s3_1', 's3_bg_color_hover' ) || '#25D366';

	const boxShadow = ( ctx.value( 'ht_ctc_s3_1', 's3_box_shadow' ) || '' ) !== '' ?
		'box-shadow: 0px 0px 11px rgba(0,0,0,.5);' :
		'';
	const extendCss = `background-color: ${padBgColor}; padding: ${padding}; ` +
		`border-radius: 50%; ${boxShadow}`;

	const boxShadowHover = ( ctx.value( 'ht_ctc_s3_1', 's3_box_shadow_hover' ) || '' ) !== '' ?
		'box-shadow:0px 0px 11px rgba(0,0,0,.5);' :
		'';
	const safeHoverBg = escapeCssValue( padBgColorHover );
	const hoverCss = `background-color:${safeHoverBg} !important;${boxShadowHover}`;

	const hoverStyles = [
		`.ht-ctc .ctc_s_3_1:hover svg stop{stop-color:${safeHoverBg};}`,
		'.ht-ctc .ctc_s_3_1:hover .ht_ctc_padding,',
		`.ht-ctc .ctc_s_3_1:hover .ctc_cta_stick{${hoverCss}}`,
	].join( '' );

	return `<style id="ht-ctc-s3">${hoverStyles}${hoverRevealRule( 'ctc_s_3_1' )}</style>
	<div ${titleAttr} style="${escapeAttr( css )}" ` +
		`class="ctc_s_3_1 ctc_s3_1 ctc_nb" data-nb_top="-4px" data-nb_right="-4px">
		<p class="ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">
			${escapeHTML( callToAction )}
		</p>
		<div class="ht_ctc_padding" style="${escapeAttr( extendCss )}">
			${logoIcon( imgSize, 'chat', svgCss, { bgColor: padBgColor } )}
		</div>
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// import { normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-3_1.php:
// - Preserve explicit empty CTA and pad colors; PHP uses isset, not truthiness.
// - Style 3 Extend preserves blank CTA text and uses the active device side.
// - Normalize live image, padding, and CTA font dimensions before saving.
// - Shadow options follow key-presence semantics: an existing empty value is
//   still enabled by PHP, while null/undefined means the key is absent.
// - An explicitly empty pad color leaves the outer background declaration
//   empty, but the PHP SVG helper falls back to its green gradient colors.
// - PHP puts ctc-analytics on the CTA and padding div, not the outer wrapper.
// - The PHP notification offsets are -4px; PreviewManager currently ignores
//   template data offsets and hardcodes the preview badge to -11px.
// - PreviewManager still needs ctx.type and feature-specific settings for
//   group/share parity.
// - The shared JS logoIcon omits PHP's s3_1_offset_1/s3_1_offset_2 stop IDs;
//   visual output is unaffected, but exact SVG DOM parity remains incomplete.
// - Admin help calls 20px the padding default, while PHP and its sanitizer
//   both preserve an empty padding value. This candidate follows PHP.
// - The active branch imports escapeCssValue, but the current local css.js
//   does not export it, so this module cannot load until that helper is added.
// export default function renderStyle31 ( ctx ) {
// 	const rawImgSize = ctx.value( 'ht_ctc_s3_1', 's3_img_size' ) ?? '';
// 	let imgSize = normalizeCssLength( rawImgSize, 's3_img_size' );
// 	if ( imgSize === '' ) {
// 		imgSize = '40px';
// 	}

// 	const ctaType = ctx.value( 'ht_ctc_s3_1', 'cta_type' ) ?? 'hover';
// 	const textColor = ctx.value( 'ht_ctc_s3_1', 'cta_textcolor' ) ?? '';
// 	const bgColor = ctx.value( 'ht_ctc_s3_1', 'cta_bgcolor' ) ?? '#ffffff';
// 	const rawFontSize = ctx.value( 'ht_ctc_s3_1', 'cta_font_size' ) ?? '';
// 	const fontSize = normalizeCssLength( rawFontSize, 'cta_font_size' );
// 	const callToAction = String( ctx.cta ?? '' );

// 	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
// 		ctaType,
// 		textColor,
// 		bgColor,
// 		fontSize,
// 		side2: ctx.side2,
// 		cta: callToAction,
// 	} );

// 	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;
// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
// 	const css = `display:flex;justify-content:center;align-items:center;${rtlCss} `;

// 	const rawPadding = ctx.value( 'ht_ctc_s3_1', 's3_padding' ) ?? '';
// 	const padding = normalizeCssLength( rawPadding, 's3_padding' );
// 	const padBgColor = ctx.value( 'ht_ctc_s3_1', 's3_bg_color' ) ?? '#25D366';
// 	const padBgColorHover = ctx.value( 'ht_ctc_s3_1', 's3_bg_color_hover' ) ?? '#25D366';

// 	const shadowValue = ctx.value( 'ht_ctc_s3_1', 's3_box_shadow' );
// 	const hasShadow = shadowValue !== undefined && shadowValue !== null;
// 	const boxShadow = hasShadow ?
// 		'box-shadow: 0px 0px 11px rgba(0,0,0,.5);' :
// 		'';
// 	const extendCss = `background-color: ${escapeCssValue( padBgColor )}; padding: ${escapeCssValue( padding )}; border-radius: 50%; ${boxShadow}`;

// 	const hoverShadowValue = ctx.value( 'ht_ctc_s3_1', 's3_box_shadow_hover' );
// 	const hasHoverShadow = hoverShadowValue !== undefined && hoverShadowValue !== null;
// 	const boxShadowHover = hasHoverShadow ?
// 		'box-shadow:0px 0px 11px rgba(0,0,0,.5);' :
// 		'';
// 	const safeHoverColor = escapeCssValue( padBgColorHover );
// 	const hoverCss = `background-color:${safeHoverColor} !important;${boxShadowHover}`;
// 	const hoverStyles = `.ht-ctc .ctc_s_3_1:hover svg stop{stop-color:${safeHoverColor};}.ht-ctc .ctc_s_3_1:hover .ht_ctc_padding,.ht-ctc .ctc_s_3_1:hover .ctc_cta_stick{${hoverCss}}`;

// 	let ctaHoverStyle = '';
// 	if ( ctaType === 'hover' ) {
// 		ctaHoverStyle = hoverRevealRule( 'ctc_s_3_1' );
// 	}

// 	const html = `<style id="ht-ctc-s3">${hoverStyles}${ctaHoverStyle}</style>
// 	<div ${titleAttr} style="${escapeAttr( css )}" class="ctc_s_3_1 ctc_s3_1 ctc_nb" data-nb_top="-4px" data-nb_right="-4px">
// 		<p class="ctc-analytics ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">${escapeHTML( callToAction )}</p>
// 		<div class="ctc-analytics ht_ctc_padding" style="${escapeAttr( extendCss )}">
// 			${logoIcon( imgSize, ctx.type || 'chat', svgCss, { bgColor: padBgColor } )}
// 		</div>
// 	</div>`;

// 	return { html };
// }
