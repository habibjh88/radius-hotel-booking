/**
 * Dashboard REST calls and queries.
 */
import { useQuery } from '@tanstack/react-query';

import { get } from '@/api/client';

export const fetchSummary = () =>
	get( 'dashboard/summary' ).then( ( { data } ) => data );

/**
 * The dashboard summary. Cached by React Query; refreshed when the window
 * regains focus and every 60 s while the dashboard is open, so the counters
 * stay current at the front desk. (M01 adds a faster new-booking poll.)
 *
 * @return {Object} React Query result: { data, isPending, error, refetch }.
 */
export function useDashboardSummary() {
	return useQuery( {
		queryKey: [ 'dashboard', 'summary' ],
		queryFn: fetchSummary,
		refetchInterval: 60 * 1000,
	} );
}
