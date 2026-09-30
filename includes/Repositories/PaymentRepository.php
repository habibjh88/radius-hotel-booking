<?php
/**
 * Payment data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Payment;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `payments`.
 */
class PaymentRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Payment::class;

	/**
	 * A booking's ledger, oldest first.
	 *
	 * @param int $booking_id Booking id.
	 * @return Payment[]
	 */
	public function forBooking( int $booking_id ): array {
		$rows = Payment::query()->where( 'booking_id', '=', $booking_id )->orderBy( 'id', 'ASC' )->get();
		return array_map( static fn( $row ) => Payment::hydrate( $row ), $rows );
	}

	/**
	 * Lock a ledger row and read it (a void must not race another void).
	 *
	 * @param int $id Payment id.
	 * @return Payment|null
	 */
	public function lockedFind( int $id ): ?Payment {
		$row = $this->lockedRow( 'payments', $id );
		return $row ? Payment::hydrate( $row ) : null;
	}
}
