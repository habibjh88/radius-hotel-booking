/**
 * The *Hotel booking* block (M04, 4.3–4.10). Dynamic: PHP renders it
 * (Frontend\Embeds::booking(), the same template as `[rtbp_booking]`), so the
 * editor shows a placeholder and saves nothing but the block comment.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
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
	keywords: [ 'hotel', 'booking', 'room', 'book' ],
	supports: { html: false, multiple: true },
	edit: function Edit() {
		return (
			<div { ...useBlockProps() }>
				<Placeholder
					icon="calendar-alt"
					label={ __( 'Hotel booking', 'radius-hotel-booking' ) }
					instructions={ __(
						'Guests see the rooms free for their dates here and book. Put it on the booking page (Settings → Public booking).',
						'radius-hotel-booking'
					) }
				/>
			</div>
		);
	},
	save: () => null,
} );
