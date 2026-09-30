<?php
/**
 * Booking data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `bookings`.
 */
class BookingRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Booking::class;

	/**
	 * Lock a booking row for the rest of the transaction (status changes and
	 * line edits of one booking run one at a time).
	 *
	 * @param int $id Booking id.
	 * @return bool Whether the booking exists.
	 */
	public function lock( int $id ): bool {
		return $this->lockRow( 'bookings', $id );
	}

	/**
	 * Lock a live booking row and read it in the same statement. A locking
	 * read never opens the transaction's snapshot, so the room lock taken
	 * after it still sees every committed booking (booking-engine §7.1).
	 *
	 * @param int $id Booking id.
	 * @return Booking|null
	 */
	public function lockedFind( int $id ): ?Booking {
		$row = $this->lockedRow( 'bookings', $id, true );
		return $row ? Booking::hydrate( $row ) : null;
	}
}
