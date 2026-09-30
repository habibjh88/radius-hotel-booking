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
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingLineService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\StatusMachine;
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
	 * The booking screen (M03, 3.1, 3.2): the booking, its money, its guest,
	 * its lines with their rooms and the actions each allows, and who did
	 * what. From `BookingQuery::load()`.
	 *
	 * @param array $loaded `{ booking, lines, guest, room_types, rooms, users }`.
	 * @return array
	 */
	public static function record( array $loaded ): array {
		$booking = $loaded['booking'];
		$users   = $loaded['users'];
		$now     = Dates::now()->getTimestamp();
		$today   = Dates::now()->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		$lines   = array();
		$statuses = array();
		foreach ( $loaded['lines'] as $line ) {
			$item               = self::line( $line );
			$room               = $loaded['rooms'][ (int) $line->room_id ] ?? null;
			$item['room_type']  = (string) ( $loaded['room_types'][ (int) $line->room_type_id ] ?? '' );
			$item['room_state'] = $room ? ( $room['removed'] ? 'removed' : $room['state'] ) : 'removed';
			$item['checked_in_at']  = $line->checked_in_at ? Dates::to_iso( Dates::local( (string) $line->checked_in_at ) ) : null;
			$item['checked_out_at'] = $line->checked_out_at ? Dates::to_iso( Dates::local( (string) $line->checked_out_at ) ) : null;
			$item['checked_in_by']  = $line->checked_in_by ? ( $users[ (int) $line->checked_in_by ] ?? '' ) : '';
			$item['checked_out_by'] = $line->checked_out_by ? ( $users[ (int) $line->checked_out_by ] ?? '' ) : '';
			$actions                = StatusMachine::actions( (string) $line->status );
			// A no-show only once the stay should have begun; a check-in from the arrival day (early arrival).
			if ( strtotime( (string) $line->start_at_gmt . ' UTC' ) > $now ) {
				$actions = array_values( array_diff( $actions, array( 'no_show' ) ) );
			}
			if ( Dates::from_gmt( (string) $line->start_at_gmt )->setTimezone( Dates::timezone() )->format( 'Y-m-d' ) > $today ) {
				$actions = array_values( array_diff( $actions, array( 'check_in' ) ) );
			}
			// A room can be changed or removed while not yet in use (the booking's only room is cancelled, not removed).
			if ( in_array( (string) $line->status, BookingLineService::EDITABLE, true ) ) {
				$actions[] = 'line_edit';
				if ( BookingLineService::liveCount( $loaded['lines'] ) > 1 ) {
					$actions[] = 'line_remove';
				}
			}
			$item['actions'] = $actions;
			$lines[]         = $item;
			$statuses[]      = (string) $line->status;
		}
		$any = static fn( array $wanted ) => (bool) array_intersect( $wanted, $statuses );

		return array_merge(
			self::detail( $booking, $loaded['lines'], $loaded['guest'] ),
			array(
				'lines'          => $lines,
				'guest'          => $loaded['guest'] ? GuestResource::summary( $loaded['guest'] ) : null,
				'rooms_count'    => count( $lines ),
				'created_by'     => $booking->created_by ? ( $users[ (int) $booking->created_by ] ?? '' ) : '',
				'money'          => array(
					'subtotal'       => (float) $booking->subtotal,
					'discount_total' => (float) $booking->discount_total,
					'tax_total'      => (float) $booking->tax_total,
					'total'          => (float) $booking->total,
					'paid_total'     => (float) $booking->paid_total,
					'balance_due'    => (float) $booking->balance_due,
					'currency'       => (string) $booking->currency,
				),
				// Whole-booking actions: approve / decline while a line waits, cancel while one is live.
				'actions'        => array_values(
					array_filter(
						array(
							$any( array( StatusMachine::PENDING ) ) ? 'approve' : null,
							$any( array( StatusMachine::PENDING ) ) ? 'decline' : null,
							$any( array( StatusMachine::PENDING, StatusMachine::CONFIRMED ) ) ? 'cancel' : null,
							in_array( (string) $booking->status, BookingLineService::OPEN, true ) ? 'line_add' : null,
						)
					)
				),
				'cancelled_reason' => (string) $booking->cancelled_reason,
			)
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
