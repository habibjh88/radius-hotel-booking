<?php
/**
 * Printable documents: invoices and receipts (M05, 5.6, 5.12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Documents
 */

namespace RadiusTheme\RadiusHotelBooking\Documents;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use RadiusTheme\RadiusHotelBooking\Models\Payment;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceVersionRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\PaymentRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\InvoiceService;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a document's data — an invoice version from its stored snapshot, a
 * receipt from its ledger row — and hands it to a **renderer** from the
 * `rtbp_document_renderers` registry: `format => callable( string $type,
 * array $data ): array { body, content_type, filename }`. The free plugin
 * registers `html`: the templates in `templates/documents/` (overridable from
 * a theme at `radius-hotel-booking/documents/…`), printed from the browser.
 * Pro registers `pdf` (M05 T7, ADR-005) — free never depends on it.
 */
class DocumentService {

	/**
	 * Document types.
	 */
	public const TYPES = array( 'invoice', 'receipt' );

	/**
	 * The renderers, by format.
	 *
	 * @return array<string, callable>
	 */
	public static function renderers(): array {
		/**
		 * Document renderers by format. Each is `callable( string $type, array $data ): array`
		 * returning `{ body (string), content_type, filename }`; `$data` is what the
		 * `templates/documents/<type>.php` template receives. Free: `html`.
		 *
		 * @param array<string, callable> $renderers Format => renderer.
		 */
		$renderers = (array) apply_filters( 'rtbp_document_renderers', array( 'html' => array( self::class, 'html' ) ) );
		return array_filter( $renderers, 'is_callable' );
	}

	/**
	 * Render a document.
	 *
	 * @param string $type   invoice|receipt.
	 * @param array  $data   Document data.
	 * @param string $format A registered format.
	 * @return array `{ body, content_type, filename }`.
	 * @throws DomainException 404 for an unknown format.
	 */
	public static function render( string $type, array $data, string $format = 'html' ): array {
		$renderer = self::renderers()[ $format ] ?? null;
		if ( ! $renderer ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with wp_die(), escaped there.
			throw DomainException::notFound( __( 'This document format is not available.', 'radius-hotel-booking' ) );
		}
		return (array) call_user_func( $renderer, $type, $data );
	}

	/**
	 * The free renderer: the HTML print view.
	 *
	 * @param string $type invoice|receipt.
	 * @param array  $data Document data.
	 * @return array
	 */
	public static function html( string $type, array $data ): array {
		ob_start();
		rtbp_get_template( 'documents/' . $type . '.php', array( 'doc' => $data ) );
		return array(
			'body'         => (string) ob_get_clean(),
			'content_type' => 'text/html; charset=utf-8',
			'filename'     => sanitize_file_name( $data['number'] . '.html' ),
		);
	}

	/**
	 * A booking's invoice, as issued in one version (default: the current one).
	 * Issues the invoice first when the booking predates invoices.
	 *
	 * @param int $booking_id Booking id.
	 * @param int $version    Version (0 = current).
	 * @return array
	 * @throws DomainException 404.
	 */
	public function invoice( int $booking_id, int $version = 0 ): array {
		$invoice = ( new InvoiceService() )->ensure( $booking_id );
		if ( ! $invoice ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with wp_die(), escaped there.
			throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
		}
		$versions = ( new InvoiceVersionRepository() )->forInvoice( (int) $invoice->id );
		$chosen   = null;
		foreach ( $versions as $row ) {
			if ( ( $version ? $version : (int) $invoice->version ) === (int) $row->version ) {
				$chosen = $row;
			}
		}
		if ( ! $chosen ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with wp_die(), escaped there.
			throw DomainException::notFound( __( 'This version of the invoice does not exist.', 'radius-hotel-booking' ) );
		}
		$snapshot = (array) json_decode( (string) $chosen->snapshot, true );
		return array_merge(
			$snapshot,
			array(
				'type'      => 'invoice',
				'status'    => (string) $invoice->status,
				// An older version, printed again: say so on the page.
				'superseded' => (int) $chosen->version < (int) $invoice->version,
				'latest'    => (int) $invoice->version,
				'logo_url'  => self::logoUrl( (int) ( $snapshot['hotel']['logo'] ?? 0 ) ),
			)
		);
	}

	/**
	 * A payment's receipt: the payment, the booking and invoice it belongs
	 * to, and what was still due right after it.
	 *
	 * @param int $payment_id Payment id.
	 * @return array
	 * @throws DomainException 404, 409 `no_receipt` (a refund or a void).
	 */
	public function receipt( int $payment_id ): array {
		$payments = new PaymentRepository();
		$payment  = $payment_id > 0 ? $payments->find( $payment_id ) : null;
		if ( ! $payment instanceof Payment ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with wp_die(), escaped there.
			throw DomainException::notFound( __( 'This payment does not exist.', 'radius-hotel-booking' ) );
		}
		if ( '' === (string) $payment->receipt_no ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown with wp_die(), escaped there.
			throw DomainException::conflict( 'no_receipt', __( 'Only payments have a receipt.', 'radius-hotel-booking' ) );
		}
		$booking = ( new BookingRepository() )->find( (int) $payment->booking_id );
		$invoice = ( new InvoiceRepository() )->forBooking( (int) $payment->booking_id );
		$guest   = $booking && $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		$general = (array) SettingsHelper::get_setting( 'general' );

		// What the ledger held right after this payment: every row up to it, voids of
		// earlier payments included (critical review); and whether it was voided since.
		$paid   = 0.0;
		$voided = '';
		foreach ( $payments->forBooking( (int) $payment->booking_id ) as $row ) {
			if ( (int) $row->id <= (int) $payment->id ) {
				$paid += (float) $row->amount;
			}
			if ( 'void' === $row->type && (int) $row->voids_payment_id === (int) $payment->id ) {
				$voided = (string) $row->note;
			}
		}
		$recorder = $payment->recorded_by ? get_userdata( (int) $payment->recorded_by ) : null;
		// The balance frozen when the payment was recorded; the total then is paid + that balance.
		// Rows from before it was stored fall back to today's total.
		$total = null !== $payment->balance_after
			? $paid + (float) $payment->balance_after
			: ( $booking ? (float) $booking->total : 0.0 );

		return array(
			'type'           => 'receipt',
			'number'         => (string) $payment->receipt_no,
			'received_at'    => Dates::to_iso( Dates::from_gmt( (string) $payment->received_at_gmt ) ),
			'amount'         => Money::round( (float) $payment->amount ),
			'method'         => PaymentSettings::method_label( (string) $payment->method ),
			'reference'      => (string) $payment->reference,
			'note'           => (string) $payment->note,
			'recorded_by'    => $recorder ? (string) $recorder->display_name : '',
			'voided'         => $voided,
			'booking'        => array(
				'reference' => $booking ? (string) $booking->reference : '',
				'total'     => Money::round( $total ),
				'currency'  => $booking ? (string) $booking->currency : '',
			),
			'invoice_number' => $invoice ? (string) $invoice->number : '',
			'paid_to_date'   => Money::round( $paid ),
			'balance_after'  => Money::round( $total - $paid ),
			'guest'          => $guest ? array(
				'name'  => $guest->fullName(),
				'phone' => (string) $guest->phone,
			) : null,
			'hotel'          => array(
				'name'        => (string) ( '' !== (string) ( $general['legalName'] ?? '' ) ? $general['legalName'] : ( $general['companyName'] ?? '' ) ),
				'address'     => (string) ( $general['address'] ?? '' ),
				'phone'       => (string) ( $general['phone'] ?? '' ),
				'email'       => (string) ( $general['contactEmail'] ?? '' ),
				'tax_number'  => (string) ( $general['taxNumber'] ?? '' ),
				'cnps_number' => (string) ( $general['cnpsNumber'] ?? '' ),
			),
			'logo_url'       => self::logoUrl( (int) ( $general['logo'] ?? 0 ) ),
			'footer'         => (string) rtbp_setting( 'invoices', 'footerText', '' ),
		);
	}

	/**
	 * The signed staff link to a document (admin-post; the nonce is the user's).
	 *
	 * @param string $type   invoice|receipt.
	 * @param int    $id     Booking id (invoice) or payment id (receipt).
	 * @param string $format Format.
	 * @param array  $extra  More query args (`version`, `download`).
	 * @return string
	 */
	public static function url( string $type, int $id, string $format = 'html', array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'action'   => DocumentEndpoint::ACTION,
					'doc'      => $type,
					'id'       => $id,
					'format'   => $format,
					'_wpnonce' => wp_create_nonce( DocumentEndpoint::ACTION ),
				),
				$extra
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The links of a document in every format available.
	 *
	 * @param string $type invoice|receipt.
	 * @param int    $id   Booking or payment id.
	 * @return array<string, string> Format => URL.
	 */
	public static function urls( string $type, int $id ): array {
		$out = array();
		foreach ( array_keys( self::renderers() ) as $format ) {
			$out[ $format ] = self::url( $type, $id, (string) $format );
		}
		return $out;
	}

	/**
	 * The hotel logo's URL ('' when none).
	 *
	 * @param int $attachment_id Attachment id.
	 * @return string
	 */
	private static function logoUrl( int $attachment_id ): string {
		$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		return $url ? (string) $url : '';
	}
}
