<?php
/**
 * Availability search responses (booking-engine §5.1).
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

defined( 'ABSPATH' ) || exit;

use RadiusTheme\RadiusHotelBooking\Frontend\RoomTypePage;

/**
 * Shapes `AvailabilityService::search()` for its audience.
 *
 * - **Staff** get everything the search found, including the booking
 *   reference behind a `booked` room and a block's reason.
 * - **Public** responses never carry booking references or ids, block
 *   reasons or the time a room frees up (§5.1): a guest learns only that a
 *   room is not available, the generic reason code and, for a room in
 *   maintenance or out of service, the reason staff gave it (its state note,
 *   shown as a tooltip on the booking form).
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
	 * The public shape: no staff-only reason fields (booking references, block
	 * reasons); optionally without sold-out rates and rooms, or without the
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

		// The type's photo as a URL (the id means nothing to a visitor), and
		// every photo for the lightbox the thumbnail opens (as on its page).
		$image_ids = array();
		foreach ( $result['room_types'] as $type ) {
			$image_ids[] = (int) ( $type['details']['featured_image_id'] ?? 0 );
			$image_ids   = array_merge( $image_ids, (array) ( $type['details']['gallery_ids'] ?? array() ) );
		}
		$image_ids = array_values( array_unique( array_filter( array_map( 'intval', $image_ids ) ) ) );
		if ( $image_ids ) {
			_prime_post_caches( $image_ids, false, true );
		}
		// The amenity library, read once: each chip shows its icon, as on the room type page.
		$library = null;
		foreach ( $result['room_types'] as &$type ) {
			if ( isset( $type['details'] ) ) {
				if ( ! empty( $type['details']['amenities'] ) && class_exists( RoomTypePage::class ) ) {
					$library                          = $library ?? ( new \RadiusTheme\RadiusHotelBooking\Services\Inventory\AmenityService() )->display();
					$type['details']['amenity_icons'] = (object) RoomTypePage::amenityIcons( (array) $type['details']['amenities'], $library );
					// By section, for the room details popup (`[rtbp_booking room_details="popup"]`).
					$type['details']['amenity_groups'] = RoomTypePage::amenityGroups( (array) $type['details']['amenities'], $library );
				}
				$image_id                   = (int) ( $type['details']['featured_image_id'] ?? 0 );
				$type['details']['image']   = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
				$type['details']['url']     = '' !== $type['details']['slug'] && class_exists( RoomTypePage::class ) ? RoomTypePage::url( $type['details']['slug'] ) : '';
				$type['details']['gallery'] = class_exists( RoomTypePage::class ) ? RoomTypePage::slides( RoomTypePage::imageIds( $image_id, (array) ( $type['details']['gallery_ids'] ?? array() ) ) ) : array();
				unset( $type['details']['featured_image_id'], $type['details']['gallery_ids'] );
			}
			foreach ( $type['floors'] as &$floor ) {
				foreach ( $floor['rooms'] as &$room ) {
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
