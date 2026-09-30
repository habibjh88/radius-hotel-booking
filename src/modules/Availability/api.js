/**
 * Availability calendar: REST calls and React Query hooks (M08).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post, put } from '@/api/client';

export const CALENDAR_KEY = [ 'availability-calendar' ];
export const BLOCKS_KEY = [ 'blocks' ];

/**
 * One room type's month grid.
 *
 * @param {number} typeId Room type id (falsy = no query).
 * @param {string} month  `YYYY-MM`.
 * @return {Object} React Query result.
 */
export function useCalendar( typeId, month ) {
	return useQuery( {
		queryKey: [ ...CALENDAR_KEY, Number( typeId ), month ],
		queryFn: () =>
			get( 'availability/calendar', {
				room_type_id: typeId,
				month,
			} ).then( ( { data } ) => data ),
		enabled: Boolean( typeId ) && Boolean( month ),
		placeholderData: ( previous ) => previous,
	} );
}

/**
 * Change calendar cells; refreshes every month of the calendar.
 *
 * @return {Object} Mutation; `mutateAsync( { typeId, cells } )` resolves with `{ changed }`.
 */
export function useSaveCalendar() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { typeId, cells } ) =>
			put( 'availability/calendar', {
				room_type_id: typeId,
				cells,
			} ).then( ( { data } ) => data ),
		onSuccess: () => client.invalidateQueries( { queryKey: CALENDAR_KEY } ),
	} );
}

/**
 * How many cells a bulk update would change (8.5). Kept under the calendar
 * key, so it refreshes after any save.
 *
 * @param {Object|null} input `{ room_type_id, from, to, weekdays, rate_plan_ids, action, price? }`;
 *                            null = no query.
 * @return {Object} React Query result; data `{ dates, cells, changes }`.
 */
export function useBulkPreview( input ) {
	return useQuery( {
		queryKey: [ ...CALENDAR_KEY, 'bulk-preview', input ],
		queryFn: () =>
			post( 'availability/calendar/bulk', {
				...input,
				preview: true,
			} ).then( ( { data } ) => data ),
		enabled: Boolean( input ),
		placeholderData: ( previous ) => previous,
		retry: false,
	} );
}

/**
 * Apply a bulk update; refreshes every month of the calendar.
 *
 * @return {Object} Mutation; `mutateAsync( input )` resolves with `{ dates, cells, changes }`.
 */
export function useBulkCalendar() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( input ) =>
			post( 'availability/calendar/bulk', input ).then(
				( { data } ) => data
			),
		onSuccess: () => client.invalidateQueries( { queryKey: CALENDAR_KEY } ),
	} );
}

/**
 * A page of blocked dates (8.12).
 *
 * @param {Object} filters `{ when: 'upcoming'|'past'|'all', scope?, page, per_page }`.
 * @return {Object} React Query result; data `{ blocks, total }`.
 */
export function useBlocks( filters ) {
	return useQuery( {
		queryKey: [ ...BLOCKS_KEY, filters ],
		queryFn: () =>
			get( 'blocks', filters ).then( ( { data, meta } ) => ( {
				blocks: data.blocks,
				total: meta?.total ?? data.blocks.length,
			} ) ),
		placeholderData: ( previous ) => previous,
	} );
}

/**
 * Create (`id` absent) or change a block; refreshes the list and the calendar.
 *
 * @return {Object} Mutation; `mutateAsync( { id?, ...fields } )` resolves with
 *                  `{ block, bookings_inside }`.
 */
export function useSaveBlock() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { id, ...fields } ) =>
			( id
				? put( `blocks/${ id }`, fields )
				: post( 'blocks', fields )
			).then( ( { data } ) => data ),
		onSuccess: () => {
			client.invalidateQueries( { queryKey: BLOCKS_KEY } );
			client.invalidateQueries( { queryKey: CALENDAR_KEY } );
		},
	} );
}

/**
 * Remove a block; refreshes the list and the calendar.
 *
 * @return {Object} Mutation; `mutateAsync( id )`.
 */
export function useDeleteBlock() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( id ) => del( `blocks/${ id }` ),
		onSuccess: () => {
			client.invalidateQueries( { queryKey: BLOCKS_KEY } );
			client.invalidateQueries( { queryKey: CALENDAR_KEY } );
		},
	} );
}
