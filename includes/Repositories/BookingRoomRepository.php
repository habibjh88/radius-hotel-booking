<?php
/**
 * BookingRoom data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `booking_rooms`.
 */
class BookingRoomRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = BookingRoom::class;

	/**
	 * A booking's lines, in order.
	 *
	 * @param int $booking_id Booking id.
	 * @return BookingRoom[]
	 */
	public function forBooking( int $booking_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a booking's few lines, read live.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE booking_id = %d ORDER BY id', rtbp_table( 'booking_rooms' ), $booking_id ), ARRAY_A );
		return array_map( array( BookingRoom::class, 'hydrate' ), (array) $rows );
	}

	/**
	 * Lock one line and read it in the same statement (see
	 * `BookingRepository::lockedFind()`).
	 *
	 * @param int $id Line id.
	 * @return BookingRoom|null
	 */
	public function lockedFind( int $id ): ?BookingRoom {
		$row = $this->lockedRow( 'booking_rooms', $id );
		return $row ? BookingRoom::hydrate( $row ) : null;
	}
}
