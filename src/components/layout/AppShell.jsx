/**
 * Admin app shell: sidebar + top bar + routed screen.
 *
 * Same structure as the Radius Booking admin (sidebar that collapses to
 * icons, mobile drawer below 960px, a white header carrying the page title and
 * description), built on the plugin's own route table and design tokens.
 * The sidebar is sticky rather than fixed, so it needs no knowledge of the
 * WordPress admin menu width and also works on a front-end mount.
 */
import {
	Suspense,
	lazy,
	useCallback,
	useEffect,
	useRef,
	useState,
} from 'react';
import { useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { Toaster } from 'sonner';

import MobileTabBar from './MobileTabBar';
import Sidebar from './Sidebar';
import Topbar from './Topbar';
import { PageActionsProvider } from './PageActions';
import useSidebarState from './useSidebarState';
import { useCommandShortcut } from './shortcuts';

// Loaded on first open: keeps cmdk and the dialog out of the initial bundle.
const CommandPalette = lazy( () => import( './CommandPalette' ) );

/**
 * A new screen opens at its top. The page (not the shell) scrolls, so the
 * scroll left from the previous screen would otherwise carry over. Only a
 * path change counts: filters and Settings tabs (`?…`) keep the position.
 *
 * @param {Object} shellRef The shell element.
 * @return {void}
 */
function useScrollTopOnNavigate( shellRef ) {
	const { pathname } = useLocation();
	const first = useRef( true );

	useEffect( () => {
		if ( first.current ) {
			first.current = false;
			return;
		}
		const shell = shellRef.current;
		if ( ! shell ) {
			return;
		}
		// Under the admin bar, as the sticky header sits.
		const offset =
			parseFloat(
				window
					.getComputedStyle( shell )
					.getPropertyValue( '--rtbp-top' )
			) || 0;
		const top = shell.getBoundingClientRect().top - offset;
		// Already showing the top of the app (a front-end page above it stays put).
		if ( top >= 0 ) {
			return;
		}
		window.scrollTo( { top: Math.max( 0, window.scrollY + top ) } );
	}, [ pathname, shellRef ] );
}

/**
 * @param {Object}      props          Props.
 * @param {JSX.Element} props.children Routed screen.
 * @return {JSX.Element} Shell.
 */
export default function AppShell( { children } ) {
	const { collapsed, toggleCollapsed, mobileOpen, setMobileOpen, isDesktop } =
		useSidebarState();
	const [ paletteOpen, setPaletteOpen ] = useState( false );
	const [ paletteLoaded, setPaletteLoaded ] = useState( false );
	const shellRef = useRef( null );
	useScrollTopOnNavigate( shellRef );

	const openPalette = useCallback( ( next = true ) => {
		setPaletteLoaded( true );
		setPaletteOpen( next );
	}, [] );
	const togglePalette = useCallback( () => {
		setPaletteLoaded( true );
		setPaletteOpen( ( current ) => ! current );
	}, [] );
	useCommandShortcut( togglePalette );

	return (
		<PageActionsProvider>
			<div
				ref={ shellRef }
				className="rtbp-root rtbp-shell flex bg-app text-foreground"
			>
				{ isDesktop ? (
					<Sidebar
						collapsed={ collapsed }
						onToggleCollapse={ toggleCollapsed }
					/>
				) : null }

				{ ! isDesktop && mobileOpen ? (
					<div
						className="fixed inset-0 z-[100000] flex"
						role="dialog"
						aria-modal="true"
						aria-label={ __( 'Menu', 'radius-hotel-booking' ) }
					>
						<button
							type="button"
							className="absolute inset-0 border-0 p-0"
							style={ { background: 'rgba(0, 19, 77, 0.4)' } }
							onClick={ () => setMobileOpen( false ) }
							aria-label={ __(
								'Close menu',
								'radius-hotel-booking'
							) }
						/>
						<div className="relative h-full">
							<Sidebar
								mobile
								onClose={ () => setMobileOpen( false ) }
							/>
						</div>
					</div>
				) : null }

				<div className="flex min-w-0 flex-1 flex-col">
					<Topbar
						isDesktop={ isDesktop }
						onOpenMenu={ () => setMobileOpen( true ) }
						onOpenSearch={ () => openPalette( true ) }
					/>
					{ /* Phones: bottom padding clears the tab bar. */ }
					<main
						className={
							isDesktop
								? 'min-w-0 flex-1 p-4 md:p-8'
								: 'min-w-0 flex-1 p-4 pb-24'
						}
					>
						<div className="mx-auto w-full max-w-[1400px]">
							{ children }
						</div>
					</main>
				</div>

				{ ! isDesktop ? (
					<MobileTabBar onOpenMenu={ () => setMobileOpen( true ) } />
				) : null }

				{ /* Toasts: top-right under the header (never over its buttons); top on phones. */ }
				<Toaster
					position={ isDesktop ? 'top-right' : 'top-center' }
					offset={ {
						top: 'calc(var(--rtbp-top) + 84px)',
						right: 24,
					} }
					mobileOffset={ { top: 'calc(var(--rtbp-top) + 8px)' } }
					richColors
					closeButton
					theme="light"
					toastOptions={ { className: 'rtbp-toast' } }
				/>

				{ paletteLoaded ? (
					<Suspense fallback={ null }>
						<CommandPalette
							open={ paletteOpen }
							onOpenChange={ openPalette }
						/>
					</Suspense>
				) : null }
			</div>
		</PageActionsProvider>
	);
}
