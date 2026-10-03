import { escapeHTML, escapeAttr } from '../../core/Utils.js';
import { createBaseWrapper, appendHelpText } from './BaseField.js';

export const renderButton = ( field, config ) => {
	const { wrapper, inputClass } = createBaseWrapper( field, config, 'form-group' );

	const buttonClass = inputClass || 'button button-secondary';

	// eslint-disable-next-line no-unsanitized/property -- Contains static HTML/Safely escaped dynamic values
	wrapper.innerHTML = `
        <div class="field-button-wrapper">
            ${field.label ? `<label class="field-label">${escapeHTML( field.label )}</label>` : ''}
            <button 
                type="button" 
                id="${escapeAttr( field.id )}" 
                class="${escapeAttr( buttonClass )}"
            >
                ${escapeHTML( field.button_text || 'Click Here' )}
            </button>
        </div>
    `;

	appendHelpText( wrapper, field );

	return wrapper;
};
