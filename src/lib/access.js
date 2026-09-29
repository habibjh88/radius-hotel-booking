/**
 * The M13 access map on the client: key => 'open' | 'passcode' | 'locked'.
 *
 * The first map is localized with the page (`radius_hotel_booking_param.access`)
 * so routes and buttons are right on first paint; `GET access/me` refreshes it.
 * This only hides what the user cannot use — AccessMiddleware on the server is
 * the guarantee (conventions §3.5).
 *
 *   const level = useAccess( 'bookings.cancel' );
 *   if ( level === 'locked' ) return null;   // 'passcode': Pro asks for the PIN
 */
import { useQuery } from '@tanstack/react-query';

import { get } from '@/api/client';
import { queryClient } from '@/lib/query-client';

export const ACCESS_QUERY_KEY = [ 'access', 'me' ];

/** @type {Object<string, string>|undefined} */
const initialLevels = window.radius_hotel_booking_param?.access?.levels;

export const fetchAccess = () =>
	get( 'access/me' ).then( ( { data } ) => data?.levels ?? {} );

/**
 * A level from a map. Without any map (a page cached from before M13) the UI
 * shows everything and the server decides; an unknown key is locked.
 *
 * @param {Object|undefined} levels Key => level.
 * @param {string}           key    Access key.
 * @return {string} Level.
 */
function levelIn( levels, key ) {
	if ( ! levels ) {
		return 'open';
	}
	return levels[ key ] ?? 'locked';
}

/**
 * The current user's whole map.
 *
 * @return {Object} React Query result; `data` is key => level.
 */
export function useAccessMap() {
	return useQuery( {
		queryKey: ACCESS_QUERY_KEY,
		queryFn: fetchAccess,
		initialData: initialLevels,
		staleTime: 5 * 60 * 1000,
	} );
}

/**
 * The current user's level for one key.
 *
 * @param {string} key Access key, e.g. 'bookings.cancel'.
 * @return {string} 'open' | 'passcode' | 'locked'.
 */
export function useAccess( key ) {
	const { data } = useAccessMap();
	return levelIn( data, key );
}

/**
 * The level for one key, outside React (routes, event handlers).
 *
 * @param {string} key Access key.
 * @return {string} Level.
 */
export function getAccessLevel( key ) {
	return levelIn(
		queryClient.getQueryData( ACCESS_QUERY_KEY ) ?? initialLevels,
		key
	);
}

/**
 * Whether any of the keys is usable (not locked). No key = usable.
 *
 * @param {string|string[]|undefined} keys Access key(s).
 * @return {boolean} Usable.
 */
export function canAccess( keys ) {
	const list = [].concat( keys ?? [] );
	return (
		list.length === 0 ||
		list.some( ( key ) => getAccessLevel( key ) !== 'locked' )
	);
}

/**
 * Refetch the map after a permission change.
 *
 * @return {Promise} Settles when refetched.
 */
export function refreshAccess() {
	return queryClient.invalidateQueries( { queryKey: ACCESS_QUERY_KEY } );
}
