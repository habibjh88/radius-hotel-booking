import { NavLink, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';

import routes from '@/admin/routes';
import { cn } from '@/lib/utils';

/**
 * Admin shell: a sidebar of routes plus a header showing the active screen.
 *
 * @param {Object}      props          Component props.
 * @param {JSX.Element} props.children Routed screen.
 * @return {JSX.Element} Layout.
 */
export default function Layout( { children } ) {
	const location = useLocation();
	const active = routes.find( ( route ) => route.path === location.pathname );

	return (
		<div className="rtbp-root flex min-h-[calc(100vh-32px)] bg-background text-foreground">
			<aside className="hidden w-60 shrink-0 border-r border-sidebar-border bg-sidebar md:block">
				<div className="px-5 py-4">
					<span className="text-base font-semibold text-sidebar-foreground">
						{ __( 'Radius Hotel Booking', 'radius-hotel-booking' ) }
					</span>
				</div>

				<nav className="space-y-1 px-3 pb-6">
					{ routes.map( ( { path, label, icon: Icon } ) => (
						<NavLink
							key={ path }
							to={ path }
							end={ path === '/' }
							className={ ( { isActive } ) =>
								cn(
									'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
									isActive
										? 'bg-sidebar-accent text-sidebar-accent-foreground'
										: 'text-sidebar-foreground hover:bg-sidebar-accent/60'
								)
							}
						>
							{ Icon ? (
								<Icon className="h-4 w-4" aria-hidden="true" />
							) : null }
							{ label }
						</NavLink>
					) ) }
				</nav>
			</aside>

			<div className="flex min-w-0 flex-1 flex-col">
				<header className="border-b border-border bg-card px-6 py-4">
					<h1 className="m-0 text-lg font-semibold">
						{ active?.label ??
							__( 'Radius Hotel Booking', 'radius-hotel-booking' ) }
					</h1>
				</header>

				<main className="min-w-0 flex-1 p-6">{ children }</main>
			</div>
		</div>
	);
}
