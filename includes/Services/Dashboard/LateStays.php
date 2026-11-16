<?php
/**
 * Late check-ins and check-outs: the reminder behind the staff bell.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Dashboard
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Dashboard;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * The rooms the desk forgot (Settings → Notifications → *Remind staff of
 * late check-ins and check-outs*), read by `notifications/poll`:
 *
 * - `check_in`: a confirmed room whose stay started more than the reminder
 *   delay ago and that is still not checked in — the guest came and nobody
 *   pressed *Check in*, or did not come (then: no-show). Kept for a day
 *   after the stay's end, so an old forgotten booking does not remind for ever.
 * - `check_out`: a checked-in room whose stay ended more than the delay ago
 *   and that is still in house — the guest left and nobody pressed *Check out*.
 *
 * One query on the stay times, oldest first, at most `LIMIT` rows plus the total.
 */
class LateStays {

	/**
	 * Most reminders returned (the total is counted apart).
	 */
	public const LIMIT = 20;

	/**
	 * Whether the reminders are on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) rtbp_setting( 'notifications', 'lateReminders', true );
	}

	/**
	 * Minutes after the start / end before a room is late.
	 *
	 * @return int
	 */
	public static function delay(): int {
		return max( 0, (int) rtbp_setting( 'notifications', 'lateMinutes', 30 ) );
	}

	/**
	 * The late rooms now.
	 *
	 * @param DateTimeImmutable|null $now Now.
	 * @return array{total: int, items: array[]} Items `{ line_id, booking_id, reference, guest, room, kind, due, minutes }`.
	 */
	public function get( ?DateTimeImmutable $now = null ): array {
		if ( ! self::enabled() ) {
			return array(
				'total' => 0,
				'items' => array(),
			);
		}
		global $wpdb;
		$now    = $now ?? Dates::now();
		$cutoff = Dates::to_gmt_db( $now->modify( '-' . self::delay() . ' minutes' ) );
		$since  = Dates::to_gmt_db( $now->modify( '-1 day' ) );
		$where  = $wpdb->prepare(
			"( ( br.status = 'confirmed' AND br.start_at_gmt <= %s AND br.end_at_gmt > %s )
				OR ( br.status = 'checked_in' AND br.occupied_until_gmt <= %s ) )",
			$cutoff,
			$since,
			$cutoff
		);
		$from   = $wpdb->prepare(
			'FROM %i br JOIN %i b ON b.id = br.booking_id AND b.deleted_at IS NULL LEFT JOIN %i g ON g.id = b.guest_id',
			rtbp_table( 'booking_rooms' ),
			rtbp_table( 'bookings' ),
			rtbp_table( 'guests' )
		);
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $from and $where are prepared above; a live poll.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) {$from} WHERE {$where}" );
		$rows  = $total ? (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT br.id, br.booking_id, b.reference, br.room_number, br.status, br.start_at_gmt, br.occupied_until_gmt,
					TRIM( CONCAT( COALESCE( g.first_name, '' ), ' ', COALESCE( g.last_name, '' ) ) ) AS guest
				{$from} WHERE {$where}
				ORDER BY IF( br.status = 'checked_in', br.occupied_until_gmt, br.start_at_gmt ), br.id
				LIMIT %d",
				self::LIMIT
			),
			ARRAY_A
		) : array();
		// phpcs:enable

		$items = array();
		foreach ( $rows as $row ) {
			$out     = 'checked_in' === $row['status'];
			$due     = Dates::from_gmt( (string) ( $out ? $row['occupied_until_gmt'] : $row['start_at_gmt'] ) );
			$items[] = array(
				'line_id'    => (int) $row['id'],
				'booking_id' => (int) $row['booking_id'],
				'reference'  => (string) $row['reference'],
				'guest'      => (string) $row['guest'],
				'room'       => (string) $row['room_number'],
				'kind'       => $out ? 'check_out' : 'check_in',
				'due'        => Dates::to_iso( $due ),
				'minutes'    => max( 0, (int) floor( ( $now->getTimestamp() - $due->getTimestamp() ) / MINUTE_IN_SECONDS ) ),
			);
		}
		return array(
			'total' => $total,
			'items' => $items,
		);
	}
}
