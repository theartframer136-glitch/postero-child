/**
 * Preview Template — Style 6 (plain text link)
 *
 * JS port of new/inc/styles/style-6.php. The PHP template uses inline
 * onmouseover/onmouseout handlers; the preview replicates the same hover
 * effect with a scoped :hover rule instead (visually identical).
 */
import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { escapeCssValue } from '../css.js';

export default function renderStyle6 ( ctx ) {
	const txtColor = ctx.value( 'ht_ctc_s6', 's6_txt_color' ) || '';
	const txtColorOnHover = ctx.value( 'ht_ctc_s6', 's6_txt_color_on_hover' ) || '';
	const txtDecoration = ctx.value( 'ht_ctc_s6', 's6_txt_decoration' ) ?? 'none';
	const txtDecorationOnHover = ctx.value( 'ht_ctc_s6', 's6_txt_decoration_on_hover' ) ?? 'underline';

	const callToAction = ctx.cta || 'WhatsApp us';

	let styles = '';
	if ( txtColor !== '' ) { styles += `color: ${txtColor}; `; }
	if ( txtDecoration !== '' ) { styles += `text-decoration: ${txtDecoration}; `; }

	let hoverStyles = '';
	if ( txtColorOnHover !== '' ) { hoverStyles += `color: ${escapeCssValue( txtColorOnHover )} !important; `; }
	if ( txtDecorationOnHover !== '' ) { hoverStyles += `text-decoration: ${escapeCssValue( txtDecorationOnHover )} !important; `; }

	const hoverRule = ( hoverStyles !== '' ) ? `<style>.ht-ctc .ctc_s_6:hover{${hoverStyles}}</style>` : '';

	return `${hoverRule}
	<a class="ctc_s_6 ctc_cta" style="${escapeAttr( styles )}">${escapeHTML( callToAction )}</a>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Research findings against new/inc/styles/style-6.php and its parent renderers:
// - The earlier blank-CTA finding was incorrect. Parent chat/group/share code
//   assigns a fallback before including Style 6: "WhatsApp us" for chat and
//   "WhatsApp Share" for group/share.
// - PreviewManager must expose ctx.type and feature-specific settings before
//   the non-chat fallback can be selected correctly.
// - CSS :hover is safer than PHP's inline event handlers. Explicitly empty
//   hover values need inherit/initial so they clear the base inline values.
// - Preserve explicit empty colors/decorations and sanitize CSS values.
// - Restore ctc-analytics on the anchor; PHP has no href on this element.
// - The active branch imports escapeCssValue, but current local css.js does
//   not export it, so Style 6 cannot currently be imported.
// export default function renderStyle6 ( ctx ) {
// 	const txtColor = escapeCssValue( ctx.value( 'ht_ctc_s6', 's6_txt_color' ) ?? '' );
// 	const txtColorOnHover = escapeCssValue( ctx.value( 'ht_ctc_s6', 's6_txt_color_on_hover' ) ?? '' );
// 	const txtDecoration = escapeCssValue( ctx.value( 'ht_ctc_s6', 's6_txt_decoration' ) ?? 'none' );
// 	const txtDecorationOnHover = escapeCssValue( ctx.value( 'ht_ctc_s6', 's6_txt_decoration_on_hover' ) ?? 'underline' );

// 	let callToAction = String( ctx.cta ?? '' );
// 	if ( callToAction === '' ) {
// 		callToAction = ctx.type && ctx.type !== 'chat' ? 'WhatsApp Share' : 'WhatsApp us';
// 	}

// 	let styles = '';
// 	if ( txtColor !== '' ) { styles += `color: ${txtColor}; `; }
// 	if ( txtDecoration !== '' ) { styles += `text-decoration: ${txtDecoration}; `; }

// 	const hoverColor = txtColorOnHover === '' ? 'inherit' : txtColorOnHover;
// 	const hoverDecoration = txtDecorationOnHover === '' ? 'initial' : txtDecorationOnHover;
// 	const hoverStyles = `color: ${hoverColor} !important; text-decoration: ${hoverDecoration} !important; `;
// 	const hoverRule = `<style>.ht-ctc .ctc_s_6:hover{${hoverStyles}}</style>`;

// 	const html = `${hoverRule}
// 	<a class="ctc-analytics ctc_s_6 ctc_cta" style="${escapeAttr( styles )}">${escapeHTML( callToAction )}</a>`;

// 	return { html };
// }
