<?php
/**
 * Busy intervals for the availability engine (booking-engine §4, §5.3).
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Services\Availability\Overlap;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * One query answers "what occupies these rooms in this envelope": booking
 * lines, active holds and blocks, as one UNION (§5.3 query 3). The search
 * (M08 T2) and the locked write path (T3) both read it, so the two can never
 * disagree about what "free" means.
 */
class AvailabilityRepository {

	/**
	 * Line statuses that occupy a room (§4, D5). `checked_out` occupies only
	 * up to `occupied_until_gmt`, which the interval already ends at.
	 */
	public const OCCUPYING = array( 'pending', 'confirmed', 'checked_in', 'checked_out' );

	/**
	 * Query 1 (§5.3): live room types with their rates and rate plans, one row
	 * per rate (a type without rates comes back once, with NULL rate fields).
	 * Removed plans are left out; switched-off rates and inactive plans are
	 * returned so the caller can report them.
	 *
	 * @param int  $room_type_id     Only this room type; 0 = all.
	 * @param bool $include_inactive Include hidden room types (the calendar).
	 * @return array<int, array> Rows in type order, then rate order.
	 */
	public function catalogue( int $room_type_id = 0, bool $include_inactive = false ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one join query per search.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT t.id AS type_id, t.name AS type_name, t.max_adults, t.max_children, t.buffer_minutes,
					r.id AS rate_id, r.rate_plan_id, r.price, r.sale_price, r.min_units, r.max_units, r.enabled,
					p.name AS plan_name, p.type AS plan_type, p.start_time, p.end_time, p.duration_minutes,
					p.checkin_from, p.checkin_until, p.multi_unit, p.is_active AS plan_active
				FROM %i t
				LEFT JOIN %i r ON r.room_type_id = t.id
				LEFT JOIN %i p ON p.id = r.rate_plan_id AND p.deleted_at IS NULL
				WHERE t.deleted_at IS NULL AND ( t.is_active = 1 OR %d = 1 ) AND ( %d = 0 OR t.id = %d )
				ORDER BY t.sort_order, t.id, r.sort_order, r.id',
				rtbp_table( 'room_types' ),
				rtbp_table( 'room_type_rates' ),
				rtbp_table( 'rate_plans' ),
				$include_inactive ? 1 : 0,
				$room_type_id,
				$room_type_id
			),
			ARRAY_A
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Query 2 (§5.3): the live rooms of some room types, whatever their state,
	 * with their floor, in floor then natural number order. Selecting by
	 * `room_type_id` is what keeps each type to its own rooms.
	 *
	 * @param int[] $room_type_ids Room type ids.
	 * @return array<int, array> `{ id, room_type_id, floor_id, number, state, state_note, floor_name }`.
	 */
	public function rooms( array $room_type_ids ): array {
		global $wpdb;
		$room_type_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_type_ids ) ) ) );
		if ( ! $room_type_ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $room_type_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%d,…".
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.room_type_id, r.floor_id, r.number, r.state, r.state_note, COALESCE( f.name, '' ) AS floor_name
				FROM %i r LEFT JOIN %i f ON f.id = r.floor_id
				WHERE r.room_type_id IN ( $placeholders ) AND r.deleted_at IS NULL
				ORDER BY f.sort_order, r.floor_id, r.number_sort, r.id",
				array_merge( array( rtbp_table( 'rooms' ), rtbp_table( 'floors' ) ), $room_type_ids )
			),
			ARRAY_A
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * Busy intervals of the given rooms that touch `[from, to)`.
	 *
	 * The caller widens the envelope by the cleaning buffer (on the left, so a
	 * stay ending just before `from` still counts); rows are returned as they
	 * are stored and `Overlap` applies the buffer.
	 *
	 * @param int[]  $room_ids     Room ids.
	 * @param string $from_gmt     Envelope start, GMT `Y-m-d H:i:s`.
	 * @param string $to_gmt       Envelope end (exclusive), GMT.
	 * @param string $hold_token   The requester's own hold token: not a conflict (§10 #11).
	 * @param int    $exclude_line A booking line being edited: not a conflict with itself.
	 * @param string $now_gmt      "Now" for hold expiry; empty = the current time.
	 * @return array<int, array<int, array>> Room id => intervals `{ s, e, kind, ref_id, label }`,
	 *                                       sorted by start. `label` is the booking reference
	 *                                       for a line and the reason for a block.
	 * @throws \RadiusTheme\RadiusHotelBooking\Exceptions\DomainException 503 when the read fails.
	 */
	public function busy( array $room_ids, string $from_gmt, string $to_gmt, string $hold_token = '', int $exclude_line = 0, string $now_gmt = '' ): array {
		global $wpdb;

		$room_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_ids ) ) ) );
		if ( ! $room_ids || $from_gmt >= $to_gmt ) {
			return array();
		}
		if ( '' === $now_gmt ) {
			$now_gmt = Dates::to_gmt_db( Dates::now() );
		}

		$rooms    = implode( ',', array_fill( 0, count( $room_ids ), '%d' ) );
		$statuses = implode( ',', array_fill( 0, count( self::OCCUPYING ), '%s' ) );

		// Lines: occupying status, overlapping the envelope, the edited line
		// excluded (`id <> 0` excludes nothing).
		// Holds: not expired, not the requester's own token ('' matches no row).
		// Blocks: the property, the room, its room type or its floor.
		$sql = "SELECT br.room_id, br.start_at_gmt AS s, br.occupied_until_gmt AS e, 'line' AS kind, br.booking_id AS ref_id, COALESCE( bk.reference, '' ) AS label
			FROM %i br LEFT JOIN %i bk ON bk.id = br.booking_id
			WHERE br.room_id IN ( $rooms ) AND br.status IN ( $statuses ) AND br.id <> %d
				AND br.start_at_gmt < %s AND br.occupied_until_gmt > %s
			UNION ALL
			SELECT h.room_id, h.start_at_gmt, h.end_at_gmt, 'hold', h.id, ''
			FROM %i h
			WHERE h.room_id IN ( $rooms ) AND h.expires_at_gmt > %s AND h.token <> %s
				AND h.start_at_gmt < %s AND h.end_at_gmt > %s
			UNION ALL
			SELECT r.id, b.start_at_gmt, b.end_at_gmt, 'block', b.id, b.reason
			FROM %i b INNER JOIN %i r ON (
					b.scope = 'property'
					OR ( b.scope = 'room' AND b.scope_id = r.id )
					OR ( b.scope = 'room_type' AND b.scope_id = r.room_type_id )
					OR ( b.scope = 'floor' AND b.scope_id = r.floor_id )
				)
			WHERE r.id IN ( $rooms ) AND b.start_at_gmt < %s AND b.end_at_gmt > %s";

		$args = array_merge(
			array( rtbp_table( 'booking_rooms' ), rtbp_table( 'bookings' ) ),
			$room_ids,
			self::OCCUPYING,
			array( $exclude_line, $to_gmt, $from_gmt, rtbp_table( 'holds' ) ),
			$room_ids,
			array( $now_gmt, $hold_token, $to_gmt, $from_gmt, rtbp_table( 'blocks' ), rtbp_table( 'rooms' ) ),
			$room_ids,
			array( $to_gmt, $from_gmt )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $rooms / $statuses are only "%d,…" / "%s,…" placeholder lists; every value is bound. Live inventory: never cached.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		// phpcs:enable
		// A failed read must never look like "nothing booked": that would let
		// the write path book over existing stays (critical review, M08).
		if ( '' !== (string) $wpdb->last_error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new \RadiusTheme\RadiusHotelBooking\Exceptions\DomainException( 'busy', __( 'Availability could not be checked right now. Please try again.', 'radius-hotel-booking' ), 503 );
		}

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['room_id'] ][] = array(
				's'      => (string) $row['s'],
				'e'      => (string) $row['e'],
				'kind'   => (string) $row['kind'],
				'ref_id' => (int) $row['ref_id'],
				'label'  => (string) $row['label'],
			);
		}
		foreach ( $out as &$intervals ) {
			usort( $intervals, static fn( $a, $b ) => strcmp( $a['s'], $b['s'] ) );
		}
		unset( $intervals );

		return $out;
	}

	/**
	 * What stops each requested room from taking its window — the check the
	 * locked write path repeats inside the lock (§7.1 step 2). Room state is
	 * the caller's (it reads it with the lock).
	 *
	 * @param array<int, array> $requests       `{ room_id, start_gmt, end_gmt, buffer_minutes }`.
	 * @param string            $hold_token     The requester's hold token.
	 * @param int               $exclude_line   A line being edited.
	 * @param string            $now_gmt        "Now"; empty = the current time.
	 * @return array<int, array> Request index => `{ reason, interval }`, only for conflicting requests.
	 */
	public function conflicts( array $requests, string $hold_token = '', int $exclude_line = 0, string $now_gmt = '' ): array {
		if ( ! $requests ) {
			return array();
		}

		$room_ids = array();
		$from     = null;
		$to       = null;
		foreach ( $requests as $request ) {
			$buffer     = max( 0, (int) ( $request['buffer_minutes'] ?? 0 ) ) * MINUTE_IN_SECONDS;
			$room_ids[] = (int) $request['room_id'];
			$start      = Overlap::ts( (string) $request['start_gmt'] ) - $buffer;
			$end        = Overlap::ts( (string) $request['end_gmt'] ) + $buffer;
			$from       = null === $from ? $start : min( $from, $start );
			$to         = null === $to ? $end : max( $to, $end );
		}

		$busy = $this->busy( $room_ids, gmdate( 'Y-m-d H:i:s', $from ), gmdate( 'Y-m-d H:i:s', $to ), $hold_token, $exclude_line, $now_gmt );

		$out = array();
		foreach ( $requests as $index => $request ) {
			$conflict = Overlap::conflict(
				$busy[ (int) $request['room_id'] ] ?? array(),
				Overlap::ts( (string) $request['start_gmt'] ),
				Overlap::ts( (string) $request['end_gmt'] ),
				(int) ( $request['buffer_minutes'] ?? 0 )
			);
			if ( $conflict ) {
				$out[ $index ] = $conflict;
			}
		}
		return $out;
	}

	/**
	 * Lock live rooms until the transaction ends, in ascending id order so two
	 * writers can never deadlock on each other (booking-engine §7.1 step 1).
	 * Must run inside `Transaction::run()`.
	 *
	 * @param int[] $room_ids Room ids.
	 * @return array<int, array> Room id => `{ id, room_type_id, floor_id, number, state, floor_name }`, live rooms only.
	 * @throws \RadiusTheme\RadiusHotelBooking\Exceptions\DomainException 503 when the lock cannot be taken.
	 */
	public function lockRooms( array $room_ids ): array {
		global $wpdb;
		$room_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_ids ) ) ) );
		sort( $room_ids );
		if ( ! $room_ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $room_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%d,…"; a row lock.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, room_type_id, floor_id, number, state FROM %i WHERE id IN ( $placeholders ) AND deleted_at IS NULL ORDER BY id FOR UPDATE",
				array_merge( array( rtbp_table( 'rooms' ) ), $room_ids )
			),
			ARRAY_A
		);
		// phpcs:enable
		if ( '' !== (string) $wpdb->last_error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new \RadiusTheme\RadiusHotelBooking\Exceptions\DomainException( 'busy', __( 'Someone else is booking this room right now. Please try again.', 'radius-hotel-booking' ), 503 );
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = $row;
		}
		return $out;
	}

	/**
	 * The cleaning buffer of each room type: its own `buffer_minutes`, or the
	 * `booking.bufferMinutes` setting when it has none (D4).
	 *
	 * @param int[] $room_type_ids Room type ids.
	 * @return array<int, int> Type id => minutes.
	 */
	public function buffers( array $room_type_ids ): array {
		global $wpdb;
		$room_type_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_type_ids ) ) ) );
		if ( ! $room_type_ids ) {
			return array();
		}
		$default      = max( 0, (int) rtbp_setting( 'booking', 'bufferMinutes', 0 ) );
		$placeholders = implode( ',', array_fill( 0, count( $room_type_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $placeholders is only "%d,…".
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, buffer_minutes FROM %i WHERE id IN ( $placeholders )",
				array_merge( array( rtbp_table( 'room_types' ) ), $room_type_ids )
			)
		);
		// phpcs:enable
		$out = array();
		foreach ( $room_type_ids as $id ) {
			$out[ $id ] = $default;
		}
		foreach ( (array) $rows as $row ) {
			if ( null !== $row->buffer_minutes ) {
				$out[ (int) $row->id ] = max( 0, (int) $row->buffer_minutes );
			}
		}
		return $out;
	}
}
