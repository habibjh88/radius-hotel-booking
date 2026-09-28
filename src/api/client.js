/**
 * REST client.
 *
 * Thin wrapper around @wordpress/api-fetch that points at the plugin's REST
 * namespace and unwraps the ApiResponse envelope the PHP side returns:
 *
 *   { success, status_code, message, data, errors, meta }
 *
 * Every helper resolves with `{ data, meta, message }` and rejects with an
 * Error carrying `.errors` and `.status` so forms can show field-level errors.
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const params =
	( typeof window !== 'undefined' &&
		( window.radius_hotel_booking_param ||
			window.radius_hotel_booking_site_param ) ) ||
	{};

/*
 * `apiFetch` is WordPress's shared global instance (externalised to
 * `wp.apiFetch`). Registering middleware on it with `apiFetch.use()` would
 * leak into every other plugin's requests, and another plugin's root-URL
 * middleware would hijack ours — so each request carries its own absolute
 * URL and nonce header instead.
 */

/**
 * Absolute URL for a path relative to the plugin's REST namespace.
 *
 * @param {string} path Path such as 'settings' or 'bookings/5?x=1'.
 * @return {string|undefined} URL, or undefined when the root is unknown.
 */
function toUrl( path ) {
	if ( ! params.rest_url ) {
		return undefined;
	}
	return `${ params.rest_url.replace( /\/+$/, '' ) }/${ String( path ).replace(
		/^\/+/,
		''
	) }`;
}

/**
 * Turn the ApiResponse envelope into a resolved value or a rich Error.
 *
 * @param {Object} response Parsed JSON body.
 * @return {{data: *, meta: Object, message: string}} Unwrapped payload.
 */
function unwrap( response ) {
	if ( response && response.success === false ) {
		const error = new Error(
			response.message || __( 'Request failed.', 'radius-hotel-booking' )
		);
		error.errors = response.errors || {};
		error.status = response.status_code;
		throw error;
	}

	return {
		data: response?.data ?? response,
		meta: response?.meta ?? {},
		message: response?.message ?? '',
	};
}

/**
 * Perform a request against the plugin's REST namespace.
 *
 * @param {string} path    Path relative to the namespace, e.g. 'bookings'.
 * @param {Object} options apiFetch options (method, data, …).
 * @return {Promise<{data: *, meta: Object, message: string}>} Unwrapped response.
 */
export async function request( path, options = {} ) {
	try {
		const url = toUrl( path );
		const response = await apiFetch( {
			...( url ? { url } : { path } ),
			...options,
			headers: {
				// PermissionMiddleware requires the REST nonce on every request.
				...( params.nonce ? { 'X-WP-Nonce': params.nonce } : {} ),
				...( options.headers || {} ),
			},
		} );
		return unwrap( response );
	} catch ( error ) {
		// apiFetch rejects with the parsed body on a non-2xx response.
		if ( error && typeof error === 'object' && 'success' in error ) {
			return unwrap( error );
		}
		throw error;
	}
}

export const get = ( path, query ) =>
	request( addQueryArgs( path, query ), { method: 'GET' } );

export const post = ( path, data ) => request( path, { method: 'POST', data } );

export const put = ( path, data ) => request( path, { method: 'PUT', data } );

export const del = ( path ) => request( path, { method: 'DELETE' } );

/**
 * Append a query string, skipping empty values.
 *
 * @param {string} path  Base path.
 * @param {Object} query Query parameters.
 * @return {string} Path with query string.
 */
export function addQueryArgs( path, query ) {
	if ( ! query ) {
		return path;
	}

	const search = new URLSearchParams();

	Object.entries( query ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.append( key, value );
		}
	} );

	const queryString = search.toString();

	return queryString ? `${ path }?${ queryString }` : path;
}

export default { request, get, post, put, del };
