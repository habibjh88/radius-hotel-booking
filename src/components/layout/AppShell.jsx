/**
 * Admin app shell: sidebar + top bar + routed screen.
 *
 * Same structure as the Radius Booking admin (sidebar that collapses to
 * icons, mobile drawer below 960px, a white header carrying the page title and
 * description), built on the plugin's own route table and design tokens.
 * The sidebar is sticky rather than fixed, so it needs no knowledge of the
 * WordPress admin menu width and also works on a front-end mount.
 */
import { __ } from '@wordpress/i18n';

import Sidebar from './Sidebar';
import Topbar from './Topbar';
import { PageActionsProvider } from './PageActions';
import useSidebarState from './useSidebarState';

/**
 * @param {Object}      props          Props.
 * @param {JSX.Element} props.children Routed screen.
 * @return {JSX.Element} Shell.
 */
export default function AppShell( { children } ) {
	const { collapsed, toggleCollapsed, mobileOpen, setMobileOpen, isDesktop } =
		useSidebarState();

	return (
		<PageActionsProvider>
			<div className="rtbp-root flex min-h-[calc(100vh-32px)] bg-app text-foreground">
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
							aria-label={ __( 'Close menu', 'radius-hotel-booking' ) }
						/>
						<div className="relative h-full">
							<Sidebar mobile onClose={ () => setMobileOpen( false ) } />
						</div>
					</div>
				) : null }

				<div className="flex min-w-0 flex-1 flex-col">
					<Topbar
						isDesktop={ isDesktop }
						onOpenMenu={ () => setMobileOpen( true ) }
					/>
					<main className="min-w-0 flex-1 p-4 md:p-8">
						<div className="mx-auto w-full max-w-[1400px]">
							{ children }
						</div>
					</main>
				</div>
			</div>
		</PageActionsProvider>
	);
}
