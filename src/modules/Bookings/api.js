/**
 * Bookings: REST calls and React Query hooks (M03).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post, put } from '@/api/client';

export const BOOKINGS_KEY = [ 'bookings' ];

/**
 * One booking with everything its screen shows (lines, guest, money,
 * allowed actions).
 *
 * @param {number} id Booking id.
 * @return {Object} React Query result; data is the booking.
 */
export function useBooking( id ) {
	return useQuery( {
		queryKey: [ ...BOOKINGS_KEY, 'one', Number( id ) ],
		queryFn: () =>
			get( `bookings/${ id }` ).then( ( { data } ) => data.booking ),
		enabled: Boolean( id ),
	} );
}

/**
 * A status change (3.5–3.10). The server answers with the booking as it is
 * now; the screen uses it at once, and the availability search refreshes.
 *
 * @param {number} id Booking id.
 * @return {Object} Mutation; `mutateAsync( { action, lineId?, reason?, roomId? } )` resolves with `{ booking, message }`.
 */
export function useBookingAction( id ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { action, lineId, reason, roomId } ) => {
			const lineActions = {
				check_in: 'check-in',
				check_out: 'check-out',
				no_show: 'no-show',
			};
			const request = lineActions[ action ]
				? post(
						`booking-lines/${ lineId }/${ lineActions[ action ] }`,
						{
							...( roomId ? { room_id: roomId } : {} ),
						}
				  )
				: post( `bookings/${ id }/${ action }`, {
						...( lineId ? { line_id: lineId } : {} ),
						...( reason ? { reason } : {} ),
				  } );
			return request.then( ( { data, message } ) => ( {
				booking: data.booking,
				message,
			} ) );
		},
		onSuccess: ( { booking } ) => refreshAfter( client, id, booking ),
	} );
}

/**
 * Use the booking the server answered with, and refresh what it changes.
 *
 * @param {Object} client  Query client.
 * @param {number} id      Booking id.
 * @param {Object} booking The booking as it is now.
 * @return {void}
 */
function refreshAfter( client, id, booking ) {
	client.setQueryData( [ ...BOOKINGS_KEY, 'one', Number( id ) ], booking );
	client.invalidateQueries( { queryKey: [ 'availability' ] } );
	client.invalidateQueries( { queryKey: [ 'guests' ] } );
	// Add-on panels on the booking (Pro's History) read the activity.
	client.invalidateQueries( { queryKey: [ 'activity' ] } );
}

/**
 * Add, change or remove a room on the booking (3.11, 3.12).
 *
 * @param {number} id Booking id.
 * @return {Object} Mutation; `mutateAsync( { kind: 'add'|'edit'|'remove', lineId?, body? } )` resolves with `{ booking, message }`.
 */
export function useLineChange( id ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { kind, lineId, body = {} } ) => {
			let request;
			if ( 'add' === kind ) {
				request = post( `bookings/${ id }/lines`, body );
			} else if ( 'edit' === kind ) {
				request = put( `booking-lines/${ lineId }`, body );
			} else {
				request = del( `booking-lines/${ lineId }` );
			}
			return request.then( ( { data, message } ) => ( {
				booking: data.booking,
				message,
			} ) );
		},
		onSuccess: ( { booking } ) => refreshAfter( client, id, booking ),
	} );
}

/**
 * Rooms a line could move to at check-in (same type, free for its own
 * window), from the server.
 *
 * @param {Object|null} line The line (null = no query).
 * @return {Object} React Query result; data is `[ { id, number, floor } ]`.
 */
export function useFreeRoomsFor( line ) {
	return useQuery( {
		queryKey: [ 'availability', 'line-rooms', line?.id ],
		queryFn: () =>
			get( `booking-lines/${ line.id }/rooms` ).then(
				( { data } ) => data.rooms
			),
		enabled: Boolean( line ),
		staleTime: 0,
	} );
}
