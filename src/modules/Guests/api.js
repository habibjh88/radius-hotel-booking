/**
 * Guests: REST calls and React Query hooks (M09).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { get, post, put } from '@/api/client';

export const GUESTS_KEY = [ 'guests' ];

/**
 * A page of guests.
 *
 * @param {Object} filters `{ q, standing, page, per_page }`.
 * @return {Object} React Query result; data `{ guests, total, idTypes }`.
 */
export function useGuests( filters ) {
	return useQuery( {
		queryKey: [ ...GUESTS_KEY, 'list', filters ],
		queryFn: () =>
			get( 'guests', filters ).then( ( { data, meta } ) => ( {
				guests: data.guests,
				idTypes: data.id_types || {},
				total: meta?.total ?? data.guests.length,
			} ) ),
		placeholderData: ( previous ) => previous,
	} );
}

/**
 * Add a guest. A guest already on file rejects with `code: 'guest_exists'`
 * and the matches in `error.data.guests`.
 *
 * @return {Object} Mutation; resolves with the guest.
 */
export function useCreateGuest() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( fields ) =>
			post( 'guests', fields ).then( ( { data } ) => data.guest ),
		onSuccess: () => client.invalidateQueries( { queryKey: GUESTS_KEY } ),
	} );
}

/**
 * One guest (detail shape) and the document types.
 *
 * @param {number} id Guest id.
 * @return {Object} React Query result; data `{ guest, idTypes }`.
 */
export function useGuest( id ) {
	return useQuery( {
		queryKey: [ ...GUESTS_KEY, 'one', Number( id ) ],
		queryFn: () =>
			get( `guests/${ id }` ).then( ( { data } ) => ( {
				guest: data.guest,
				idTypes: data.id_types || {},
			} ) ),
		enabled: Boolean( id ),
	} );
}

/**
 * Change a guest (only the fields sent); refreshes the guest and the list.
 *
 * @param {number} id Guest id.
 * @return {Object} Mutation; resolves with the guest.
 */
export function useUpdateGuest( id ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( fields ) =>
			put( `guests/${ id }`, fields ).then( ( { data } ) => data.guest ),
		onSuccess: ( guest ) => {
			client.setQueryData(
				[ ...GUESTS_KEY, 'one', Number( id ) ],
				( old ) => ( old ? { ...old, guest } : old )
			);
			client.invalidateQueries( { queryKey: [ ...GUESTS_KEY, 'list' ] } );
			// Booking screens show the guest too (M03's guest panel).
			client.invalidateQueries( { queryKey: [ 'bookings' ] } );
		},
	} );
}

/**
 * The full identity document number. Every call is logged by the server,
 * so it is fetched only on request and never cached.
 *
 * @param {number} id Guest id.
 * @return {Promise<Object>} `{ id_type, id_type_label, id_number }`.
 */
export const revealIdNumber = ( id ) =>
	get( `guests/${ id }/id-number` ).then( ( { data } ) => data );

/**
 * A guest's stays (9.4): bookings with their rooms, newest arrival first.
 *
 * @param {number} id Guest id.
 * @return {Object} React Query result; data is the stays array.
 */
export function useGuestStays( id ) {
	return useQuery( {
		queryKey: [ ...GUESTS_KEY, 'stays', Number( id ) ],
		queryFn: () =>
			get( `guests/${ id }/stays` ).then( ( { data } ) => data.stays ),
		enabled: Boolean( id ),
	} );
}

/**
 * Ban or lift a ban (9.10), with a reason; refreshes the guest and the list.
 *
 * @param {number} id Guest id.
 * @return {Object} Mutation; `mutateAsync( { action: 'ban'|'unban', reason } )`.
 */
export function useSetStanding( id ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { action, reason } ) =>
			post( `guests/${ id }/${ action }`, { reason } ).then(
				( { data } ) => data.guest
			),
		onSuccess: ( guest ) => {
			client.setQueryData(
				[ ...GUESTS_KEY, 'one', Number( id ) ],
				( old ) => ( old ? { ...old, guest } : old )
			);
			client.invalidateQueries( { queryKey: [ ...GUESTS_KEY, 'list' ] } );
		},
	} );
}
