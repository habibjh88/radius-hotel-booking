/**
 * Rate plans: REST calls and React Query hooks (M07).
 */
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { del, get, post, put } from '@/api/client';

export const RATE_PLANS_KEY = [ 'rate-plans' ];

/**
 * The rate plan library, in order, with where each plan is used.
 *
 * @return {Object} React Query result.
 */
export function useRatePlans() {
	return useQuery( {
		queryKey: RATE_PLANS_KEY,
		queryFn: () =>
			get( 'rate-plans' ).then( ( { data } ) => data.rate_plans ),
	} );
}

/**
 * Create (no id) or save a plan.
 *
 * @return {Object} Mutation; `mutateAsync( { id?, values } )` resolves with the plan.
 */
export function useSaveRatePlan() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( { id, values } ) =>
			( id
				? put( `rate-plans/${ id }`, values )
				: post( 'rate-plans', values )
			).then( ( { data } ) => data.rate_plan ),
		onSuccess: () =>
			client.invalidateQueries( { queryKey: RATE_PLANS_KEY } ),
	} );
}

/**
 * Delete a plan (refused by the server while it is in use).
 *
 * @return {Object} Mutation; `mutateAsync( id )`.
 */
export function useDeleteRatePlan() {
	const client = useQueryClient();
	return useMutation( {
		mutationFn: ( id ) => del( `rate-plans/${ id }` ),
		onSuccess: () =>
			client.invalidateQueries( { queryKey: RATE_PLANS_KEY } ),
	} );
}
