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
}
