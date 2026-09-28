/**
 * Keyboard-shortcut helpers, kept out of CommandPalette so the palette (and
 * its cmdk dependency) can be lazy-loaded on first use.
 */
import { useEffect } from 'react';

/**
 * Whether the platform uses ⌘ rather than Ctrl.
 *
 * @return {boolean} Mac-like.
 */
export const isMac = () =>
	typeof navigator !== 'undefined' &&
	/Mac|iPhone|iPad/.test( navigator.platform || navigator.userAgent );

/**
 * Call `onToggle` on ⌘K / Ctrl+K anywhere in the app.
 *
 * @param {Function} onToggle Handler.
 * @return {void}
 */
export function useCommandShortcut( onToggle ) {
	useEffect( () => {
		const onKey = ( event ) => {
			if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 'k' ) {
				event.preventDefault();
				onToggle();
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ onToggle ] );
}
