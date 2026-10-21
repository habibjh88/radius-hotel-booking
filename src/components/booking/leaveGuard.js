/**
 * Keeps a guest from losing a booking in progress by accident (M04).
 *
 * - The browser's Back button: each step past the first is its own history
 *   entry, added when the guest moves forward (so after a click: Chrome skips
 *   entries a page adds without one, and Back would then leave the page).
 *   Back goes to the previous step, like the page's own back button
 *   (`onBack`). On the first step with rooms held, one entry still sits on
 *   top of the page (put back on the guest's next click when Back led
 *   there); Back removes it and the flow asks first (`asking`).
 *   *Stay* puts the entry back; *Leave* goes back for real (the held rooms are
 *   released on unload).
 * - The page's own back buttons take the history back with them, so the
 *   browser's Back never lands on a step the guest already left.
 * - Refresh or closing the tab: the browser's own "Leave site?" prompt
 *   (browsers do not let a page set that text).
 *
 * Once the booking is made, `allowLeave()` turns both off, so the redirect to
 * the booking's page goes through without a prompt.
 */
import { useCallback, useEffect, useRef, useState } from 'react';

const KEY = 'rtbpBookingDepth';

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
 * How many of our entries sit on top of the page in the current one.
 *
 * @param {Object|null} state A history state.
 * @return {number} Depth (0 is the page itself).
 */
function depthOf( state ) {
	return state && Number.isInteger( state[ KEY ] ) ? state[ KEY ] : 0;
}

/**
 * @param {number}   level    History entries the current step needs above the
 *                            page: 0 when there is nothing to keep, else the
 *                            step's number (at least 1).
 * @param {Function} [onBack] Goes to the previous step; returns false on the first step.
 * @return {{asking: boolean, stay: Function, leave: Function}} The prompt's state and answers.
 */
export function useLeaveGuard( level, onBack ) {
	const [ asking, setAsking ] = useState( false );
	const levelRef = useRef( level );
	const backRef = useRef( onBack );
	// Our entries on top of the page right now.
	const depth = useRef( depthOf( window.history.state ) );
	// Pops we caused ourselves (the page's back buttons): not the guest's Back.
	const ignore = useRef( 0 );
	// The guest chose to leave: let the page go.
	const leaving = useRef( false );

	levelRef.current = level;
	backRef.current = onBack;

	// Keep the history as deep as the step: forward adds entries, the page's
	// back buttons remove them.
	const sync = useRef( null );
	sync.current = () => {
		if ( leaving.current || allowed || asking ) {
			return;
		}
		const want = levelRef.current;
		if ( want > depth.current ) {
			while ( depth.current < want ) {
				depth.current += 1;
				window.history.pushState( { [ KEY ]: depth.current }, '' );
			}
		} else if ( want < depth.current ) {
			ignore.current += 1;
			window.history.go( want - depth.current );
			depth.current = want;
		}
	};
	useEffect( () => sync.current(), [ level, asking ] );

	useEffect( () => {
		const onPop = ( event ) => {
			if ( leaving.current || allowed ) {
				return;
			}
			const now = depthOf( event.state );
			if ( ignore.current > 0 ) {
				ignore.current -= 1;
				depth.current = now;
				return;
			}
			const was = depth.current;
			depth.current = now;
			if ( now >= was || ! levelRef.current ) {
				// Forward, or nothing to keep: the sync effect sorts it out.
				return;
			}
			// A step to go back to: take it.
			if ( backRef.current && false !== backRef.current() ) {
				// Back on the first step with rooms still held: its entry goes
				// back on the guest's next click or key (Chrome skips entries
				// added without one, and Back would then leave the page).
				document.addEventListener( 'pointerdown', onTouch, true );
				document.addEventListener( 'keydown', onTouch, true );
				return;
			}
			setAsking( true );
		};
		const onTouch = () => {
			document.removeEventListener( 'pointerdown', onTouch, true );
			document.removeEventListener( 'keydown', onTouch, true );
			sync.current();
		};
		const onBeforeUnload = ( event ) => {
			if ( ! levelRef.current || leaving.current || allowed ) {
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
			document.removeEventListener( 'pointerdown', onTouch, true );
			document.removeEventListener( 'keydown', onTouch, true );
		};
	}, [] );

	// Called from the button's click, so this entry is not skipped later.
	const stay = useCallback( () => {
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
