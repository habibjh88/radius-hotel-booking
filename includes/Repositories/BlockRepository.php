<?php
/**
 * Block data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Block;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `blocks`. Rows come back with `target` (the floor, room type or
 * room number they close, '' for the property) and `room_type_name` (for a
 * room) and `room_type_id` (for a room). The engine reads blocks through `AvailabilityRepository::busy()`.
 */
class BlockRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Block::class;

	/**
	 * A page of blocks.
	 *
	 * @param array  $filters `{ when: upcoming|past|all, scope?: string }`.
	 * @param string $now_gmt Now, UTC `Y-m-d H:i:s`.
	 * @param int    $page    Page (1-based).
	 * @param int    $per     Rows per page.
	 * @return array{items: array[], total: int}
	 */
	public function page( array $filters, string $now_gmt, int $page, int $per ): array {
		global $wpdb;
		$where = array( '1=1' );
		$args  = array();
		$when  = $filters['when'] ?? 'upcoming';
		if ( 'upcoming' === $when ) {
			$where[] = 'b.end_at_gmt > %s';
			$args[]  = $now_gmt;
		} elseif ( 'past' === $when ) {
			$where[] = 'b.end_at_gmt <= %s';
			$args[]  = $now_gmt;
		}
		if ( ! empty( $filters['scope'] ) ) {
			$where[] = 'b.scope = %s';
			$args[]  = (string) $filters['scope'];
		}
		$where = implode( ' AND ', $where );
		// Coming blocks soonest first; finished ones latest first.
		$order = 'upcoming' === $when ? 'b.start_at_gmt ASC, b.id ASC' : 'b.start_at_gmt DESC, b.id DESC';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where holds only fixed conditions with placeholders; $order and select() are fixed SQL. Live inventory: never cached.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i b WHERE $where", array_merge( array( rtbp_table( 'blocks' ) ), $args ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				$this->select() . " WHERE $where ORDER BY $order LIMIT %d OFFSET %d",
				array_merge( $this->tables(), $args, array( $per, ( max( 1, $page ) - 1 ) * $per ) )
			),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Blocks by id, with their labels.
	 *
	 * @param int[] $ids Block ids.
	 * @return array<int, array> Id => row, by start.
	 */
	public function labelled( array $ids ): array {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%d,…"; select() is fixed SQL. Live inventory.
		$rows = $wpdb->get_results(
			$wpdb->prepare( $this->select() . " WHERE b.id IN ( $placeholders ) ORDER BY b.start_at_gmt, b.id", array_merge( $this->tables(), $ids ) ),
			ARRAY_A
		);
		// phpcs:enable
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * The blocks an external feed created, for a sync to reconcile by UID
	 * (`rtbp_block_sources`, e.g. Pro's iCal import).
	 *
	 * @param string $source  Source key.
	 * @param int    $feed_id Feed id.
	 * @return array<string, array> External UID => row (`id`, `external_uid`, `start_at_gmt`, `end_at_gmt`, `scope`, `scope_id`, `reason`).
	 */
	public function forFeed( string $source, int $feed_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live inventory, index feed_uid.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, external_uid, start_at_gmt, end_at_gmt, scope, scope_id, reason FROM %i WHERE feed_id = %d AND source = %s ORDER BY id', rtbp_table( 'blocks' ), $feed_id, $source ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['external_uid'] ] = $row;
		}
		return $out;
	}

	/**
	 * Booking lines already on rooms a block would close, overlapping its
	 * window: the block does not move them, so staff are told.
	 *
	 * @param string $scope     Scope.
	 * @param int    $scope_id  Scope id.
	 * @param string $start_gmt Start, UTC.
	 * @param string $end_gmt   End, UTC.
	 * @return int
	 */
	public function linesInside( string $scope, int $scope_id, string $start_gmt, string $end_gmt ): int {
		global $wpdb;
		$statuses = implode( ',', array_fill( 0, count( AvailabilityRepository::OCCUPYING ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $statuses is only "%s,…". Live count.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i br INNER JOIN %i r ON r.id = br.room_id
				WHERE br.status IN ( $statuses ) AND br.start_at_gmt < %s AND br.occupied_until_gmt > %s
					AND ( %s = 'property'
						OR ( %s = 'room' AND r.id = %d )
						OR ( %s = 'room_type' AND r.room_type_id = %d )
						OR ( %s = 'floor' AND r.floor_id = %d ) )",
				array_merge(
					array( rtbp_table( 'booking_rooms' ), rtbp_table( 'rooms' ) ),
					AvailabilityRepository::OCCUPYING,
					array( $end_gmt, $start_gmt, $scope, $scope, $scope_id, $scope, $scope_id, $scope, $scope_id )
				)
			)
		);
		// phpcs:enable
	}

	/**
	 * The live rooms a block of this scope closes.
	 *
	 * @param string $scope    Scope.
	 * @param int    $scope_id Scope id.
	 * @return int[]
	 */
	public function roomIdsInScope( string $scope, int $scope_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live rooms, before a lock.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM %i r WHERE r.deleted_at IS NULL
					AND ( %s = 'property'
						OR ( %s = 'room' AND r.id = %d )
						OR ( %s = 'room_type' AND r.room_type_id = %d )
						OR ( %s = 'floor' AND r.floor_id = %d ) )
				ORDER BY id",
				rtbp_table( 'rooms' ),
				$scope,
				$scope,
				$scope_id,
				$scope,
				$scope_id,
				$scope,
				$scope_id
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether the thing a block closes exists: a floor, a room type (hidden
	 * ones too) or a live room. The property always exists.
	 *
	 * @param string $scope    Scope.
	 * @param int    $scope_id Id.
	 * @return bool
	 */
	public function targetExists( string $scope, int $scope_id ): bool {
		global $wpdb;
		if ( 'property' === $scope ) {
			return true;
		}
		$tables = array(
			'floor'     => array( 'floors', false ),
			'room_type' => array( 'room_types', true ),
			'room'      => array( 'rooms', true ),
		);
		if ( ! isset( $tables[ $scope ] ) || $scope_id <= 0 ) {
			return false;
		}
		list( $table, $soft ) = $tables[ $scope ];
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence check.
		return (bool) ( $soft
			? $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d AND deleted_at IS NULL', rtbp_table( $table ), $scope_id ) )
			: $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', rtbp_table( $table ), $scope_id ) ) );
		// phpcs:enable
	}

	/**
	 * The select with labels (placeholders for the five tables of tables()).
	 *
	 * @return string
	 */
	private function select(): string {
		return "SELECT b.*,
				CASE b.scope WHEN 'floor' THEN f.name WHEN 'room_type' THEN t.name WHEN 'room' THEN r.number ELSE '' END AS target,
				COALESCE( rt.name, '' ) AS room_type_name, COALESCE( rt.id, 0 ) AS room_type_id
			FROM %i b
			LEFT JOIN %i f ON b.scope = 'floor' AND f.id = b.scope_id
			LEFT JOIN %i t ON b.scope = 'room_type' AND t.id = b.scope_id
			LEFT JOIN %i r ON b.scope = 'room' AND r.id = b.scope_id
			LEFT JOIN %i rt ON rt.id = r.room_type_id";
	}

	/**
	 * Tables for select().
	 *
	 * @return string[]
	 */
	private function tables(): array {
		return array( rtbp_table( 'blocks' ), rtbp_table( 'floors' ), rtbp_table( 'room_types' ), rtbp_table( 'rooms' ), rtbp_table( 'room_types' ) );
	}
}
