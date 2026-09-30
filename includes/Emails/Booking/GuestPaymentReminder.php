<?php
/**
 * The guest's e-mail: a reminder to pay (M05, 5.14).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Sent when the desk presses *Remind* on an unpaid booking
 * (`rtbp_payment_reminder`): what is still due, the deadline, how to pay and
 * the links — the booking-received layout with a reminder at the top.
 */
class GuestPaymentReminder extends BaseEmail {

	/**
	 * Template, under `templates/emails/`.
	 *
	 * @var string
	 */
	public $template_html = 'guest-booking-received.php';

	/**
	 * Recipient type.
	 *
	 * @var string
	 */
	public $recipient_type = 'guest';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Payment reminder', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when staff send a reminder about an unpaid booking.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_payment_reminder';
	}

	/**
	 * Trigger action.
	 *
	 * @return string
	 */
	public function get_trigger_action() {
		return 'rtbp_payment_reminder';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Reminder: payment for booking {booking_reference}', 'radius-hotel-booking' ),
			'heading' => __( 'A reminder about your payment', 'radius-hotel-booking' ),
		);
	}

	/**
	 * The reminder for a booking.
	 *
	 * @param array $args `[ Booking ]`.
	 * @return array
	 */
	protected function prepare_email_data( $args ) {
		$booking = $args[0] ?? null;
		if ( ! $booking instanceof Booking ) {
			return array();
		}
		$guest = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		if ( ! $guest || $guest->email_is_placeholder || ! is_email( (string) $guest->email ) ) {
			return array();
		}
		$view = GuestBookingView::data( $booking );
		if ( $view['balance_due'] <= 0 || ! $view['instructions'] ) {
			return array();
		}
		return array(
			'to'         => (string) $guest->email,
			'data'       => array(
				'intro'        => $view['overdue']
					? __( 'Hello {guest_name}, the payment for booking {booking_reference} was due and we have not received it yet. Please pay now so we can keep your room.', 'radius-hotel-booking' )
					: __( 'Hello {guest_name}, a reminder that payment for booking {booking_reference} is still due.', 'radius-hotel-booking' ),
				'rooms'        => array_map(
					static fn( $room ) => array(
						'room'  => $room['room'],
						'rate'  => $room['rate_plan'],
						'start' => Dates::format( Dates::from_iso( $room['start'] ), 'datetime' ),
						'end'   => Dates::format( Dates::from_iso( $room['end'] ), 'datetime' ),
						'total' => Money::format( $room['total'] ),
					),
					array_filter( $view['rooms'], static fn( $room ) => $room['charged'] )
				),
				'total'        => Money::format( $view['total'] ),
				'paid'         => $view['paid_total'] > 0 ? Money::format( $view['paid_total'] ) : '',
				'due'          => Money::format( $view['balance_due'] ),
				'deadline'     => $view['payment_due_at'] && ! $view['overdue'] ? Dates::format( Dates::from_iso( $view['payment_due_at'] ), 'datetime' ) : '',
				'instructions' => $view['instructions'],
				'reference'    => $view['reference'],
				'page_url'     => $view['page_url'],
				'invoice'      => $view['invoice'],
			),
			'merge_tags' => array(
				'guest_name'        => $guest->fullName(),
				'booking_reference' => (string) $booking->reference,
			),
		);
	}
}
