/**
 * Keeps a guest from losing a booking in progress by accident (M04).
 *
 * - The browser's Back button: while the booking is under way, one extra
 *   history entry sits on top of the page. Back removes that entry instead of
 *   leaving, and the flow asks first (`asking`). *Stay* puts the entry back;
 *   *Leave* goes back for real (the held rooms are released on unload).
 * - Refresh or closing the tab: the browser's own "Leave site?" prompt
 *   (browsers do not let a page set that text).
 *
 * Once the booking is made, `allowLeave()` turns both off, so the redirect to
 * the booking's page goes through without a prompt.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

const GUARD = { rtbpBookingGuard: true };

// The booking is done: nothing left to lose.
let allowed = false;

/**
 * Let the page go without asking (the booking was made).
 *
 * @return {void}
 */
export function allowLeave() {
	allowed = true;
}

/**
 * @param {boolean} active Whether the booking has progress worth keeping.
 * @return {{asking: boolean, stay: Function, leave: Function}} The prompt's state and answers.
 */
export function useLeaveGuard( active ) {
	const [ asking, setAsking ] = useState( false );
	const activeRef = useRef( active );
	// Our entry is on top of the history.
	const guarded = useRef( false );
	// The guest chose to leave: let the page go.
	const leaving = useRef( false );

	activeRef.current = active;

	useEffect( () => {
		if ( active && ! guarded.current ) {
			window.history.pushState( GUARD, '' );
			guarded.current = true;
		}
	}, [ active ] );

	useEffect( () => {
		const onPop = () => {
			if ( ! guarded.current || leaving.current || allowed ) {
				return;
			}
			guarded.current = false;
			if ( activeRef.current ) {
				setAsking( true );
			}
		};
		const onBeforeUnload = ( event ) => {
			if ( ! activeRef.current || leaving.current || allowed ) {
				return;
			}
			event.preventDefault();
			// Older browsers need a value to show the prompt.
			event.returnValue = '';
		};
		window.addEventListener( 'popstate', onPop );
		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () => {
			window.removeEventListener( 'popstate', onPop );
			window.removeEventListener( 'beforeunload', onBeforeUnload );
		};
	}, [] );

	const stay = useCallback( () => {
		window.history.pushState( GUARD, '' );
		guarded.current = true;
		setAsking( false );
	}, [] );

	const leave = useCallback( () => {
		leaving.current = true;
		setAsking( false );
		window.history.back();
		// Nothing to go back to (the guest landed here): start again instead.
		window.setTimeout( () => window.location.reload(), 400 );
	}, [] );

	return { asking, stay, leave };
}
