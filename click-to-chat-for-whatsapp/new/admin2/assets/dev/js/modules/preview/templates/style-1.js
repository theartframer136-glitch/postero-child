/**
 * Preview Template — Style 1
 *
 * Mirrors new/inc/styles/style-1.php. Style 1 uses the active theme's button
 * design on the frontend, so this template can only preview its configured
 * colors, icon, text, and mobile full-width rule.
 */
import { escapeAttr, escapeHTML } from '../../core/Utils.js';
import { singleColorIcon } from '../icons.js';

export default function renderStyle1 ( ctx ) {
	const textColor = ctx.value( 'ht_ctc_s1', 's1_text_color' ) ?? '';
	const bgColor = ctx.value( 'ht_ctc_s1', 's1_bg_color' ) ?? '';
	const addIcon = ctx.value( 'ht_ctc_s1', 's1_add_icon' ) ?? '';
	let iconColor = ctx.value( 'ht_ctc_s1', 's1_icon_color' ) ?? '';
	let iconSize = ctx.value( 'ht_ctc_s1', 's1_icon_size' ) ?? '';
	const mobileFullWidth = ctx.value( 'ht_ctc_s1', 's1_m_fullwidth' ) ?? '';

	if ( iconSize === '' ) {
		iconSize = '15';
	}
	if ( iconColor === '' ) {
		iconColor = '#ffffff';
	}

	let callToAction = String( ctx.cta ?? '' );
	if ( callToAction === '' ) {
		callToAction = 'WhatsApp us';
	}

	let buttonCss = 'cursor:pointer; display:flex; align-items:center; justify-content:center;';
	buttonCss += textColor !== '' ? `color:${textColor};` : '';
	buttonCss += bgColor !== '' ? `background-color:${bgColor};` : '';
	buttonCss += 'padding:5px 7px;';

	let icon = '';
	if ( addIcon !== '' ) {
		icon = singleColorIcon( {
			color: iconColor,
			iconSize,
			type: ctx.type || 'chat',
			svgCss: 'margin-right:6px;',
		} );
	}

	let fullWidthStyle = '';
	if ( mobileFullWidth !== '' ) {
		let css = '@media(max-width:1201px){';
		css += '.ht-ctc.style-1{left:unset !important;right:0px !important;}';
		css += '.ht-ctc.style-1,.ht-ctc .s1_btn{width:100%;}}';
		fullWidthStyle = `<style id="ht-ctc-s1">${css}</style>`;
	}

	const buttonStyle = escapeAttr( buttonCss );
	const html = `${fullWidthStyle}<button style="${buttonStyle}" ` +
		`class="ctc-analytics s1_btn ctc_s_1">
		${icon}
		<span class="ctc_cta">${escapeHTML( callToAction )}</span>
	</button>`;

	return {
		html,
		note: 'Style 1 inherits the active theme’s button design on the frontend.',
	};
}
