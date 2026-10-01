<?php
/**
 * The front desk counters (M01, 1.1–1.4).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Dashboard
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Dashboard;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\Overlap;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * What the front desk sees at a glance, filled into `dashboard/summary`'s
 * `stats` (the M00 contract):
 *
 * - `awaiting_approval` — bookings still pending a decision;
 * - `arrivals_today` / `departures_today` — booked rooms whose stay starts /
 *   ends today, site time, **from 00:00 included** (the legacy count skipped
 *   midnight), in any live or finished state (pending, confirmed, checked in,
 *   checked out — legacy parity: the day's arrivals include those already in);
 * - `in_house` — rooms checked in now;
 * - `overdue` — bookings past their payment deadline with money still due
 *   (the same rule as `bookings?overdue=1`);
 * - `rooms_free` / `rooms_total` — rooms that could be sold this minute (an
 *   active room type, the room available, nothing occupying it now, buffer
 *   included: `Overlap::roomReason()`, the booking rule) out of the rooms of
 *   active room types.
 *
 * One SQL statement for the booking counts plus the three availability reads
 * the booking engine already uses; cached for 15 s and dropped whenever a
 * booking, payment or block changes.
 */
class DashboardCounters {

	/**
	 * Transient holding the last counts.
	 */
	public const CACHE = 'rtbp_dashboard_counters';

	/**
	 * Seconds the counts are kept.
	 */
	public const TTL = 15;

	/**
	 * Line statuses counted in the day's arrivals and departures.
	 */
	public const DAY_STATUSES = array( 'pending', 'confirmed', 'checked_in', 'checked_out' );

	/**
	 * Hook in: fill the summary; drop the cache on every change.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_dashboard_summary', array( self::class, 'summary' ) );
		foreach ( array( 'rtbp_booking_created', 'rtbp_booking_changed', 'rtbp_booking_status_changed', 'rtbp_payment_recorded', 'rtbp_block_changed' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
	}

	/**
	 * Drop the cached counts.
	 *
	 * @return void
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * `rtbp_dashboard_summary`: the counters.
	 *
	 * @param array $summary Summary.
	 * @return array
	 */
	public static function summary( $summary ): array {
		$summary          = (array) $summary;
		$summary['stats'] = array_merge( (array) ( $summary['stats'] ?? array() ), ( new self() )->get() );
		return $summary;
	}

	/**
	 * The counts, from the cache when fresh.
	 *
	 * @return array<string, int>
	 */
	public function get(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$counts = $this->compute( Dates::now() );
		set_transient( self::CACHE, $counts, self::TTL );
		return $counts;
	}

	/**
	 * Count everything at `$now` (never cached).
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return array<string, int>
	 */
	public function compute( DateTimeImmutable $now ): array {
		return array_merge( $this->bookings( $now ), $this->rooms( $now ) );
	}

	/**
	 * The booking counts, one statement.
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return array<string, int>
	 */
	private function bookings( DateTimeImmutable $now ): array {
		global $wpdb;
		list( $today, $tomorrow ) = self::day( $now );
		$now_gmt                  = Dates::to_gmt_db( $now );
		$statuses                 = implode( ',', array_fill( 0, count( self::DAY_STATUSES ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $statuses is only "%s,…"; cached by the caller.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					( SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND status = 'pending' ) AS awaiting_approval,
					( SELECT COUNT(*) FROM %i WHERE deleted_at IS NULL AND status IN ('pending','confirmed')
						AND payment_status IN ('unpaid','partially_paid') AND balance_due > 0
						AND payment_due_at_gmt IS NOT NULL AND payment_due_at_gmt < %s ) AS overdue,
					COALESCE( SUM( CASE WHEN r.start_at_gmt >= %s AND r.start_at_gmt < %s THEN 1 ELSE 0 END ), 0 ) AS arrivals_today,
					COALESCE( SUM( CASE WHEN r.end_at_gmt >= %s AND r.end_at_gmt < %s THEN 1 ELSE 0 END ), 0 ) AS departures_today,
					COALESCE( SUM( CASE WHEN r.status = 'checked_in' THEN 1 ELSE 0 END ), 0 ) AS in_house
				FROM %i r INNER JOIN %i b ON b.id = r.booking_id AND b.deleted_at IS NULL
				WHERE r.status IN ( $statuses )
					AND ( r.status = 'checked_in'
						OR ( r.start_at_gmt >= %s AND r.start_at_gmt < %s )
						OR ( r.end_at_gmt >= %s AND r.end_at_gmt < %s ) )",
				array_merge(
					array(
						rtbp_table( 'bookings' ),
						rtbp_table( 'bookings' ),
						$now_gmt,
						$today,
						$tomorrow,
						$today,
						$tomorrow,
						rtbp_table( 'booking_rooms' ),
						rtbp_table( 'bookings' ),
					),
					self::DAY_STATUSES,
					array( $today, $tomorrow, $today, $tomorrow )
				)
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( array( 'awaiting_approval', 'arrivals_today', 'departures_today', 'in_house', 'overdue' ) as $key ) {
			$out[ $key ] = (int) ( $row[ $key ] ?? 0 );
		}
		return $out;
	}

	/**
	 * Rooms free to sell this minute, out of the rooms of active room types.
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return array{rooms_free: int, rooms_total: int}
	 */
	private function rooms( DateTimeImmutable $now ): array {
		$repository = new AvailabilityRepository();
		$type_ids   = array_values( array_unique( array_map( static fn( $row ) => (int) $row['type_id'], $repository->catalogue() ) ) );
		$rooms      = $type_ids ? $repository->rooms( $type_ids ) : array();
		if ( ! $rooms ) {
			return array(
				'rooms_free'  => 0,
				'rooms_total' => 0,
			);
		}

		$buffers = $repository->buffers( $type_ids );
		$widest  = $buffers ? max( $buffers ) : 0;
		$start   = $now->getTimestamp();
		$end     = $start + MINUTE_IN_SECONDS;
		// The envelope is widened by the longest buffer on both sides, as the availability search does.
		$busy = $repository->busy(
			array_map( static fn( $room ) => (int) $room['id'], $rooms ),
			gmdate( 'Y-m-d H:i:s', $start - $widest * MINUTE_IN_SECONDS ),
			gmdate( 'Y-m-d H:i:s', $end + $widest * MINUTE_IN_SECONDS ),
			'',
			0,
			Dates::to_gmt_db( $now )
		);

		$free = 0;
		foreach ( $rooms as $room ) {
			$reason = Overlap::roomReason( (string) $room['state'], $busy[ (int) $room['id'] ] ?? array(), $start, $end, (int) ( $buffers[ (int) $room['room_type_id'] ] ?? 0 ) );
			if ( null === $reason ) {
				++$free;
			}
		}
		return array(
			'rooms_free'  => $free,
			'rooms_total' => count( $rooms ),
		);
	}

	/**
	 * Today in site time, as a GMT `[start, end)` pair.
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return string[] `[ today 00:00, tomorrow 00:00 ]` in GMT `Y-m-d H:i:s`.
	 */
	public static function day( DateTimeImmutable $now ): array {
		$local = $now->setTimezone( Dates::timezone() )->setTime( 0, 0 );
		return array( Dates::to_gmt_db( $local ), Dates::to_gmt_db( $local->modify( '+1 day' ) ) );
	}
}
