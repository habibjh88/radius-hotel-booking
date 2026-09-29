/**
 * Rate plan windows for display (booking-engine §2): the same rules as
 * Services\Availability\StayWindow, for previews and summaries only — the
 * server derives every real window.
 *
 *   describeWindow( { type: 'fixed', start_time: '20:00', end_time: '08:00' } )
 *   → "Check-in 20:00, check-out 08:00 the next day (12 h)"
 */
import { __, sprintf } from '@wordpress/i18n';

import { formatTime } from '@/lib/format';

/**
 * Minutes since midnight of `HH:MM`, or null.
 *
 * @param {string} time Time.
 * @return {number|null} Minutes.
 */
export function minutesOf( time ) {
	const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec( String( time ?? '' ) );
	return match ? Number( match[ 1 ] ) * 60 + Number( match[ 2 ] ) : null;
}

/**
 * A fixed window's length: (end − start) mod 24 h, where 0 means 24 h.
 *
 * @param {string} start `HH:MM`.
 * @param {string} end   `HH:MM`.
 * @return {number} Minutes (0 when a time is invalid).
 */
export function fixedMinutes( start, end ) {
	const from = minutesOf( start );
	const to = minutesOf( end );
	if ( from === null || to === null ) {
		return 0;
	}
	return ( to - from + 1440 ) % 1440 || 1440;
}

/**
 * `HH:MM` in the site's time format (12 h or 24 h).
 *
 * @param {string} time `HH:MM`.
 * @return {string} Formatted time.
 */
export function formatClock( time ) {
	return minutesOf( time ) === null
		? ''
		: formatTime( `2000-01-01 ${ time }` );
}

/**
 * The 48 half-hour times of a day, as Select options.
 *
 * @return {Array<{value: string, label: string}>} Options.
 */
export function halfHourOptions() {
	const options = [];
	for ( let minutes = 0; minutes < 1440; minutes += 30 ) {
		const value = `${ String( Math.floor( minutes / 60 ) ).padStart(
			2,
			'0'
		) }:${ String( minutes % 60 ).padStart( 2, '0' ) }`;
		options.push( { value, label: formatClock( value ) } );
	}
	return options;
}

/**
 * A length of time: "12 h", "8 h 30 min", "30 min", "2 days".
 *
 * @param {number} minutes Minutes.
 * @return {string} Text.
 */
export function formatDuration( minutes ) {
	const total = Math.max( 0, Math.round( Number( minutes ) || 0 ) );
	if ( total > 1440 && total % 1440 === 0 ) {
		/* translators: %d: number of days. */
		return sprintf( __( '%d days', 'radius-hotel-booking' ), total / 1440 );
	}
	const hours = Math.floor( total / 60 );
	const rest = total % 60;
	if ( ! hours ) {
		/* translators: %d: minutes. */
		return sprintf( __( '%d min', 'radius-hotel-booking' ), rest );
	}
	return rest
		? /* translators: 1: hours, 2: minutes. */
		  sprintf(
				__( '%1$d h %2$d min', 'radius-hotel-booking' ),
				hours,
				rest
		  )
		: /* translators: %d: hours. */
		  sprintf( __( '%d h', 'radius-hotel-booking' ), hours );
}

/**
 * The window a plan sells, in one sentence.
 *
 * @param {Object} plan Plan fields (`type`, times, `duration_minutes`, check-in range).
 * @return {string} Text ('' while incomplete).
 */
export function describeWindow( plan ) {
	if ( plan?.type === 'flexible' ) {
		const minutes = Number( plan.duration_minutes ) || 0;
		if (
			! minutes ||
			minutesOf( plan.checkin_from ) === null ||
			minutesOf( plan.checkin_until ) === null
		) {
			return '';
		}
		return sprintf(
			/* translators: 1: earliest check-in, 2: latest check-in, 3: stay length, e.g. "24 h". */
			__(
				'Check-in any time from %1$s to %2$s, check-out %3$s later',
				'radius-hotel-booking'
			),
			formatClock( plan.checkin_from ),
			formatClock( plan.checkin_until ),
			formatDuration( minutes )
		);
	}

	const minutes = fixedMinutes( plan?.start_time, plan?.end_time );
	if ( ! minutes ) {
		return '';
	}
	const nextDay = minutesOf( plan.end_time ) <= minutesOf( plan.start_time );
	return sprintf(
		nextDay
			? /* translators: 1: check-in time, 2: check-out time, 3: stay length, e.g. "12 h". */
			  __(
					'Check-in %1$s, check-out %2$s the next day (%3$s)',
					'radius-hotel-booking'
			  )
			: /* translators: 1: check-in time, 2: check-out time, 3: stay length, e.g. "8 h 30 min". */
			  __(
					'Check-in %1$s, check-out %2$s the same day (%3$s)',
					'radius-hotel-booking'
			  ),
		formatClock( plan.start_time ),
		formatClock( plan.end_time ),
		formatDuration( minutes )
	);
}
