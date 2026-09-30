<?php
/**
 * The guest's e-mail: booking declined (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

defined( 'ABSPATH' ) || exit;

/**
 * Booking declined: to the guest, once the whole booking is declined.
 */
class GuestBookingDeclined extends GuestBookingEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Booking declined', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when their booking is declined, with the reason.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_booking_decline';
	}

	/**
	 * The status action.
	 *
	 * @return string
	 */
	protected function action(): string {
		return 'decline';
	}

	/**
	 * The sentence under the heading.
	 *
	 * @return string
	 */
	protected function intro(): string {
		return __( 'Hello {guest_name}, we are sorry: we could not accept your booking {booking_reference}.', 'radius-hotel-booking' );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Your booking {booking_reference} could not be accepted', 'radius-hotel-booking' ),
			'heading' => __( 'We could not accept your booking', 'radius-hotel-booking' ),
		);
	}
}
