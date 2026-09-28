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
 * - `element`     Lazy component. Routes without one render the module
 *                 placeholder until their module (`module`) is built.
 *
 * Add-ons append routes through the `rtbp.admin.routes` filter (ADR-015) —
 * the free plugin never lists Pro screens itself.
 */
import { lazy } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
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
const SETTINGS = 'rtbp_manage_settings';

const baseRoutes = [
	{
		path: '/',
		group: 'overview',
		label: __( 'Dashboard', 'radius-hotel-booking' ),
		description: __( 'Today at your hotel, at a glance', 'radius-hotel-booking' ),
		icon: LayoutDashboard,
		capability: VIEW,
		element: lazy( () => import( '@/modules/Dashboard' ) ),
	},
	{
		path: '/calendar',
		group: 'overview',
		label: __( 'Availability', 'radius-hotel-booking' ),
		description: __( 'Open, close and price your rooms by date', 'radius-hotel-booking' ),
		icon: CalendarDays,
		capability: VIEW,
		module: 'M08',
	},
	{
		path: '/bookings/new',
		group: 'frontDesk',
		label: __( 'New booking', 'radius-hotel-booking' ),
		description: __( 'Take a walk-in booking at the front desk', 'radius-hotel-booking' ),
		icon: CalendarPlus,
		capability: VIEW,
		module: 'M02',
	},
	{
		path: '/bookings',
		group: 'frontDesk',
		label: __( 'Bookings', 'radius-hotel-booking' ),
		description: __( 'Every booking, arrival and departure', 'radius-hotel-booking' ),
		icon: BedDouble,
		capability: VIEW,
		module: 'M01',
	},
	{
		path: '/guests',
		group: 'frontDesk',
		label: __( 'Guests', 'radius-hotel-booking' ),
		description: __( 'Guest records, documents and stay history', 'radius-hotel-booking' ),
		icon: Users,
		capability: VIEW,
		module: 'M09',
	},
	{
		path: '/payments',
		group: 'frontDesk',
		label: __( 'Payments', 'radius-hotel-booking' ),
		description: __( 'Payments received and bookings to follow up', 'radius-hotel-booking' ),
		icon: Wallet,
		capability: VIEW,
		module: 'M05',
	},
	{
		path: '/rooms',
		group: 'hotel',
		label: __( 'Rooms & floors', 'radius-hotel-booking' ),
		description: __( 'Room types, floors and every physical room', 'radius-hotel-booking' ),
		icon: DoorOpen,
		capability: SETTINGS,
		module: 'M06',
	},
	{
		path: '/rate-plans',
		group: 'hotel',
		label: __( 'Rate plans', 'radius-hotel-booking' ),
		description: __( 'Stay windows such as Half Day or Overnight, and their prices', 'radius-hotel-booking' ),
		icon: Clock,
		capability: SETTINGS,
		module: 'M07',
	},
	{
		path: '/reports',
		group: 'insights',
		label: __( 'Reports', 'radius-hotel-booking' ),
		description: __( 'Sales, rooms and availability reports', 'radius-hotel-booking' ),
		icon: BarChart3,
		capability: VIEW,
		module: 'M10',
	},
	{
		path: '/exports',
		group: 'insights',
		label: __( 'Exports', 'radius-hotel-booking' ),
		description: __( 'Export bookings and guests to a spreadsheet', 'radius-hotel-booking' ),
		icon: Download,
		capability: SETTINGS,
		module: 'M11',
	},
	{
		path: '/permissions',
		group: 'system',
		label: __( 'Permissions', 'radius-hotel-booking' ),
		description: __( 'Choose what each staff role may open and do', 'radius-hotel-booking' ),
		icon: ShieldCheck,
		capability: SETTINGS,
		module: 'M13',
	},
	{
		path: '/settings',
		group: 'system',
		label: __( 'Settings', 'radius-hotel-booking' ),
		description: __( 'Hotel details, booking rules and appearance', 'radius-hotel-booking' ),
		icon: Settings,
		capability: SETTINGS,
		element: lazy( () => import( '@/modules/Settings' ) ),
	},
];

let cache = null;

/**
 * All routes, including those add-ons register. Computed once, on first use,
 * after every add-on script has run.
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
 * Whether the current user may see a route. Administrators hold every plugin
 * capability; the REST layer is the real gate — this keeps the menu honest.
 *
 * @param {Object} route Route.
 * @return {boolean} Visible.
 */
export function canSee( route ) {
	const caps = window.radius_hotel_booking_param?.capabilities;
	if ( ! route.capability || ! caps ) {
		return true;
	}
	return caps[ route.capability ] === true;
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
