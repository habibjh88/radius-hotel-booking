<?php
/**
 * Hold data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Rows of `holds` (feature 2.14). A token groups the holds of one booking in
 * progress (a booking may hold several rooms). Writes happen inside the
 * locked write path (`HoldService`), never on their own.
 */
class HoldRepository {

	/**
	 * Insert a hold.
	 *
	 * @param array $row Columns.
	 * @return int New id.
	 * @throws \RuntimeException When the insert fails.
	 */
	public function insert( array $row ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin table.
		$ok = $wpdb->insert(
			rtbp_table( 'holds' ),
			array_merge(
				$row,
				array(
					'created_at' => $now,
					'updated_at' => $now,
				)
			)
		);
		if ( ! $ok ) {
			throw new \RuntimeException( 'Could not save the hold: ' . esc_html( (string) $wpdb->last_error ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Live holds of one web visitor, by session or by network (the cap, M04).
	 *
	 * @param string $column  `session_key` or `client_key`.
	 * @param string $value   Its value.
	 * @param string $now_gmt Now (GMT).
	 * @return int
	 */
	public function countLive( string $column, string $value, string $now_gmt ): int {
		global $wpdb;
		if ( '' === $value || ! in_array( $column, array( 'session_key', 'client_key' ), true ) ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live inventory, indexed.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %s AND expires_at_gmt > %s', rtbp_table( 'holds' ), $column, $value, $now_gmt ) );
	}

	/**
	 * The holds of a token, expired ones included.
	 *
	 * @param string $token Token.
	 * @return array<int, array> Rows, oldest first.
	 */
	public function forToken( string $token ): array {
		global $wpdb;
		if ( '' === $token ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live inventory.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE token = %s ORDER BY id', rtbp_table( 'holds' ), $token ), ARRAY_A );
	}

	/**
	 * Move the expiry of every live hold of a token.
	 *
	 * @param string $token       Token.
	 * @param string $expires_gmt New expiry, GMT.
	 * @param string $now_gmt     Now, GMT: expired holds are not revived.
	 * @return int Live holds of the token after the update. Counted, not taken
	 *             from "rows affected": MySQL leaves out rows whose value did
	 *             not change (an extension in the same second).
	 */
	public function extend( string $token, string $expires_gmt, string $now_gmt ): int {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET expires_at_gmt = %s, updated_at = %s WHERE token = %s AND expires_at_gmt > %s', rtbp_table( 'holds' ), $expires_gmt, $now_gmt, $token, $now_gmt ) );
		$live = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE token = %s AND expires_at_gmt > %s', rtbp_table( 'holds' ), $token, $now_gmt ) );
		// phpcs:enable
		return (int) $live;
	}

	/**
	 * Delete the holds of a token (all, or one of them).
	 *
	 * @param string $token   Token.
	 * @param int    $hold_id One hold; 0 = all of the token.
	 * @return int Holds deleted.
	 */
	public function delete( string $token, int $hold_id = 0 ): int {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table.
		$deleted = $hold_id
			? $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE token = %s AND id = %d', rtbp_table( 'holds' ), $token, $hold_id ) )
			: $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE token = %s', rtbp_table( 'holds' ), $token ) );
		// phpcs:enable
		return (int) $deleted;
	}

	/**
	 * Delete holds that expired before a moment (the sweep).
	 *
	 * @param string $before_gmt GMT.
	 * @param int    $limit      Rows per call.
	 * @return int Holds deleted.
	 */
	public function deleteExpired( string $before_gmt, int $limit = 1000 ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin table.
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at_gmt <= %s ORDER BY id LIMIT %d', rtbp_table( 'holds' ), $before_gmt, max( 1, $limit ) ) );
	}
}
