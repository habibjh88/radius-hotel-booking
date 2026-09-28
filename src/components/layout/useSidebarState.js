/**
 * Sidebar state: collapsed (desktop, remembered per browser) and open (the
 * mobile drawer, below the 960px breakpoint — the same breakpoint as the
 * Radius Booking admin).
 */
import { useCallback, useEffect, useState } from 'react';

export const DESKTOP_QUERY = '(min-width: 960px)';

const STORAGE_KEY = 'rtbp.sidebar.collapsed';

/**
 * Read the remembered collapsed state. Storage can be unavailable (private
 * mode, blocked site data), so failures fall back to expanded.
 *
 * @return {boolean} Collapsed.
 */
function readCollapsed() {
	try {
		return window.localStorage.getItem( STORAGE_KEY ) === '1';
	} catch ( e ) {
		return false;
	}
}

/**
 * @return {{collapsed: boolean, toggleCollapsed: Function, mobileOpen: boolean, setMobileOpen: Function, isDesktop: boolean}} State.
 */
export default function useSidebarState() {
	const [ collapsed, setCollapsed ] = useState( readCollapsed );
	const [ mobileOpen, setMobileOpen ] = useState( false );
	const [ isDesktop, setIsDesktop ] = useState(
		() => window.matchMedia?.( DESKTOP_QUERY ).matches ?? true
	);

	useEffect( () => {
		const query = window.matchMedia?.( DESKTOP_QUERY );
		if ( ! query ) {
			return undefined;
		}
		const onChange = ( event ) => {
			setIsDesktop( event.matches );
			if ( event.matches ) {
				setMobileOpen( false );
			}
		};
		query.addEventListener( 'change', onChange );
		return () => query.removeEventListener( 'change', onChange );
	}, [] );

	// Escape closes the mobile drawer.
	useEffect( () => {
		if ( ! mobileOpen ) {
			return undefined;
		}
		const onKey = ( event ) => event.key === 'Escape' && setMobileOpen( false );
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ mobileOpen ] );

	const toggleCollapsed = useCallback( () => {
		setCollapsed( ( current ) => {
			const next = ! current;
			try {
				window.localStorage.setItem( STORAGE_KEY, next ? '1' : '0' );
			} catch ( e ) {
				// Not persisted; the toggle still works for this page view.
			}
			return next;
		} );
	}, [] );

	return { collapsed, toggleCollapsed, mobileOpen, setMobileOpen, isDesktop };
}
