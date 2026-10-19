<?php
/**
 * A booking's stored totals, from its lines.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Services\Payments\PaymentDeadline;
use RadiusTheme\RadiusHotelBooking\Services\Payments\PaymentService;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * The booking row keeps its subtotal, total, balance and guest counts. They
 * follow the lines after every change (a status move, an added, edited or
 * removed room): a declined or cancelled room is not charged and not counted;
 * a no-show still is. Each line's own price stays frozen (3.16) — only the
 * sum moves. The staff discount (`discount_type` amount | percent) is worked
 * out from the rooms' sum each time: a percent follows the rooms, an amount
 * never exceeds them; a discount stored without a type (an import) stays as
 * it is. Tax stays as stored. The balance is total − paid, and the payment
 * status is re-derived with `PaymentService::statusFor()` (the ledger itself
 * is `recalculate()`).
 */
class BookingTotals {

	/**
	 * Line statuses that no longer count towards the booking.
	 */
	public const NOT_CHARGED = array( 'declined', 'cancelled' );

	/**
	 * The booking fields that differ from what the lines add up to.
	 *
	 * @param Booking       $booking Booking, read under its lock.
	 * @param BookingRoom[] $lines   Its lines as stored now.
	 * @return array Changed fields (empty when nothing moved).
	 */
	public static function changes( Booking $booking, array $lines ): array {
		$charged  = array();
		$adults   = 0;
		$children = 0;
		foreach ( $lines as $line ) {
			if ( in_array( (string) $line->status, self::NOT_CHARGED, true ) ) {
				continue;
			}
			$charged[] = (float) $line->total;
			$adults   += (int) $line->adults;
			$children += (int) $line->children;
		}

		$fields   = array();
		$subtotal = Money::sum( $charged );
		$discount = self::discount( $booking, $subtotal );
		$total    = max( 0, $subtotal - $discount + (float) $booking->tax_total );
		if ( ! Money::equals( $subtotal, (float) $booking->subtotal )
			|| ! Money::equals( $discount, (float) $booking->discount_total )
			|| ! Money::equals( $total, (float) $booking->total ) ) {
			$fields['subtotal']       = $subtotal;
			$fields['discount_total'] = $discount;
			$fields['total']          = $total;
			$fields['balance_due']    = $total - (float) $booking->paid_total;
			// The payment status follows (M05): what was paid against the new total.
			$fields['payment_status'] = PaymentService::statusFor( (float) $booking->paid_total, $total, 'refunded' === (string) $booking->payment_status );
		}
		// A booking with every room declined keeps its last counts (they say who was expected).
		if ( $charged && ( $adults !== (int) $booking->adults || $children !== (int) $booking->children ) ) {
			$fields['adults']   = $adults;
			$fields['children'] = $children;
		}
		// The payment deadline follows the first stay (5.4): a room added, changed or removed can move it.
		return array_merge( $fields, PaymentDeadline::fields( $booking, $lines ) );
	}

	/**
	 * What the booking's discount comes to on a rooms' sum: a percent of it,
	 * or a fixed amount (never more than the sum).
	 *
	 * @param Booking $booking  Booking (`discount_type`, `discount_value`, `discount_total`).
	 * @param float   $subtotal The charged rooms' sum.
	 * @return float Discount, rounded.
	 */
	public static function discount( Booking $booking, float $subtotal ): float {
		$value = (float) $booking->discount_value;
		switch ( (string) $booking->discount_type ) {
			case 'percent':
				return Money::round( $subtotal * min( 100, max( 0, $value ) ) / 100 );
			case 'amount':
				return Money::round( min( $subtotal, max( 0, $value ) ) );
		}
		// No staff discount: whatever was stored (0, or an imported booking's own).
		return Money::round( min( $subtotal, (float) $booking->discount_total ) );
	}
}
