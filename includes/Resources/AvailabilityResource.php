<?php
/**
 * Availability search responses (booking-engine §5.1).
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

defined( 'ABSPATH' ) || exit;

/**
 * Shapes `AvailabilityService::search()` for its audience.
 *
 * - **Staff** get everything the search found, including the booking
 *   reference behind a `booked` room, a block's reason and a room's state
 *   note.
 * - **Public** responses never carry booking references or ids, block
 *   reasons, state notes or the time a room frees up (§5.1): a guest learns
 *   only that a room is not available and the generic reason code.
 */
final class AvailabilityResource {

	/**
	 * Fields of a room-level reason that only staff may see.
	 */
	private const STAFF_REASON_FIELDS = array( 'booking_id', 'booking_ref', 'block_id', 'block_reason', 'until' );

	/**
	 * The staff shape: the search result as it is.
	 *
	 * @param array $result Search result.
	 * @return array
	 */
	public static function staff( array $result ): array {
		return $result;
	}

	/**
	 * The public shape: no state notes and no staff-only reason fields (booking
	 * references); optionally without sold-out rates and rooms, or without the
	 * room list at all.
	 *
	 * @param array $result  Search result.
	 * @param array $options `hide_unavailable` (bool), `rooms` (bool, default true).
	 * @return array
	 */
	public static function public( array $result, array $options = array() ): array {
		$hide  = ! empty( $options['hide_unavailable'] );
		$rooms = ! array_key_exists( 'rooms', $options ) || ! empty( $options['rooms'] );

		// M04: sold-out rates and rooms disappear when the hotel hides them (4.4,
		// `booking.unavailableRooms` = hide); a room type with nothing to sell goes too.
		if ( $hide ) {
			foreach ( $result['room_types'] as $index => &$type ) {
				$type['rates'] = array_values( array_filter( (array) $type['rates'], static fn( $rate ) => ! empty( $rate['available'] ) ) );
				foreach ( $type['floors'] as &$floor ) {
					$floor['rooms'] = array_values( array_filter( (array) $floor['rooms'], static fn( $room ) => ! empty( $room['available_for'] ) ) );
				}
				unset( $floor );
				$type['floors'] = array_values( array_filter( (array) $type['floors'], static fn( $floor ) => ! empty( $floor['rooms'] ) ) );
				if ( ! $type['rates'] ) {
					unset( $result['room_types'][ $index ] );
				}
			}
			unset( $type );
			$result['room_types'] = array_values( $result['room_types'] );
		}

		// When guests do not choose their room (`website.guestPicksRoom` off),
		// the room list is not needed and is not sent.
		if ( ! $rooms ) {
			foreach ( $result['room_types'] as &$type ) {
				$type['floors'] = array();
			}
			unset( $type );
		}

		foreach ( $result['room_types'] as &$type ) {
			foreach ( $type['floors'] as &$floor ) {
				foreach ( $floor['rooms'] as &$room ) {
					unset( $room['state_note'] );
					$reasons = array();
					foreach ( (array) $room['reasons_by_rate'] as $plan_id => $reason ) {
						$reasons[ $plan_id ] = array_diff_key( (array) $reason, array_flip( self::STAFF_REASON_FIELDS ) );
					}
					$room['reasons_by_rate'] = (object) $reasons;
				}
				unset( $room );
			}
			unset( $floor );
		}
		unset( $type );
		return $result;
	}
}
