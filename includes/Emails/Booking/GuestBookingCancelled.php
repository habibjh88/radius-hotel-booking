<?php
/**
 * The guest's e-mail: booking cancelled (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Booking cancelled: to the guest, once the whole booking is cancelled.
 */
class GuestBookingCancelled extends GuestBookingEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Booking cancelled', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when their booking is cancelled, with the reason.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_booking_cancel';
	}

	/**
	 * The status action.
	 *
	 * @return string
	 */
	protected function action(): string {
		return 'cancel';
	}

	/**
	 * The sentence under the heading.
	 *
	 * @return string
	 */
	protected function intro(): string {
		return __( 'Hello {guest_name}, your booking {booking_reference} has been cancelled. The rooms are no longer held for you.', 'radius-hotel-booking' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Your booking {booking_reference} is cancelled', 'radius-hotel-booking' ),
			'heading' => __( 'Your booking is cancelled', 'radius-hotel-booking' ),
		);
	}
}
