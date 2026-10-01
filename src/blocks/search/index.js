/**
 * The *Hotel search bar* block (M04, 4.1). Dynamic: PHP renders it
 * (Frontend\Embeds::search(), the same template as `[rtbp_search]`), so the
 * editor shows a placeholder and saves nothing but the block comment.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'radius-hotel-booking/search', {
	apiVersion: 3,
	title: __( 'Hotel search bar', 'radius-hotel-booking' ),
	description: __(
		'Arrival, departure and guests, then Find a room — sends guests to the booking page.',
		'radius-hotel-booking'
	),
	category: 'radius-hotel-booking',
	icon: 'search',
	keywords: [ 'hotel', 'booking', 'room', 'search' ],
	supports: { html: false, multiple: true },
	edit: function Edit() {
		return (
			<div { ...useBlockProps() }>
				<Placeholder
					icon="search"
					label={ __( 'Hotel search bar', 'radius-hotel-booking' ) }
					instructions={ __(
						'Guests pick their dates and party here, then go to the booking page (Settings → Public booking).',
						'radius-hotel-booking'
					) }
				/>
			</div>
		);
	},
	save: () => null,
} );
