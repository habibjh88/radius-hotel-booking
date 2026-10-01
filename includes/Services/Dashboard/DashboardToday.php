<?php
/**
 * Today's overview on the dashboard (M01, 1.11).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Dashboard
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Dashboard;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * What happens at the front desk today, as one timeline, filled into
 * `dashboard/summary` → `today` (the M00 contract, `{ arrivals, departures }`,
 * plus `events`):
 *
 * - **scheduled** — `arrival`: a room whose stay starts today (00:00 included);
 *   `done` once checked in, `overdue` once its start has passed while it is
 *   still pending or confirmed, `todo` otherwise. `departure`: a room that
 *   checked in and ends today; `done` once checked out, `overdue` once its end
 *   has passed while the guest is still in.
 * - **actual** — `checked_in` / `checked_out`: what the desk did today, from
 *   the rooms' own `checked_in_at` / `checked_out_at` (the activity log is
 *   stored only by Pro, so free does not read it); always `done`.
 *
 * In time order; on a tie a scheduled event comes before an actual one
 * (legacy rule). One query, cached for 15 s with the counters' invalidation.
 */
class DashboardToday {

	/**
	 * Transient holding today's events.
	 */
	public const CACHE = 'rtbp_dashboard_today';

	/**
	 * Hook in: fill the summary; drop the cache on every change.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_dashboard_summary', array( self::class, 'summary' ) );
		foreach ( array( 'rtbp_booking_created', 'rtbp_booking_changed', 'rtbp_booking_status_changed' ) as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
	}

	/**
	 * Drop the cached events.
	 *
	 * @return void
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * `rtbp_dashboard_summary`: today's overview.
	 *
	 * @param array $summary Summary.
	 * @return array
	 */
	public static function summary( $summary ): array {
		$summary = (array) $summary;
		$cached  = get_transient( self::CACHE );
		$today   = is_array( $cached ) ? $cached : ( new self() )->compute( Dates::now() );
		if ( ! is_array( $cached ) ) {
			set_transient( self::CACHE, $today, DashboardCounters::TTL );
		}
		$summary['today'] = $today;
		return $summary;
	}

	/**
	 * Today's events at `$now` (never cached).
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return array{events: array[], arrivals: array[], departures: array[]}
	 */
	public function compute( DateTimeImmutable $now ): array {
		global $wpdb;
		list( $today, $tomorrow ) = DashboardCounters::day( $now );
		// checked_in_at / checked_out_at are stored in site time.
		$local_today    = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d 00:00:00' );
		$local_tomorrow = $now->setTimezone( Dates::timezone() )->setTime( 0, 0 )->modify( '+1 day' )->format( 'Y-m-d 00:00:00' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached by the caller.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.booking_id, r.status, r.room_number, r.start_at_gmt, r.end_at_gmt, r.checked_in_at, r.checked_out_at,
					b.reference, COALESCE( t.name, '' ) AS room_type,
					TRIM( CONCAT( COALESCE( g.first_name, '' ), ' ', COALESCE( g.last_name, '' ) ) ) AS guest
				FROM %i r
				INNER JOIN %i b ON b.id = r.booking_id AND b.deleted_at IS NULL
				LEFT JOIN %i g ON g.id = b.guest_id
				LEFT JOIN %i t ON t.id = r.room_type_id
				WHERE ( r.status IN ('pending','confirmed','checked_in','checked_out') AND r.start_at_gmt >= %s AND r.start_at_gmt < %s )
					OR ( r.status IN ('checked_in','checked_out') AND r.end_at_gmt >= %s AND r.end_at_gmt < %s )
					OR ( r.checked_in_at >= %s AND r.checked_in_at < %s )
					OR ( r.checked_out_at >= %s AND r.checked_out_at < %s )",
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'bookings' ),
				rtbp_table( 'guests' ),
				rtbp_table( 'room_types' ),
				$today,
				$tomorrow,
				$today,
				$tomorrow,
				$local_today,
				$local_tomorrow,
				$local_today,
				$local_tomorrow
			),
			ARRAY_A
		);
		// phpcs:enable

		$ts     = $now->getTimestamp();
		$events = array();
		foreach ( $rows as $row ) {
			$start  = strtotime( $row['start_at_gmt'] . ' UTC' );
			$end    = strtotime( $row['end_at_gmt'] . ' UTC' );
			$status = (string) $row['status'];
			$base   = array(
				'booking_id' => (int) $row['booking_id'],
				'line_id'    => (int) $row['id'],
				'reference'  => (string) $row['reference'],
				'guest'      => (string) $row['guest'],
				'room'       => (string) $row['room_number'],
				'room_type'  => (string) $row['room_type'],
			);
			if ( $row['start_at_gmt'] >= $today && $row['start_at_gmt'] < $tomorrow && in_array( $status, DashboardCounters::DAY_STATUSES, true ) ) {
				$arrived  = in_array( $status, array( 'checked_in', 'checked_out' ), true );
				$events[] = $base + array(
					'kind'  => 'arrival',
					'type'  => 'scheduled',
					'at'    => $start,
					'state' => $arrived ? 'done' : ( $start < $ts ? 'overdue' : 'todo' ),
				);
			}
			if ( $row['end_at_gmt'] >= $today && $row['end_at_gmt'] < $tomorrow && in_array( $status, array( 'checked_in', 'checked_out' ), true ) ) {
				$events[] = $base + array(
					'kind'  => 'departure',
					'type'  => 'scheduled',
					'at'    => $end,
					'state' => 'checked_out' === $status ? 'done' : ( $end < $ts ? 'overdue' : 'todo' ),
				);
			}
			foreach ( array(
				'checked_in' => 'checked_in_at',
				'checked_out' => 'checked_out_at',
			) as $kind => $column ) {
				if ( $row[ $column ] && $row[ $column ] >= $local_today && $row[ $column ] < $local_tomorrow ) {
					$events[] = $base + array(
						'kind'  => $kind,
						'type'  => 'actual',
						'at'    => Dates::local( (string) $row[ $column ] )->getTimestamp(),
						'state' => 'done',
					);
				}
			}
		}

		// Time order; on a tie, scheduled before actual (legacy).
		usort(
			$events,
			static fn( $a, $b ) => array( $a['at'], 'scheduled' === $a['type'] ? 0 : 1, $a['line_id'] ) <=> array( $b['at'], 'scheduled' === $b['type'] ? 0 : 1, $b['line_id'] )
		);
		$tz     = Dates::timezone();
		$events = array_map(
			static function ( $event ) use ( $tz ) {
				$event['id'] = $event['kind'] . '-' . $event['line_id'];
				$event['at'] = Dates::to_iso( ( new DateTimeImmutable( '@' . $event['at'] ) )->setTimezone( $tz ) );
				return $event;
			},
			$events
		);

		return array(
			'events'     => $events,
			'arrivals'   => array_values( array_filter( $events, static fn( $e ) => 'arrival' === $e['kind'] ) ),
			'departures' => array_values( array_filter( $events, static fn( $e ) => 'departure' === $e['kind'] ) ),
		);
	}
}
