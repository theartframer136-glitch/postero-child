/**
 * Preview Template — Style 2
 *
 * Mirrors new/inc/styles/style-2.php.
 */
import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { squareIcon } from '../icons.js';
import { iconCtaBlock, hoverRevealRule } from '../cta.js';

export default function renderStyle2 ( ctx ) {
	let imgSize = ctx.value( 'ht_ctc_s2', 's2_img_size' ) || '';
	if ( imgSize === '' ) { imgSize = '50px'; }

	const ctaType = ctx.value( 'ht_ctc_s2', 'cta_type' ) || 'hover';
	const textColor = ctx.value( 'ht_ctc_s2', 'cta_textcolor' ) || '';
	const bgColor = ctx.value( 'ht_ctc_s2', 'cta_bgcolor' ) ?? '#ffffff';
	const fontSize = ctx.value( 'ht_ctc_s2', 'cta_font_size' ) || '';

	const callToAction = ctx.cta || 'WhatsApp us';

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
	const css = `display: flex; justify-content: center; align-items: center; ${rtlCss} `;

	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
		ctaType,
		textColor,
		bgColor: bgColor === '' ? '#ffffff' : bgColor,
		fontSize,
		side2: ctx.side2,
		cta: callToAction,
	} );

	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;

	return `<style>${hoverRevealRule( 'ctc_s_2' )}</style>
	<div ${titleAttr} style="${escapeAttr( css )}" class="ctc_s_2">
		<p class="ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">
			${escapeHTML( callToAction )}
		</p>
		${squareIcon( imgSize, 'chat', svgCss )}
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// import { normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-2.php:
// - Preserve explicit empty cta_type and cta_bgcolor values (PHP uses isset).
// - Style 2 must preserve a blank CTA; the frontend fallback applies only to
//   Styles 1, 4, 6, and 8.
// - Normalize live, unsaved image/font dimensions like the REST sanitizer.
// - Use ctx.side2. The current preview context does not expose ctx.side,
//   ctx.isSameSide, or ctx.mobileSide, and already resolves the active device.
// - Use ctx.isRtl only; PreviewManager derives it from document.documentElement.
// - Keep ctc-analytics classes and use the feature type for unique SVG IDs.
// - PreviewManager still needs to expose ctx.type and feature-specific settings
//   before group/share parity can work; the chat fallback remains valid meanwhile.
// export default function renderStyle2 ( ctx ) {
// 	const rawImgSize = ctx.value( 'ht_ctc_s2', 's2_img_size' ) ?? '';
// 	let imgSize = normalizeCssLength( rawImgSize, 's2_img_size' );
// 	if ( imgSize === '' ) {
// 		imgSize = '50px';
// 	}

// 	const ctaType = ctx.value( 'ht_ctc_s2', 'cta_type' ) ?? 'hover';
// 	const textColor = ctx.value( 'ht_ctc_s2', 'cta_textcolor' ) ?? '';
// 	const bgColor = ctx.value( 'ht_ctc_s2', 'cta_bgcolor' ) ?? '#ffffff';
// 	const rawFontSize = ctx.value( 'ht_ctc_s2', 'cta_font_size' ) ?? '';
// 	const fontSize = normalizeCssLength( rawFontSize, 'cta_font_size' );
// 	const callToAction = String( ctx.cta ?? '' );

// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
// 	const css = `display: flex; justify-content: center; align-items: center; ${rtlCss} `;

// 	const { ctaCss, ctaClass, titleAttr } = iconCtaBlock( {
// 		ctaType,
// 		textColor,
// 		bgColor,
// 		fontSize,
// 		side2: ctx.side2,
// 		cta: callToAction,
// 	} );

// 	const svgCss = `pointer-events:none; display:block; height:${imgSize}; width:${imgSize};`;
// 	const iconHtml = squareIcon( imgSize, ctx.type || 'chat', svgCss );

// 	let hoverStyle = '';
// 	if ( ctaType === 'hover' ) {
// 		hoverStyle = `<style>${hoverRevealRule( 'ctc_s_2' )}</style>`;
// 	}

// 	const html = `${hoverStyle}<div ${titleAttr} style="${escapeAttr( css )}" class="ctc-analytics ctc_s_2">
// 	<p class="ctc-analytics ctc_cta ctc_cta_stick ${escapeAttr( ctaClass )}" style="${escapeAttr( ctaCss )}">${escapeHTML( callToAction )}</p>
// 	${iconHtml}
// </div>`;

// 	return { html };
// }
