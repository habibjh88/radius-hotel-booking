/**
 * Room types, floors and rooms: REST calls and React Query hooks (M06).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post, put } from '@/api/client';

export const ROOM_TYPES_KEY = [ 'room-types' ];

export const fetchRoomTypes = () =>
	get( 'room-types' ).then( ( { data } ) => data.room_types );

export const fetchRoomType = ( id ) =>
	get( `room-types/${ id }` ).then( ( { data } ) => data.room_type );

/**
 * Every room type with its room counts.
 *
 * @return {Object} React Query result.
 */
export function useRoomTypes() {
	return useQuery( { queryKey: ROOM_TYPES_KEY, queryFn: fetchRoomTypes } );
}

/**
 * One room type.
 *
 * @param {number} id Id (falsy = no query, e.g. a new type).
 * @return {Object} React Query result.
 */
export function useRoomType( id ) {
	return useQuery( {
		queryKey: [ ...ROOM_TYPES_KEY, Number( id ) ],
		queryFn: () => fetchRoomType( id ),
		enabled: Boolean( id ),
	} );
}

/**
 * Create (no id) or save a room type; refreshes the list and the type.
 *
 * @return {Object} Mutation; `mutateAsync( { id?, values } )` resolves with the type.
 */
export function useSaveRoomType() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { id, values } ) =>
			( id
				? put( `room-types/${ id }`, values )
				: post( 'room-types', values )
			).then( ( { data } ) => data.room_type ),
		onSuccess: ( type ) => {
			client.setQueryData( [ ...ROOM_TYPES_KEY, type.id ], type );
			client.invalidateQueries( {
				queryKey: ROOM_TYPES_KEY,
				exact: true,
			} );
			// A name the library did not know was added to it.
			client.invalidateQueries( { queryKey: AMENITIES_KEY } );
		},
	} );
}

/**
 * Delete a room type (refused by the server while it has rooms).
 *
 * @return {Object} Mutation; `mutateAsync( id )`.
 */
export function useDeleteRoomType() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( id ) => del( `room-types/${ id }` ),
		onSuccess: ( _data, id ) => {
			client.removeQueries( {
				queryKey: [ ...ROOM_TYPES_KEY, Number( id ) ],
			} );
			client.invalidateQueries( {
				queryKey: ROOM_TYPES_KEY,
				exact: true,
			} );
		},
	} );
}

export const FLOORS_KEY = [ 'floors' ];

/**
 * Every floor in order, with room counts (`rooms` live, `total` incl. removed).
 *
 * @return {Object} React Query result.
 */
export function useFloors() {
	return useQuery( {
		queryKey: FLOORS_KEY,
		queryFn: () => get( 'floors' ).then( ( { data } ) => data.floors ),
	} );
}

/**
 * A floor write. Every floor endpoint answers with the whole list, which
 * replaces the cache.
 *
 * @param {Function} send Called with the mutation variables; returns the request.
 * @return {Object} Mutation.
 */
function useFloorMutation( send ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( variables ) =>
			send( variables ).then( ( { data } ) => data.floors ),
		onSuccess: ( floors ) => client.setQueryData( FLOORS_KEY, floors ),
	} );
}

export const useAddFloor = () =>
	useFloorMutation( ( name ) => post( 'floors', { name } ) );

export const useRenameFloor = () =>
	useFloorMutation( ( { id, name } ) => put( `floors/${ id }`, { name } ) );

export const useDeleteFloor = () =>
	useFloorMutation( ( id ) => del( `floors/${ id }` ) );

/**
 * Save a new floor order. Optimistic: the list moves at once and goes back
 * if the server refuses.
 *
 * @return {Object} Mutation; `mutate( ids )`.
 */
export function useReorderFloors() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( ids ) =>
			put( 'floors/order', { ids } ).then( ( { data } ) => data.floors ),
		onMutate: async ( ids ) => {
			await client.cancelQueries( { queryKey: FLOORS_KEY } );
			const previous = client.getQueryData( FLOORS_KEY );
			if ( previous ) {
				const byId = new Map(
					previous.map( ( floor ) => [ floor.id, floor ] )
				);
				client.setQueryData(
					FLOORS_KEY,
					ids.map( ( id ) => byId.get( id ) ).filter( Boolean )
				);
			}
			return { previous };
		},
		onError: ( _error, _ids, context ) => {
			if ( context?.previous ) {
				client.setQueryData( FLOORS_KEY, context.previous );
			}
		},
		onSuccess: ( floors ) => client.setQueryData( FLOORS_KEY, floors ),
	} );
}

export const AMENITIES_KEY = [ 'amenities' ];

/**
 * The amenity library in order; each with the room types that offer it
 * (`room_types: [ { id, name } ]`).
 *
 * @return {Object} React Query result.
 */
export function useAmenities() {
	return useQuery( {
		queryKey: AMENITIES_KEY,
		queryFn: () =>
			get( 'amenities' ).then( ( { data } ) => data.amenities ),
	} );
}

/**
 * An amenity write. Every amenity endpoint answers with the whole list,
 * which replaces the cache; a rename, merge, delete or new order also
 * changes the room types that offer it, so those are refreshed.
 *
 * @param {Function} send         Called with the mutation variables; returns the request.
 * @param {boolean}  touchesTypes The write can change room types (not an add).
 * @return {Object} Mutation; resolves with the full response data.
 */
function useAmenityMutation( send, touchesTypes = true ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( variables ) =>
			send( variables ).then( ( { data, message } ) => ( {
				...data,
				message,
			} ) ),
		onSuccess: ( data ) => {
			client.setQueryData( AMENITIES_KEY, data.amenities );
			if ( touchesTypes ) {
				client.invalidateQueries( { queryKey: ROOM_TYPES_KEY } );
			}
		},
	} );
}

export const useAddAmenity = () =>
	useAmenityMutation( ( name ) => post( 'amenities', { name } ), false );

/**
 * Rename an amenity; with `merge`, fold it into the amenity that already has
 * that name (the server answers `amenity_name_taken` without it).
 *
 * @return {Object} Mutation; `mutate( { id, name, merge? } )`.
 */
export const useRenameAmenity = () =>
	useAmenityMutation( ( { id, name, merge = false } ) =>
		put( `amenities/${ id }`, { name, merge } )
	);

/**
 * The common amenities, grouped, each with `added` when the list already
 * holds it. Loaded when the "Add common amenities" dialog opens.
 *
 * @param {boolean} enabled Load now.
 * @return {Object} React Query result; data `[ { label, amenities: [ { name, added } ] } ]`.
 */
export function useCommonAmenities( enabled ) {
	return useQuery( {
		queryKey: [ ...AMENITIES_KEY, 'common' ],
		queryFn: () =>
			get( 'amenities/common' ).then( ( { data } ) => data.groups ),
		enabled,
		staleTime: 0,
	} );
}

/**
 * Add several amenities at once; names already in the list are skipped.
 *
 * @return {Object} Mutation; `mutate( names )` resolves with `{ added, amenities, message }`.
 */
export const useImportAmenities = () =>
	useAmenityMutation(
		( names ) => post( 'amenities/import', { names } ),
		false
	);

export const useDeleteAmenity = () =>
	useAmenityMutation( ( id ) => del( `amenities/${ id }` ) );

/**
 * Save a new amenity order. Optimistic: the list moves at once and goes
 * back if the server refuses.
 *
 * @return {Object} Mutation; `mutate( ids )`.
 */
export function useReorderAmenities() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( ids ) =>
			put( 'amenities/order', { ids } ).then(
				( { data } ) => data.amenities
			),
		onMutate: async ( ids ) => {
			await client.cancelQueries( { queryKey: AMENITIES_KEY } );
			const previous = client.getQueryData( AMENITIES_KEY );
			if ( previous ) {
				const byId = new Map(
					previous.map( ( amenity ) => [ amenity.id, amenity ] )
				);
				client.setQueryData(
					AMENITIES_KEY,
					ids.map( ( id ) => byId.get( id ) ).filter( Boolean )
				);
			}
			return { previous };
		},
		onError: ( _error, _ids, context ) => {
			if ( context?.previous ) {
				client.setQueryData( AMENITIES_KEY, context.previous );
			}
		},
		onSuccess: ( amenities ) => {
			client.setQueryData( AMENITIES_KEY, amenities );
			client.invalidateQueries( { queryKey: ROOM_TYPES_KEY } );
		},
	} );
}

/**
 * The query key of a room type's rooms (grouped by floor).
 *
 * @param {number} typeId Room type id.
 * @return {Array} Key.
 */
export const typeRoomsKey = ( typeId ) => [
	...ROOM_TYPES_KEY,
	Number( typeId ),
	'rooms',
];

/**
 * A room type's rooms grouped by floor: `{ floors: [ { id, name, rooms } ] }`.
 *
 * @param {number} typeId Room type id.
 * @return {Object} React Query result.
 */
export function useTypeRooms( typeId ) {
	return useQuery( {
		queryKey: typeRoomsKey( typeId ),
		queryFn: () =>
			get( `room-types/${ typeId }/rooms` ).then(
				( { data } ) => data.floors
			),
		enabled: Boolean( typeId ),
	} );
}

/**
 * A room write: refreshes the type's rooms, the type cards (counts) and the
 * floors (counts).
 *
 * @param {number}   typeId Room type id.
 * @param {Function} send   Called with the variables; returns the request.
 * @return {Object} Mutation.
 */
function useRoomMutation( typeId, send ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: send,
		onSettled: () => {
			client.invalidateQueries( { queryKey: typeRoomsKey( typeId ) } );
			client.invalidateQueries( {
				queryKey: [ ...ROOM_TYPES_KEY, Number( typeId ) ],
				exact: true,
			} );
			client.invalidateQueries( {
				queryKey: ROOM_TYPES_KEY,
				exact: true,
			} );
			client.invalidateQueries( { queryKey: FLOORS_KEY } );
		},
	} );
}

export const useAddRoom = ( typeId ) =>
	useRoomMutation( typeId, ( values ) =>
		post( 'rooms', { ...values, room_type_id: typeId } )
	);

export const useUpdateRoom = ( typeId ) =>
	useRoomMutation( typeId, ( { id, ...values } ) =>
		put( `rooms/${ id }`, values )
	);

export const useSetRoomState = ( typeId ) =>
	useRoomMutation( typeId, ( { id, state, note } ) =>
		post( `rooms/${ id }/state`, { state, note } )
	);

export const useRemoveRoom = ( typeId ) =>
	useRoomMutation( typeId, ( id ) => del( `rooms/${ id }` ) );

/**
 * Preview a bulk add (nothing is written): `{ numbers, create, skip }`.
 *
 * @param {number} typeId Room type id.
 * @param {Object} values `{ floor_id, prefix, from, to, pad }`.
 * @return {Promise<Object>} Plan.
 */
export const previewBulkRooms = ( typeId, values ) =>
	post( 'rooms/bulk', {
		...values,
		room_type_id: typeId,
		dry_run: true,
	} ).then( ( { data } ) => data );

export const useBulkAddRooms = ( typeId ) =>
	useRoomMutation( typeId, ( values ) =>
		post( 'rooms/bulk', { ...values, room_type_id: typeId } )
	);

/**
 * Move a room to another type. `{ id, room_type_id }` answers the warning
 * only; add `confirm: true, bookings: n` to move. Refreshes every type (both
 * lists and counts change).
 *
 * @return {Object} Mutation; resolves with `{ moved, room, from, to, count, bookings }`.
 */
export function useMoveRoom() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { id, ...values } ) =>
			post( `rooms/${ id }/move`, values ).then( ( { data } ) => data ),
		onSuccess: ( result ) => {
			if ( result.moved ) {
				client.invalidateQueries( { queryKey: ROOM_TYPES_KEY } );
			}
		},
	} );
}

/**
 * The query key of a room type's Price & Rates grid.
 *
 * @param {number} typeId Room type id.
 * @return {Array} Key.
 */
export const typeRatesKey = ( typeId ) => [
	...ROOM_TYPES_KEY,
	Number( typeId ),
	'rates',
];

/**
 * A room type's Price & Rates grid: one row per rate plan.
 *
 * @param {number}  typeId  Room type id.
 * @param {boolean} enabled Load it (false while the tab is hidden).
 * @return {Object} React Query result.
 */
export function useTypeRates( typeId, enabled = true ) {
	return useQuery( {
		queryKey: typeRatesKey( typeId ),
		queryFn: () =>
			get( `room-types/${ typeId }/rates` ).then(
				( { data } ) => data.rates
			),
		enabled: Boolean( typeId ) && enabled,
	} );
}

/**
 * Save the whole grid (rows in display order). Refreshes the grid, the
 * room type (its card lists the plans it sells) and the rate plan library
 * (where each plan is used).
 *
 * @param {number} typeId Room type id.
 * @return {Object} Mutation; `mutateAsync( rows )` resolves with the saved grid.
 */
export function useSaveTypeRates( typeId ) {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( rows ) =>
			put( `room-types/${ typeId }/rates`, { rates: rows } ).then(
				( { data } ) => data.rates
			),
		onSuccess: ( rates ) => {
			client.setQueryData( typeRatesKey( typeId ), rates );
			client.invalidateQueries( {
				queryKey: [ ...ROOM_TYPES_KEY, Number( typeId ) ],
				exact: true,
			} );
			client.invalidateQueries( {
				queryKey: ROOM_TYPES_KEY,
				exact: true,
			} );
			client.invalidateQueries( { queryKey: [ 'rate-plans' ] } );
		},
	} );
}

/**
 * The price of a stay, with every step (`POST pricing/quote`; writes nothing).
 *
 * @return {Object} Mutation; `mutateAsync( { room_type_id, rate_plan_id, arrival, units, checkin_time, booked_at } )`.
 */
export function useQuote() {
	return useMutation( {
		mutationFn: ( values ) =>
			post( 'pricing/quote', values ).then( ( { data } ) => data.quote ),
	} );
}
