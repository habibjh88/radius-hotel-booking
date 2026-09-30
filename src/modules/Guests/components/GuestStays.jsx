/**
 * A guest's stays (9.4): each booking with its dates, rooms, rate plans,
 * booking and payment status and total, newest first. Bookings are written
 * from M02 on; each reference opens the booking record (M03).
 */
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { BedDouble } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Money from '@/components/common/Money';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { formatDateRange } from '@/lib/format';
import { useGuestStays } from '../api';

/**
 * @param {Object} props         Props.
 * @param {number} props.guestId Guest id.
 * @return {JSX.Element} Stays.
 */
export default function GuestStays( { guestId } ) {
	const stays = useGuestStays( guestId );
	const canOpen = 'locked' !== useAccess( 'page.bookings' );

	if ( stays.isPending ) {
		return (
			<div className="space-y-2">
				{ [ 0, 1 ].map( ( key ) => (
					<Skeleton key={ key } className="h-16 w-full" />
				) ) }
			</div>
		);
	}
	if ( stays.error ) {
		return (
			<div role="alert" className="space-y-2">
				<p className="m-0 text-sm text-destructive">
					{ stays.error.message }
				</p>
				<Button
					variant="outline"
					size="sm"
					onClick={ () => stays.refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}
	if ( ! stays.data.length ) {
		return (
			<EmptyState
				icon={ BedDouble }
				title={ __( 'No stays yet', 'radius-hotel-booking' ) }
				description={ __(
					'Every booking for this guest will be listed here.',
					'radius-hotel-booking'
				) }
				className="border-0 py-10"
			/>
		);
	}

	return (
		<ul className="m-0 list-none divide-y divide-border p-0">
			{ stays.data.map( ( stay ) => (
				<li key={ stay.id } className="m-0 space-y-1.5 py-3">
					<div className="flex flex-wrap items-center justify-between gap-2">
						<div className="flex flex-wrap items-center gap-2">
							{ canOpen ? (
								<Link
									to={ `/bookings/${ stay.id }` }
									className="font-semibold text-heading no-underline hover:underline"
								>
									{ stay.reference }
								</Link>
							) : (
								<span className="font-semibold text-heading">
									{ stay.reference }
								</span>
							) }
							<StatusBadge domain="stay" value={ stay.status } />
							<StatusBadge
								domain="payment"
								value={ stay.payment_status }
							/>
						</div>
						<span className="text-sm font-semibold text-heading">
							<Money value={ stay.total } />
						</span>
					</div>
					<p className="m-0 text-sm text-muted-foreground">
						{ stay.arrival && stay.departure
							? formatDateRange( stay.arrival, stay.departure )
							: '—' }
					</p>
					{ stay.lines.length ? (
						<p className="m-0 text-xs text-muted-foreground">
							{ stay.lines
								.map( ( line ) =>
									sprintf(
										/* translators: 1: room number, 2: rate plan. */
										__(
											'Room %1$s · %2$s',
											'radius-hotel-booking'
										),
										line.room_number,
										line.rate_plan_name
									)
								)
								.join( ', ' ) }
							{ stay.lines.length > 1
								? ` ${ sprintf(
										/* translators: %d: rooms in the booking. */
										_n(
											'(%d room)',
											'(%d rooms)',
											stay.lines.length,
											'radius-hotel-booking'
										),
										stay.lines.length
								  ) }`
								: null }
						</p>
					) : null }
				</li>
			) ) }
		</ul>
	);
}
