<?php
/**
 * The guest's booking page (M05, 5.2, 5.4).
 *
 * @package RadiusTheme\RadiusHotelBooking\Frontend
 */

namespace RadiusTheme\RadiusHotelBooking\Frontend;

use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;

defined( 'ABSPATH' ) || exit;

/**
 * `/?rtbp_booking=<public token>`: what the guest sees after booking and in
 * their e-mails — the status, the rooms, the money, the deadline counted
 * down, how to pay (every method the hotel offers, with the exact amount and
 * the reference to quote), the invoice and receipts. A page of its own
 * (`templates/booking/confirmation.php`, overridable from a theme), so it
 * reads the same whatever the theme; `noindex`, never cached.
 */
class BookingConfirmationPage {

	/**
	 * Hook the page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = GuestBookingView::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( self::class, 'maybe_render' ), 1 );
	}

	/**
	 * Answer the page when its query var is present.
	 *
	 * @return void
	 */
	public static function maybe_render(): void {
		$token = (string) get_query_var( GuestBookingView::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}
		$booking = GuestBookingView::byToken( sanitize_key( $token ) );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( ! $booking ) {
			status_header( 404 );
			wp_die(
				esc_html__( 'We could not find this booking. Check the link in your e-mail, or contact the hotel.', 'radius-hotel-booking' ),
				esc_html__( 'Booking not found', 'radius-hotel-booking' ),
				array( 'response' => 404 )
			);
		}
		status_header( 200 );
		rtbp_get_template( 'booking/confirmation.php', array( 'view' => GuestBookingView::data( $booking ) ) );
		exit;
	}
}
