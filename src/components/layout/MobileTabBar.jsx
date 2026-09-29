/**
 * Bottom tab bar on phones (below 960px): the front-desk routes marked
 * `mobileTab` in the route table, plus "More", which opens the full menu.
 * Keeps the actions reception uses most one thumb-tap away.
 */
import { NavLink } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { Menu } from 'lucide-react';

import { canSee, getRoutes } from '@/admin/routes';
import { useAccessMap } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props            Props.
 * @param {Function} props.onOpenMenu Opens the full menu drawer.
 * @return {JSX.Element} Tab bar.
 */
export default function MobileTabBar( { onOpenMenu } ) {
	// Re-render when the access map changes (canSee reads it).
	useAccessMap();
	const tabs = getRoutes()
		.filter( ( route ) => route.mobileTab && canSee( route ) )
		.slice( 0, 4 );

	const itemClass =
		'flex min-w-0 flex-1 flex-col items-center justify-center gap-1 border-0 bg-transparent px-0.5 text-[10.5px] font-medium tracking-tight no-underline';

	return (
		<nav
			className="rtbp-shell-tabbar fixed inset-x-0 bottom-0 z-40 flex h-16 border-t border-border bg-card shadow-[0_-1px_3px_rgba(16,24,40,0.06)]"
			aria-label={ __( 'Quick navigation', 'radius-hotel-booking' ) }
		>
			{ tabs.map( ( route ) => {
				const Icon = route.icon;
				return (
					<NavLink
						key={ route.path }
						to={ route.path }
						end={ route.path === '/' || route.path === '/bookings' }
						className={ ( { isActive } ) =>
							cn(
								itemClass,
								isActive
									? 'text-primary'
									: 'text-muted-foreground'
							)
						}
					>
						{ Icon ? (
							<Icon className="h-5 w-5" aria-hidden="true" />
						) : null }
						<span className="max-w-full truncate">
							{ route.mobileLabel || route.label }
						</span>
					</NavLink>
				);
			} ) }
			<button
				type="button"
				onClick={ onOpenMenu }
				className={ cn( itemClass, 'text-muted-foreground' ) }
			>
				<Menu className="h-5 w-5" aria-hidden="true" />
				<span>{ __( 'More', 'radius-hotel-booking' ) }</span>
			</button>
		</nav>
	);
}
