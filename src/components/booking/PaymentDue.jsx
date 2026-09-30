/**
 * The payment deadline of an unpaid booking, counted down (5.4): "Payment
 * due in 5 h 20 min · 1 October 16:34", or, once past, the *Payment overdue*
 * badge with how long ago it was due. Shown only while money is still due on
 * a booking that is pending or confirmed (the server's rule for *overdue*).
 * Used on the booking screen, the overdue list and the guest confirmation.
 */
import { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Clock } from 'lucide-react';

import StatusBadge from '@/components/common/StatusBadge';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * A span in words: "2 d 3 h", "5 h 20 min", "12 min".
 *
 * @param {number} ms Milliseconds (positive).
 * @return {string} Text.
 */
export function spanText( ms ) {
	const minutes = Math.max( 1, Math.round( ms / 60000 ) );
	const days = Math.floor( minutes / 1440 );
	const hours = Math.floor( ( minutes % 1440 ) / 60 );
	const mins = minutes % 60;
	if ( days ) {
		return sprintf(
			/* translators: 1: days, 2: hours. */
			__( '%1$d d %2$d h', 'radius-hotel-booking' ),
			days,
			hours
		);
	}
	if ( hours ) {
		return sprintf(
			/* translators: 1: hours, 2: minutes. */
			__( '%1$d h %2$d min', 'radius-hotel-booking' ),
			hours,
			mins
		);
	}
	return sprintf(
		/* translators: %d: minutes. */
		__( '%d min', 'radius-hotel-booking' ),
		mins
	);
}

/**
 * The current time, refreshed every 30 seconds.
 *
 * @return {number} Milliseconds.
 */
function useNow() {
	const [ now, setNow ] = useState( () => Date.now() );
	useEffect( () => {
		const timer = window.setInterval( () => setNow( Date.now() ), 30000 );
		return () => window.clearInterval( timer );
	}, [] );
	return now;
}

/**
 * @param {Object}  props           Props.
 * @param {Object}  props.booking   `{ payment_due_at, payment_status, status }`.
 * @param {boolean} props.compact   Short form for lists ("1 h 30 min late"); the date on hover.
 * @param {string}  props.className Extra classes.
 * @return {JSX.Element|null} Deadline, or nothing when it does not apply.
 */
export default function PaymentDue( { booking, compact = false, className } ) {
	const now = useNow();
	const applies =
		booking?.payment_due_at &&
		[ 'unpaid', 'partially_paid' ].includes( booking.payment_status ) &&
		[ 'pending', 'confirmed' ].includes( booking.status );
	if ( ! applies ) {
		return null;
	}
	const due = new Date( booking.payment_due_at ).getTime();
	const left = due - now;

	if ( compact ) {
		return (
			<span
				title={ formatDateTime( booking.payment_due_at ) }
				className={ cn(
					'inline-flex flex-wrap items-center gap-2 text-sm',
					left <= 0 ? 'text-destructive' : 'text-muted-foreground',
					className
				) }
			>
				{ left <= 0 ? (
					<StatusBadge domain="payment" value="overdue" />
				) : null }
				{ left <= 0
					? sprintf(
							/* translators: %s: how late, e.g. "1 h 30 min". */
							__( '%s late', 'radius-hotel-booking' ),
							spanText( -left )
					  )
					: sprintf(
							/* translators: %s: time left, e.g. "5 h 20 min". */
							__( 'due in %s', 'radius-hotel-booking' ),
							spanText( left )
					  ) }
			</span>
		);
	}
	if ( left <= 0 ) {
		return (
			<span
				className={ cn(
					'inline-flex flex-wrap items-center gap-2 text-sm text-destructive',
					className
				) }
			>
				<StatusBadge domain="payment" value="overdue" />
				{ sprintf(
					/* translators: 1: date and time, 2: how long ago, e.g. "2 h 5 min". */
					__( 'Was due %1$s (%2$s ago)', 'radius-hotel-booking' ),
					formatDateTime( booking.payment_due_at ),
					spanText( -left )
				) }
			</span>
		);
	}
	return (
		<span
			className={ cn(
				'inline-flex items-center gap-1.5 text-sm',
				left < 2 * 3600000 ? 'text-warning' : 'text-muted-foreground',
				className
			) }
		>
			<Clock className="h-4 w-4 shrink-0" aria-hidden="true" />
			{ sprintf(
				/* translators: 1: time left, e.g. "5 h 20 min", 2: date and time. */
				__( 'Payment due in %1$s · %2$s', 'radius-hotel-booking' ),
				spanText( left ),
				formatDateTime( booking.payment_due_at )
			) }
		</span>
	);
}
