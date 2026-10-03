import { escapeHTML, escapeAttr } from '../../core/Utils.js';
import { createBaseWrapper, appendHelpText } from './BaseField.js';

export const renderSelect = ( field, config ) => {
	const { wrapper, value, name, inputClass } = createBaseWrapper( field, config, 'form-group' );

	let optionsHtml = '';

	if ( field.options ) {
		for ( const [ key, optLabel ] of Object.entries( field.options ) ) {
			const selected = ( String( value ) === String( key ) ) ? 'selected' : '';
			optionsHtml += `
                <option value="${escapeAttr( key )}" ${selected}>
                    ${escapeHTML( optLabel )}
                </option>`;
		}
	}

	// eslint-disable-next-line no-unsanitized/property -- Contains static HTML/Safely escaped dynamic values
	wrapper.innerHTML = `
        <label for="${escapeAttr( field.id )}">${escapeHTML( field.label || '' )}</label>
        <div class="select-wrapper">
            <select 
                id="${escapeAttr( field.id )}" 
                name="${escapeAttr( name )}" 
                class="${escapeAttr( inputClass )}"
            >${optionsHtml}</select>
        </div>
    `;

	appendHelpText( wrapper, field );

	return wrapper;
};
