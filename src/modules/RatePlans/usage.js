/**
 * Where a rate plan is used, in words (feature 7.10).
 */
import { _n, sprintf } from '@wordpress/i18n';

/**
 * "Sold by 2 room types · 1 upcoming booking", or ''.
 *
 * @param {Object} usage `{ room_types, bookings }`.
 * @return {string} Text.
 */
export function usageText( usage ) {
	const parts = [];
	if ( usage?.room_types ) {
		parts.push(
			sprintf(
				/* translators: %d: number of room types. */
				_n(
					'Sold by %d room type',
					'Sold by %d room types',
					usage.room_types,
					'radius-hotel-booking'
				),
				usage.room_types
			)
		);
	}
	if ( usage?.bookings ) {
		parts.push(
			sprintf(
				/* translators: %d: number of bookings. */
				_n(
					'%d upcoming booking',
					'%d upcoming bookings',
					usage.bookings,
					'radius-hotel-booking'
				),
				usage.bookings
			)
		);
	}
	return parts.join( ' · ' );
}
