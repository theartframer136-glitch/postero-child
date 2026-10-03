import { applyConditionalAttributes, getNestedValue, escapeHTML, escapeAttr, safeUrl } from '../../core/Utils.js';

/**
 * field_type: block_grid_select
 *
 * Creates a BlockGridSelect layout component.
 *
 * Layout Concept:
 * - A grid-based card select container (e.g. template selectors with icons/descriptions).
 * - Displays options in a grid where the user can click to select a layout/template option.
 *
 * Example structure:
 * 'fields' => array(
 *     array(
 *         'field_type' => 'block_grid_select',
 *         'id' => 'greetings_template',
 *         'options' => array(
 *             array( 'value' => 'greetings-1', 'name' => 'Greetings-1', ... )
 *         )
 *     )
 * )
 */
export const createBlockGridSelect = ( field, config ) => {

	const classPr = field.class_pr || '';
	const inputClass = field.class_field || '';
	const group = document.createElement( 'div' );
	group.className = `form-group todo-form-group ${classPr}`;

	applyConditionalAttributes( group, field );
	if ( field.label ) {
		// eslint-disable-next-line no-unsanitized/property -- Contains static HTML/Safely escaped dynamic values
		group.innerHTML = `<label>${escapeHTML( field.label )}</label>`;
	}

	const grid = document.createElement( 'div' );
	grid.className = `grid ${classPr}`;

	const optionGroupName = field.option_group;
	let value = getNestedValue( config.initialSettings, optionGroupName, field.id ) || field.default || '';

	if ( field.options && field.options.length > 0 ) {
		const valueExists = field.options.some( opt => {
			const isDefined = opt.value !== undefined && opt.value !== null;
			return isDefined && String( opt.value ) === String( value );
		} );
		if ( ! valueExists ) {
			const defaultExists = field.options.some( opt => {
				const isDefined = opt.value !== undefined && opt.value !== null;
				return isDefined && String( opt.value ) === String( field.default );
			} );
			if ( field.default && defaultExists ) {
				value = field.default;
			} else {
				const firstValidOpt = field.options.find( opt => {
					return opt.value !== undefined && opt.value !== null;
				} );
				if ( firstValidOpt ) {
					value = firstValidOpt.value;
				}
			}
		}
	}

	if ( field.id && optionGroupName ) {
		const hiddenInput = document.createElement( 'input' );
		hiddenInput.type = 'hidden';
		hiddenInput.id = field.id;
		hiddenInput.name = `${optionGroupName}[${field.id}]`;
		hiddenInput.className = inputClass;
		hiddenInput.value = value;
		group.appendChild( hiddenInput );
	}

	if ( field.options ) {
		field.options.forEach( opt => {
			const optDiv = document.createElement( 'div' );
			const isSelected = String( opt.value ) === String( value );

			// is-locked = display-only behaviour (no value sync). pro-option = PRO styling/badge.
			// Currently every locked option is a PRO upsell, but the two are kept separate so a
			// non-PRO locked option can reuse is-locked without the PRO treatment.
			const isLocked = opt.locked || opt.pro;
			optDiv.className = `grid-option ctc-sync-source-click ${isSelected ? 'selected' : ''} ${isLocked ? 'is-locked' : ''} ${opt.pro ? 'pro-option' : ''}`;
			optDiv.setAttribute( 'data-value', opt.value );

			const targetSelector = classPr ?
				`.${classPr.trim()
					.replace( /\s+/g, '.' )} #${field.id}` :
				`#${field.id}`;
			optDiv.setAttribute( 'data-sync-target', targetSelector );

			optDiv.setAttribute( 'role', 'button' );
			optDiv.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
			optDiv.setAttribute( 'tabindex', '0' );

			if ( opt.description || opt.name ) {
				// The crown overlay is decorative (aria-hidden), so fold the PRO state into
				// the accessible name/tooltip now that the visible "PRO" badge is gone.
				const a11yLabel = ( opt.description || opt.name ) + ( opt.pro ? ' (Pro feature)' : '' );
				optDiv.setAttribute( 'title', a11yLabel );

				// optDiv.setAttribute( 'data-tip', a11yLabel );
				optDiv.setAttribute( 'aria-label', a11yLabel );
			}

			const isIconUrl = opt.icon && ( opt.icon.startsWith( 'http' ) || opt.icon.startsWith( '/' ) || opt.icon.startsWith( 'data:' ) );

			const iconHtml = ( opt.icon || opt.text ) ?
				`
                <div class="grid-icon ${escapeAttr( opt.class_field || '' )}">
                    ${opt.icon ? ( isIconUrl ? `<img src="${escapeAttr( safeUrl( opt.icon ) )}" class="icon-img" alt="">` : `<i class="${escapeAttr( opt.icon )}"></i>` ) : ''}
                    ${opt.text ? `<span>${escapeHTML( opt.text )}</span>` : ''}
                </div>
            ` :
				'';

			const subText = opt.sub_text || opt.description || '';
			let nameHtml = `<span class="grid-name-title">${escapeHTML( opt.name )}</span>`;
			if ( subText ) {
				nameHtml += `<span class="grid-name-desc">${escapeHTML( subText )}</span>`;
			}

			// eslint-disable-next-line no-unsanitized/property -- Static HTML template; dynamic values are safely escaped
			optDiv.innerHTML = `
            <div class="grid-preview">
                <div class="grid-widget-preview" data-style-id="${escapeAttr( opt.value )}">
                    ${opt.image ? `<img data-src="${escapeAttr( safeUrl( opt.image ) )}" class="grid-image-preview lazy" loading="lazy" alt="${escapeAttr( opt.name )}">` : ''}
                    ${iconHtml}
                </div>
                ${opt.pro ? '<span class="pro-lock"><svg class="ctc-icon" aria-hidden="true"><use href="#ctc-icon-crown"></use></svg></span>' : ''}
            </div>
            <div class="grid-name">
                ${nameHtml}
            </div>
            `;

			if ( opt.image ) {
				const img = optDiv.querySelector( '.grid-image-preview' );
				if ( img ) {
					const onLoad = ( image ) => {
						image.classList.remove( 'lazy' );
						image.classList.add( 'loaded' );
					};

					if ( 'IntersectionObserver' in window ) {
						const observer = new IntersectionObserver( ( entries, obs ) => {
							entries.forEach( entry => {
								if ( entry.isIntersecting ) {
									const lazyImage = entry.target;
									lazyImage.src = lazyImage.dataset.src;
									lazyImage.onload = () => onLoad( lazyImage );
									obs.unobserve( lazyImage );
								}
							} );
						} );
						observer.observe( img );
					} else {
						img.src = img.dataset.src;
						onLoad( img );
					}
				}
			}
			grid.appendChild( optDiv );
		} );

		grid.addEventListener( 'keydown', ( event ) => {
			const option = document.activeElement.closest( '.grid-option' );
			if ( ! option ) { return; }
			if ( [ ' ' ].includes( event.key ) ) {
				event.preventDefault();
				option.click();
			}
		} );
	}

	group.appendChild( grid );

	if ( field.help ) {
		const helpP = document.createElement( 'p' );
		helpP.className = 'help-text';
		// eslint-disable-next-line no-unsanitized/property -- Help text is defined in PHP and can contain safe HTML
		helpP.innerHTML = field.help;
		group.appendChild( helpP );
	}

	return group;
};
