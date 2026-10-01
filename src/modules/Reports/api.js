/**
 * Reports: REST calls and React Query hooks (M10).
 */
import { useQuery } from '@tanstack/react-query';

import { get } from '@/api/client';

export const REPORTS_KEY = [ 'reports' ];

/**
 * One report of the `rtbp_report_definitions` registry.
 *
 * @param {string}  name           Report name (`sales`, `rooms` …).
 * @param {Object}  params         `{ from, to, mode, … }`.
 * @param {Object}  options        Options.
 * @param {boolean} options.enabled Run the query (default true).
 * @return {Object} React Query result; `data` is the report payload.
 */
export function useReport( name, params, { enabled = true } = {} ) {
	return useQuery( {
		queryKey: [ ...REPORTS_KEY, name, params ],
		queryFn: () =>
			get( `reports/${ name }`, params ).then( ( { data } ) => data ),
		enabled: Boolean( name ) && enabled,
		placeholderData: ( previous ) => previous,
		staleTime: 60 * 1000,
	} );
}
