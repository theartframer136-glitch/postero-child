/**
 * Preview Template — Style 8 (button with icon)
 *
 * JS port of new/inc/styles/style-8.php. Class names, inline CSS, and option
 * defaults must stay in sync with the PHP template.
 */
import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { singleColorIcon } from '../icons.js';
import { escapeCssValue } from '../css.js';

export default function renderStyle8 ( ctx ) {
	const opt = ( key, fallback ) => {
		const value = ctx.value( 'ht_ctc_s8', key );
		return ( value === '' || value === undefined || value === null ) ? fallback : value;
	};

	const iconColor = opt( 's8_icon_color', '#ffffff' );
	const iconColorOnHover = opt( 's8_icon_color_on_hover', '#ffffff' );
	const txtColor = opt( 's8_txt_color', '#ffffff' );
	const txtColorOnHover = opt( 's8_txt_color_on_hover', '#ffffff' );
	const bgColor = opt( 's8_bg_color', '#26a69a' );
	const bgColorOnHover = opt( 's8_bg_color_on_hover', '#26a69a' );

	const iconPosition = opt( 's8_icon_position', 'left' );
	const btnSize = opt( 's8_btn_size', 'btn' );
	const iconSize = opt( 's8_icon_size', '16px' );

	const textSize = ctx.value( 'ht_ctc_s8', 's8_text_size' ) ?? '16px';
	const textSizeCss = ( textSize === '' ) ? '' : `font-size: ${textSize};`;

	const callToAction = ctx.cta || 'WhatsApp us';

	const height = ( btnSize === 'btn-large' ) ? '54px' : '36px';

	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';

	const iconCss = ( iconPosition === 'right' ) ? 'order:1;margin-left:15px;' : 'order:0;margin-right:15px;';

	const textCss = `height: 100%; color:${txtColor}; ${textSizeCss} `;

	const mainSpanCss = [
		'display: flex;',
		rtlCss,
		'padding: 0 2rem;',
		'letter-spacing: .5px;',
		'transition: .2s ease-out;',
		'text-align:center;',
		'justify-content: center;',
		'align-items: center;',
		'border-radius:4px;',
		`height:${height};`,
		`line-height:${height};`,
		'vertical-align:middle;',
		'box-shadow:0 2px 2px 0 rgba(0,0,0,.14), ' +
			'0 1px 5px 0 rgba(0,0,0,.12), ' +
			'0 3px 1px -2px rgba(0,0,0,.2);',
		'box-sizing:inherit;',
		`background-color:${bgColor};`,
		'overflow:hidden;',
	].join( ' ' );

	const iconCssVal = escapeCssValue( iconCss );
	const iconColorOnHoverVal = escapeCssValue( iconColorOnHover );
	const txtColorOnHoverVal = escapeCssValue( txtColorOnHover );
	const bgColorOnHoverVal = escapeCssValue( bgColorOnHover );

	const hoverStyles = [
		`.ht-ctc-style-8 .s_8 .s_8_icon{${iconCssVal};}`,
		'.ht-ctc .ht-ctc-style-8:hover .s_8 svg g path{' +
			`fill:${iconColorOnHoverVal} !important;}`,
		'.ht-ctc .ht-ctc-style-8:hover .s_8 .ht-ctc-s8-text{' +
			`color:${txtColorOnHoverVal} !important;}`,
		'.ht-ctc .ht-ctc-style-8:hover .s_8{',
		'box-shadow: 0 3px 3px 0 rgba(7,6,6,.14), ',
		'0 1px 7px 0 rgba(0,0,0,.12), ',
		'0 3px 1px -1px rgba(0,0,0,.2) !important; ',
		'transition: .2s ease-out !important; ',
		`background-color:${bgColorOnHoverVal} !important; }`,
	].join( '' );

	let icon = '';
	if ( iconPosition !== 'hide' ) {
		icon = singleColorIcon( {
			color: iconColor,
			iconSize,
			type: 'chat',
			svgCss: 'display:block;',
		} );
	}

	return `<style id="ht-ctc-s8">${hoverStyles}</style>
	<div class="ht-ctc-style-8 ctc_s_8">
		<span class="s_8" style="${escapeAttr( mainSpanCss )}">
		<span class="s_8_icon">${icon}</span>
		<span class="ht-ctc-s8-text s8_span ctc_cta" style="${escapeAttr( textCss )}">
			${escapeHTML( callToAction )}
		</span>
		</span>
	</div>`;
}

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Required if this candidate replaces the branch implementation:
// import { normalizeCssLength } from '../css.js';
//
// Research findings against new/inc/styles/style-8.php and parent renderers:
// - Earlier blank-CTA research was incorrect. Parent renderers assign
//   "WhatsApp us" for chat and "WhatsApp Share" for group/share.
// - Preserve explicit empty colors, icon position, and button size.
// - Icon size falls back to 16px for missing or empty values; text size uses
//   16px only when missing and omits font-size when explicitly empty.
// - The mobile-full-width checkbox follows key-presence (isset) semantics.
// - Selected preview device does not change browser media-query width, so
//   manager/wrapper device handling is still required for reliable preview.
// - Restore PHP's three analytics classes and use ctx.type for the SVG ID.
// - singleColorIcon forces white for explicit empty color, unlike PHP.
// - PreviewManager must expose ctx.type and feature-specific settings.
// - The active branch imports escapeCssValue, but current local css.js does
//   not export it, so Style 8 cannot currently be imported.
// export default function renderStyle8 ( ctx ) {
// 	const iconColor = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_icon_color' ) ?? '#ffffff' );
// 	const iconColorOnHover = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_icon_color_on_hover' ) ?? '#ffffff' );
// 	const txtColor = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_txt_color' ) ?? '#ffffff' );
// 	const txtColorOnHover = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_txt_color_on_hover' ) ?? '#ffffff' );
// 	const bgColor = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_bg_color' ) ?? '#26a69a' );
// 	const bgColorOnHover = escapeCssValue( ctx.value( 'ht_ctc_s8', 's8_bg_color_on_hover' ) ?? '#26a69a' );

// 	const iconPosition = ctx.value( 'ht_ctc_s8', 's8_icon_position' ) ?? 'left';
// 	const btnSize = ctx.value( 'ht_ctc_s8', 's8_btn_size' ) ?? 'btn';

// 	const rawIconSize = ctx.value( 'ht_ctc_s8', 's8_icon_size' );
// 	let iconSize = rawIconSize === undefined || rawIconSize === null ?
// 		'16px' :
// 		normalizeCssLength( rawIconSize, 's8_icon_size' );
// 	if ( iconSize === '' ) {
// 		iconSize = '16px';
// 	}

// 	const rawTextSize = ctx.value( 'ht_ctc_s8', 's8_text_size' );
// 	const textSize = rawTextSize === undefined || rawTextSize === null ?
// 		'16px' :
// 		normalizeCssLength( rawTextSize, 's8_text_size' );
// 	const textSizeCss = ( textSize === '' ) ? '' : `font-size: ${textSize};`;

// 	let callToAction = String( ctx.cta ?? '' );
// 	if ( callToAction === '' ) {
// 		callToAction = ctx.type && ctx.type !== 'chat' ? 'WhatsApp Share' : 'WhatsApp us';
// 	}

// 	const height = ( btnSize === 'btn-large' ) ? '54px' : '36px';

// 	const rtlCss = ctx.isRtl ? 'flex-direction:row-reverse;' : '';
// 	const iconCss = ( iconPosition === 'right' ) ? 'order:1;margin-left:15px;' : 'order:0;margin-right:15px;';
// 	const textCss = `height: 100%; color:${txtColor}; ${textSizeCss} `;
// 	const mainSpanCss = `display: flex; ${rtlCss} padding: 0 2rem;letter-spacing: .5px;transition: .2s ease-out;text-align:center; justify-content: center;align-items: center;border-radius:4px;height:${height};line-height:${height};vertical-align:middle;box-shadow:0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12), 0 3px 1px -2px rgba(0,0,0,.2);box-sizing:inherit;background-color:${bgColor}; overflow:hidden;`;

// 	const fullwidthSetting = ctx.value( 'ht_ctc_s8', 's8_m_fullwidth' );
// 	const hasFullwidth = fullwidthSetting !== undefined && fullwidthSetting !== null;
// 	const fullwidthCss = hasFullwidth ? '@media(max-width:1201px){.ht-ctc.style-8{left:unset !important;right:0px !important;}.ht-ctc.style-8,.ht-ctc-style-8,.ht-ctc-style-8 .s_8{width: 100%;}}' : '';

// 	const hoverStyles = `.ht-ctc-style-8 .s_8 .s_8_icon{${escapeCssValue( iconCss )};}.ht-ctc .ht-ctc-style-8:hover .s_8 svg g path{fill:${iconColorOnHover} !important;}.ht-ctc .ht-ctc-style-8:hover .s_8 .ht-ctc-s8-text{color:${txtColorOnHover} !important;}.ht-ctc .ht-ctc-style-8:hover .s_8{box-shadow: 0 3px 3px 0 rgba(7,6,6,.14), 0 1px 7px 0 rgba(0,0,0,.12), 0 3px 1px -1px rgba(0,0,0,.2) !important; transition: .2s ease-out !important; background-color:${bgColorOnHover} !important; }${fullwidthCss}`;

// 	let icon = '';
// 	if ( iconPosition !== 'hide' ) {
// 		icon = singleColorIcon( {
// 			color: iconColor,
// 			iconSize,
// 			type: ctx.type || 'chat',
// 			svgCss: 'display:block;',
// 		} );
// 	}

// 	const html = `<style id="ht-ctc-s8">${hoverStyles}</style>
// 	<div class="ht-ctc-style-8 ctc_s_8 ctc-analytics">
// 		<span class="s_8 ctc-analytics" style="${escapeAttr( mainSpanCss )}">
// 		<span class="s_8_icon">${icon}</span>
// 		<span class="ht-ctc-s8-text s8_span ctc-analytics ctc_cta" style="${escapeAttr( textCss )}">${escapeHTML( callToAction )}</span>
// 		</span>
// 	</div>`;

// 	return { html };
// }
