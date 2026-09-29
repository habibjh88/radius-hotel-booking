/**
 * REST client.
 *
 * Thin wrapper around @wordpress/api-fetch that points at the plugin's REST
 * namespace and unwraps the ApiResponse envelope the PHP side returns:
 *
 *   { success, status_code, message, data, errors, meta }
 *
 * Every helper resolves with `{ data, meta, message }` and rejects with an
 * Error carrying `.errors`, `.status` and `.code` so forms can show
 * field-level errors.
 *
 * Failed requests run the `rtbp.api.error` filter first (ADR-015), so an
 * add-on can recover from an error, for example Pro asking for a PIN on
 * `passcode_required` and retrying:
 *
 *   addFilter( 'rtbp.api.error', 'rtbp-pro/passcode', ( handled, error, { retry } ) =>
 *       handled || ( error.code === 'passcode_required'
 *           ? askForPin().then( ( pin ) => retry( { headers: { 'X-RTBP-Grant': pin } } ) )
 *           : handled ) );
 *
 * A handler returns a Promise to take over the request (its result becomes
 * the request's result) or passes `handled` through to let the error throw.
 * The retried request does not run the filter again.
 */
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
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
	return `${ params.rest_url.replace( /\/+$/, '' ) }/${ String(
		path
	).replace( /^\/+/, '' ) }`;
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
		// Machine-readable code from a DomainException (e.g. 'room_unavailable').
		error.code = response.code;
		// Error details, e.g. `{ key }` on `access_locked` / `passcode_required`.
		error.data = response.data;
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
		return await send( path, options );
	} catch ( error ) {
		const handled = applyFilters( 'rtbp.api.error', undefined, error, {
			path,
			options,
			retry: ( extra = {} ) =>
				send( path, {
					...options,
					...extra,
					headers: {
						...( options.headers || {} ),
						...( extra.headers || {} ),
					},
				} ),
		} );
		if ( handled && typeof handled.then === 'function' ) {
			return handled;
		}
		throw error;
	}
}

/**
 * One request, without the error filter.
 *
 * @param {string} path    Path relative to the namespace.
 * @param {Object} options apiFetch options.
 * @return {Promise<{data: *, meta: Object, message: string}>} Unwrapped response.
 */
async function send( path, options ) {
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

/**
 * Download URL of a protected file (Storage\ProtectedFiles). Carries the REST
 * nonce so it works as a plain link.
 *
 * @param {string}  token  File token.
 * @param {boolean} inline Show in the browser (PDFs) instead of downloading.
 * @return {string} URL.
 */
export function fileUrl( token, inline = false ) {
	const url = new URL(
		toUrl( `files/${ token }` ) || '',
		window.location.origin
	);
	if ( params.nonce ) {
		url.searchParams.set( '_wpnonce', params.nonce );
	}
	if ( inline ) {
		url.searchParams.set( 'inline', '1' );
	}
	return url.toString();
}

export default { request, get, post, put, del, fileUrl };
