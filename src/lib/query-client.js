/**
 * The app's single React Query client (ADR-013): server data is fetched,
 * cached and invalidated through it; React state is for UI only.
 *
 * One instance for the whole page, published on `window.rtbp.lib` so an
 * add-on's screens share the same cache (and invalidate each other's data).
 */
import { QueryClient } from '@tanstack/react-query';

/**
 * Retry network hiccups and server errors once; never retry a 4xx — a
 * validation error or a 403 will not fix itself.
 *
 * @param {number} failureCount Failures so far.
 * @param {Error}  error        Error from src/api/client.js (has `.status`).
 * @return {boolean} Retry.
 */
function retry( failureCount, error ) {
	if ( error?.status >= 400 && error?.status < 500 ) {
		return false;
	}
	return failureCount < 1;
}

export const queryClient = new QueryClient( {
	defaultOptions: {
		queries: {
			staleTime: 30 * 1000,
			retry,
			refetchOnWindowFocus: true,
		},
		mutations: {
			retry: false,
		},
	},
} );
