/**
 * Exports (M11): REST calls and React Query hooks.
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post } from '@/api/client';

export const EXPORTS_KEY = [ 'exports' ];

/**
 * The library page, with the kinds and formats on offer.
 *
 * @param {number} page Page (1-based).
 * @return {Object} Query; data `{ items, total, kinds, formats }`.
 */
export const useExports = ( page ) =>
	useQuery( {
		queryKey: [ ...EXPORTS_KEY, page ],
		queryFn: () =>
			get( 'exports', { page, per_page: 20 } ).then( ( r ) => r.data ),
		placeholderData: ( previous ) => previous,
	} );

/**
 * Generate a file; refreshes the library.
 *
 * @return {Object} Mutation; resolves with the library row.
 */
export function useGenerateExport() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( input ) =>
			post( 'exports', input ).then( ( r ) => r.data ),
		onSuccess: () => client.invalidateQueries( { queryKey: EXPORTS_KEY } ),
	} );
}

/**
 * Delete an export file; refreshes the library.
 *
 * @return {Object} Mutation; `mutateAsync( id )`.
 */
export function useDeleteExport() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( id ) => del( `exports/${ id }` ),
		onSuccess: () => client.invalidateQueries( { queryKey: EXPORTS_KEY } ),
	} );
}
