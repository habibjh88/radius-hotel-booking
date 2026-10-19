/**
 * The *Hotel room types* block: the room type cards. Dynamic: PHP renders it
 * (Frontend\Embeds::rooms(), the same template as `[rtbp_rooms]`), so the
 * editor shows a placeholder with the options in the sidebar.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'radius-hotel-booking/rooms', {
	apiVersion: 3,
	title: __( 'Hotel room types', 'radius-hotel-booking' ),
	description: __(
		'Your room types as cards, with photo, guests, beds and "from" price.',
		'radius-hotel-booking'
	),
	category: 'radius-hotel-booking',
	icon: 'grid-view',
	keywords: [ 'hotel', 'rooms', 'room types', 'grid' ],
	attributes: {
		layout: { type: 'string', default: 'grid' },
		columns: { type: 'number', default: 3 },
		limit: { type: 'number', default: 0 },
		showCount: { type: 'boolean', default: false },
	},
	supports: { html: false, multiple: true },
	edit: function Edit( { attributes, setAttributes } ) {
		const { layout, columns, limit, showCount } = attributes;
		return (
			<div { ...useBlockProps() }>
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
						/>
						{ 'grid' === layout ? (
							<RangeControl
								label={ __(
									'Columns',
									'radius-hotel-booking'
								) }
								value={ columns }
								min={ 2 }
								max={ 4 }
								onChange={ ( value ) =>
									setAttributes( { columns: value || 3 } )
								}
							/>
						) : null }
						<RangeControl
							label={ __( 'Limit', 'radius-hotel-booking' ) }
							help={ __(
								'How many room types to show. 0 shows them all.',
								'radius-hotel-booking'
							) }
							value={ limit }
							min={ 0 }
							max={ 24 }
							onChange={ ( value ) =>
								setAttributes( { limit: value || 0 } )
							}
						/>
						<ToggleControl
							label={ __(
								'Show the number of rooms',
								'radius-hotel-booking'
							) }
							checked={ showCount }
							onChange={ ( value ) =>
								setAttributes( { showCount: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<Placeholder
					icon="grid-view"
					label={ __( 'Hotel room types', 'radius-hotel-booking' ) }
					instructions={ __(
						'Your active room types appear here as cards on the site. Change the layout in the block settings.',
						'radius-hotel-booking'
					) }
				/>
			</div>
		);
	},
	save: () => null,
} );
