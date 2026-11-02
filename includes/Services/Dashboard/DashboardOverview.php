<?php
/**
 * The dashboard's recent bookings, rooms by state and setup progress.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Dashboard
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Dashboard;

use RadiusTheme\RadiusHotelBooking\Repositories\ReportRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Fills `recent_bookings`, `rooms_by_state` and the setup steps' `done` flags
 * of the `rtbp_dashboard_summary` payload (M01), which were left at their
 * empty defaults.
 */
class DashboardOverview {

	/**
	 * Recent bookings shown.
	 */
	public const RECENT = 6;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_dashboard_summary', array( self::class, 'summary' ) );
	}

	/**
	 * `rtbp_dashboard_summary`: the overview.
	 *
	 * @param array $summary Summary.
	 * @return array
	 */
	public static function summary( $summary ): array {
		$summary = (array) $summary;
		$self    = new self();

		$summary['recent_bookings'] = $self->recentBookings();
		$summary['rooms_by_state']  = array_merge( (array) ( $summary['rooms_by_state'] ?? array() ), ( new ReportRepository() )->roomStates() );

		$done = $self->setupDone();
		foreach ( $done as $key => $is_done ) {
			if ( isset( $summary['setup'][ $key ] ) && $is_done ) {
				$summary['setup'][ $key ]['done'] = true;
			}
		}
		return $summary;
	}

	/**
	 * The latest bookings, newest first.
	 *
	 * @return array[] `{ id, reference, guest, rooms: string[], arrival, departure, status, payment_status }`.
	 */
	public function recentBookings(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- six rows by primary key.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.reference, b.status, b.payment_status,
					TRIM( CONCAT( COALESCE( g.first_name, '' ), ' ', COALESCE( g.last_name, '' ) ) ) AS guest,
					( SELECT MIN( br.start_at ) FROM %i br WHERE br.booking_id = b.id ) AS arrival,
					( SELECT MAX( br.end_at ) FROM %i br WHERE br.booking_id = b.id ) AS departure
				FROM %i b LEFT JOIN %i g ON g.id = b.guest_id
				WHERE b.deleted_at IS NULL
				ORDER BY b.id DESC
				LIMIT %d",
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'bookings' ),
				rtbp_table( 'guests' ),
				self::RECENT
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return array();
		}

		$ids          = array_map( static fn( $row ) => (int) $row['id'], $rows );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is only "%d,…".
		$lines = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_id, room_number FROM %i WHERE booking_id IN ( $placeholders ) ORDER BY start_at, id",
				array_merge( array( rtbp_table( 'booking_rooms' ) ), $ids )
			),
			ARRAY_A
		);
		// phpcs:enable
		$rooms = array();
		foreach ( (array) $lines as $line ) {
			$rooms[ (int) $line['booking_id'] ][] = (string) $line['room_number'];
		}

		return array_map(
			static fn( $row ) => array(
				'id'             => (int) $row['id'],
				'reference'      => (string) $row['reference'],
				'guest'          => (string) $row['guest'],
				'rooms'          => array_values( array_unique( array_filter( $rooms[ (int) $row['id'] ] ?? array() ) ) ),
				'arrival'        => $row['arrival'] ? substr( (string) $row['arrival'], 0, 16 ) : null,
				'departure'      => $row['departure'] ? substr( (string) $row['departure'], 0, 16 ) : null,
				'status'         => (string) $row['status'],
				'payment_status' => (string) $row['payment_status'],
			),
			$rows
		);
	}

	/**
	 * Which setup steps the hotel has done.
	 *
	 * @return array<string, bool> Step key => done.
	 */
	public function setupDone(): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence checks, one row each.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					EXISTS( SELECT 1 FROM %i WHERE deleted_at IS NULL ) AS room_types,
					EXISTS( SELECT 1 FROM %i WHERE deleted_at IS NULL ) AS rooms,
					EXISTS( SELECT 1 FROM %i WHERE price > 0 ) AS rate_plans',
				rtbp_table( 'room_types' ),
				rtbp_table( 'rooms' ),
				rtbp_table( 'room_type_rates' )
			),
			ARRAY_A
		);
		// phpcs:enable

		$methods = (array) rtbp_setting( 'payments', 'methods', array() );
		$written = false !== get_option( 'rtbp_payments_settings', false ) && (bool) array_filter(
			$methods,
			static fn( $method ) => ! empty( $method['enabled'] ) && '' !== trim( (string) ( $method['instructions'] ?? '' ) )
		);

		return array(
			'room_types' => ! empty( $row['room_types'] ),
			'rooms'      => ! empty( $row['rooms'] ),
			'rate_plans' => ! empty( $row['rate_plans'] ),
			'payments'   => $written,
		);
	}
}
