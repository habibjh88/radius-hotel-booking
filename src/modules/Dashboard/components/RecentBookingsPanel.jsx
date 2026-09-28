/**
 * The latest bookings, newest first.
 */
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { BedDouble, Plus } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';

const COLUMNS = [
	__( 'Reference', 'radius-hotel-booking' ),
	__( 'Guest', 'radius-hotel-booking' ),
	__( 'Room', 'radius-hotel-booking' ),
	__( 'Stay', 'radius-hotel-booking' ),
	__( 'Payment', 'radius-hotel-booking' ),
	__( 'Status', 'radius-hotel-booking' ),
];

/**
 * @param {Object}  props          Props.
 * @param {Array}   props.bookings `[{ id, reference, guest, room, stay, payment_label, status_label }]`.
 * @param {boolean} props.loading  Loading.
 * @return {JSX.Element} Panel.
 */
export default function RecentBookingsPanel( { bookings = [], loading } ) {
	return (
		<Panel
			className="h-full"
			title={ __( 'Recent bookings', 'radius-hotel-booking' ) }
			description={ __(
				'The latest bookings taken at the desk or online',
				'radius-hotel-booking'
			) }
			actions={
				<Button asChild variant="outline" size="sm">
					<Link to="/bookings">
						{ __( 'View all', 'radius-hotel-booking' ) }
					</Link>
				</Button>
			}
			bodyClassName={ bookings.length && ! loading ? 'p-0' : undefined }
		>
			{ loading ? (
				<div className="space-y-3">
					{ [ 0, 1, 2, 3 ].map( ( key ) => (
						<Skeleton key={ key } className="h-10 w-full" />
					) ) }
				</div>
			) : null }

			{ ! loading && bookings.length ? (
				<div className="overflow-x-auto">
					<table className="w-full min-w-[640px] border-collapse text-sm">
						<thead>
							<tr className="bg-muted">
								{ COLUMNS.map( ( column ) => (
									<th
										key={ column }
										scope="col"
										className="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground"
									>
										{ column }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ bookings.map( ( booking ) => (
								<tr
									key={ booking.id }
									className="border-t border-border hover:bg-primary-softer"
								>
									<td className="px-5 py-3 font-semibold text-primary">
										<Link
											to={ `/bookings/${ booking.id }` }
											className="no-underline hover:underline"
										>
											{ booking.reference }
										</Link>
									</td>
									<td className="px-5 py-3 text-heading">
										{ booking.guest }
									</td>
									<td className="px-5 py-3">
										{ booking.room }
									</td>
									<td className="px-5 py-3 text-muted-foreground">
										{ booking.stay }
									</td>
									<td className="px-5 py-3">
										{ booking.payment_label }
									</td>
									<td className="px-5 py-3">
										{ booking.status_label }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : null }

			{ ! loading && ! bookings.length ? (
				<EmptyState
					icon={ BedDouble }
					title={ __( 'No bookings yet', 'radius-hotel-booking' ) }
					description={ __(
						'Take a walk-in booking at the desk, or share your booking page with guests.',
						'radius-hotel-booking'
					) }
					action={
						<Button asChild>
							<Link to="/bookings/new">
								<Plus aria-hidden="true" />
								{ __( 'New booking', 'radius-hotel-booking' ) }
							</Link>
						</Button>
					}
				/>
			) : null }
		</Panel>
	);
}
