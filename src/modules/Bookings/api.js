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
		mutationFn: ( variables ) => moveRequest( id, variables ),
		onSuccess: ( { booking } ) => refreshAfter( client, id, booking ),
	} );
}

/**
 * One status move: check in / out and no-show on the room, the rest on the
 * booking (`line_id` narrows it to one room).
 *
 * @param {number} id        Booking id.
 * @param {Object} variables `{ action, lineId?, reason?, roomId? }`.
 * @return {Promise<Object>} `{ booking, message }`.
 */
function moveRequest( id, { action, lineId, reason, roomId } ) {
	const lineActions = {
		check_in: 'check-in',
		check_out: 'check-out',
		no_show: 'no-show',
	};
	const request = lineActions[ action ]
		? post( `booking-lines/${ lineId }/${ lineActions[ action ] }`, {
				...( roomId ? { room_id: roomId } : {} ),
		  } )
		: post( `bookings/${ id }/${ action }`, {
				...( lineId ? { line_id: lineId } : {} ),
				...( reason ? { reason } : {} ),
		  } );
	return request.then( ( { data, message } ) => ( {
		booking: data.booking,
		message,
	} ) );
}

// The room's status right after each move (the optimistic row).
const MOVED_TO = {
	approve: 'confirmed',
	decline: 'declined',
	cancel: 'cancelled',
	check_in: 'checked_in',
	check_out: 'checked_out',
};

/**
 * A status move from a row of the front desk list (M01, 1.10), on that one
 * room. The row shows its new status at once and goes back if the server
 * refuses; then the list, its counts and the dashboard reload.
 *
 * @return {Object} Mutation; `mutateAsync( { bookingId, lineId, action, reason? } )` resolves with `{ booking, message }`.
 */
export function useLineAction() {
	const client = useQueryClient();
	const listKey = [ ...BOOKINGS_KEY, 'lines' ];
	return useMutation( {
		mutationFn: ( { bookingId, ...variables } ) =>
			moveRequest( bookingId, variables ),
		onMutate: async ( { lineId, action } ) => {
			await client.cancelQueries( { queryKey: listKey } );
			const previous = client.getQueriesData( { queryKey: listKey } );
			client.setQueriesData( { queryKey: listKey }, ( data ) =>
				data?.lines
					? {
							...data,
							lines: data.lines.map( ( line ) =>
								line.id === lineId
									? {
											...line,
											status:
												MOVED_TO[ action ] ||
												line.status,
											actions: [],
									  }
									: line
							),
					  }
					: data
			);
			return { previous };
		},
		onError: ( _error, _variables, context ) =>
			context?.previous?.forEach( ( [ key, data ] ) =>
				client.setQueryData( key, data )
			),
		onSuccess: ( { booking }, { bookingId } ) =>
			refreshAfter( client, bookingId, booking ),
		onSettled: () => {
			client.invalidateQueries( { queryKey: listKey } );
			client.invalidateQueries( { queryKey: [ 'dashboard' ] } );
		},
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

/**
 * A booking's payments (5.13): the ledger, the money and the methods the
 * record dialog offers.
 *
 * @param {number} id Booking id.
 * @return {Object} React Query result; data `{ payments, summary, methods }`.
 */
export function usePayments( id ) {
	return useQuery( {
		queryKey: [ ...BOOKINGS_KEY, 'payments', Number( id ) ],
		queryFn: () =>
			get( `bookings/${ id }/payments` ).then( ( { data } ) => data ),
		enabled: Boolean( id ),
	} );
}

/**
 * Record a payment or refund, void a row, or set *on hold* (5.9–5.11). The
 * server answers with the ledger and the booking; both are used at once.
 *
 * @param {number} id Booking id.
 * @return {Object} Mutation; `mutateAsync( { kind: 'record'|'void'|'hold', paymentId?, body } )` resolves with `{ message }`.
 */
export function usePaymentAction( id ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { kind, paymentId, body = {} } ) => {
			let path = `bookings/${ id }/payments`;
			if ( 'void' === kind ) {
				path = `payments/${ paymentId }/void`;
			} else if ( 'hold' === kind ) {
				path = `bookings/${ id }/payment-status`;
			}
			return post( path, body ).then( ( { data, message } ) => ( {
				data,
				message,
			} ) );
		},
		onSuccess: ( { data } ) => {
			const { booking, ...ledger } = data;
			client.setQueryData(
				[ ...BOOKINGS_KEY, 'payments', Number( id ) ],
				ledger
			);
			refreshAfter( client, id, booking );
		},
	} );
}

/**
 * The front desk list, one row per booked room (M01, 1.5–1.9), with every
 * tab's count under the same date range and search.
 *
 * @param {Object} filters `{ tab, from, to, mode, q, page, per_page }`.
 * @return {Object} React Query result; data `{ lines, counts, total }`.
 */
export function useBookingLines( filters ) {
	return useQuery( {
		queryKey: [ ...BOOKINGS_KEY, 'lines', filters ],
		queryFn: () =>
			get( 'booking-lines', filters ).then( ( { data, meta } ) => ( {
				lines: data.lines,
				counts: data.counts,
				total: meta?.total ?? data.lines.length,
			} ) ),
		// Keep the rows while the next filter loads: no flash of empty table.
		placeholderData: ( previous ) => previous,
		// Arrivals, check-ins and deadlines move while the desk watches.
		refetchInterval: 60000,
	} );
}

/**
 * Bookings past their payment deadline (5.14), oldest deadline first.
 *
 * @param {number} page    Page (1-based).
 * @param {number} perPage Rows per page.
 * @return {Object} React Query result; data `{ bookings, total }`.
 */
export function useOverdue( page, perPage ) {
	return useQuery( {
		queryKey: [ ...BOOKINGS_KEY, 'overdue', page, perPage ],
		queryFn: () =>
			get( 'bookings', { overdue: 1, page, per_page: perPage } ).then(
				( { data, meta } ) => ( {
					bookings: data.bookings,
					total: meta?.total ?? data.bookings.length,
				} )
			),
		placeholderData: ( previous ) => previous,
		// Deadlines pass while the screen is open.
		refetchInterval: 60000,
	} );
}

/**
 * Remind or release an overdue booking from a list (5.14).
 *
 * @return {Object} Mutation; `mutateAsync( { id, action: 'remind'|'release', reason? } )` resolves with `{ message }`.
 */
export function useFollowUp() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { id, action, reason } ) =>
			post(
				`bookings/${ id }/${ action }`,
				reason ? { reason } : {}
			).then( ( { data, message } ) => ( {
				booking: data.booking,
				message,
			} ) ),
		onSuccess: ( { booking } ) => {
			if ( booking?.id ) {
				refreshAfter( client, booking.id, booking );
			}
			client.invalidateQueries( {
				queryKey: [ ...BOOKINGS_KEY, 'overdue' ],
			} );
		},
	} );
}
