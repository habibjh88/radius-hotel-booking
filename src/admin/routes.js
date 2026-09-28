/**
 * Admin routes.
 *
 * Add a screen by dropping a module under `src/modules/` and adding a line
 * here. Every element is lazy-loaded, so a screen's code only downloads when
 * the route is first visited.
 */
import { lazy } from 'react';
import { LayoutDashboard, List, Settings } from 'lucide-react';
import { __ } from '@wordpress/i18n';

const routes = [
	{
		path: '/',
		label: __( 'Dashboard', 'radius-hotel-booking' ),
		icon: LayoutDashboard,
		element: lazy( () => import( '@/modules/Dashboard' ) ),
	},
	{
		path: '/items',
		label: __( 'Items', 'radius-hotel-booking' ),
		icon: List,
		element: lazy( () => import( '@/modules/Items' ) ),
	},
	{
		path: '/settings',
		label: __( 'Settings', 'radius-hotel-booking' ),
		icon: Settings,
		element: lazy( () => import( '@/modules/Settings' ) ),
	},
];

export default routes;
