import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * `radius-hotel-booking/items` — the example block.
 *
 * Attributes mirror the shortcode's, and the front end is rendered by the
 * block's PHP render_callback, so both paths produce identical markup.
 */
registerBlockType( 'radius-hotel-booking/items', {
	apiVersion: 3,
	title: __( 'Item List', 'radius-hotel-booking' ),
	description: __( 'Displays the published items.', 'radius-hotel-booking' ),
	category: 'radius-hotel-booking',
	icon: 'screenoptions',
	supports: { html: false },
	attributes: {
		layout: { type: 'string', default: 'grid' },
		columns: { type: 'number', default: 3 },
		perPage: { type: 'number', default: 9 },
	},

	edit: function Edit( { attributes, setAttributes } ) {
		const { layout, columns, perPage } = attributes;
		const blockProps = useBlockProps();

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Layout', 'radius-hotel-booking' ) }>
						<SelectControl
							label={ __( 'Layout', 'radius-hotel-booking' ) }
							value={ layout }
							options={ [
								{
									label: __( 'Grid', 'radius-hotel-booking' ),
									value: 'grid',
								},
								{
									label: __( 'List', 'radius-hotel-booking' ),
									value: 'list',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { layout: value } )
							}
							__nextHasNoMarginBottom
						/>
						<RangeControl
							label={ __( 'Columns', 'radius-hotel-booking' ) }
							value={ columns }
							min={ 1 }
							max={ 6 }
							onChange={ ( value ) =>
								setAttributes( { columns: value } )
							}
							__nextHasNoMarginBottom
						/>
						<RangeControl
							label={ __(
								'Items per page',
								'radius-hotel-booking'
							) }
							value={ perPage }
							min={ 1 }
							max={ 48 }
							onChange={ ( value ) =>
								setAttributes( { perPage: value } )
							}
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				</InspectorControls>

				<div { ...blockProps }>
					<div className="components-placeholder">
						<div className="components-placeholder__label">
							{ __( 'Item List', 'radius-hotel-booking' ) }
						</div>
						<div className="components-placeholder__instructions">
							{ __(
								'The published items render here on the front end.',
								'radius-hotel-booking'
							) }
						</div>
					</div>
				</div>
			</>
		);
	},

	// Rendered in PHP — see Blocks\BlockManager::render_items_block().
	save() {
		return null;
	},
} );
