<?php
/**
 * Room data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Room;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `rooms`. Model queries exclude removed (soft-deleted) rooms; the
 * uniqueness lookup includes them.
 */
class RoomRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Room::class;

	/**
	 * Rooms that can be sold: state `available`, not removed, of the given
	 * room types. The availability engine (M08) starts from these.
	 *
	 * @param int[] $room_type_ids Room type ids.
	 * @return Room[]
	 */
	public function sellableRooms( array $room_type_ids ): array {
		$room_type_ids = array_values( array_filter( array_map( 'intval', $room_type_ids ) ) );
		if ( ! $room_type_ids ) {
			return array();
		}
		$rows = Room::query()
			->whereIn( 'room_type_id', $room_type_ids )
			->where( 'state', '=', Room::AVAILABLE )
			->orderBy( 'number_sort' )
			->get();
		return array_map( static fn( $row ) => Room::hydrate( $row ), $rows );
	}

	/**
	 * The rooms of a room type, in natural order.
	 *
	 * @param int $room_type_id Room type id.
	 * @return Room[]
	 */
	public function ofType( int $room_type_id ): array {
		$rows = Room::query()->where( 'room_type_id', '=', $room_type_id )->orderBy( 'number_sort' )->get();
		return array_map( static fn( $row ) => Room::hydrate( $row ), $rows );
	}

	/**
	 * A room by number, removed rooms included (numbers are unique across the
	 * property, 6.9). Returns the raw row with its room type name.
	 *
	 * @param string $number Room number.
	 * @return object|null `{ id, number, room_type_id, room_type_name, deleted_at }`.
	 */
	public function findByNumberAny( string $number ): ?object {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uniqueness check across removed rooms.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT r.id, r.number, r.room_type_id, r.deleted_at, t.name AS room_type_name FROM %i r LEFT JOIN %i t ON t.id = r.room_type_id WHERE r.number = %s LIMIT 1',
				rtbp_table( 'rooms' ),
				rtbp_table( 'room_types' ),
				$number
			)
		);
		return $row ? $row : null;
	}

	/**
	 * The numbers among a list that already exist (removed rooms included).
	 *
	 * @param string[] $numbers Numbers.
	 * @return array<string, string> Number => owning room type name.
	 */
	public function existingNumbers( array $numbers ): array {
		global $wpdb;
		// 'strlen' keeps the number "0".
		$numbers = array_values( array_unique( array_filter( array_map( 'strval', $numbers ), 'strlen' ) ) );
		if ( ! $numbers ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $numbers ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%s,%s,…", one per value.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.number, t.name FROM %i r LEFT JOIN %i t ON t.id = r.room_type_id WHERE r.number IN ( $placeholders )",
				array_merge( array( rtbp_table( 'rooms' ), rtbp_table( 'room_types' ) ), $numbers )
			)
		);
		// phpcs:enable
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->number ] = (string) $row->name;
		}
		return $out;
	}

	/**
	 * Room counts per room type and state (for the overview cards).
	 *
	 * @param int[] $room_type_ids Room type ids; empty = all.
	 * @return array<int, array<string, int>> Type id => state => count.
	 */
	public function stateCounts( array $room_type_ids = array() ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one grouped count.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT room_type_id, state, COUNT(*) AS n FROM %i WHERE deleted_at IS NULL GROUP BY room_type_id, state', rtbp_table( 'rooms' ) ) );

		$wanted = array_map( 'intval', $room_type_ids );
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$type = (int) $row->room_type_id;
			if ( $wanted && ! in_array( $type, $wanted, true ) ) {
				continue;
			}
			$out[ $type ][ (string) $row->state ] = (int) $row->n;
		}
		return $out;
	}

	/**
	 * Lock a live room row until the transaction ends. Booking writes lock
	 * rooms the same way (booking-engine §7), so a remove or move and a new
	 * booking of the room cannot interleave.
	 *
	 * @param int $room_id Room id.
	 * @return bool Whether the room exists and is not removed.
	 */
	public function lock( int $room_id ): bool {
		return $this->lockRow( 'rooms', $room_id, true );
	}
}
