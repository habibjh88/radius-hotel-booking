<?php
/**
 * Rate calendar data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * `rate_calendar` (booking-engine §5.3 query 4). Writes come only from
 * `CalendarService`, inside its transaction. A row that neither overrides the
 * price nor closes the date is deleted, not kept empty.
 */
class RateCalendarRepository {

	/**
	 * Every calendar row of the given room types between two local dates.
	 *
	 * @param int[]  $room_type_ids Room type ids.
	 * @param string $from          First local date `Y-m-d`.
	 * @param string $to            Last local date (inclusive).
	 * @return array<int, array> `{ room_type_id, rate_plan_id, date, price_override (float|null), is_closed (bool) }`.
	 */
	public function between( array $room_type_ids, string $from, string $to ): array {
		global $wpdb;
		$room_type_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_type_ids ) ) ) );
		if ( ! $room_type_ids || $from > $to ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $room_type_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%d,…". Live inventory: never cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT room_type_id, rate_plan_id, date, price_override, is_closed FROM %i WHERE room_type_id IN ( $placeholders ) AND date BETWEEN %s AND %s",
				array_merge( array( rtbp_table( 'rate_calendar' ) ), $room_type_ids, array( $from, $to ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map(
			static fn( $row ) => array(
				'room_type_id'   => (int) $row['room_type_id'],
				'rate_plan_id'   => (int) $row['rate_plan_id'],
				'date'           => (string) $row['date'],
				'price_override' => null === $row['price_override'] ? null : (float) $row['price_override'],
				'is_closed'      => (bool) (int) $row['is_closed'],
			),
			(array) $rows
		);
	}

	/**
	 * Insert or update one cell.
	 *
	 * @param int        $room_type_id   Room type id.
	 * @param int        $rate_plan_id   Rate plan id; 0 = the whole room type.
	 * @param string     $date           Local date.
	 * @param float|null $price_override Price, or null for none.
	 * @param bool       $is_closed      Closed.
	 * @return void
	 * @throws \RuntimeException When the write fails.
	 */
	public function upsert( int $room_type_id, int $rate_plan_id, string $date, ?float $price_override, bool $is_closed ): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		// NULL cannot be bound through prepare(): the price column is chosen here, never from input.
		$price = null === $price_override ? 'NULL' : $wpdb->prepare( '%f', $price_override );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $price is 'NULL' or a prepared float.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i ( room_type_id, rate_plan_id, date, price_override, is_closed, created_at, updated_at )
				VALUES ( %d, %d, %s, $price, %d, %s, %s )
				ON DUPLICATE KEY UPDATE price_override = VALUES( price_override ), is_closed = VALUES( is_closed ), updated_at = VALUES( updated_at )",
				rtbp_table( 'rate_calendar' ),
				$room_type_id,
				$rate_plan_id,
				$date,
				$is_closed ? 1 : 0,
				$now,
				$now
			)
		);
		// phpcs:enable
		if ( '' !== (string) $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not save the calendar: ' . esc_html( (string) $wpdb->last_error ) );
		}
	}

	/**
	 * Delete one cell.
	 *
	 * @param int    $room_type_id Room type id.
	 * @param int    $rate_plan_id Rate plan id; 0 = the whole room type.
	 * @param string $date         Local date.
	 * @return void
	 */
	public function delete( int $room_type_id, int $rate_plan_id, string $date ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE room_type_id = %d AND rate_plan_id = %d AND date = %s', rtbp_table( 'rate_calendar' ), $room_type_id, $rate_plan_id, $date ) );
	}
}
