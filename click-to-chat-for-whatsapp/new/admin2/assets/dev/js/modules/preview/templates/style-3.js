import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { logoIcon } from '../icons.js';
import { iconCtaBlock, hoverRevealRule } from '../cta.js';

export default function renderStyle3 ( ctx ) {
	let imgSize = ctx.value( 'ht_ctc_s3', 's3_img_size' ) || '';
	if ( imgSize === '' ) { imgSize = '50px'; }

	const ctaType = ctx.value( 'ht_ctc_s3', 'cta_type' ) || 'hover';
	const textColor = ctx.value( 'ht_ctc_s3', 'cta_textcolor' ) || '';
	const bgColor = ctx.value( 'ht_ctc_s3', 'cta_bgcolor' ) ?? '#ffffff';
	const fontSize = ctx.value( 'ht_ctc_s3', 'cta_font_size' ) || '';

	const callToAction = ctx.cta || 'WhatsApp us';

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
	const css = `display:flex;justify-content:center;align-items:center;${rtlCss} `;

	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
		ctaType,
		textColor,
		bgColor: bgColor === '' ? '#ffffff' : bgColor,
		fontSize,
		side2: ctx.side2,
		cta: callToAction,
	} );

	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;

	return `<style>${hoverRevealRule( 'ctc_s_3' )}</style>
	<div ${titleAttr} style="${escapeAttr( css )}" ` +
		`class="ctc_s_3 ctc_nb" data-nb_top="-5px" data-nb_right="-5px">
		<p class="ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">
			${escapeHTML( callToAction )}
		</p>
		${logoIcon( imgSize, 'chat', svgCss )}
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// import { normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-3.php:
// - Preserve explicit empty cta_type and cta_bgcolor values (PHP uses isset).
// - Style 3 preserves a blank CTA; it is not in the frontend fallback list.
// - Normalize live, unsaved image/font dimensions like the REST sanitizer.
// - Use ctx.side2 and ctx.isRtl. PreviewManager does not expose ctx.side,
//   ctx.isSameSide, or ctx.mobileSide and already resolves the active device.
// - PHP places ctc-analytics on the CTA paragraph, not the outer wrapper.
// - Keep the exact notification offsets and use the feature type for SVG IDs.
// - PreviewManager still needs ctx.type and feature-specific settings for
//   group/share parity. It also currently ignores data-nb_top/data-nb_right
//   and hardcodes the preview badge offsets to -11px.
// - PHP reads s3_type but does not use it in the rendered Style 3 markup.
// export default function renderStyle3 ( ctx ) {
// 	const rawImgSize = ctx.value( 'ht_ctc_s3', 's3_img_size' ) ?? '';
// 	let imgSize = normalizeCssLength( rawImgSize, 's3_img_size' );
// 	if ( imgSize === '' ) {
// 		imgSize = '50px';
// 	}

// 	const ctaType = ctx.value( 'ht_ctc_s3', 'cta_type' ) ?? 'hover';
// 	const textColor = ctx.value( 'ht_ctc_s3', 'cta_textcolor' ) ?? '';
// 	const bgColor = ctx.value( 'ht_ctc_s3', 'cta_bgcolor' ) ?? '#ffffff';
// 	const rawFontSize = ctx.value( 'ht_ctc_s3', 'cta_font_size' ) ?? '';
// 	const fontSize = normalizeCssLength( rawFontSize, 'cta_font_size' );
// 	const callToAction = String( ctx.cta ?? '' );

// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
// 	const css = `display:flex;justify-content:center;align-items:center;${rtlCss} `;

// 	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
// 		ctaType,
// 		textColor,
// 		bgColor,
// 		fontSize,
// 		side2: ctx.side2,
// 		cta: callToAction,
// 	} );

// 	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;
// 	const iconHtml = logoIcon( imgSize, ctx.type || 'chat', svgCss );

// 	let hoverStyle = '';
// 	if ( ctaType === 'hover' ) {
// 		hoverStyle = `<style>${hoverRevealRule( 'ctc_s_3' )}</style>`;
// 	}

// 	const html = `${hoverStyle}
// <div ${titleAttr} style="${escapeAttr( css )}" class="ctc_s_3 ctc_nb" data-nb_top="-5px" data-nb_right="-5px">
// 	<p class="ctc-analytics ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">${escapeHTML( callToAction )}</p>
// 	${iconHtml}
// </div>`;

// 	return { html };
// }
