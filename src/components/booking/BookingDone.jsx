/**
 * The booking was made: its reference, guest, rooms and total, and a way to
 * take the next one.
 */
import { __, sprintf } from '@wordpress/i18n';
import { CheckCircle2, Plus } from 'lucide-react';

import Money from '@/components/common/Money';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { formatDateRange } from '@/lib/format';

/**
 * @param {Object}   props           Props.
 * @param {Object}   props.booking   The booking (API shape).
 * @param {string}   props.guestName The guest's name.
 * @param {Function} props.onAnother Start a new booking.
 * @return {JSX.Element} Screen.
 */
export default function BookingDone( { booking, guestName, onAnother } ) {
	return (
		<div className="space-y-4 text-center">
			<CheckCircle2
				className="mx-auto h-12 w-12 text-success"
				aria-hidden="true"
			/>
			<div className="space-y-1">
				<h2 className="m-0 text-xl font-semibold text-heading">
					{ 'confirmed' === booking.status
						? sprintf(
								/* translators: %s: booking reference. */
								__(
									'Booking %s is confirmed',
									'radius-hotel-booking'
								),
								booking.reference
						  )
						: sprintf(
								/* translators: %s: booking reference. */
								__(
									'Booking %s is waiting for approval',
									'radius-hotel-booking'
								),
								booking.reference
						  ) }
				</h2>
				<p className="m-0 text-sm text-muted-foreground">
					{ guestName }
				</p>
			</div>
			<ul className="mx-auto m-0 max-w-lg list-none space-y-2 p-0 text-left">
				{ ( booking.lines || [] ).map( ( line ) => (
					<li
						key={ line.id }
						className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3 text-sm"
					>
						<span className="min-w-0">
							<span className="block font-semibold text-heading">
								{ sprintf(
									/* translators: 1: room number, 2: rate name. */
									__(
										'Room %1$s · %2$s',
										'radius-hotel-booking'
									),
									line.room_number,
									line.rate_plan_name
								) }
							</span>
							<span className="block text-xs text-muted-foreground">
								{ formatDateRange( line.start, line.end ) }
							</span>
						</span>
						<Money value={ line.total } />
					</li>
				) ) }
			</ul>
			<div className="flex flex-wrap items-center justify-center gap-3 text-sm">
				<span className="font-semibold text-heading">
					{ __( 'Total', 'radius-hotel-booking' ) }{ ' ' }
					<Money value={ booking.total } />
				</span>
				<StatusBadge
					domain="payment"
					value={ booking.payment_status }
				/>
			</div>
			<Button type="button" onClick={ onAnother }>
				<Plus className="h-4 w-4" aria-hidden="true" />
				{ __( 'Take another booking', 'radius-hotel-booking' ) }
			</Button>
		</div>
	);
}
