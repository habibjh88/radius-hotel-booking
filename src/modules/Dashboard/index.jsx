/**
 * Dashboard: today at the hotel.
 *
 * Reads everything from one call, `GET dashboard/summary`. On a fresh install
 * the figures are zero and the setup checklist leads the way; each module
 * fills its part of the summary on the server as it is built (M01 bookings,
 * M05 payments, M06 rooms …), with no change needed here.
 */
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { AlertCircle, BedDouble, Hourglass, LogIn, LogOut, Plus } from 'lucide-react';

import StatCard from '@/components/common/StatCard';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { useDashboardSummary } from './api';
import RecentBookingsPanel from './components/RecentBookingsPanel';
import RoomStatusPanel from './components/RoomStatusPanel';
import SetupPanel from './components/SetupPanel';
import TodayPanel from './components/TodayPanel';

/**
 * @return {JSX.Element} Screen.
 */
export default function Dashboard() {
	const { data, loading, error, reload } = useDashboardSummary();
	const stats = data?.stats || {};

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
				<AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" aria-hidden="true" />
				<div className="flex-1">
					<p className="m-0 text-sm font-semibold text-heading">
						{ __( 'The dashboard could not be loaded.', 'radius-hotel-booking' ) }
					</p>
					<p className="m-0 mt-1 text-[13px] text-muted-foreground">{ error.message }</p>
				</div>
				<Button variant="outline" size="sm" onClick={ reload }>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
				<StatCard
					icon={ Hourglass }
					label={ __( 'Awaiting approval', 'radius-hotel-booking' ) }
					value={ stats.awaiting_approval ?? 0 }
					hint={ __( 'Bookings to accept or decline', 'radius-hotel-booking' ) }
					to="/bookings"
					loading={ loading }
				/>
				<StatCard
					icon={ LogIn }
					label={ __( 'Arriving today', 'radius-hotel-booking' ) }
					value={ stats.arrivals_today ?? 0 }
					hint={ __( 'Guests due to check in', 'radius-hotel-booking' ) }
					to="/bookings"
					loading={ loading }
				/>
				<StatCard
					icon={ LogOut }
					label={ __( 'Leaving today', 'radius-hotel-booking' ) }
					value={ stats.departures_today ?? 0 }
					hint={ __( 'Guests due to check out', 'radius-hotel-booking' ) }
					to="/bookings"
					loading={ loading }
				/>
				<StatCard
					icon={ BedDouble }
					label={ __( 'Rooms free now', 'radius-hotel-booking' ) }
					value={ stats.rooms_free ?? 0 }
					hint={ sprintf(
						/* translators: %d: total number of rooms. */
						__( 'of %d rooms', 'radius-hotel-booking' ),
						stats.rooms_total ?? 0
					) }
					to="/calendar"
					loading={ loading }
				/>
			</div>

			<div className="grid gap-6 lg:grid-cols-3">
				<div className="lg:col-span-2">
					<TodayPanel today={ data?.today } loading={ loading } />
				</div>
				<SetupPanel steps={ data?.setup } loading={ loading } />
			</div>

			<div className="grid gap-6 lg:grid-cols-3">
				<div className="lg:col-span-2">
					<RecentBookingsPanel bookings={ data?.recent_bookings } loading={ loading } />
				</div>
				<RoomStatusPanel counts={ data?.rooms_by_state } loading={ loading } />
			</div>
		</div>
	);
}
