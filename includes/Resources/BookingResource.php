<?php
/**
 * Booking API shapes.
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * A booking with its lines, for staff (M02; the booking record screen, M03,
 * builds on it). Times are ISO 8601 in the site time zone.
 */
final class BookingResource {

	/**
	 * The booking and its lines.
	 *
	 * @param Booking       $booking Booking.
	 * @param BookingRoom[] $lines   Its lines.
	 * @param Guest|null    $guest   Its guest (the one the server used).
	 * @return array
	 */
	public static function detail( Booking $booking, array $lines, ?Guest $guest = null ): array {
		return array(
			'id'               => (int) $booking->id,
			'reference'        => (string) $booking->reference,
			'guest_id'         => $booking->guest_id ? (int) $booking->guest_id : null,
			'guest'            => $guest ? array(
				'id'        => (int) $guest->id,
				'reference' => (string) $guest->reference,
				'name'      => $guest->fullName(),
			) : null,
			'source'           => (string) $booking->source,
			'status'           => (string) $booking->status,
			'payment_status'   => (string) $booking->payment_status,
			'total'            => (float) $booking->total,
			'paid_total'       => (float) $booking->paid_total,
			'balance_due'      => (float) $booking->balance_due,
			'currency'         => (string) $booking->currency,
			'adults'           => (int) $booking->adults,
			'children'         => (int) $booking->children,
			'special_requests' => (string) $booking->special_requests,
			'created_at'       => $booking->created_at_gmt ? Dates::to_iso( Dates::from_gmt( (string) $booking->created_at_gmt ) ) : null,
			'lines'            => array_map( array( self::class, 'line' ), $lines ),
		);
	}

	/**
	 * One line.
	 *
	 * @param BookingRoom $line Line.
	 * @return array
	 */
	public static function line( BookingRoom $line ): array {
		$breakdown = json_decode( (string) $line->price_breakdown, true );
		return array(
			'id'             => (int) $line->id,
			'room_id'        => (int) $line->room_id,
			'room_number'    => (string) $line->room_number,
			'floor_name'     => (string) $line->floor_name,
			'room_type_id'   => (int) $line->room_type_id,
			'rate_plan_id'   => (int) $line->rate_plan_id,
			'rate_plan_name' => (string) $line->rate_plan_name,
			'start'          => Dates::to_iso( Dates::from_gmt( (string) $line->start_at_gmt ) ),
			'end'            => Dates::to_iso( Dates::from_gmt( (string) $line->end_at_gmt ) ),
			'units'          => (int) $line->units,
			'adults'         => (int) $line->adults,
			'children'       => (int) $line->children,
			'status'         => (string) $line->status,
			'unit_price'     => (float) $line->unit_price,
			'total'          => (float) $line->total,
			'price_steps'    => is_array( $breakdown ) ? ( $breakdown['steps'] ?? array() ) : array(),
		);
	}
}
