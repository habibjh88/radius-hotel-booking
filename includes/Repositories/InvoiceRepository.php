<?php
/**
 * Invoice data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Invoice;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `invoices`.
 */
class InvoiceRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Invoice::class;

	/**
	 * A booking's invoice.
	 *
	 * @param int $booking_id Booking id.
	 * @return Invoice|null
	 */
	public function forBooking( int $booking_id ): ?Invoice {
		$rows = Invoice::query()->where( 'booking_id', '=', $booking_id )->limit( 1 )->get();
		return $rows ? Invoice::hydrate( $rows[0] ) : null;
	}
}
