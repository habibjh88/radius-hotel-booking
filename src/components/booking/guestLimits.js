/**
 * The arrivals a guest may pick on the website (M04, 4.8, 4.9) — the server's
 * own rules (`AvailabilityService`), mirrored so the date pickers disable what
 * the API would refuse anyway:
 * - today only while same-day booking is on **and** the cut-off has not been
 *   reached (the server refuses at `now >= cutoff`);
 * - no later than today + the booking window (0 = no limit).
 */
import { addDaysYmd, siteNowTime, siteToday } from '@/lib/format';

/**
 * @param {Object}  rules                   `radius_hotel_booking_site_param.booking`.
 * @param {number}  rules.bookingWindowDays Days ahead (0 = unlimited).
 * @param {boolean} rules.sameDayEnabled    Same-day bookings allowed.
 * @param {string}  rules.sameDayCutoff     `HH:MM`, site time.
 * @return {{min: string, max: string}} First and last arrival, `Y-m-d` ('' = no limit).
 */
export function guestDateLimits( rules = {} ) {
	const today = siteToday();
	const sameDayOpen =
		Boolean( rules.sameDayEnabled ) &&
		siteNowTime() < ( rules.sameDayCutoff || '14:00' );
	const window = Number( rules.bookingWindowDays ) || 0;
	return {
		min: sameDayOpen ? today : addDaysYmd( today, 1 ),
		max: window > 0 ? addDaysYmd( today, window ) : '',
	};
}

/**
 * The public booking rules from the site payload.
 *
 * @return {Object} Rules (`Frontend\Embeds::rules()`).
 */
export function siteBookingRules() {
	return (
		( typeof window !== 'undefined' &&
			window.radius_hotel_booking_site_param?.booking ) ||
		{}
	);
}

/**
 * The search a guest arrived with (the search bar's query string), kept only
 * where it fits the website's rules; the rest falls back to the defaults.
 *
 * @param {Object} rules  Public booking rules.
 * @param {Object} limits `{ min, max }` arrivals (`guestDateLimits()`).
 * @return {Object} `{ arrival, departure, adults, children, rooms, room_type_id }`.
 */
export function searchFromUrl( rules, limits ) {
	const url = new URLSearchParams( window.location.search );
	const valid = ( ymd ) =>
		/^\d{4}-\d{2}-\d{2}$/.test( ymd || '' ) &&
		ymd >= limits.min &&
		( ! limits.max || ymd <= limits.max );
	const arrival = valid( url.get( 'arrival' ) )
		? url.get( 'arrival' )
		: limits.min;
	const departure =
		valid( url.get( 'departure' ) ) && url.get( 'departure' ) >= arrival
			? url.get( 'departure' )
			: arrival;
	const between = ( value, min, max, fallback ) => {
		const n = Number( value );
		return Number.isInteger( n ) && n >= min && n <= max ? n : fallback;
	};
	return {
		arrival,
		departure,
		adults: between( url.get( 'adults' ), 1, 20, rules.defaultAdults || 2 ),
		children: between( url.get( 'children' ), 0, 10, 0 ),
		rooms: between( url.get( 'rooms' ), 1, 10, 1 ),
		// From a room type page's *Book this room* (0 = every room type).
		room_type_id: between( url.get( 'room_type' ), 1, 1000000000, 0 ),
	};
}
