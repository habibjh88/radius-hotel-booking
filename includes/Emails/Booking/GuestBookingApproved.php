<?php
/**
 * The guest's e-mail: booking approved (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Booking approved: to the guest, once the whole booking is approved.
 */
class GuestBookingApproved extends GuestBookingEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Booking approved', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when their booking is approved.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_booking_approve';
	}

	/**
	 * The status action.
	 *
	 * @return string
	 */
	protected function action(): string {
		return 'approve';
	}

	/**
	 * The sentence under the heading.
	 *
	 * @return string
	 */
	protected function intro(): string {
		return __( 'Hello {guest_name}, your booking {booking_reference} is confirmed. We look forward to welcoming you.', 'radius-hotel-booking' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Your booking {booking_reference} is confirmed', 'radius-hotel-booking' ),
			'heading' => __( 'Your booking is confirmed', 'radius-hotel-booking' ),
		);
	}
}
