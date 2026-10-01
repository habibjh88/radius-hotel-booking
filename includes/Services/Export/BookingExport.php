<?php
/**
 * The booking export (M11, 11.1).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Export
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Export;

use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportRange;
use RadiusTheme\RadiusHotelBooking\Services\Reports\ReportService;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * One row per booked room (booking line), carrying the whole booking, its
 * guest, the line and the payment summary — every line status, since this is
 * the data backup.
 *
 * **Columns:** the legacy "Booking Backup" set first, in its order and with
 * its names (`class-booking-backup.php:299-329`), so the client's
 * spreadsheets keep working — WooCommerce notions mapped to ours (*Order ID* →
 * the booking reference, *Order Status* → the payment status, *Shipping
 * Total* → 0, *Billing Address* → empty: guests have no address) — then the
 * new columns. Numbers are numbers (the legacy typed them as text).
 *
 * Read in chunks of 500 lines by id — no row limit (the legacy stopped
 * silently at 20,000).
 */
class BookingExport {

	/**
	 * Lines per read.
	 */
	public const CHUNK = 500;

	/**
	 * Header labels.
	 *
	 * @return string[]
	 */
	public static function columns(): array {
		return array(
			// The legacy set, in order.
			__( 'Order ID', 'radius-hotel-booking' ),
			__( 'Order Status', 'radius-hotel-booking' ),
			__( 'Order Date Created', 'radius-hotel-booking' ),
			__( 'Payment Method', 'radius-hotel-booking' ),
			__( 'Currency', 'radius-hotel-booking' ),
			__( 'Order Total', 'radius-hotel-booking' ),
			__( 'Order Total Tax', 'radius-hotel-booking' ),
			__( 'Shipping Total', 'radius-hotel-booking' ),
			__( 'Discount Total', 'radius-hotel-booking' ),
			__( 'Customer First Name', 'radius-hotel-booking' ),
			__( 'Customer Last Name', 'radius-hotel-booking' ),
			__( 'Customer Email', 'radius-hotel-booking' ),
			__( 'Customer Phone', 'radius-hotel-booking' ),
			__( 'Billing Address', 'radius-hotel-booking' ),
			__( 'ID Type', 'radius-hotel-booking' ),
			__( 'ID Number', 'radius-hotel-booking' ),
			__( 'Customer Username', 'radius-hotel-booking' ),
			__( 'Room Type', 'radius-hotel-booking' ),
			__( 'Room Number', 'radius-hotel-booking' ),
			__( 'Floor', 'radius-hotel-booking' ),
			__( 'Rate Plan', 'radius-hotel-booking' ),
			__( 'Check-in', 'radius-hotel-booking' ),
			__( 'Check-out', 'radius-hotel-booking' ),
			__( 'Nights', 'radius-hotel-booking' ),
			__( 'Booking Status', 'radius-hotel-booking' ),
			__( 'Line Item', 'radius-hotel-booking' ),
			__( 'Qty', 'radius-hotel-booking' ),
			__( 'Line Subtotal', 'radius-hotel-booking' ),
			__( 'Line Total', 'radius-hotel-booking' ),
			__( 'Line Details', 'radius-hotel-booking' ),
			__( 'Customer Note', 'radius-hotel-booking' ),
			// New.
			__( 'Booking Source', 'radius-hotel-booking' ),
			__( 'Adults', 'radius-hotel-booking' ),
			__( 'Children', 'radius-hotel-booking' ),
			__( 'Amount Paid', 'radius-hotel-booking' ),
			__( 'Balance Due', 'radius-hotel-booking' ),
			__( 'Guest Reference', 'radius-hotel-booking' ),
			__( 'Taken By', 'radius-hotel-booking' ),
			__( 'Cancellation Reason', 'radius-hotel-booking' ),
			__( 'Booking ID', 'radius-hotel-booking' ),
			__( 'Line ID', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Every row of the period, a chunk at a time.
	 *
	 * @param ReportRange $range Period (`arrival`: the line's start; `created`: the booking's creation).
	 * @return \Generator<array> Rows in column order.
	 * @throws \RuntimeException When a read fails.
	 */
	public function rows( ReportRange $range ): \Generator {
		$rate  = max( 0.0, (float) rtbp_setting( 'invoices', 'taxRate', 0 ) );
		$types = GuestService::idTypes();
		$users = array();
		$after = 0;
		do {
			$lines = $this->chunk( $range, $after );
			$read  = count( $lines );
			if ( ! $read ) {
				break;
			}
			$methods = $this->methods( array_values( array_unique( array_map( static fn( $line ) => (int) $line['booking_id'], $lines ) ) ) );
			foreach ( $lines as $line ) {
				$after = (int) $line['line_id'];
				yield $this->row( $line, $methods, $types, $rate, $users );
			}
		} while ( self::CHUNK === $read );
	}

	/**
	 * One row.
	 *
	 * @param array $line    Line + booking + guest.
	 * @param array $methods Booking id => latest payment method key.
	 * @param array $types   ID type key => label.
	 * @param float $rate    Tax rate (%).
	 * @param array $users   User id => names (cache, filled here).
	 * @return array
	 */
	private function row( array $line, array $methods, array $types, float $rate, array &$users ): array {
		$user = static function ( $id ) use ( &$users ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				return array( '', '' );
			}
			if ( ! isset( $users[ $id ] ) ) {
				$account      = get_userdata( $id );
				$users[ $id ] = $account ? array( (string) $account->user_login, (string) $account->display_name ) : array( '', '' );
			}
			return $users[ $id ];
		};
		$total  = (float) $line['booking_total'];
		$method = $methods[ (int) $line['booking_id'] ] ?? '';
		$units  = max( 1, (int) $line['units'] );

		return array(
			(string) $line['reference'],
			ReportService::statusLabel( (string) $line['payment_status'] ),
			substr( (string) $line['booking_created'], 0, 16 ),
			'' !== $method ? PaymentSettings::method_label( $method ) : '',
			(string) $line['currency'],
			Money::round( $total ),
			$rate > 0 ? Money::round( $total - $total / ( 1 + $rate / 100 ) ) : 0.0,
			0,
			Money::round( (float) $line['discount_total'] ),
			(string) $line['first_name'],
			(string) $line['last_name'],
			(int) $line['email_is_placeholder'] ? '' : (string) $line['email'],
			(string) $line['phone'],
			'',
			(string) ( $types[ (string) $line['id_type'] ] ?? $line['id_type'] ),
			(string) $line['id_number'],
			$user( $line['wp_user_id'] )[0],
			(string) $line['room_type'],
			(string) $line['room_number'],
			(string) $line['floor_name'],
			(string) $line['rate_plan_name'],
			substr( (string) $line['start_at'], 0, 16 ),
			substr( (string) $line['end_at'], 0, 16 ),
			$units,
			ReportService::statusLabel( (string) $line['status'] ),
			trim( $line['room_type'] . ' · ' . $line['rate_plan_name'], ' ·' ),
			1,
			Money::round( (float) $line['unit_price'] * $units ),
			Money::round( (float) $line['line_total'] ),
			sprintf(
				/* translators: 1: adults, 2: children. */
				__( 'Adults: %1$d, children: %2$d', 'radius-hotel-booking' ),
				(int) $line['adults'],
				(int) $line['children']
			),
			(string) $line['special_requests'],
			(string) $line['source'],
			(int) $line['adults'],
			(int) $line['children'],
			Money::round( (float) $line['paid_total'] ),
			Money::round( (float) $line['balance_due'] ),
			(string) $line['guest_reference'],
			$user( $line['created_by'] )[1],
			(string) $line['cancelled_reason'],
			(int) $line['booking_id'],
			(int) $line['line_id'],
		);
	}

	/**
	 * The next lines of the period after a line id.
	 *
	 * @param ReportRange $range Period.
	 * @param int         $after Last line id read.
	 * @return array[]
	 * @throws \RuntimeException When the read fails.
	 */
	private function chunk( ReportRange $range, int $after ): array {
		global $wpdb;
		$period = 'created' === $range->mode
			? $wpdb->prepare( 'b.created_at_gmt >= %s AND b.created_at_gmt < %s', $range->from_gmt, $range->to_gmt )
			: $wpdb->prepare( 'br.start_at_gmt >= %s AND br.start_at_gmt < %s', $range->from_gmt, $range->to_gmt );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $period is prepared above; an export reads each row once.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT br.id AS line_id, br.booking_id, br.room_number, br.floor_name, br.rate_plan_name, br.start_at, br.end_at,
					br.units, br.adults, br.children, br.status, br.unit_price, br.total AS line_total,
					b.reference, b.payment_status, b.currency, b.total AS booking_total, b.discount_total, b.paid_total, b.balance_due,
					b.special_requests, b.source, b.created_at AS booking_created, b.created_by, b.cancelled_reason,
					COALESCE( t.name, '' ) AS room_type,
					COALESCE( g.reference, '' ) AS guest_reference, COALESCE( g.first_name, '' ) AS first_name, COALESCE( g.last_name, '' ) AS last_name,
					COALESCE( g.email, '' ) AS email, COALESCE( g.email_is_placeholder, 0 ) AS email_is_placeholder, COALESCE( g.phone, '' ) AS phone,
					COALESCE( g.id_type, '' ) AS id_type, COALESCE( g.id_number, '' ) AS id_number, g.wp_user_id
				FROM %i br
				JOIN %i b ON b.id = br.booking_id AND b.deleted_at IS NULL
				LEFT JOIN %i t ON t.id = br.room_type_id
				LEFT JOIN %i g ON g.id = b.guest_id
				WHERE {$period} AND br.id > %d
				ORDER BY br.id
				LIMIT %d",
				rtbp_table( 'booking_rooms' ),
				rtbp_table( 'bookings' ),
				rtbp_table( 'room_types' ),
				rtbp_table( 'guests' ),
				$after,
				self::CHUNK
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// A failed read must never look like "no more rows": the file would stop
		// early and still be stored (and archived bookings deleted).
		if ( null === $rows || '' !== (string) $wpdb->last_error ) {
			throw new \RuntimeException( 'Reading the bookings for the export failed: ' . esc_html( (string) $wpdb->last_error ) );
		}
		return (array) $rows;
	}

	/**
	 * Each booking's latest live payment's method (the Sales report's rule).
	 *
	 * @param int[] $booking_ids Bookings.
	 * @return array<int, string>
	 */
	private function methods( array $booking_ids ): array {
		global $wpdb;
		if ( ! $booking_ids ) {
			return array();
		}
		$ids = implode( ',', array_map( 'intval', $booking_ids ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $ids are integers; once per chunk.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.booking_id, SUBSTRING_INDEX( GROUP_CONCAT( p.method ORDER BY p.received_at_gmt DESC, p.id DESC SEPARATOR '|' ), '|', 1 ) AS method
				FROM %i p
				WHERE p.booking_id IN ( {$ids} ) AND p.type = 'payment'
					AND NOT EXISTS ( SELECT 1 FROM %i v WHERE v.voids_payment_id = p.id )
				GROUP BY p.booking_id",
				rtbp_table( 'payments' ),
				rtbp_table( 'payments' )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['booking_id'] ] = (string) $row['method'];
		}
		return $out;
	}
}
