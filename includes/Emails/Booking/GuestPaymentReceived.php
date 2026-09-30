<?php
/**
 * The guest's e-mail: payment received, with the receipt (M05, 5.12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\Payment;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Sent when a payment is recorded (`rtbp_payment_recorded`, after the
 * commit) — not for a refund or a void — to a guest with a real e-mail: the
 * amount, how it was paid, the receipt number, what is still due, and the
 * links to the receipt and the booking page. *Paid now* at the desk sends it
 * too. Pro attaches the receipt PDF (T7). Logged `receipts.send`.
 */
class GuestPaymentReceived extends BaseEmail {

	/**
	 * Template, under `templates/emails/`.
	 *
	 * @var string
	 */
	public $template_html = 'guest-payment-received.php';

	/**
	 * Recipient type.
	 *
	 * @var string
	 */
	public $recipient_type = 'guest';

	/**
	 * The payment the last e-mail was about (for the log after sending).
	 *
	 * @var array|null `{ payment, booking }`.
	 */
	private ?array $about = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Payment received', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when a payment is recorded, with the receipt.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_payment_received';
	}

	/**
	 * Trigger action.
	 *
	 * @return string
	 */
	public function get_trigger_action() {
		return 'rtbp_payment_recorded';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Payment received for booking {booking_reference}', 'radius-hotel-booking' ),
			'heading' => __( 'Thank you for your payment', 'radius-hotel-booking' ),
		);
	}

	/**
	 * The e-mail for a payment.
	 *
	 * @param array $args `[ Payment, Booking ]`.
	 * @return array
	 */
	protected function prepare_email_data( $args ) {
		list( $payment, $booking ) = array_pad( (array) $args, 2, null );
		if ( ! $payment instanceof Payment || ! $booking instanceof Booking || 'payment' !== $payment->type || '' === (string) $payment->receipt_no ) {
			return array();
		}
		$guest = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		if ( ! $guest || $guest->email_is_placeholder || ! is_email( (string) $guest->email ) ) {
			return array();
		}
		$this->about = array(
			'payment' => $payment,
			'booking' => $booking,
		);
		$due         = (float) $booking->balance_due;

		return array(
			'to'         => (string) $guest->email,
			'data'       => array(
				'intro'       => __( 'Hello {guest_name}, we have received your payment for booking {booking_reference}. Thank you.', 'radius-hotel-booking' ),
				'amount'      => Money::format( (float) $payment->amount ),
				'method'      => PaymentSettings::method_label( (string) $payment->method ),
				'reference'   => (string) $payment->reference,
				'received_at' => Dates::format( Dates::from_gmt( (string) $payment->received_at_gmt ), 'datetime' ),
				'receipt_no'  => (string) $payment->receipt_no,
				'paid'        => Money::format( (float) $booking->paid_total ),
				'due'         => $due > 0 ? Money::format( $due ) : '',
				'receipt_url' => GuestBookingView::documentUrl( $booking, 'receipt', (int) $payment->id ),
				'page_url'    => GuestBookingView::pageUrl( $booking ),
			),
			'merge_tags' => array(
				'guest_name'        => $guest->fullName(),
				'booking_reference' => (string) $booking->reference,
			),
		);
	}

	/**
	 * Send, then log the receipt as sent.
	 *
	 * @param array $email_data Email data.
	 * @return bool
	 */
	public function send( $email_data ) {
		$sent = parent::send( $email_data );
		if ( $sent && $this->about ) {
			$booking = $this->about['booking'];
			$payment = $this->about['payment'];
			rtbp_activity(
				'receipts.send',
				array(
					'type'  => 'booking',
					'id'    => (int) $booking->id,
					'label' => (string) $booking->reference,
				),
				array(
					'after'       => array(
						'receipt' => (string) $payment->receipt_no,
						'to'      => (string) ( is_array( $email_data['to'] ) ? implode( ', ', $email_data['to'] ) : $email_data['to'] ),
					),
					'description' => sprintf(
						/* translators: 1: receipt number, 2: booking reference. */
						__( 'Sent receipt %1$s for %2$s to the guest', 'radius-hotel-booking' ),
						$payment->receipt_no,
						$booking->reference
					),
				)
			);
		}
		return $sent;
	}
}
