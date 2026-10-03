import { __ } from '@wordpress/i18n';
import { PanelBody, TextControl } from '@wordpress/components';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

import ColorPickerWithLabel from '../editor-components/color-picker-with-label';

export default function Edit( { attributes, setAttributes } ) {
	const label = attributes.label || __( 'Review', 'customer-reviews-woocommerce' );

	return (
		<div { ...useBlockProps( { style: { textAlign: 'center' } } ) }>
			<InspectorControls key="setting">
				<PanelBody title={ __( 'Review Button Settings', 'customer-reviews-woocommerce' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Label', 'customer-reviews-woocommerce' ) }
						value={ attributes.label }
						placeholder={ __( 'Review', 'customer-reviews-woocommerce' ) }
						onChange={ ( value ) => setAttributes( { label: value } ) }
					/>
					<TextControl
						label={ __( 'Border Radius', 'customer-reviews-woocommerce' ) }
						value={ attributes.radius }
						onChange={ ( value ) => setAttributes( { radius: value } ) }
					/>
					<ColorPickerWithLabel
						label={ __( 'Background Color', 'customer-reviews-woocommerce' ) }
						color={ attributes.bg }
						disableAlpha={ true }
						onChange={ ( value ) => setAttributes( { bg: value.hex } ) }
					/>
					<ColorPickerWithLabel
						label={ __( 'Text Color', 'customer-reviews-woocommerce' ) }
						color={ attributes.color }
						disableAlpha={ true }
						onChange={ ( value ) => setAttributes( { color: value.hex } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<span
				style={ {
					display: 'inline-block',
					padding: '12px 24px',
					backgroundColor: attributes.bg,
					color: attributes.color,
					borderRadius: attributes.radius,
				} }
			>
				{ label }
			</span>
		</div>
	);
}
