/**
 * Room holds for the booking flow (2.14): picking a room holds it for the
 * hold period (Settings → booking), every room of the booking under one
 * token. While staff keep working on the form the holds are extended
 * automatically, at most twice; leaving the flow releases them.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

import { del, post, put } from '@/api/client';

// Extend when this little is left (ms), and only if the form was used within
// the last hold-sized stretch of time.
const EXTEND_WHEN_LEFT = 2 * 60 * 1000;
export const MAX_EXTENSIONS = 2;

/**
 * Release a token on the way out (tab closed, reload): `keepalive` lets the
 * request finish after the page is gone.
 *
 * @param {string} token Token.
 * @return {void}
 */
function releaseOnUnload( token, base ) {
	const params =
		window.radius_hotel_booking_param ||
		window.radius_hotel_booking_site_param ||
		{};
	if ( ! token || ! params.rest_url ) {
		return;
	}
	try {
		window.fetch(
			`${ params.rest_url.replace( /\/+$/, '' ) }/${ base }/${ token }`,
			{
				method: 'DELETE',
				keepalive: true,
				credentials: 'same-origin',
				headers: params.nonce ? { 'X-WP-Nonce': params.nonce } : {},
			}
		);
	} catch ( e ) {
		// Nothing to do: the holds expire on their own.
	}
}

/**
 * @param {string} base `holds` (staff) or `public/holds` (the website, M04; held for the visitor's cookie).
 * @return {Object} `{ token, expiresAt, extensions, expired, place, release, releaseAll, touch }`.
 */
export function useHolds( base = 'holds' ) {
	const [ token, setToken ] = useState( '' );
	const [ expiresAt, setExpiresAt ] = useState( null );
	const [ extensions, setExtensions ] = useState( 0 );
	const [ expired, setExpired ] = useState( false );
	const lastActivity = useRef( Date.now() );
	const tokenRef = useRef( '' );
	tokenRef.current = token;

	/**
	 * Hold a room (adds it to the token's holds).
	 *
	 * @param {Object} request `{ room_id, room_type_id, rate_plan_id, arrival, units, checkin_time? }`.
	 * @return {Promise<Object>} The hold `{ id, room_id, room, window, total, … }`.
	 */
	const place = useCallback( async ( request ) => {
		const { data } = await post( base, {
			...request,
			...( tokenRef.current ? { token: tokenRef.current } : {} ),
		} );
		setToken( data.token );
		setExpiresAt( data.expires_at );
		setExpired( false );
		lastActivity.current = Date.now();
		return data.hold;
	}, [] );

	/**
	 * Release one room's hold.
	 *
	 * @param {number} holdId Hold id.
	 * @return {Promise<void>}
	 */
	const release = useCallback( async ( holdId ) => {
		if ( ! tokenRef.current || ! holdId ) {
			return;
		}
		try {
			await del( `${ base }/${ tokenRef.current }?hold_id=${ holdId }` );
		} catch ( e ) {
			// Already gone (expired): nothing held any more.
		}
	}, [] );

	/**
	 * Release everything and start over (after a booking is made, or on reset).
	 *
	 * @param {boolean} remote Also tell the server (false after a booking consumed them).
	 * @return {void}
	 */
	const releaseAll = useCallback( ( remote = true ) => {
		if ( remote && tokenRef.current ) {
			del( `${ base }/${ tokenRef.current }` ).catch( () => {} );
		}
		setToken( '' );
		setExpiresAt( null );
		setExtensions( 0 );
		setExpired( false );
	}, [] );

	// The form is in use: note it (keys, clicks, touches anywhere).
	const touch = useCallback( () => {
		lastActivity.current = Date.now();
	}, [] );
	useEffect( () => {
		const events = [ 'keydown', 'pointerdown' ];
		events.forEach( ( name ) =>
			window.addEventListener( name, touch, { passive: true } )
		);
		return () =>
			events.forEach( ( name ) =>
				window.removeEventListener( name, touch )
			);
	}, [ touch ] );

	// Extend automatically near the end while the form is in use (at most twice);
	// mark the holds expired when time runs out.
	useEffect( () => {
		if ( ! token || ! expiresAt ) {
			return undefined;
		}
		const timer = window.setInterval( async () => {
			const left = new Date( expiresAt ).getTime() - Date.now();
			if ( left <= 0 ) {
				setExpired( true );
				return;
			}
			const active = Date.now() - lastActivity.current < 10 * 60 * 1000;
			if (
				left <= EXTEND_WHEN_LEFT &&
				active &&
				extensions < MAX_EXTENSIONS
			) {
				setExtensions( ( n ) => n + 1 );
				try {
					const { data } = await put( `${ base }/${ token }` );
					setExpiresAt( data.expires_at );
				} catch ( e ) {
					setExpired( true );
				}
			}
		}, 5000 );
		return () => window.clearInterval( timer );
	}, [ token, expiresAt, extensions ] );

	// Leaving the flow (another screen, closing the tab) releases the holds.
	useEffect( () => {
		const onUnload = () => releaseOnUnload( tokenRef.current, base );
		window.addEventListener( 'pagehide', onUnload );
		return () => {
			window.removeEventListener( 'pagehide', onUnload );
			if ( tokenRef.current ) {
				del( `${ base }/${ tokenRef.current }` ).catch( () => {} );
			}
		};
	}, [] );

	return {
		token,
		expiresAt,
		extensions,
		expired,
		place,
		release,
		releaseAll,
		touch,
	};
}
