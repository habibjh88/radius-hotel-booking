/**
 * The reports the free plugin ships, in order. Add-ons append theirs with the
 * `rtbp.reports.tabs` filter (same shape): `{ key, label, icon, accessKey,
 * Component, range = true, mode = true, export = true, report }` — `range` /
 * `mode` false hide the date picker or its arrival / booking-date switch for
 * that tab; `export` false hides *Export*; `report` is the server report it
 * exports (`GET reports/{report}/export`, default the tab's key). `Component`
 * receives `{ range: { from, to, mode } }`.
 */
import { lazy } from 'react';
import { __ } from '@wordpress/i18n';
import { Banknote, BedDouble, LayoutGrid } from 'lucide-react';

/**
 * Core tabs.
 *
 * @return {Array<Object>} Tabs.
 */
export default function coreTabs() {
	return [
		{
			key: 'sales',
			label: __( 'Sales', 'radius-hotel-booking' ),
			icon: Banknote,
			accessKey: 'page.reports_sales',
			Component: lazy( () => import( './Sales' ) ),
		},
		{
			key: 'rooms',
			label: __( 'Rooms', 'radius-hotel-booking' ),
			icon: BedDouble,
			accessKey: 'page.reports_rooms',
			Component: lazy( () => import( './Rooms' ) ),
		},
		{
			key: 'availability',
			label: __( 'Room availability', 'radius-hotel-booking' ),
			icon: LayoutGrid,
			accessKey: 'page.reports_rooms',
			Component: lazy( () => import( './Availability' ) ),
		},
	];
}
