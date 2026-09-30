<?php
/**
 * The guest's e-mail: booking released for non-payment (M05, 5.14).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Booking released: to the guest when an unpaid booking past its deadline
 * is released (by the desk, or automatically with Pro), instead of the
 * *cancelled* e-mail.
 */
class GuestBookingReleased extends GuestBookingEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Booking released (unpaid)', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when an unpaid booking is released after its payment deadline.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_booking_released';
	}

	/**
	 * The status action.
	 *
	 * @return string
	 */
	protected function action(): string {
		return 'release';
	}

	/**
	 * The sentence under the heading.
	 *
	 * @return string
	 */
	protected function intro(): string {
		return __( 'Hello {guest_name}, we did not receive the payment for booking {booking_reference} in time, so the rooms are no longer held for you. Contact us if you would still like to stay.', 'radius-hotel-booking' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Your booking {booking_reference} was released', 'radius-hotel-booking' ),
			'heading' => __( 'Your booking was released', 'radius-hotel-booking' ),
		);
	}
}
