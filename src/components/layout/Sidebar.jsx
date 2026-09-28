/**
 * Sidebar navigation: brand, grouped routes, current user.
 *
 * Built from src/admin/routes.js, so a route added there (or by an add-on
 * through `rtbp.admin.routes`) appears here with no other change.
 */
import { NavLink } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';

import Logo from '@/components/brand/Logo';
import { NAV_GROUPS, canSee, getRoutes } from '@/admin/routes';
import { cn } from '@/lib/utils';

/**
 * Brand block at the top of the sidebar.
 *
 * @param {Object}  props           Props.
 * @param {boolean} props.collapsed Icon-only mode.
 * @return {JSX.Element} Brand.
 */
function Brand( { collapsed } ) {
	return (
		<div
			className={ cn(
				'flex h-[72px] shrink-0 items-center gap-3 border-b border-border',
				collapsed ? 'justify-center px-0' : 'px-5'
			) }
		>
			<Logo className="h-9 w-9 shrink-0" title="Radius Hotel Booking" />
			{ ! collapsed ? (
				<div className="min-w-0">
					<p className="m-0 truncate text-[17px] font-bold leading-5 tracking-tight text-heading">
						RadiusHotel
					</p>
					<p className="m-0 mt-0.5 truncate text-[10px] font-semibold uppercase leading-3 tracking-[0.08em] text-muted-foreground">
						{ __( 'Manage your hotel', 'radius-hotel-booking' ) }
					</p>
				</div>
			) : null }
		</div>
	);
}

/**
 * One navigation link.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.route     Route.
 * @param {boolean}  props.collapsed Icon-only mode.
 * @param {Function} props.onNavigate Called after a click (closes the drawer).
 * @return {JSX.Element} Link.
 */
function NavItem( { route, collapsed, onNavigate } ) {
	const Icon = route.icon;

	return (
		<NavLink
			to={ route.path }
			end={ route.path === '/' }
			onClick={ onNavigate }
			title={ collapsed ? route.label : undefined }
			aria-label={ collapsed ? route.label : undefined }
			className={ ( { isActive } ) =>
				cn(
					'group relative flex h-10 items-center gap-3 rounded-lg text-sm no-underline outline-none transition-colors duration-150',
					'focus-visible:ring-2 focus-visible:ring-ring',
					collapsed ? 'justify-center px-0' : 'px-3',
					isActive
						? 'bg-primary-soft font-semibold text-primary'
						: 'font-medium text-sidebar-foreground hover:bg-primary-softer hover:text-heading'
				)
			}
		>
			{ ( { isActive } ) => (
				<>
					{ isActive ? (
						<span
							aria-hidden="true"
							className="absolute left-0 top-2 bottom-2 w-[3px] rounded-r-full bg-primary"
						/>
					) : null }
					{ Icon ? (
						<Icon
							className={ cn(
								'h-[18px] w-[18px] shrink-0',
								isActive
									? 'text-primary'
									: 'text-muted-foreground group-hover:text-heading'
							) }
							aria-hidden="true"
						/>
					) : null }
					{ ! collapsed ? (
						<span className="truncate">{ route.label }</span>
					) : null }
				</>
			) }
		</NavLink>
	);
}

/**
 * Initials for the avatar.
 *
 * @param {string} name Display name.
 * @return {string} Up to two letters.
 */
const initials = ( name = '' ) =>
	name
		.split( /\s+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( part ) => part[ 0 ].toUpperCase() )
		.join( '' ) || '?';

/**
 * The sidebar.
 *
 * @param {Object}   props                 Props.
 * @param {boolean}  props.collapsed       Icon-only mode (desktop).
 * @param {Function} props.onToggleCollapse Collapse button handler (desktop).
 * @param {boolean}  props.mobile          Rendered inside the mobile drawer.
 * @param {Function} props.onClose         Closes the mobile drawer.
 * @return {JSX.Element} Sidebar.
 */
export default function Sidebar( {
	collapsed = false,
	onToggleCollapse,
	mobile = false,
	onClose,
} ) {
	const routes = getRoutes().filter(
		( route ) => route.group && ! route.hidden && canSee( route )
	);
	const knownGroups = NAV_GROUPS.map( ( group ) => group.key );
	const groups = [
		...NAV_GROUPS,
		// Groups an add-on introduces, after the built-in ones.
		...[ ...new Set( routes.map( ( route ) => route.group ) ) ]
			.filter( ( key ) => ! knownGroups.includes( key ) )
			.map( ( key ) => ( { key, label: key } ) ),
	]
		.map( ( group ) => ( {
			...group,
			routes: routes.filter( ( route ) => route.group === group.key ),
		} ) )
		.filter( ( group ) => group.routes.length );

	const user = window.radius_hotel_booking_param?.current_user || {};

	return (
		<aside
			className={ cn(
				'flex flex-col border-r border-border bg-sidebar',
				// Desktop: .rtbp-shell-sidebar makes it sticky (which also
				// anchors the absolutely positioned collapse button). A
				// `relative` utility here would override that.
				mobile
					? 'relative h-full w-[280px] shadow-xl'
					: 'rtbp-shell-sidebar z-30 transition-[width] duration-200 ease-out',
				! mobile && ( collapsed ? 'w-[76px]' : 'w-[256px]' )
			) }
			aria-label={ __( 'Hotel navigation', 'radius-hotel-booking' ) }
		>
			<Brand collapsed={ collapsed } />

			{ mobile ? (
				<button
					type="button"
					onClick={ onClose }
					className="absolute right-3 top-5 flex h-8 w-8 items-center justify-center rounded-md border-0 bg-transparent text-muted-foreground hover:bg-muted hover:text-heading"
					aria-label={ __( 'Close menu', 'radius-hotel-booking' ) }
				>
					<X className="h-4 w-4" aria-hidden="true" />
				</button>
			) : (
				<button
					type="button"
					onClick={ onToggleCollapse }
					className="absolute -right-3.5 top-[22px] z-10 flex h-7 w-7 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-sm transition-colors hover:border-primary hover:text-primary"
					aria-label={
						collapsed
							? __( 'Expand menu', 'radius-hotel-booking' )
							: __( 'Collapse menu', 'radius-hotel-booking' )
					}
					aria-expanded={ ! collapsed }
				>
					{ collapsed ? (
						<ChevronRight className="h-3.5 w-3.5" aria-hidden="true" />
					) : (
						<ChevronLeft className="h-3.5 w-3.5" aria-hidden="true" />
					) }
				</button>
			) }

			<nav className="flex-1 overflow-y-auto px-3 pb-4">
				{ groups.map( ( group ) => (
					<div key={ group.key } className="pt-5">
						{ collapsed ? (
							<div
								className="mx-auto mb-2 h-px w-8 bg-border"
								aria-hidden="true"
							/>
						) : (
							<p className="m-0 mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
								{ group.label }
							</p>
						) }
						<ul className="m-0 list-none space-y-0.5 p-0">
							{ group.routes.map( ( route ) => (
								<li key={ route.path } className="m-0">
									<NavItem
										route={ route }
										collapsed={ collapsed }
										onNavigate={ mobile ? onClose : undefined }
									/>
								</li>
							) ) }
						</ul>
					</div>
				) ) }
			</nav>

			<div
				className={ cn(
					'flex shrink-0 items-center gap-3 border-t border-border py-3',
					collapsed ? 'justify-center px-0' : 'px-4'
				) }
			>
				<span
					className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary"
					title={ collapsed ? user.name : undefined }
				>
					{ initials( user.name ) }
				</span>
				{ ! collapsed ? (
					<div className="min-w-0">
						<p className="m-0 truncate text-sm font-semibold text-heading">
							{ user.name }
						</p>
						<p className="m-0 truncate text-xs text-muted-foreground">
							{ user.email }
						</p>
					</div>
				) : null }
			</div>
		</aside>
	);
}
