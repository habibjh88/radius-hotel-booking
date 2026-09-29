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
	 * The public shape.
	 *
	 * @param array $result Search result.
	 * @return array
	 */
	public static function public( array $result ): array {
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
