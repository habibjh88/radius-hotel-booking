<?php
/**
 * What the guest sees about their booking (M05, 5.2, 5.7).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Payments
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Payments;

use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\PaymentRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingTotals;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * One source for the guest's view of a booking — the confirmation page, its
 * JSON for the booking flow (M04) and the e-mails: the status, the rooms,
 * the money, the deadline, the **payment instructions** of every method the
 * hotel offers (Settings → Payments, merge tags filled in: `{amount}` = the
 * balance, `{reference}` = the booking reference, `{deadline}`,
 * `{hotel_name}`), and the guest's links (the page, the invoice, receipts).
 *
 * The guest reaches all of it with the booking's **public token** (128 bits,
 * set at creation) — no account. Nothing staff-only is exposed: no notes,
 * no identity document, no one else's data.
 */
class GuestBookingView {

	/**
	 * The admin-post action of the guest's documents.
	 */
	public const DOCUMENT_ACTION = 'rtbp_guest_document';

	/**
	 * The query var of the confirmation page.
	 */
	public const QUERY_VAR = 'rtbp_booking';

	/**
	 * A booking from its public token (null when unknown or malformed).
	 *
	 * @param string $token Token.
	 * @return Booking|null
	 */
	public static function byToken( string $token ): ?Booking {
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$rows = Booking::query()->where( 'public_token', '=', $token )->limit( 1 )->get();
		return $rows ? Booking::hydrate( $rows[0] ) : null;
	}

	/**
	 * The guest's page for a booking.
	 *
	 * @param Booking $booking Booking.
	 * @return string
	 */
	public static function pageUrl( Booking $booking ): string {
		return add_query_arg( self::QUERY_VAR, (string) $booking->public_token, home_url( '/' ) );
	}

	/**
	 * A guest's document link (the current invoice, or one of their receipts).
	 *
	 * @param Booking $booking Booking.
	 * @param string  $type    invoice|receipt.
	 * @param int     $id      Payment id for a receipt.
	 * @param string  $format  Format.
	 * @return string
	 */
	public static function documentUrl( Booking $booking, string $type, int $id = 0, string $format = 'html' ): string {
		return add_query_arg(
			array_filter(
				array(
					'action' => self::DOCUMENT_ACTION,
					'token'  => (string) $booking->public_token,
					'doc'    => $type,
					'id'     => $id ? $id : null,
					'format' => $format,
				)
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The payment instructions of the methods offered, filled in for a booking.
	 *
	 * @param Booking $booking Booking.
	 * @return array[] `{ key, label, instructions, account }`.
	 */
	public static function instructions( Booking $booking ): array {
		$due  = max( 0, (float) $booking->balance_due );
		$tags = array(
			'{amount}'     => Money::format( $due ),
			'{reference}'  => (string) $booking->reference,
			'{deadline}'   => $booking->payment_due_at_gmt ? Dates::format( Dates::from_gmt( (string) $booking->payment_due_at_gmt ), 'datetime' ) : __( 'your arrival', 'radius-hotel-booking' ),
			'{hotel_name}' => (string) ( SettingsHelper::get_setting( 'general' )['companyName'] ?? get_bloginfo( 'name' ) ),
		);
		return array_map(
			static fn( $method ) => array(
				'key'          => (string) $method['key'],
				'label'        => (string) $method['label'],
				'instructions' => strtr( (string) $method['instructions'], $tags ),
				'account'      => (string) $method['account'],
			),
			PaymentSettings::enabled_methods()
		);
	}

	/**
	 * Everything the guest sees.
	 *
	 * @param Booking $booking Booking.
	 * @return array
	 */
	public static function data( Booking $booking ): array {
		$general  = (array) SettingsHelper::get_setting( 'general' );
		$guest    = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		$invoice  = ( new InvoiceRepository() )->forBooking( (int) $booking->id );
		$rooms    = array();
		foreach ( ( new BookingRoomRepository() )->forBooking( (int) $booking->id ) as $line ) {
			$rooms[] = array(
				'room'      => (string) $line->room_number,
				'rate_plan' => (string) $line->rate_plan_name,
				'start'     => Dates::to_iso( Dates::from_gmt( (string) $line->start_at_gmt ) ),
				'end'       => Dates::to_iso( Dates::from_gmt( (string) $line->end_at_gmt ) ),
				'status'    => (string) $line->status,
				'charged'   => ! in_array( (string) $line->status, BookingTotals::NOT_CHARGED, true ),
				'total'     => Money::round( (float) $line->total ),
			);
		}
		$receipts = array();
		foreach ( ( new PaymentRepository() )->forBooking( (int) $booking->id ) as $row ) {
			if ( '' !== (string) $row->receipt_no ) {
				$receipts[] = array(
					'number'      => (string) $row->receipt_no,
					'amount'      => Money::round( (float) $row->amount ),
					'received_at' => Dates::to_iso( Dates::from_gmt( (string) $row->received_at_gmt ) ),
					'url'         => self::documentUrl( $booking, 'receipt', (int) $row->id ),
				);
			}
		}
		$due = (float) $booking->balance_due;
		return array(
			'reference'      => (string) $booking->reference,
			'status'         => (string) $booking->status,
			'payment_status' => (string) $booking->payment_status,
			'guest_name'     => $guest ? $guest->fullName() : '',
			'rooms'          => $rooms,
			'total'          => Money::round( (float) $booking->total ),
			'paid_total'     => Money::round( (float) $booking->paid_total ),
			'balance_due'    => Money::round( $due ),
			'currency'       => (string) $booking->currency,
			'payment_due_at' => $booking->payment_due_at_gmt ? Dates::to_iso( Dates::from_gmt( (string) $booking->payment_due_at_gmt ) ) : null,
			'overdue'        => PaymentDeadline::overdue( $booking ),
			// Instructions only while money is due on a booking still going ahead.
			'instructions'   => $due > 0 && in_array( (string) $booking->status, array( 'pending', 'confirmed' ), true ) ? self::instructions( $booking ) : array(),
			'invoice'        => $invoice ? array(
				'number' => (string) $invoice->number,
				'status' => (string) $invoice->status,
				'url'    => self::documentUrl( $booking, 'invoice' ),
			) : null,
			'receipts'       => $receipts,
			'page_url'       => self::pageUrl( $booking ),
			'hotel'          => array(
				'name'    => (string) ( $general['companyName'] ?? get_bloginfo( 'name' ) ),
				'address' => (string) ( $general['address'] ?? '' ),
				'phone'   => (string) ( $general['phone'] ?? '' ),
				'email'   => (string) ( $general['contactEmail'] ?? '' ),
				'logo'    => ! empty( $general['logo'] ) ? (string) wp_get_attachment_image_url( (int) $general['logo'], 'medium' ) : '',
			),
		);
	}

	/**
	 * A booking by id (for the e-mails, after the commit).
	 *
	 * @param int $id Booking id.
	 * @return Booking|null
	 */
	public static function booking( int $id ): ?Booking {
		return ( new BookingRepository() )->find( $id );
	}
}
