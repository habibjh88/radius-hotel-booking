/**
 * The *Hotel booking* block (M04, 4.3–4.10). Dynamic: PHP renders it
 * (Frontend\Embeds::booking(), the same template as `[rtbp_booking]`), so the
 * editor shows a placeholder with the options in the sidebar.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'radius-hotel-booking/booking', {
	apiVersion: 3,
	title: __( 'Hotel booking', 'radius-hotel-booking' ),
	description: __(
		"The rooms free for the dates searched, then the guest's details and confirmation.",
		'radius-hotel-booking'
	),
	category: 'radius-hotel-booking',
	icon: 'calendar-alt',
	keywords: [ 'hotel', 'booking', 'room', 'book', 'popup', 'modal' ],
	// Defaults: Embeds::BOOKING_DEFAULTS.
	attributes: {
		layout: { type: 'string', default: 'onepage' },
		display: { type: 'string', default: 'inline' },
		buttonText: { type: 'string', default: '' },
		roomDetails: { type: 'string', default: 'popup' },
		roomCard: { type: 'string', default: 'split' },
		ratesShown: { type: 'number', default: 2 },
		priceBreakdown: { type: 'boolean', default: true },
	},
	supports: { html: false, multiple: true },
	edit: function Edit( { attributes, setAttributes } ) {
		const {
			layout,
			display,
			buttonText,
			roomDetails,
			roomCard,
			ratesShown,
			priceBreakdown,
		} = attributes;
		const modal = 'modal' === display;
		return (
			<div { ...useBlockProps() }>
				<InspectorControls>
					<PanelBody title={ __( 'Layout', 'radius-hotel-booking' ) }>
						<SelectControl
							label={ __( 'Layout', 'radius-hotel-booking' ) }
							value={ layout }
							options={ [
								{
									label: __(
										'Steps (one step at a time)',
										'radius-hotel-booking'
									),
									value: 'steps',
								},
								{
									label: __(
										'One page (everything on one page)',
										'radius-hotel-booking'
									),
									value: 'onepage',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { layout: value } )
							}
						/>
						<SelectControl
							label={ __( 'Display', 'radius-hotel-booking' ) }
							help={ __(
								'In a popup, a button opens the form. Any link to #rtbp-booking on the page opens it too.',
								'radius-hotel-booking'
							) }
							value={ display }
							options={ [
								{
									label: __(
										'On the page',
										'radius-hotel-booking'
									),
									value: 'inline',
								},
								{
									label: __(
										'In a popup, opened by a button',
										'radius-hotel-booking'
									),
									value: 'modal',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { display: value } )
							}
						/>
						{ modal ? (
							<TextControl
								label={ __(
									'Button text',
									'radius-hotel-booking'
								) }
								placeholder={ __(
									'Book now',
									'radius-hotel-booking'
								) }
								value={ buttonText }
								onChange={ ( value ) =>
									setAttributes( { buttonText: value } )
								}
							/>
						) : null }
						<SelectControl
							label={ __(
								'Room details',
								'radius-hotel-booking'
							) }
							help={ __(
								'What "Room details" on each room type opens.',
								'radius-hotel-booking'
							) }
							value={ roomDetails }
							options={ [
								{
									label: __(
										'Its own page',
										'radius-hotel-booking'
									),
									value: 'page',
								},
								{
									label: __(
										'A popup, without leaving the form',
										'radius-hotel-booking'
									),
									value: 'popup',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { roomDetails: value } )
							}
						/>
						<SelectControl
							label={ __(
								'Room type design',
								'radius-hotel-booking'
							) }
							help={ __(
								'How each room type and its rates are laid out in the results.',
								'radius-hotel-booking'
							) }
							value={ roomCard }
							options={ [
								{
									label: __(
										'Classic (rates in rows under the room)',
										'radius-hotel-booking'
									),
									value: 'classic',
								},
								{
									label: __(
										'Split (room on the left, rate cards on the right)',
										'radius-hotel-booking'
									),
									value: 'split',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { roomCard: value } )
							}
						/>
						<RangeControl
							label={ __(
								'Rates shown first',
								'radius-hotel-booking'
							) }
							help={ __(
								'Rates per room type before "Show more rates". 0 shows them all, without the button.',
								'radius-hotel-booking'
							) }
							value={ ratesShown }
							min={ 0 }
							max={ 20 }
							onChange={ ( value ) =>
								setAttributes( { ratesShown: value ?? 2 } )
							}
						/>
						<ToggleControl
							label={ __(
								'Price breakdown',
								'radius-hotel-booking'
							) }
							help={ __(
								'An info icon next to each price opens how it is made: the stay, any discount and the total.',
								'radius-hotel-booking'
							) }
							checked={ priceBreakdown }
							onChange={ ( value ) =>
								setAttributes( { priceBreakdown: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<Placeholder
					icon="calendar-alt"
					label={ __( 'Hotel booking', 'radius-hotel-booking' ) }
					instructions={
						modal
							? __(
									'A button that opens the booking form in a popup. Change the text and layout in the block settings.',
									'radius-hotel-booking'
							  )
							: __(
									'Guests see the rooms free for their dates here and book. Put it on the booking page (Settings → Public booking).',
									'radius-hotel-booking'
							  )
					}
				/>
			</div>
		);
	},
	save: () => null,
} );
