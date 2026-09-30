<?php
/**
 * The staff document link (M05, 5.6, 5.12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Documents
 */

namespace RadiusTheme\RadiusHotelBooking\Documents;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Repositories\PaymentRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;

defined( 'ABSPATH' ) || exit;

/**
 * `admin-post.php?action=rtbp_document&doc=invoice|receipt&id=&format=html[&version=][&download=1]&_wpnonce=`
 *
 * A document opens in its own browser tab to print or save, so it cannot be
 * a REST call (those need the nonce in a header). The link carries the
 * user's own nonce (`DocumentService::url()`, handed out by the API), and the
 * request must come from a signed-in user with the dashboard capability and
 * `page.bookings` — the same key as the booking screen. A key that needs a
 * PIN cannot be answered from a plain link, so it is refused here.
 */
class DocumentEndpoint {

	/**
	 * The admin-post action (also the nonce action).
	 */
	public const ACTION = 'rtbp_document';

	/**
	 * Hook the endpoint.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		// The guest's own documents, by the booking's public token (signed in or not).
		add_action( 'admin_post_nopriv_' . GuestBookingView::DOCUMENT_ACTION, array( self::class, 'handleGuest' ) );
		add_action( 'admin_post_' . GuestBookingView::DOCUMENT_ACTION, array( self::class, 'handleGuest' ) );
	}

	/**
	 * A guest's document: `admin-post.php?action=rtbp_guest_document&token=&doc=invoice|receipt[&id=]&format=`.
	 * The booking's public token is the key (128 bits, the same as the
	 * confirmation page): the current invoice of that booking, or one of
	 * **its** receipts — never another booking's.
	 *
	 * @return void
	 */
	public static function handleGuest(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the booking's secret token is the credential (a guest has no session).
		$token  = sanitize_key( (string) ( $_GET['token'] ?? '' ) );
		$type   = sanitize_key( (string) ( $_GET['doc'] ?? '' ) );
		$id     = absint( $_GET['id'] ?? 0 );
		$format = sanitize_key( (string) ( $_GET['format'] ?? 'html' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$booking = GuestBookingView::byToken( $token );
		if ( ! $booking || ! in_array( $type, DocumentService::TYPES, true ) ) {
			self::fail( __( 'This document does not exist.', 'radius-hotel-booking' ), 404 );
		}
		try {
			$service = new DocumentService();
			if ( 'invoice' === $type ) {
				$data = $service->invoice( (int) $booking->id );
			} else {
				$payment = $id ? ( new PaymentRepository() )->find( $id ) : null;
				if ( ! $payment || (int) $payment->booking_id !== (int) $booking->id ) {
					self::fail( __( 'This document does not exist.', 'radius-hotel-booking' ), 404 );
				}
				$data = $service->receipt( $id );
			}
			$out = DocumentService::render( $type, $data, $format );
		} catch ( DomainException $e ) {
			self::fail( $e->getMessage(), $e->getStatus() );
		}
		self::output( $out, false );
	}

	/**
	 * Answer a document request.
	 *
	 * @return void
	 */
	public static function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified just below.
		$nonce  = sanitize_text_field( wp_unslash( (string) ( $_GET['_wpnonce'] ?? '' ) ) );
		$type   = sanitize_key( (string) ( $_GET['doc'] ?? '' ) );
		$id     = absint( $_GET['id'] ?? 0 );
		$format = sanitize_key( (string) ( $_GET['format'] ?? 'html' ) );
		$ver    = absint( $_GET['version'] ?? 0 );
		$save   = ! empty( $_GET['download'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, self::ACTION ) ) {
			self::fail( __( 'This link has expired. Open the document again from the booking.', 'radius-hotel-booking' ), 403 );
		}
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) || ( class_exists( Access::class ) && ! Access::can( 'page.bookings' ) ) ) {
			self::fail( __( 'You do not have permission to see this document.', 'radius-hotel-booking' ), 403 );
		}
		if ( ! in_array( $type, DocumentService::TYPES, true ) || ! $id ) {
			self::fail( __( 'This document does not exist.', 'radius-hotel-booking' ), 404 );
		}

		try {
			$service = new DocumentService();
			$data    = 'invoice' === $type ? $service->invoice( $id, $ver ) : $service->receipt( $id );
			$out     = DocumentService::render( $type, $data, $format );
		} catch ( DomainException $e ) {
			self::fail( $e->getMessage(), $e->getStatus() );
		}

		self::output( $out, $save );
	}

	/**
	 * Send a rendered document and stop.
	 *
	 * @param array $out  `{ body, content_type, filename }`.
	 * @param bool  $save Download instead of showing.
	 * @return never
	 */
	private static function output( array $out, bool $save ) {
		nocache_headers();
		header( 'Content-Type: ' . ( $out['content_type'] ?? 'text/html; charset=utf-8' ) );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Content-Disposition: ' . ( $save ? 'attachment' : 'inline' ) . '; filename="' . sanitize_file_name( (string) ( $out['filename'] ?? 'document' ) ) . '"' );
		echo $out['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes every value; a PDF is binary.
		exit;
	}

	/**
	 * Stop with a message.
	 *
	 * @param string $message Message (plain text).
	 * @param int    $status  HTTP status.
	 * @return never
	 */
	private static function fail( string $message, int $status ) {
		wp_die( esc_html( $message ), esc_html__( 'Document', 'radius-hotel-booking' ), array( 'response' => (int) $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- an HTTP status code, not output.
	}
}
