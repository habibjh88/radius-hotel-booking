/**
 * Sidebar navigation: brand and grouped routes. The account menu is in the
 * top bar (UserMenu, R1-T6).
 *
 * Built from src/admin/routes.js, so a route added there (or by an add-on
 * through `rtbp.admin.routes`) appears here with no other change.
 */
import { useEffect, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { ChevronDown, ChevronLeft, ChevronRight, X } from 'lucide-react';

import Logo from '@/components/brand/Logo';
import { useBrand } from '@/lib/brand';
import {
	NAV_GROUPS,
	canSee,
	getRoutes,
	navKey,
	navTarget,
} from '@/admin/routes';
import { useAccessMap } from '@/lib/access';
import { cn } from '@/lib/utils';

/**
 * Brand block at the top of the sidebar.
 *
 * @param {Object}  props           Props.
 * @param {boolean} props.collapsed Icon-only mode.
 * @return {JSX.Element} Brand.
 */
function Brand( { collapsed } ) {
	// The hotel's own name and logo (Settings → General); the product's otherwise.
	const brand = useBrand();
	const name = brand.name || 'RadiusHotel';
	return (
		<div
			className={ cn(
				'flex h-[72px] shrink-0 items-center gap-3 border-b border-border',
				collapsed ? 'justify-center px-0' : 'px-5'
			) }
		>
			{ brand.logo_url ? (
				<img
					src={ brand.logo_url }
					alt={ name }
					className="h-9 w-auto max-w-[7rem] shrink-0 object-contain"
				/>
			) : (
				<Logo className="h-9 w-9 shrink-0" title={ name } />
			) }
			{ ! collapsed ? (
				<div className="min-w-0">
					<p
						className="m-0 truncate text-[17px] font-bold leading-5 tracking-tight text-heading"
						title={ name }
					>
						{ name }
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
 * One navigation link, with its sub-links (`route.subnav`) below it. They show
 * only while one of the item's pages is open; clicking the item there folds
 * them away again.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.route      Route.
 * @param {Array}    props.claimed    `navSearch` of the menu-only entries sharing this path.
 * @param {boolean}  props.collapsed  Icon-only mode.
 * @param {Function} props.onNavigate Called after a click (closes the drawer).
 * @return {JSX.Element} Link.
 */
function NavItem( { route, claimed = [], collapsed, onNavigate } ) {
	const Icon = route.icon;
	const { pathname, search } = useLocation();
	const query = new URLSearchParams( search );
	const matches = ( wanted ) =>
		Object.entries( wanted ).every(
			( [ key, value ] ) => query.get( key ) === String( value )
		);
	// A menu-only entry is active on its query; the plain entry yields to it.
	const resolve = ( isActive ) => {
		if ( route.navSearch ) {
			return pathname === route.path && matches( route.navSearch );
		}
		return isActive && ! claimed.some( matches );
	};
	const subnav =
		! collapsed && Array.isArray( route.subnav ) ? route.subnav : [];
	const inside =
		pathname === route.path || pathname.startsWith( `${ route.path }/` );
	const [ open, setOpen ] = useState( inside );

	// Opening one of the item's pages shows its sub-links; leaving hides them.
	useEffect( () => {
		setOpen( inside );
	}, [ inside, pathname ] );

	const onClick = () => {
		if ( subnav.length ) {
			setOpen( ( was ) => ( inside ? ! was : true ) );
		}
		onNavigate?.();
	};
	const shown = subnav.length > 0 && open;

	// Another page of the site (an add-on's other dashboard, R1-T18): a plain link.
	if ( route.href ) {
		return (
			<a
				href={ route.href }
				onClick={ () => onNavigate?.() }
				title={ collapsed ? route.label : undefined }
				aria-label={ collapsed ? route.label : undefined }
				className={ cn(
					'group relative flex h-10 items-center gap-3 rounded-lg text-sm font-medium text-sidebar-foreground no-underline outline-none transition-colors duration-150 hover:bg-primary-softer hover:text-heading',
					'focus-visible:ring-2 focus-visible:ring-ring',
					collapsed ? 'justify-center px-0' : 'px-3'
				) }
			>
				{ Icon ? (
					<Icon
						className="h-[18px] w-[18px] shrink-0 text-muted-foreground group-hover:text-heading"
						aria-hidden="true"
					/>
				) : null }
				{ ! collapsed ? (
					<span className="min-w-0 flex-1 truncate">
						{ route.label }
					</span>
				) : null }
			</a>
		);
	}

	return (
		<>
			<NavLink
				to={ navTarget( route ) }
				// `/bookings` must not light up on `/bookings/new` (as the tab bar does).
				end={ route.path === '/' || route.path === '/bookings' }
				onClick={ onClick }
				title={ collapsed ? route.label : undefined }
				aria-label={ collapsed ? route.label : undefined }
				aria-expanded={ subnav.length ? shown : undefined }
				className={ ( { isActive: linkActive } ) => {
					const isActive = resolve( linkActive );
					return cn(
						'group relative flex h-10 items-center gap-3 rounded-lg text-sm no-underline outline-none transition-colors duration-150',
						'focus-visible:ring-2 focus-visible:ring-ring',
						collapsed ? 'justify-center px-0' : 'px-3',
						isActive
							? 'bg-primary-soft font-semibold text-primary'
							: 'font-medium text-sidebar-foreground hover:bg-primary-softer hover:text-heading'
					);
				} }
			>
				{ ( { isActive: linkActive } ) => {
					const isActive = resolve( linkActive );
					return (
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
								<span className="min-w-0 flex-1 truncate">
									{ route.label }
								</span>
							) : null }
							{ subnav.length ? (
								<ChevronDown
									className={ cn(
										'h-4 w-4 shrink-0 transition-transform duration-200',
										isActive
											? 'text-primary'
											: 'text-muted-foreground',
										shown && 'rotate-180'
									) }
									aria-hidden="true"
								/>
							) : null }
						</>
					);
				} }
			</NavLink>
			{ shown ? (
				<ul className="m-0 mb-1 ml-[21px] mt-0.5 list-none space-y-0.5 border-l border-border p-0 pl-3">
					{ subnav.map( ( item ) => (
						<li key={ item.path } className="m-0">
							<NavLink
								to={ item.path }
								end
								onClick={ onNavigate }
								className={ ( { isActive } ) =>
									cn(
										'flex h-8 items-center rounded-md px-3 text-[13px] no-underline outline-none transition-colors duration-150',
										'focus-visible:ring-2 focus-visible:ring-ring',
										isActive
											? 'bg-primary-softer font-semibold text-primary'
											: 'font-medium text-muted-foreground hover:bg-primary-softer hover:text-heading'
									)
								}
							>
								<span className="truncate">{ item.label }</span>
							</NavLink>
						</li>
					) ) }
				</ul>
			) : null }
		</>
	);
}

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
	// Re-render when the access map changes (canSee reads it).
	useAccessMap();
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
						<ChevronRight
							className="h-3.5 w-3.5"
							aria-hidden="true"
						/>
					) : (
						<ChevronLeft
							className="h-3.5 w-3.5"
							aria-hidden="true"
						/>
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
								<li key={ navKey( route ) } className="m-0">
									<NavItem
										route={ route }
										claimed={ routes
											.filter(
												( other ) =>
													other.navOnly &&
													other.path === route.path
											)
											.map(
												( other ) => other.navSearch
											) }
										collapsed={ collapsed }
										onNavigate={
											mobile ? onClose : undefined
										}
									/>
								</li>
							) ) }
						</ul>
					</div>
				) ) }
			</nav>
		</aside>
	);
}
