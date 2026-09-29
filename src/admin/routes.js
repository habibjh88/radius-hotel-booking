/**
 * Admin routes: the single source of truth for the router, the sidebar and
 * the page header (title + description).
 *
 * Each route:
 * - `path`        Hash-router path.
 * - `group`       Sidebar section (see NAV_GROUPS). Omit or set `hidden` to
 *                 keep a route out of the sidebar (detail/create pages).
 * - `label`       Sidebar label; also the page title unless `title` is set.
 * - `description` One line under the page title.
 * - `icon`        lucide-react component.
 * - `capability`  WordPress capability needed to see it (localized caps).
 * - `accessKey`   M13 access key(s) of the page; hidden when every one is
 *                 locked (src/lib/access.js). The server enforces the same keys.
 * - `mobileTab`   Shown in the bottom tab bar on phones (keep it to 4);
 *                 `mobileLabel` is its tab label, an `_x()` string with a
 *                 "phone tab bar" context so a translator can pick a
 *                 shorter word than the menu label (French: "Accueil").
 * - `redirect`    Path to send the user to instead of rendering a screen.
 * - `element`     Lazy component. Routes without one render the module
 *                 placeholder until their module (`module`) is built.
 *
 * Add-ons append routes through the `rtbp.admin.routes` filter (ADR-015) —
 * the free plugin never lists Pro screens itself.
 */
import { lazy } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __, _x } from '@wordpress/i18n';

import { canAccess } from '@/lib/access';
import {
	BarChart3,
	BedDouble,
	CalendarDays,
	CalendarPlus,
	Clock,
	DoorOpen,
	Download,
	LayoutDashboard,
	Settings,
	ShieldCheck,
	Users,
	Wallet,
} from 'lucide-react';

/**
 * Sidebar sections, in display order.
 *
 * @type {Array<{key: string, label: string}>}
 */
export const NAV_GROUPS = [
	{ key: 'overview', label: __( 'Overview', 'radius-hotel-booking' ) },
	{ key: 'frontDesk', label: __( 'Front desk', 'radius-hotel-booking' ) },
	{ key: 'hotel', label: __( 'Hotel setup', 'radius-hotel-booking' ) },
	{ key: 'insights', label: __( 'Reports & data', 'radius-hotel-booking' ) },
	{ key: 'system', label: __( 'System', 'radius-hotel-booking' ) },
];

const VIEW = 'rtbp_view_dashboard';

const baseRoutes = [
	{
		path: '/',
		mobileTab: true,
		mobileLabel: _x(
			'Dashboard',
			'phone tab bar: keep it short',
			'radius-hotel-booking'
		),
		group: 'overview',
		label: __( 'Dashboard', 'radius-hotel-booking' ),
		description: __(
			'Today at your hotel, at a glance',
			'radius-hotel-booking'
		),
		icon: LayoutDashboard,
		capability: VIEW,
		accessKey: 'page.dashboard',
		element: lazy( () => import( '@/modules/Dashboard' ) ),
	},
	{
		path: '/calendar',
		group: 'overview',
		label: __( 'Availability', 'radius-hotel-booking' ),
		description: __(
			'Open, close and price your rooms by date',
			'radius-hotel-booking'
		),
		icon: CalendarDays,
		capability: VIEW,
		accessKey: 'page.availability',
		module: 'M08',
	},
	{
		path: '/bookings/new',
		mobileTab: true,
		mobileLabel: _x(
			'New',
			'phone tab bar: new booking, keep it short',
			'radius-hotel-booking'
		),
		group: 'frontDesk',
		label: __( 'New booking', 'radius-hotel-booking' ),
		description: __(
			'Take a walk-in booking at the front desk',
			'radius-hotel-booking'
		),
		icon: CalendarPlus,
		capability: VIEW,
		accessKey: 'bookings.create',
		module: 'M02',
	},
	{
		path: '/bookings',
		mobileTab: true,
		mobileLabel: _x(
			'Bookings',
			'phone tab bar: keep it short',
			'radius-hotel-booking'
		),
		group: 'frontDesk',
		label: __( 'Bookings', 'radius-hotel-booking' ),
		description: __(
			'Every booking, arrival and departure',
			'radius-hotel-booking'
		),
		icon: BedDouble,
		capability: VIEW,
		accessKey: 'page.bookings',
		module: 'M01',
	},
	{
		path: '/guests',
		mobileTab: true,
		mobileLabel: _x(
			'Guests',
			'phone tab bar: keep it short',
			'radius-hotel-booking'
		),
		group: 'frontDesk',
		label: __( 'Guests', 'radius-hotel-booking' ),
		description: __(
			'Guest records, documents and stay history',
			'radius-hotel-booking'
		),
		icon: Users,
		capability: VIEW,
		accessKey: 'page.guests',
		module: 'M09',
	},
	{
		path: '/payments',
		group: 'frontDesk',
		label: __( 'Payments', 'radius-hotel-booking' ),
		description: __(
			'Payments received and bookings to follow up',
			'radius-hotel-booking'
		),
		icon: Wallet,
		capability: VIEW,
		accessKey: 'page.bookings',
		module: 'M05',
	},
	{
		path: '/rooms',
		group: 'hotel',
		label: __( 'Rooms & floors', 'radius-hotel-booking' ),
		description: __(
			'Room types, floors and every physical room',
			'radius-hotel-booking'
		),
		icon: DoorOpen,
		capability: VIEW,
		accessKey: 'page.rooms',
		module: 'M06',
	},
	{
		path: '/rate-plans',
		group: 'hotel',
		label: __( 'Rate plans', 'radius-hotel-booking' ),
		description: __(
			'Stay windows such as Half Day or Overnight, and their prices',
			'radius-hotel-booking'
		),
		icon: Clock,
		capability: VIEW,
		accessKey: 'page.rates',
		module: 'M07',
	},
	{
		path: '/reports',
		group: 'insights',
		label: __( 'Reports', 'radius-hotel-booking' ),
		description: __(
			'Sales, rooms and availability reports',
			'radius-hotel-booking'
		),
		icon: BarChart3,
		capability: VIEW,
		accessKey: [ 'page.reports_sales', 'page.reports_rooms' ],
		module: 'M10',
	},
	{
		path: '/exports',
		group: 'insights',
		label: __( 'Exports', 'radius-hotel-booking' ),
		description: __(
			'Export bookings and guests to a spreadsheet',
			'radius-hotel-booking'
		),
		icon: Download,
		capability: VIEW,
		accessKey: 'page.exports',
		module: 'M11',
	},
	{
		path: '/permissions',
		group: 'system',
		label: __( 'Permissions', 'radius-hotel-booking' ),
		description: __(
			'Choose what each staff role may open and do',
			'radius-hotel-booking'
		),
		icon: ShieldCheck,
		capability: VIEW,
		accessKey: 'access.manage',
		// The permission map is a Settings tab.
		redirect: '/settings?section=access',
	},
	{
		path: '/settings',
		group: 'system',
		label: __( 'Settings', 'radius-hotel-booking' ),
		description: __(
			'Hotel details, booking rules and appearance',
			'radius-hotel-booking'
		),
		icon: Settings,
		capability: VIEW,
		accessKey: 'page.settings',
		element: lazy( () => import( '@/modules/Settings' ) ),
	},
];

/*
 * Developer UI kit (#/dev/ui): only with WP_DEBUG on, for administrators
 * (LoadAssets localizes `dev_ui`). Lazy chunk; hidden from menu and search.
 */
if ( window.radius_hotel_booking_param?.dev_ui ) {
	baseRoutes.push( {
		path: '/dev/ui',
		hidden: true,
		label: 'UI kit',
		description: 'Every shared component in every state (WP_DEBUG only)',
		element: lazy( () => import( '@/modules/DevUi' ) ),
	} );
}

let cache = null;

/**
 * All routes, including those add-ons register. Computed once, on first use;
 * main.jsx mounts on DOMContentLoaded so every add-on script has run by then.
 *
 * @return {Array<Object>} Routes.
 */
export function getRoutes() {
	if ( ! cache ) {
		const filtered = applyFilters( 'rtbp.admin.routes', baseRoutes );
		cache = ( Array.isArray( filtered ) ? filtered : baseRoutes ).filter(
			( route ) => route && typeof route.path === 'string'
		);
	}
	return cache;
}

/**
 * Whether the current user may see a route: its capability, and its access
 * key(s) not all locked. The REST layer is the real gate; this keeps the menu
 * honest. Components that call it subscribe with `useAccessMap()` so they
 * re-render when the map changes.
 *
 * @param {Object} route Route.
 * @return {boolean} Visible.
 */
export function canSee( route ) {
	const caps = window.radius_hotel_booking_param?.capabilities;
	if ( route.capability && caps && caps[ route.capability ] !== true ) {
		return false;
	}
	return canAccess( route.accessKey );
}

/**
 * The route that best matches a pathname: exact first, then the longest
 * matching prefix (so `/bookings/42` resolves to `/bookings`).
 *
 * @param {string} pathname Current pathname.
 * @return {Object|undefined} Route.
 */
export function matchRoute( pathname ) {
	const routes = getRoutes();
	const exact = routes.find( ( route ) => route.path === pathname );
	if ( exact ) {
		return exact;
	}
	return routes
		.filter(
			( route ) =>
				route.path !== '/' && pathname.startsWith( route.path + '/' )
		)
		.sort( ( a, b ) => b.path.length - a.path.length )[ 0 ];
}
