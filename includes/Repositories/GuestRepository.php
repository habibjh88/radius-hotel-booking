<?php
/**
 * Guest data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\Guest;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `guests`. Removed (soft-deleted) guests are never returned.
 */
class GuestRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = Guest::class;

	/**
	 * Live guests that are the same person as the given contact details: the
	 * same E.164 phone, the same real e-mail, or — when either number could
	 * not be placed in E.164 — the same last 8 digits.
	 *
	 * @param string|null $e164      E.164 phone.
	 * @param string      $tail      Last 8 digits.
	 * @param string|null $email_key Lower-cased real e-mail.
	 * @param int         $except_id A guest being edited.
	 * @return array[] Rows `{ id, reference, first_name, last_name, phone, email, match }`
	 *                 (`match` = phone | email).
	 */
	public function duplicates( ?string $e164, string $tail, ?string $email_key, int $except_id = 0 ): array {
		global $wpdb;
		$table = rtbp_table( 'guests' );
		$out   = array();
		if ( null !== $e164 || '' !== $tail ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- duplicate check, indexed.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, reference, first_name, last_name, phone, email FROM %i
					WHERE deleted_at IS NULL AND id <> %d
						AND ( ( %s <> '' AND phone_e164 = %s )
							OR ( %s <> '' AND phone_tail = %s AND ( phone_e164 IS NULL OR %s = '' ) ) )
					ORDER BY id LIMIT 5",
					$table,
					$except_id,
					(string) $e164,
					(string) $e164,
					$tail,
					$tail,
					(string) $e164
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				$out[ (int) $row['id'] ] = $row + array( 'match' => 'phone' );
			}
		}
		if ( null !== $email_key && '' !== $email_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- duplicate check, unique key.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, reference, first_name, last_name, phone, email FROM %i WHERE deleted_at IS NULL AND id <> %d AND email_key = %s LIMIT 1', $table, $except_id, $email_key ), ARRAY_A );
			if ( $row && ! isset( $out[ (int) $row['id'] ] ) ) {
				$out[ (int) $row['id'] ] = $row + array( 'match' => 'email' );
			}
		}
		return array_values( $out );
	}

	/**
	 * A page of guests.
	 *
	 * @param array $filters `{ q?: search terms (see terms()), standing?: normal|banned }`.
	 * @param int   $page    Page (1-based).
	 * @param int   $per     Rows per page.
	 * @return array{items: array[], total: int}
	 */
	public function page( array $filters, int $page, int $per ): array {
		global $wpdb;
		list( $where, $args ) = $this->filterSql( $filters );
		$table                = rtbp_table( 'guests' );
		// Recently seen first; guests who never stayed by newest record.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where holds only fixed conditions with placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $where", array_merge( array( $table ), $args ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE $where ORDER BY last_stay_at IS NULL, last_stay_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( array( $table ), $args, array( $per, ( max( 1, $page ) - 1 ) * $per ) )
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
	 * The quick lookup of the booking forms: an exact phone first, then names
	 * and e-mails, at most `$limit`.
	 *
	 * @param array $terms See terms().
	 * @param int   $limit Most rows.
	 * @return array[] Rows.
	 */
	public function lookup( array $terms, int $limit ): array {
		global $wpdb;
		list( $where, $args ) = $this->filterSql( array( 'q' => $terms ) );
		$tail                 = $terms['tail'] ?? '';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where holds only fixed conditions with placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE $where ORDER BY ( %s <> '' AND phone_tail = %s ) DESC, name_search, id LIMIT %d",
				array_merge( array( rtbp_table( 'guests' ) ), $args, array( $tail, $tail, $limit ) )
			),
			ARRAY_A
		);
		// phpcs:enable
		return (array) $rows;
	}

	/**
	 * A guest's bookings, newest arrival first, each with its lines (9.4).
	 * Filled once bookings are written (M02); cancelled ones included.
	 *
	 * @param int $guest_id Guest id.
	 * @param int $limit    Most bookings.
	 * @return array[] Booking rows with `lines`.
	 */
	public function stays( int $guest_id, int $limit = 100 ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the guest's history, index guest_id.
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.id, b.reference, b.status, b.payment_status, b.total, b.balance_due, b.source,
					MIN( br.start_at ) AS arrival, MAX( br.end_at ) AS departure
				FROM %i b LEFT JOIN %i br ON br.booking_id = b.id
				WHERE b.guest_id = %d AND b.deleted_at IS NULL
				GROUP BY b.id
				ORDER BY arrival IS NULL, arrival DESC, b.id DESC
				LIMIT %d',
				rtbp_table( 'bookings' ),
				rtbp_table( 'booking_rooms' ),
				$guest_id,
				$limit
			),
			ARRAY_A
		);
		if ( ! $bookings ) {
			return array();
		}
		$ids          = array_map( static fn( $row ) => (int) $row['id'], $bookings );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is only "%d,…".
		$lines = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, booking_id, room_number, rate_plan_name, start_at, end_at, status, total FROM %i WHERE booking_id IN ( $placeholders ) ORDER BY start_at, id",
				array_merge( array( rtbp_table( 'booking_rooms' ) ), $ids )
			),
			ARRAY_A
		);
		// phpcs:enable
		$by = array();
		foreach ( (array) $lines as $line ) {
			$by[ (int) $line['booking_id'] ][] = $line;
		}
		foreach ( $bookings as &$booking ) {
			$booking['lines'] = $by[ (int) $booking['id'] ] ?? array();
		}
		unset( $booking );
		return $bookings;
	}

	/**
	 * Lock a guest row until the transaction ends.
	 *
	 * @param int $id Guest id.
	 * @return bool Whether it exists (and is live).
	 */
	public function lock( int $id ): bool {
		return $this->lockRow( 'guests', $id, true );
	}

	/**
	 * The WHERE clause for the list and the lookup.
	 *
	 * @param array $filters `{ q?: { name, digits, tail, email }, standing? }`.
	 * @return array{0: string, 1: array} SQL (placeholders only) and bindings.
	 */
	private function filterSql( array $filters ): array {
		global $wpdb;
		$where = array( 'deleted_at IS NULL' );
		$args  = array();
		if ( ! empty( $filters['standing'] ) ) {
			$where[] = 'standing = %s';
			$args[]  = (string) $filters['standing'];
		}
		$q = $filters['q'] ?? null;
		if ( is_array( $q ) && ( '' !== $q['name'] || '' !== $q['digits'] ) ) {
			$any = array();
			if ( '' !== $q['name'] ) {
				// Every word must appear: "kone awa" finds "Awa Koné".
				$words = array();
				foreach ( explode( ' ', $q['name'] ) as $word ) {
					$words[] = 'name_search LIKE %s';
					$args[]  = '%' . $wpdb->esc_like( $word ) . '%';
				}
				$any[] = '( ' . implode( ' AND ', $words ) . ' )';
				// An e-mail starts with what was typed.
				$any[]  = 'email_key LIKE %s';
				$args[] = $wpdb->esc_like( $q['email'] ) . '%';
				$any[]  = 'reference = %s';
				$args[] = strtoupper( $q['email'] );
			}
			if ( '' !== $q['digits'] ) {
				// Digits-only phone matching (legacy): the tail, or part of the number.
				if ( '' !== $q['tail'] ) {
					$any[]  = 'phone_tail = %s';
					$args[] = $q['tail'];
				}
				$any[]  = 'phone_e164 LIKE %s';
				$args[] = '%' . $wpdb->esc_like( $q['digits'] ) . '%';
			}
			$where[] = '( ' . implode( ' OR ', $any ) . ' )';
		}
		return array( implode( ' AND ', $where ), $args );
	}
}
