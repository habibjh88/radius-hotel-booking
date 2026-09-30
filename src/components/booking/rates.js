/**
 * Small helpers for rates in the booking flow.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

import { formatDate, formatTime, siteNowTime, siteToday } from '@/lib/format';

/**
 * The legacy default check-in for 24-hour stays (legacy-reference, module 2).
 */
export const DEFAULT_CHECKIN = '08:00';

/**
 * The local date part of an ISO date-time (`2026-10-03T08:00:00+00:00` → `2026-10-03`):
 * the engine sends local times with their offset.
 *
 * @param {string} iso ISO 8601.
 * @return {string} `Y-m-d`.
 */
export const localDate = ( iso ) => String( iso || '' ).slice( 0, 10 );

/**
 * How a window reads on a card: `08:30–17:00`, `20:00 → 08:00` across
 * midnight, or `02/10/2026 20:00 → 04/10/2026 08:00 · 2 nights` over several units.
 *
 * @param {Object} window `{ start, end, units, crosses_midnight }`.
 * @return {string} Label.
 */
export function windowLabel( window ) {
	if ( ! window ) {
		return '';
	}
	const times = window.crosses_midnight
		? sprintf(
				/* translators: 1: start time, 2: end time the next day. */
				__( '%1$s → %2$s', 'radius-hotel-booking' ),
				formatTime( window.start ),
				formatTime( window.end )
		  )
		: sprintf(
				/* translators: 1: start time, 2: end time. */
				__( '%1$s–%2$s', 'radius-hotel-booking' ),
				formatTime( window.start ),
				formatTime( window.end )
		  );
	if ( ( window.units || 1 ) <= 1 ) {
		return times;
	}
	return sprintf(
		/* translators: 1: start date and time, 2: end date and time, 3: number of nights or days. */
		__( '%1$s → %2$s · %3$s', 'radius-hotel-booking' ),
		`${ formatDate( window.start ) } ${ formatTime( window.start ) }`,
		`${ formatDate( window.end ) } ${ formatTime( window.end ) }`,
		// `minutes` is the whole stay: one unit of a 24-hour plan is a day.
		( window.minutes || 0 ) / window.units >= 1440
			? sprintf(
					/* translators: %d: number of 24-hour days. */
					_n(
						'%d day',
						'%d days',
						window.units,
						'radius-hotel-booking'
					),
					window.units
			  )
			: sprintf(
					/* translators: %d: number of nights. */
					_n(
						'%d night',
						'%d nights',
						window.units,
						'radius-hotel-booking'
					),
					window.units
			  )
	);
}

/**
 * The check-in time to start from for flexible plans: for today, the next
 * half hour (a walk-in checks in now); for a later day, 08:00. Kept inside
 * the plan's allowed range when one is known.
 *
 * @param {string}      arrival `Y-m-d`.
 * @param {Object|null} checkin `{ from, until }` of a flexible plan, or null.
 * @return {string} `HH:MM`.
 */
export function defaultCheckin( arrival, checkin = null ) {
	let time = DEFAULT_CHECKIN;
	if ( arrival === siteToday() ) {
		const [ h, m ] = siteNowTime().split( ':' ).map( Number );
		const next = Math.min(
			23 * 60 + 30,
			Math.ceil( ( h * 60 + m ) / 30 ) * 30
		);
		time = `${ String( Math.floor( next / 60 ) ).padStart(
			2,
			'0'
		) }:${ String( next % 60 ).padStart( 2, '0' ) }`;
	}
	if ( checkin ) {
		if ( time < checkin.from ) {
			return checkin.from;
		}
		if ( time > checkin.until ) {
			return checkin.until;
		}
	}
	return time;
}

/**
 * The check-in range of the flexible rates in a search result: the chosen
 * rate's when it is flexible, else the first flexible rate's; null when the
 * result has none (the time field is then hidden).
 *
 * @param {Object|undefined} data   Search result.
 * @param {Object|null}      chosen The chosen rate, if any.
 * @return {Object|null} `{ from, until }`.
 */
export function checkinRange( data, chosen ) {
	if ( chosen?.checkin ) {
		return chosen.checkin;
	}
	for ( const type of data?.room_types || [] ) {
		const flexible = ( type.rates || [] ).find( ( rate ) => rate.checkin );
		if ( flexible ) {
			return flexible.checkin;
		}
	}
	return null;
}
