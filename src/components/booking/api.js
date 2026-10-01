/**
 * The booking flow's server state (M02): the availability search. Holds and
 * the booking itself come with the room and confirm steps.
 */
import { useQuery } from '@tanstack/react-query';

import { get } from '@/api/client';

export const AVAILABILITY_KEY = [ 'availability', 'search' ];

/**
 * Whether a search can be sent: both dates, departure not before arrival,
 * at least one adult.
 *
 * @param {Object} search `{ arrival, departure, adults, children, checkin_time }`.
 * @return {boolean} Valid.
 */
export const searchIsValid = ( search ) =>
	Boolean( search.arrival ) &&
	Boolean( search.departure ) &&
	search.departure >= search.arrival &&
	Number( search.adults ) >= 1;

/**
 * Every sellable rate grouped by room type, with live prices, and the
 * unsellable ones with their reason (2.2–2.4).
 *
 * @param {Object} search `{ arrival, departure, adults, children, checkin_time?, hold_token?, exclude_booking_line? }`.
 * @param {string} path   `availability` (staff) or `public/availability` (the website, M04).
 * @return {Object} React Query result; data `{ span, room_types, other_options }`.
 */
export function useAvailability( search, path = 'availability' ) {
	const params = {
		arrival: search.arrival,
		departure: search.departure,
		adults: search.adults,
		children: search.children,
	};
	if ( search.checkin_time ) {
		params.checkin_time = search.checkin_time;
	}
	if ( search.hold_token ) {
		params.hold_token = search.hold_token;
	}
	if ( search.room_type_id ) {
		// One room type only (the website, from its room type page).
		params.room_type_id = search.room_type_id;
	}
	if ( search.exclude_booking_line ) {
		// A room being edited: its own stay is not busy, nor counted for occupancy pricing.
		params.exclude_booking_line = search.exclude_booking_line;
	}
	return useQuery( {
		queryKey: [ ...AVAILABILITY_KEY, path, params ],
		queryFn: () => get( path, params ).then( ( { data } ) => data ),
		enabled: searchIsValid( search ),
		placeholderData: ( previous ) => previous,
		// Rooms go quickly at the desk: refresh when the tab comes back.
		refetchOnWindowFocus: true,
	} );
}

/**
 * Find guests by name, phone or e-mail (2.7): at least two characters.
 * Also returns the identity document types for the new-guest form.
 *
 * @param {string} q Search.
 * @return {Object} React Query result; data `{ guests, id_types }`.
 */
export function useGuestLookup( q ) {
	const term = String( q || '' ).trim();
	return useQuery( {
		queryKey: [ 'guests', 'lookup', term ],
		queryFn: () =>
			get( 'guests/lookup', { q: term } ).then( ( { data } ) => data ),
		placeholderData: ( previous ) => previous,
		staleTime: 10 * 1000,
	} );
}
