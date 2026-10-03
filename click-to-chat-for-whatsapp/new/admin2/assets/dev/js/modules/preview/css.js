/**
 * Preview CSS helpers
 */

/**
 * Sanitize a value interpolated into a <style> block.
 * Strips characters that could close the style tag or open new rules,
 * while keeping normal CSS values (colors, sizes, %) intact.
 *
 * @param {string} value
 * @returns {string}
 */
export const escapeCssValue = ( value ) => {
	return String( value ?? '' )
		.replace( /[<>{}]/g, '' );
};

// ============================================================================
// OUR WORKING IMPLEMENTATION (COMMENTED OUT) - PHP PARITY LOGIC
// ============================================================================
// Research findings:
// - Keep the feature-branch escapeCssValue() above active for branch parity.
// - normalizeCssLength() is required only by the commented PHP-parity
//   candidates until those candidates are approved for activation.
// - PHP is_numeric() accepts signed decimals and scientific notation; the
//   candidate numeric check below mirrors that behavior more closely.
// - A single helper cannot safely handle both CSS values and complete CSS
//   declaration fragments. Current callers that pass static declarations
//   (for example Style 8 iconCss) must stop doing so before activating the
//   hardened value-only escapeCssValue() candidate.
// - URLs must use safeUrl()/URL validation, not a CSS-value sanitizer.
// - The active escapeCssValue() strips only angle/brace characters and does
//   not block semicolon injection, quotes, comments, url(), expression(), or
//   dangerous schemes in unsaved live values.
//
// /**
//  * CSS normalization utilities for preview rendering.
//  *
//  * Mirrors PHP ctc_sanitize_normalize_css_suffix() behavior.
//  */
//
// const CSS_DEFAULTS = {
// 	s5_img_height: '70px',
// 	s5_img_width: '70px',
// 	s5_content_height: '70px',
// 	s5_content_width: '270px',
// 	s7_icon_size: '24px',
// 	s7_border_size: '12px',
// 	s7_border_radius: '4px',
// 	side_1_value: '0px',
// 	side_2_value: '0px',
// 	mobile_side_1_value: '0px',
// 	mobile_side_2_value: '0px',
// };
//
// const ALLOWED_UNITS = [ 'px', 'em', 'rem', '%', 'vh', 'vw' ];
//
// /**
//  * Normalizes a CSS dimension value according to the PHP sanitizer rules.
//  *
//  * @param {string|number} value Raw value.
//  * @param {string} [key=''] Field key, used for defaults.
//  * @returns {string} Normalized CSS dimension.
//  */
// export function normalizeCssLength( value, key = '' ) {
// 	let str = String( value ?? '' ).replace( /\s+/g, '' );
//
// 	if ( str === '' ) {
// 		return CSS_DEFAULTS[ key ] || '';
// 	}
//
// 	// PHP is_numeric-compatible number, including .5 and scientific notation.
// 	if ( /^[-+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?$/i.test( str ) ) {
// 		return str + 'px';
// 	}
//
// 	// Number + unit
// 	const match = str.match( /^(-?\d+(?:\.\d+)?)([a-z%]+)$/i );
// 	if ( match ) {
// 		const number = match[ 1 ];
// 		const unit = match[ 2 ].toLowerCase();
// 		return ALLOWED_UNITS.includes( unit ) ? number + unit : number + 'px';
// 	}
//
// 	// Garbage fallback
// 	return '0px';
// }
//
// /**
//  * Sanitize one CSS property value for interpolation into a style block.
//  * This is deliberately not valid for a declaration list or URL.
//  *
//  * @param {string|number} value Candidate property value.
//  * @returns {string} Safe property value, or blank when dangerous.
//  */
// export const escapeCssValue = ( value ) => {
// 	const str = String( value ?? '' ).trim();
// 	if ( /(?:url\s*\(|expression\s*\(|@import|javascript\s*:|data\s*:|behavior\s*:)/i.test( str ) ) {
// 		return '';
// 	}
// 	return str
// 		.replace( /[\u0000-\u001f\u007f<>{};'"`\\]/g, '' )
// 		.replace( /\/\*|\*\//g, '' );
// };
