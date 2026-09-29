<?php
/**
 * Floor data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Floor;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `floors`.
 */
class FloorRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Floor::class;

	/**
	 * Every floor in display order.
	 *
	 * @return Floor[]
	 */
	public function ordered(): array {
		$rows = Floor::query()->orderBy( 'sort_order' )->orderBy( 'id' )->get();
		return array_map( static fn( $row ) => Floor::hydrate( $row ), $rows );
	}

	/**
	 * Rooms on a floor, removed rooms included (their history names the floor).
	 *
	 * @param int $floor_id Floor id.
	 * @return int
	 */
	public function roomCount( int $floor_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for a guard.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE floor_id = %d', rtbp_table( 'rooms' ), $floor_id ) );
	}

	/**
	 * The next sort position.
	 *
	 * @return int
	 */
	public function nextSortOrder(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small read.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX(sort_order), -1 ) + 1 FROM %i', rtbp_table( 'floors' ) ) );
	}

	/**
	 * Rooms per floor: live rooms and all rooms (removed ones included).
	 *
	 * @return array<int, array{rooms: int, total: int}> Floor id => counts.
	 */
	public function roomCounts(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped count.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT floor_id, SUM( deleted_at IS NULL ) AS live, COUNT(*) AS total FROM %i GROUP BY floor_id', rtbp_table( 'rooms' ) ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->floor_id ] = array(
				'rooms' => (int) $row->live,
				'total' => (int) $row->total,
			);
		}
		return $out;
	}

	/**
	 * Whether another floor already has this name (case-insensitive).
	 *
	 * @param string $name      Name.
	 * @param int    $except_id Ignore this floor.
	 * @return bool
	 */
	public function nameTaken( string $name, int $except_id = 0 ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uniqueness check.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE LOWER( name ) = LOWER( %s ) AND id <> %d LIMIT 1', rtbp_table( 'floors' ), $name, $except_id ) );
	}

	/**
	 * Lock a floor row until the transaction ends, so no room can be put on
	 * it while it is being deleted.
	 *
	 * @param int $floor_id Floor id.
	 * @return bool Whether the floor exists.
	 */
	public function lock( int $floor_id ): bool {
		return $this->lockRow( 'floors', $floor_id );
	}
}
