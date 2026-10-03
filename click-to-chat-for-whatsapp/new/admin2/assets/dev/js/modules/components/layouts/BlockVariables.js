import { applyConditionalAttributes, escapeHTML, copyToClipboard } from '../../core/Utils.js';

/**
 * field_type: block_variables
 *
 * Renders a reference of template variables as a responsive grid of tiles —
 * token chip on top, description below — matching the feature-box design.
 *
 * Free tiles are click-to-copy. PRO tiles are informational teasers (crown
 * badge, not copyable) for variables that only work in the PRO version.
 *
 * Field config:
 *   title     - optional heading (default: 'Variables')
 *   badge     - optional badge shown next to the title (e.g. 'PRO')
 *   note      - optional muted line under the grid
 *   pro       - when true, every tile defaults to the PRO (non-copyable) style
 *   variables - object map of token => description, where the value is either
 *               a string, or an object { desc, pro } to override per token.
 *               e.g. { '{product}': 'Product name' }
 *                    { '{time}': { desc: 'Click time', pro: true } }
 */
export const createBlockVariables = ( field ) => {
	const el = document.createElement( 'div' );
	el.className = `block-variables ${field.class_pr || ''}`.trim();
	if ( field.id ) { el.id = field.id; }

	// Corner cues come from the shared sprite (HT_CTC_Icons) via <use>.
	const icon = ( name, cls ) => `<svg class="ctc-icon ${cls}" aria-hidden="true">` +
		`<use href="#ctc-icon-${name}"></use></svg>`;
	const copyIcon = icon( 'copy', 'variable-copy-icon' );
	const checkIcon = icon( 'check', 'variable-check-icon' );
	const crownIcon = icon( 'crown', 'variable-pro-icon' );

	const proDefault = !! field.pro;
	const variables = field.variables || {};

	const items = Object.entries( variables )
		.map( ( [ token, value ] ) => {
			const isObj = value && typeof value === 'object';
			const label = isObj ? ( value.desc || '' ) : value;
			const isPro = ( isObj && value.pro !== undefined ) ? !! value.pro : proDefault;
			const safeToken = escapeHTML( token );
			const safeLabel = escapeHTML( label );

			if ( isPro ) {
				// Informational teaser — not a copy target.
				return '<div class="variable-tile is-pro" data-tip="PRO feature"' +
					` aria-label="${safeToken} — ${safeLabel} (PRO feature)">` +
					`<code>${safeToken}</code>` +
					`<span class="variable-desc">${safeLabel}</span>` +
					`<span class="variable-tile-cue">${crownIcon}</span>` +
					'</div>';
			}

			return `<button type="button" class="variable-tile" data-token="${safeToken}"` +
				' data-tip="Click to copy">' +
				`<code>${safeToken}</code>` +
				`<span class="variable-desc">${safeLabel}</span>` +
				`<span class="variable-tile-cue">${copyIcon}${checkIcon}</span>` +
				'</button>';
		} )
		.join( '' );

	const title = field.title || 'Variables';
	const badge = field.badge ?
		`<span class="variables-badge">${escapeHTML( field.badge )}</span>` :
		'';
	const note = field.note ?
		`<p class="variables-note">${escapeHTML( field.note )}</p>` :
		'';

	applyConditionalAttributes( el, field );

	// eslint-disable-next-line no-unsanitized/property -- static wrapper; tokens and labels are escaped above
	el.innerHTML = `
        <span class="variables-header">
            <span class="variables-title">${escapeHTML( title )}</span>${badge}
        </span>
        <div class="variables-grid">${items}</div>
        ${note}
        <span class="screen-reader-text" aria-live="polite"></span>
    `;

	const liveRegion = el.querySelector( '[aria-live]' );

	// Click a free tile to copy its token; PRO teaser tiles have no data-token.
	el.addEventListener( 'click', ( event ) => {
		const tile = event.target.closest( '.variable-tile' );
		if ( ! tile || ! tile.dataset.token ) { return; }
		copyToClipboard( tile.dataset.token )
			.then( () => {
				const chip = tile.querySelector( 'code' );
				tile.classList.add( 'copied' );
				const original = chip.textContent;
				chip.textContent = 'Copied';
				liveRegion.textContent = `${original} copied to clipboard`;
				setTimeout( () => {
					chip.textContent = original;
					tile.classList.remove( 'copied' );
				}, 900 );
			} )
			.catch( () => {
				// Clipboard unavailable; tile stays as-is.
			} );
	} );

	return el;
};
