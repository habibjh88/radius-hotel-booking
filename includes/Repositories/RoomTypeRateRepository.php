<?php
/**
 * Room type rate data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\RoomTypeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `room_type_rates`.
 */
class RoomTypeRateRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = RoomTypeRate::class;

	/**
	 * A room type's rate rows, keyed by rate plan id.
	 *
	 * @param int $room_type_id Room type id.
	 * @return array<int, object> Plan id => raw row.
	 */
	public function forRoomType( int $room_type_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read per grid.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE room_type_id = %d', rtbp_table( 'room_type_rates' ), $room_type_id ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->rate_plan_id ] = $row;
		}
		return $out;
	}

	/**
	 * One rate row (the pair), or null.
	 *
	 * @param int $room_type_id Room type id.
	 * @param int $rate_plan_id Rate plan id.
	 * @return object|null Raw row.
	 */
	public function pair( int $room_type_id, int $rate_plan_id ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one pricing read.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE room_type_id = %d AND rate_plan_id = %d', rtbp_table( 'room_type_rates' ), $room_type_id, $rate_plan_id ) );
		return $row ? $row : null;
	}

	/**
	 * The live room types selling a plan (enabled rate, room type not removed).
	 *
	 * @param int $rate_plan_id Rate plan id.
	 * @return int
	 */
	public function roomTypesSelling( int $rate_plan_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for a guard.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i r JOIN %i t ON t.id = r.room_type_id AND t.deleted_at IS NULL WHERE r.rate_plan_id = %d AND r.enabled = 1',
				rtbp_table( 'room_type_rates' ),
				rtbp_table( 'room_types' ),
				$rate_plan_id
			)
		);
	}

	/**
	 * The enabled, live rate plans of each room type, in grid order (the cards).
	 *
	 * @return array<int, string[]> Room type id => plan names.
	 */
	public function enabledPlanNames(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one read for every card.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT r.room_type_id, p.name FROM %i r JOIN %i p ON p.id = r.rate_plan_id AND p.deleted_at IS NULL AND p.is_active = 1 WHERE r.enabled = 1 ORDER BY r.room_type_id, r.sort_order, p.sort_order',
				rtbp_table( 'room_type_rates' ),
				rtbp_table( 'rate_plans' )
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->room_type_id ][] = (string) $row->name;
		}
		return $out;
	}

	/**
	 * A plan just became multi-unit: its rates were forced to 1–1 while it
	 * was single-unit, so lift that cap (no maximum, as for a new rate).
	 *
	 * @param int $rate_plan_id Rate plan id.
	 * @return int Rows changed.
	 */
	public function liftSingleUnitCap( int $rate_plan_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one bulk update.
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE %i SET max_units = NULL WHERE rate_plan_id = %d AND min_units = 1 AND max_units = 1', rtbp_table( 'room_type_rates' ), $rate_plan_id ) );
	}
}
