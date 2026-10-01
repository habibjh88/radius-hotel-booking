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

	/**
	 * Bookings past their payment deadline (M05, 5.14): pending or confirmed,
	 * unpaid or partially paid, the deadline before `$now_gmt`; the oldest
	 * deadline first, with the guest and the first stay. One query.
	 *
	 * @param string $now_gmt  Now (UTC, `Y-m-d H:i:s`).
	 * @param int    $page     Page, from 1.
	 * @param int    $per_page Rows per page (1–100).
	 * @return array `{ rows: [ { booking: Booking, guest_name, guest_phone, first_start } ], total }`.
	 */
	public function overdue( string $now_gmt, int $page = 1, int $per_page = 25 ): array {
		global $wpdb;
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( max( 1, $page ) - 1 ) * $per_page;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a live list, read once per page.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS b.*, TRIM(CONCAT(COALESCE(g.first_name, ''), ' ', COALESCE(g.last_name, ''))) AS guest_name, COALESCE(g.phone, '') AS guest_phone,
					(SELECT MIN(r.start_at_gmt) FROM %i r WHERE r.booking_id = b.id AND r.status IN ('pending','confirmed','checked_in')) AS first_start
				FROM %i b LEFT JOIN %i g ON g.id = b.guest_id
				WHERE b.deleted_at IS NULL AND b.status IN ('pending','confirmed') AND b.payment_status IN ('unpaid','partially_paid') AND b.balance_due > 0
					AND b.payment_due_at_gmt IS NOT NULL AND b.payment_due_at_gmt < %s
				ORDER BY b.payment_due_at_gmt ASC, b.id ASC
				LIMIT %d OFFSET %d",
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'bookings' ),
				rtbp_table( 'guests' ),
				$now_gmt,
				$per_page,
				$offset
			),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array(
			'rows'  => array_map(
				static fn( $row ) => array(
					'booking'     => Booking::hydrate( $row ),
					'guest_name'  => (string) $row['guest_name'],
					'guest_phone' => (string) $row['guest_phone'],
					'first_start' => $row['first_start'],
				),
				(array) $rows
			),
			'total' => $total,
		);
	}
}
