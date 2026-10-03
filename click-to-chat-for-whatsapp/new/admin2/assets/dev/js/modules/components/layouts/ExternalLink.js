import { safeUrl } from '../../core/Utils.js';

/**
 * field_type: block_external_link
 *
 * Creates an ExternalLink layout component.
 */
export const createExternalLink = ( field ) => {
	const classPr = field.class_pr || '';
	const link = document.createElement( 'a' );
	link.href = safeUrl( field.url );

	// `external-link--block` marks a standalone doc link (own line) so it can be
	// styled as a secondary "resource" affordance, distinct from the inline
	// `.external-link` used within sentences/help text.
	link.className = `external-link external-link--block ${classPr}`.trim();
	link.target = '_blank';

	// link.rel = 'noopener noreferrer';
	// Icon trails the label: the external/"open elsewhere" glyph describes the
	// link's destination, so by convention it sits after the text (not before).
	// eslint-disable-next-line no-unsanitized/property -- Static HTML for link icon and label
	link.innerHTML = `${field.label || ''} <span class="${field.icon || 'dashicons dashicons-external'}"></span>`;
	return link;
};
