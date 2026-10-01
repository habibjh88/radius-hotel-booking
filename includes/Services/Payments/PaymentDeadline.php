<?php
/**
 * The payment deadline of a booking (M05, 5.4, D6).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Payments
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Payments;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * An unpaid booking is due `paymentDueHours` after it was made, but no
 * later than `paymentBeforeArrivalHours` before its first room's stay
 * (Settings → Booking rules; D6: 24 h, 2 h). When that cap falls before the
 * booking was even made (a guest arriving within the hour), it is due by
 * arrival; a stay already under way is due at once. `paymentDueHours` 0 means
 * no deadline. The deadline is stored on the booking (`payment_due_at`,
 * `_gmt`) with the first stay it was computed from (`payment_due_start_gmt`), so
 * changing the setting later, or a status change, does not move it; it is
 * re-derived when the first stay moves (a room added, changed or removed).
 *
 * **Overdue** is derived, never stored: a deadline in the past on a booking
 * still pending or confirmed whose money is not all in (unpaid or partially
 * paid). *On hold* does not change it (the desk is waiting, but it is late);
 * the automatic release skips held bookings (Pro, T8).
 */
class PaymentDeadline {

	/**
	 * Booking statuses a deadline still matters for (before the guest is in).
	 */
	public const OPEN = array( 'pending', 'confirmed' );

	/**
	 * Payment statuses with money still due.
	 */
	public const UNSETTLED = array( 'unpaid', 'partially_paid' );

	/**
	 * The deadline for a booking made at `$created` whose first stay starts at `$start`.
	 *
	 * @param DateTimeImmutable      $created When the booking was made.
	 * @param DateTimeImmutable|null $start   The first live room's start (null: no room).
	 * @return DateTimeImmutable|null Null when there is no deadline.
	 */
	public static function compute( DateTimeImmutable $created, ?DateTimeImmutable $start ): ?DateTimeImmutable {
		$hours = (int) rtbp_setting( 'booking', 'paymentDueHours', 24 );
		if ( $hours <= 0 ) {
			return null;
		}
		$due = $created->modify( '+' . $hours . ' hours' );
		if ( ! $start ) {
			return $due;
		}
		$before = max( 0, (int) rtbp_setting( 'booking', 'paymentBeforeArrivalHours', 2 ) );
		$cap    = $start->modify( '-' . $before . ' hours' );
		if ( $cap->getTimestamp() > $created->getTimestamp() ) {
			return $cap < $due ? $cap : $due;
		}
		// Arriving too soon for the cap: due by arrival, or at once when the stay has begun.
		return $start->getTimestamp() > $created->getTimestamp() ? $start : $created;
	}

	/**
	 * The booking fields for the deadline, from its lines as stored now
	 * (empty when nothing changed).
	 *
	 * @param Booking       $booking Booking.
	 * @param BookingRoom[] $lines   Its lines.
	 * @return array `{ payment_due_at, payment_due_at_gmt }` when they change.
	 */
	public static function fields( Booking $booking, array $lines ): array {
		if ( ! $booking->created_at_gmt ) {
			return array();
		}
		$start = null;
		foreach ( $lines as $line ) {
			if ( in_array( (string) $line->status, array( 'pending', 'confirmed', 'checked_in' ), true ) ) {
				$at    = Dates::from_gmt( (string) $line->start_at_gmt );
				$start = ! $start || $at < $start ? $at : $start;
			}
		}
		$basis     = $start ? Dates::to_gmt_db( $start ) : null;
		$old_basis = $booking->payment_due_start_gmt ? (string) $booking->payment_due_start_gmt : null;
		$old_due   = $booking->payment_due_at_gmt ? (string) $booking->payment_due_at_gmt : null;

		// Only a move of the first stay re-derives the deadline (critical review):
		// a status change, or a settings change since, must not move it.
		if ( $old_basis === $basis && ( $old_basis || $old_due ) ) {
			return array();
		}
		// A booking from before the stored first stay: keep its deadline, record the stay.
		if ( null === $old_basis && $old_due ) {
			return array( 'payment_due_start_gmt' => $basis );
		}

		$due = self::compute( Dates::from_gmt( (string) $booking->created_at_gmt ), $start );
		$gmt = $due ? Dates::to_gmt_db( $due ) : null;
		return array(
			'payment_due_at'        => $due ? Dates::to_db( $due ) : null,
			'payment_due_at_gmt'    => $gmt,
			'payment_due_start_gmt' => $basis,
		);
	}

	/**
	 * Whether a booking is overdue now.
	 *
	 * @param Booking                $booking Booking.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return bool
	 */
	public static function overdue( Booking $booking, ?DateTimeImmutable $now = null ): bool {
		// Money must actually be due: a booking priced at 0 is never overdue (critical review).
		if ( ! $booking->payment_due_at_gmt
			|| ! in_array( (string) $booking->status, self::OPEN, true )
			|| ! in_array( (string) $booking->payment_status, self::UNSETTLED, true )
			|| Money::round( (float) $booking->balance_due ) <= 0 ) {
			return false;
		}
		$now = $now ?? Dates::now();
		return strtotime( (string) $booking->payment_due_at_gmt . ' UTC' ) < $now->getTimestamp();
	}
}
