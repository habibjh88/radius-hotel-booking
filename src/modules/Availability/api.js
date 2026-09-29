/**
 * Availability calendar: REST calls and React Query hooks (M08).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { get, put } from '@/api/client';

export const CALENDAR_KEY = [ 'availability-calendar' ];

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
