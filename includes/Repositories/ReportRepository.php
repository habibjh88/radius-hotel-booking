<?php
/**
 * Aggregate reads for the reports (M10).
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportRange;

defined( 'ABSPATH' ) || exit;

/**
 * Grouped SQL over the stored booking lines and payments — one statement per
 * figure set, never a row-by-row load (the legacy N+1). Nothing is re-priced:
 * a line's money is its frozen `total`.
 */
class ReportRepository {

	/**
	 * Line statuses that did not happen (10.4), and no-shows (counted apart).
	 */
	public const FAILED_STATUSES = array( 'cancelled', 'declined' );

	/**
	 * Booking lines of the period, grouped.
	 *
	 * Per group: `bucket` (`''`, `Y-m-d` or `Y-m` in site time, by the mode's
	 * date), `method` (the booking's latest live payment's method, `''` when
	 * none), `outcome` (`sold` | `failed` | `no_show`), `paid` (the booking is
	 * fully paid), `lines`, `bookings` (distinct), `total`.
	 *
	 * *Failed* = a cancelled or declined line, or any line of a booking whose
	 * money was all refunded (`payment_status = refunded`) — legacy
	 * `cancelled|failed|refunded`.
	 *
	 * @param ReportRange $range Period.
	 * @param string      $unit  `method` (no date bucket), `day` or `month`.
	 * @return array[]
	 */
	public function salesGroups( ReportRange $range, string $unit ): array {
		global $wpdb;
		$lines    = rtbp_table( 'booking_rooms' );
		$bookings = rtbp_table( 'bookings' );
		$payments = rtbp_table( 'payments' );

		// Arrival: the line's local start (written from the same moment as
		// `start_at_gmt`). Created: `created_at_gmt` itself — the column the
		// period is filtered on (the local `created_at` is stamped at insert
		// and can fall in the next day, M10 critical review) — in 15-minute
		// GMT slots, turned into site-time days below (every UTC offset is a
		// multiple of 15 minutes).
		$slots = 'created' === $range->mode && 'method' !== $unit;
		if ( 'method' === $unit ) {
			$bucket = "''";
		} elseif ( $slots ) {
			$bucket = "TIMESTAMPDIFF( MINUTE, '1970-01-01 00:00:00', b.created_at_gmt ) DIV 15";
		} else {
			// Departure groups by the line's local end, arrival by its local start (R1-T12).
			$local  = 'departure' === $range->mode ? 'br.end_at' : 'br.start_at';
			$bucket = 'month' === $unit ? "DATE_FORMAT( {$local}, '%%Y-%%m' )" : "DATE_FORMAT( {$local}, '%%Y-%%m-%%d' )";
		}
		$column = $range->column();
		$where  = "{$column} >= %s AND {$column} < %s";

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $bucket and $where are fixed SQL fragments chosen above (two %s in $where); $sql is prepared; the report caches its result.
		// `line_count`: a stay split by a room move counts once, on its first part (its arrival).
		$sql = $wpdb->prepare(
			"SELECT {$bucket} AS bucket,
				COALESCE( pm.method, '' ) AS method,
				CASE
					WHEN br.status IN ( 'cancelled', 'declined' ) OR b.payment_status = 'refunded' THEN 'failed'
					WHEN br.status = 'no_show' THEN 'no_show'
					ELSE 'sold'
				END AS outcome,
				IF( b.payment_status = 'paid', 1, 0 ) AS paid,
				SUM( CASE WHEN br.moved_from = 0 THEN 1 ELSE 0 END ) AS line_count,
				COUNT( DISTINCT b.id ) AS booking_count,
				SUM( br.total ) AS total
			FROM %i br
			JOIN %i b ON b.id = br.booking_id AND b.deleted_at IS NULL
			LEFT JOIN (
				SELECT p.booking_id,
					SUBSTRING_INDEX( GROUP_CONCAT( p.method ORDER BY p.received_at_gmt DESC, p.id DESC SEPARATOR '|' ), '|', 1 ) AS method
				FROM %i p
				WHERE p.type = 'payment'
					AND NOT EXISTS ( SELECT 1 FROM %i v WHERE v.voids_payment_id = p.id )
				GROUP BY p.booking_id
			) pm ON pm.booking_id = b.id
			WHERE {$where}
			GROUP BY bucket, method, outcome, paid",
			$lines,
			$bookings,
			$payments,
			$payments,
			$range->from_gmt,
			$range->to_gmt
		);
		$rows = (array) $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable

		$groups = array();
		foreach ( (array) $rows as $row ) {
			$bucket = (string) $row['bucket'];
			if ( $slots ) {
				$local  = ( new \DateTimeImmutable( '@' . ( (int) $bucket * 900 ) ) )->setTimezone( \RadiusTheme\RadiusHotelBooking\Support\Dates::timezone() );
				$bucket = $local->format( 'month' === $unit ? 'Y-m' : 'Y-m-d' );
			}
			$key = implode( '|', array( $bucket, $row['method'], $row['outcome'], $row['paid'] ) );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'bucket'   => $bucket,
					'method'   => (string) $row['method'],
					'outcome'  => (string) $row['outcome'],
					'paid'     => 1 === (int) $row['paid'],
					'lines'    => 0,
					'bookings' => 0,
					'total'    => 0.0,
				);
			}
			// A booking has one creation moment, so it sits in one slot: summing slots counts it once.
			$groups[ $key ]['lines']    += (int) $row['line_count'];
			$groups[ $key ]['bookings'] += (int) $row['booking_count'];
			$groups[ $key ]['total']    += (float) $row['total'];
		}
		return array_values( $groups );
	}

	/**
	 * Each booking's sold total in the period (the tax is computed per
	 * booking, like its invoice, then added up).
	 *
	 * @param ReportRange $range Period.
	 * @return float[]
	 */
	public function soldPerBooking( ReportRange $range ): array {
		global $wpdb;
		$column = $range->column();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is a fixed column name from ReportRange::column().
		$where = $wpdb->prepare( "{$column} >= %s AND {$column} < %s", $range->from_gmt, $range->to_gmt );
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where is prepared above; the report caches its result.
		$totals = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT SUM( br.total ) FROM %i br
				JOIN %i b ON b.id = br.booking_id AND b.deleted_at IS NULL
				WHERE br.status NOT IN ( 'cancelled', 'declined', 'no_show' ) AND b.payment_status <> 'refunded' AND {$where}
				GROUP BY b.id",
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'bookings' )
			)
		);
		// phpcs:enable
		return array_map( 'floatval', (array) $totals );
	}

	/**
	 * Money collected, by method: every ledger row is stored signed (refunds
	 * and voids negative), so the sum is what the hotel kept.
	 *
	 * `created` mode: rows received in the period. `arrival` mode: every row of
	 * the bookings with a line arriving in the period, whenever received.
	 *
	 * @param ReportRange $range Period.
	 * @return array<string, float> Method key => amount.
	 */
	public function collected( ReportRange $range ): array {
		global $wpdb;
		$payments = rtbp_table( 'payments' );
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an aggregate; the report caches its result.
		if ( 'created' === $range->mode ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT p.method, SUM( p.amount ) AS amount FROM %i p
					JOIN %i b ON b.id = p.booking_id AND b.deleted_at IS NULL
					WHERE p.received_at_gmt >= %s AND p.received_at_gmt < %s
					GROUP BY p.method',
					$payments,
					rtbp_table( 'bookings' ),
					$range->from_gmt,
					$range->to_gmt
				),
				ARRAY_A
			);
		} else {
			$column = $range->column();
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is a fixed column name from ReportRange::column().
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.method, SUM( p.amount ) AS amount FROM %i p
					JOIN %i b ON b.id = p.booking_id AND b.deleted_at IS NULL
					WHERE p.booking_id IN ( SELECT br.booking_id FROM %i br WHERE {$column} >= %s AND {$column} < %s )
					GROUP BY p.method",
					$payments,
					rtbp_table( 'bookings' ),
					rtbp_table( 'booking_rooms' ),
					$range->from_gmt,
					$range->to_gmt
				),
				ARRAY_A
			);
			// phpcs:enable
		}
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['method'] ] = (float) $row['amount'];
		}
		return $out;
	}

	/**
	 * Line statuses that occupy a room (the module's definition).
	 */
	public const OCCUPYING_STATUSES = array( 'confirmed', 'checked_in', 'checked_out' );

	/**
	 * Line statuses listed in the rooms report's bookings table.
	 */
	public const LIVE_STATUSES = array( 'pending', 'confirmed', 'checked_in', 'checked_out' );

	/**
	 * The SQL that picks the period's lines (alias `br`, bookings `b`).
	 *
	 * `arrival` mode: stays **overlapping** the period — a room is occupied at
	 * any moment of it, multi-night stays that started before included (the
	 * legacy counted only check-ins inside the window), up to the guest's real
	 * departure (`occupied_until_gmt`: the end, or an early check-out). `created` mode: lines
	 * of bookings taken in the period.
	 *
	 * @param ReportRange $range Period.
	 * @return string A prepared condition.
	 */
	public function periodCondition( ReportRange $range ): string {
		global $wpdb;
		if ( 'created' === $range->mode ) {
			return $wpdb->prepare( 'b.created_at_gmt >= %s AND b.created_at_gmt < %s', $range->from_gmt, $range->to_gmt );
		}
		if ( 'departure' === $range->mode ) {
			// Stays ending in the period (R1-T12).
			return $wpdb->prepare( 'br.end_at_gmt >= %s AND br.end_at_gmt < %s', $range->from_gmt, $range->to_gmt );
		}
		// `occupied_until_gmt` = the stay's end, or the moment of an early check-out.
		return $wpdb->prepare( 'br.start_at_gmt < %s AND br.occupied_until_gmt > %s', $range->to_gmt, $range->from_gmt );
	}

	/**
	 * Rooms by state, now (rooms of live room types).
	 *
	 * @return array<string, int> State => rooms.
	 */
	public function roomStates(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an aggregate; the report caches its result.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.state, COUNT(*) AS rooms FROM %i r JOIN %i t ON t.id = r.room_type_id AND t.deleted_at IS NULL
				WHERE r.deleted_at IS NULL GROUP BY r.state',
				rtbp_table( 'rooms' ),
				rtbp_table( 'room_types' )
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['state'] ] = (int) $row['rooms'];
		}
		return $out;
	}
}
