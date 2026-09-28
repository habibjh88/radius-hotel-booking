/**
 * Dashboard REST calls.
 */
import { useCallback, useEffect, useState } from 'react';

import { get } from '@/api/client';

export const fetchSummary = () => get( 'dashboard/summary' );

/**
 * Load the dashboard summary, with loading / error / reload state.
 *
 * @return {{data: Object|null, loading: boolean, error: Error|null, reload: Function}} State.
 */
export function useDashboardSummary() {
	const [ state, setState ] = useState( {
		data: null,
		loading: true,
		error: null,
	} );

	const load = useCallback( () => {
		let cancelled = false;
		setState( ( current ) => ( { ...current, loading: true, error: null } ) );

		fetchSummary()
			.then( ( { data } ) => {
				if ( ! cancelled ) {
					setState( { data, loading: false, error: null } );
				}
			} )
			.catch( ( error ) => {
				if ( ! cancelled ) {
					setState( { data: null, loading: false, error } );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	useEffect( () => load(), [ load ] );

	return { ...state, reload: load };
}
