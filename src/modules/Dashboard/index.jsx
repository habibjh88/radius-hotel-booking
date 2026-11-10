/**
 * Dashboard: today at the hotel.
 *
 * Reads everything from one call, `GET dashboard/summary`. On a fresh install
 * the figures are zero and the setup checklist leads the way; each module
 * fills its part of the summary on the server as it is built (M01 bookings,
 * M05 payments, M06 rooms …), with no change needed here.
 *
 * Add-ons add widgets on `rtbp.dashboard.widgets` (ADR-015):
 * `{ key, Component, area?: 'top' | 'side', order?, accessKey? }`. `top` sits
 * under the counters, `side` under the side panels; a widget whose access key
 * is locked is left out, and the server still guards its data.
 */
import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlarmClock,
	AlertCircle,
	BedDouble,
	CalendarArrowDown,
	CalendarArrowUp,
	Clock3,
	DoorOpen,
	Plus,
} from 'lucide-react';

import SiteChecks from '@/components/common/SiteChecks';
import StatCard from '@/components/common/StatCard';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { canAccess, useAccessMap } from '@/lib/access';
import { BookingList } from '@/modules/Bookings';
import { useDashboardSummary } from './api';
import RoomStatusPanel from './components/RoomStatusPanel';
import SetupPanel from './components/SetupPanel';
import TodayPanel from './components/TodayPanel';

/**
 * Add-on widgets for one area, in order.
 *
 * @param {Array}  widgets Widgets from the filter.
 * @param {string} area    'top' | 'side'.
 * @return {JSX.Element[]} Rendered widgets.
 */
const renderArea = ( widgets, area ) =>
	widgets
		.filter( ( widget ) => ( widget.area || 'top' ) === area )
		.map( ( { key, Component } ) => <Component key={ key } /> );

/**
 * @return {JSX.Element} Screen.
 */
export default function Dashboard() {
	const {
		data,
		isPending: loading,
		error,
		refetch: reload,
	} = useDashboardSummary();
	const stats = data?.stats || {};
	// Re-read when the access map refreshes, so widgets follow a permission change.
	const { data: levels } = useAccessMap();
	const widgets = useMemo( () => {
		const filtered = applyFilters( 'rtbp.dashboard.widgets', [] );
		return ( Array.isArray( filtered ) ? filtered : [] )
			.filter(
				( widget ) =>
					widget &&
					widget.key &&
					widget.Component &&
					canAccess( widget.accessKey )
			)
			.sort( ( a, b ) => ( a.order ?? 50 ) - ( b.order ?? 50 ) );
	}, [ levels ] );

	// Each counter opens its tab of the booking list (1.1–1.4, M01 T1).
	const counters = [
		{
			key: 'awaiting_approval',
			icon: Clock3,
			tone: 'red',
			label: __( 'Awaiting approval', 'radius-hotel-booking' ),
			hint: __( 'Bookings to accept or decline', 'radius-hotel-booking' ),
			to: '/bookings?tab=awaiting',
		},
		{
			key: 'arrivals_today',
			icon: CalendarArrowUp,
			tone: 'green',
			label: __( 'Arriving today', 'radius-hotel-booking' ),
			hint: __( 'Guests due to check in', 'radius-hotel-booking' ),
			to: '/bookings?tab=arriving',
		},
		{
			key: 'departures_today',
			icon: CalendarArrowDown,
			tone: 'orange',
			label: __( 'Leaving today', 'radius-hotel-booking' ),
			hint: __( 'Guests due to check out', 'radius-hotel-booking' ),
			to: '/bookings?tab=leaving',
		},
		{
			key: 'in_house',
			icon: DoorOpen,
			tone: 'purple',
			label: __( 'In house', 'radius-hotel-booking' ),
			hint: __( 'Rooms checked in now', 'radius-hotel-booking' ),
			to: '/bookings?tab=in_house',
		},
		{
			key: 'rooms_free',
			icon: BedDouble,
			tone: 'blue',
			label: __( 'Rooms free now', 'radius-hotel-booking' ),
			hint: sprintf(
				/* translators: %d: total number of rooms. */
				_n(
					'of %d room',
					'of %d rooms',
					stats.rooms_total ?? 0,
					'radius-hotel-booking'
				),
				stats.rooms_total ?? 0
			),
			to: '/calendar',
		},
		{
			key: 'overdue',
			icon: AlarmClock,
			tone: 'amber',
			label: __( 'Payment overdue', 'radius-hotel-booking' ),
			hint: __( 'Past their payment deadline', 'radius-hotel-booking' ),
			to: '/bookings?tab=overdue',
		},
	];

	usePageActions(
		<Button asChild>
			<Link to="/bookings/new">
				<Plus aria-hidden="true" />
				<span className="hidden sm:inline">
					{ __( 'New booking', 'radius-hotel-booking' ) }
				</span>
			</Link>
		</Button>
	);

	if ( error ) {
		return (
			<div className="flex items-start gap-3 rounded-xl border border-destructive bg-destructive-soft p-5">
				<AlertCircle
					className="mt-0.5 h-5 w-5 shrink-0 text-destructive"
					aria-hidden="true"
				/>
				<div className="flex-1">
					<p className="m-0 text-sm font-semibold text-heading">
						{ __(
							'The dashboard could not be loaded.',
							'radius-hotel-booking'
						) }
					</p>
					<p className="m-0 mt-1 text-[13px] text-muted-foreground">
						{ error.message }
					</p>
				</div>
				<Button variant="outline" size="sm" onClick={ () => reload() }>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<SiteChecks />
			{ /* A strip that scrolls sideways on a phone (M01); a grid from sm up. */ }
			<div className="-mx-4 flex snap-x scroll-px-4 gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-4 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3 min-[1600px]:grid-cols-6">
				{ counters.map( ( counter ) => (
					<div
						key={ counter.key }
						className="w-[15rem] shrink-0 snap-start sm:w-auto"
					>
						<StatCard
							icon={ counter.icon }
							label={ counter.label }
							value={ stats[ counter.key ] ?? 0 }
							hint={ counter.hint }
							to={ counter.to }
							tone={ counter.tone }
							loading={ loading }
						/>
					</div>
				) ) }
			</div>

			{ renderArea( widgets, 'top' ) }

			{ /* The booking list under the counters, as on the old dashboard (R1-T10). */ }
			<BookingList withPageActions={ false } />

			<div className="grid gap-6 lg:grid-cols-3">
				<div className="min-w-0 lg:col-span-2">
					<TodayPanel today={ data?.today } loading={ loading } />
				</div>
				{ /* Not stretched to the row: the panels inside are h-full. */ }
				<div className="space-y-6 self-start">
					<SetupPanel steps={ data?.setup } loading={ loading } />
					<RoomStatusPanel
						counts={ data?.rooms_by_state }
						loading={ loading }
					/>
					{ renderArea( widgets, 'side' ) }
				</div>
			</div>
		</div>
	);
}
