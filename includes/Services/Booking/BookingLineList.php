<?php
/**
 * The front desk booking list, one row per booked room (M01, 1.5–1.9).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Resources\BookingResource;
use RadiusTheme\RadiusHotelBooking\Services\Dashboard\DashboardCounters;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the list behind the Bookings screen:
 *
 * - **Tabs** (1.6): `all`, `awaiting` (pending rooms of pending bookings),
 *   `arriving` / `leaving` (stay starting / ending today, site time, from 00:00
 *   included — the counters' rule), `in_house` (checked in), `overdue`
 *   (the M05 rule: money due, deadline passed). Every tab's count comes back
 *   with the page, under the same date range and search.
 * - **Date range** (1.7, 1.8): one continuous span `from` – `to` in site time
 *   (`Y-m-d` or `Y-m-d H:i`; a bare `to` date runs to the end of that day),
 *   meaning the stay's start (`mode=arrival`, legacy `checkin` / `stay`) or the
 *   booking's creation (`mode=created`).
 * - **Search** (1.9): a leading `#` is dropped; the booking reference (part of
 *   it), the room number, or the guest's name / phone by the guest list's own
 *   rules (`GuestService::terms()`).
 * - Pages of 20, at most 100 (legacy). Two queries: the counts, then the page.
 *
 * Counts are of booked rooms (lines). The dashboard's *Awaiting approval* and
 * *Payment overdue* count bookings, so a booking of two rooms shows 1 there
 * and 2 here.
 */
class BookingLineList {

	/**
	 * Tabs, in display order.
	 */
	public const TABS = array( 'all', 'awaiting', 'arriving', 'leaving', 'in_house', 'overdue' );

	/**
	 * Date modes.
	 */
	public const MODES = array( 'arrival', 'created' );

	/**
	 * Default and largest page size.
	 */
	public const PER_PAGE = 20;

	/**
	 * Status moves offered on a row (the booking screen has the rest).
	 */
	public const ROW_MOVES = array( 'approve', 'decline', 'cancel', 'check_in', 'check_out' );
	public const MAX_PER_PAGE = 100;

	/**
	 * A page of the list with every tab's count.
	 *
	 * @param array                  $params `{ tab?, from?, to?, mode?, q?, page?, per_page? }`.
	 * @param DateTimeImmutable|null $now    Now.
	 * @return array{rows: array[], counts: array<string, int>, total: int, page: int, per_page: int}
	 * @throws DomainException 422 for a bad tab, mode or date.
	 */
	public function search( array $params, ?DateTimeImmutable $now = null ): array {
		global $wpdb;
		$now      = $now ?? Dates::now();
		$filters  = self::validate( $params );
		$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
		$per_page = max( 1, min( self::MAX_PER_PAGE, (int) ( $params['per_page'] ?? self::PER_PAGE ) ) );

		list( $where, $args ) = self::filterSql( $filters );
		$tabs                 = self::tabSql( $now );

		// 1. Every tab's count under the same filters.
		$cases = array();
		$cargs = array();
		foreach ( $tabs as $tab => $sql ) {
			$cases[] = "COALESCE( SUM( CASE WHEN {$sql[0]} THEN 1 ELSE 0 END ), 0 ) AS `{$tab}`";
			$cargs   = array_merge( $cargs, $sql[1] );
		}
		$from = self::fromSql();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the fragments hold placeholders only; every value is bound. A live list.
		$counts = (array) $wpdb->get_row(
			$wpdb->prepare( 'SELECT ' . implode( ', ', $cases ) . " FROM {$from[0]} WHERE {$where}", array_merge( $cargs, $from[1], $args ) ),
			ARRAY_A
		);
		$counts = array_map( 'intval', array_merge( array_fill_keys( self::TABS, 0 ), $counts ) );

		// 2. The page.
		$tab   = $filters['tab'];
		$rows  = array();
		$total = $counts[ $tab ];
		if ( $total > 0 ) {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.id, r.booking_id, r.status, r.room_number, r.floor_name, r.rate_plan_name, r.start_at_gmt, r.end_at_gmt,
						r.units, r.adults, r.children, r.total,
						b.reference, b.status AS booking_status, b.payment_status, b.balance_due, b.payment_due_at_gmt, b.on_hold, b.source, b.created_at_gmt,
						COALESCE( t.name, '' ) AS room_type,
						TRIM( CONCAT( COALESCE( g.first_name, '' ), ' ', COALESCE( g.last_name, '' ) ) ) AS guest_name, COALESCE( g.phone, '' ) AS guest_phone,
						( {$tabs['overdue'][0]} ) AS is_overdue
					FROM {$from[0]} LEFT JOIN %i t ON t.id = r.room_type_id
					WHERE {$where} AND ( {$tabs[ $tab ][0]} )
					ORDER BY " . self::order( $tab, $filters['mode'], $filters['has_range'] ) . '
					LIMIT %d OFFSET %d',
					array_merge( $tabs['overdue'][1], $from[1], array( rtbp_table( 'room_types' ) ), $args, $tabs[ $tab ][1], array( $per_page, ( $page - 1 ) * $per_page ) )
				),
				ARRAY_A
			);
		}
		// phpcs:enable
		if ( '' !== (string) $wpdb->last_error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'list_failed', __( 'The bookings could not be loaded. Please try again.', 'radius-hotel-booking' ), 503 );
		}

		return array(
			'rows'     => array_map( static fn( $row ) => self::row( $row, $now ), $rows ),
			'counts'   => $counts,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Check and normalise the filters.
	 *
	 * @param array $params Request parameters.
	 * @return array{tab: string, mode: string, from: ?string, to: ?string, q: string, has_range: bool}
	 * @throws DomainException 422.
	 */
	public static function validate( array $params ): array {
		$errors = array();
		$tab    = sanitize_key( (string) ( $params['tab'] ?? 'all' ) );
		$tab    = '' === $tab ? 'all' : $tab;
		if ( ! in_array( $tab, self::TABS, true ) ) {
			$errors['tab'] = __( 'Unknown list tab.', 'radius-hotel-booking' );
		}
		$mode = sanitize_key( (string) ( $params['mode'] ?? 'arrival' ) );
		// Legacy values: `checkin` and `stay` meant the arrival.
		$mode = in_array( $mode, array( '', 'checkin', 'stay' ), true ) ? 'arrival' : $mode;
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$errors['mode'] = __( 'Choose arrival or created.', 'radius-hotel-booking' );
		}
		$from = self::parseDate( (string) ( $params['from'] ?? '' ), false );
		$to   = self::parseDate( (string) ( $params['to'] ?? '' ), true );
		if ( false === $from ) {
			$errors['from'] = __( 'Use a date such as 2026-10-01 or 2026-10-01 08:30.', 'radius-hotel-booking' );
		}
		if ( false === $to ) {
			$errors['to'] = __( 'Use a date such as 2026-10-01 or 2026-10-01 08:30.', 'radius-hotel-booking' );
		}
		if ( is_string( $from ) && is_string( $to ) && '' !== $from && '' !== $to && $to <= $from ) {
			$errors['to'] = __( 'The end must come after the start.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		$q = trim( wp_strip_all_tags( (string) ( $params['q'] ?? '' ) ) );
		$q = ltrim( $q, '#' );
		return array(
			'tab'       => $tab,
			'mode'      => $mode,
			'from'      => $from ? $from : null,
			'to'        => $to ? $to : null,
			'q'         => mb_substr( trim( $q ), 0, 100 ),
			'has_range' => (bool) ( $from || $to ),
		);
	}

	/**
	 * A site-time date or date-time as GMT `Y-m-d H:i:s`; '' when empty, false when malformed.
	 *
	 * @param string $value  `Y-m-d` or `Y-m-d H:i`.
	 * @param bool   $is_end A bare date ends at the next midnight; a time at the end of that minute.
	 * @return string|false
	 */
	private static function parseDate( string $value, bool $is_end ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$tz = Dates::timezone();
		foreach ( array(
			'!Y-m-d H:i' => '+1 minute',
			'!Y-m-d' => '+1 day',
		) as $format => $end_step ) {
			$at = DateTimeImmutable::createFromFormat( $format, $value, $tz );
			if ( $at && $at->format( substr( $format, 1 ) ) === $value ) {
				return Dates::to_gmt_db( $is_end ? $at->modify( $end_step ) : $at );
			}
		}
		return false;
	}

	/**
	 * The joined tables (lines, their booking and guest).
	 *
	 * @return array{0: string, 1: array}
	 */
	private static function fromSql(): array {
		return array(
			'%i r INNER JOIN %i b ON b.id = r.booking_id AND b.deleted_at IS NULL LEFT JOIN %i g ON g.id = b.guest_id',
			array( rtbp_table( 'booking_rooms' ), rtbp_table( 'bookings' ), rtbp_table( 'guests' ) ),
		);
	}

	/**
	 * The shared WHERE: date range and search.
	 *
	 * @param array $filters Validated filters.
	 * @return array{0: string, 1: array}
	 */
	private static function filterSql( array $filters ): array {
		global $wpdb;
		$where  = array( '1 = 1' );
		$args   = array();
		$column = 'created' === $filters['mode'] ? 'b.created_at_gmt' : 'r.start_at_gmt';
		if ( $filters['from'] ) {
			$where[] = "{$column} >= %s";
			$args[]  = $filters['from'];
		}
		if ( $filters['to'] ) {
			$where[] = "{$column} < %s";
			$args[]  = $filters['to'];
		}
		$q = $filters['q'];
		if ( '' !== $q ) {
			$terms = GuestService::terms( $q );
			$any   = array( 'b.reference LIKE %s', 'r.room_number = %s' );
			$args  = array_merge( $args, array( '%' . $wpdb->esc_like( strtoupper( $q ) ) . '%', $q ) );
			if ( '' !== $terms['name'] ) {
				// Every word must appear, as in the guest list.
				$words = array();
				foreach ( explode( ' ', $terms['name'] ) as $word ) {
					$words[] = 'g.name_search LIKE %s';
					$args[]  = '%' . $wpdb->esc_like( $word ) . '%';
				}
				$any[] = '( ' . implode( ' AND ', $words ) . ' )';
			}
			if ( '' !== $terms['digits'] ) {
				if ( '' !== $terms['tail'] ) {
					$any[]  = 'g.phone_tail = %s';
					$args[] = $terms['tail'];
				}
				$any[]  = 'g.phone_e164 LIKE %s';
				$args[] = '%' . $wpdb->esc_like( $terms['digits'] ) . '%';
			}
			$where[] = '( ' . implode( ' OR ', $any ) . ' )';
		}
		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * Each tab's condition at `$now`.
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return array<string, array{0: string, 1: array}>
	 */
	private static function tabSql( DateTimeImmutable $now ): array {
		list( $today, $tomorrow ) = DashboardCounters::day( $now );
		$day                      = "r.status IN ('" . implode( "','", DashboardCounters::DAY_STATUSES ) . "')";
		return array(
			'all'      => array( '1 = 1', array() ),
			'awaiting' => array( "r.status = 'pending' AND b.status = 'pending'", array() ),
			'arriving' => array( "{$day} AND r.start_at_gmt >= %s AND r.start_at_gmt < %s", array( $today, $tomorrow ) ),
			'leaving'  => array( "{$day} AND r.end_at_gmt >= %s AND r.end_at_gmt < %s", array( $today, $tomorrow ) ),
			'in_house' => array( "r.status = 'checked_in'", array() ),
			'overdue'  => array(
				"b.status IN ('pending','confirmed') AND b.payment_status IN ('unpaid','partially_paid') AND b.balance_due > 0 AND b.payment_due_at_gmt IS NOT NULL AND b.payment_due_at_gmt < %s",
				array( Dates::to_gmt_db( $now ) ),
			),
		);
	}

	/**
	 * Row order per tab: what the desk handles next comes first.
	 *
	 * @param string $tab       Tab.
	 * @param string $mode      Date mode.
	 * @param bool   $has_range A date range is set.
	 * @return string
	 */
	private static function order( string $tab, string $mode, bool $has_range ): string {
		switch ( $tab ) {
			case 'arriving':
				return 'r.start_at_gmt ASC, r.id ASC';
			case 'leaving':
			case 'in_house':
				return 'r.end_at_gmt ASC, r.id ASC';
			case 'awaiting':
				return 'b.created_at_gmt ASC, r.id ASC';
			case 'overdue':
				return 'b.payment_due_at_gmt ASC, r.id ASC';
		}
		// All: a range reads in its own order; otherwise the newest bookings first.
		if ( $has_range ) {
			return 'created' === $mode ? 'b.created_at_gmt ASC, r.id ASC' : 'r.start_at_gmt ASC, r.id ASC';
		}
		return 'b.created_at_gmt DESC, r.id DESC';
	}

	/**
	 * One list row, with the moves the desk can make on it (1.10): the room's
	 * own status moves (the booking screen's rule, `BookingResource::lineActions()`)
	 * and *record a payment* while money is due on a booking still going ahead.
	 *
	 * @param array             $row Database row.
	 * @param DateTimeImmutable $now Now.
	 * @return array
	 */
	public static function row( array $row, DateTimeImmutable $now ): array {
		$moves   = BookingResource::lineActions( (string) $row['status'], (string) $row['start_at_gmt'], $now->getTimestamp(), $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' ) );
		$actions = array_values( array_intersect( $moves, self::ROW_MOVES ) );
		if ( (float) $row['balance_due'] > 0 && ! in_array( (string) $row['booking_status'], array( 'cancelled', 'declined', 'no_show' ), true ) ) {
			$actions[] = 'record_payment';
		}
		return array(
			'id'             => (int) $row['id'],
			'booking_id'     => (int) $row['booking_id'],
			'reference'      => (string) $row['reference'],
			'guest'          => array(
				'name'  => (string) $row['guest_name'],
				'phone' => (string) $row['guest_phone'],
			),
			'room_type'      => (string) $row['room_type'],
			'rate_plan'      => (string) $row['rate_plan_name'],
			'floor'          => (string) $row['floor_name'],
			'room'           => (string) $row['room_number'],
			'start'          => Dates::to_iso( Dates::from_gmt( (string) $row['start_at_gmt'] ) ),
			'end'            => Dates::to_iso( Dates::from_gmt( (string) $row['end_at_gmt'] ) ),
			'units'          => (int) $row['units'],
			'adults'         => (int) $row['adults'],
			'children'       => (int) $row['children'],
			'total'          => Money::round( (float) $row['total'] ),
			'status'         => (string) $row['status'],
			'booking_status' => (string) $row['booking_status'],
			'payment_status' => (string) $row['payment_status'],
			'balance_due'    => Money::round( (float) $row['balance_due'] ),
			'on_hold'        => (bool) $row['on_hold'],
			'overdue'        => (bool) $row['is_overdue'],
			'source'         => (string) $row['source'],
			'created_at'     => Dates::to_iso( Dates::from_gmt( (string) $row['created_at_gmt'] ) ),
			'actions'        => $actions,
		);
	}
}
