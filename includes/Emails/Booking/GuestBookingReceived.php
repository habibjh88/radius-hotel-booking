<?php
/**
 * The guest's e-mail: booking received, with how to pay (M05, 5.2, 5.7).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Sent the moment a booking is made (`rtbp_booking_created`, after the
 * commit), desk or web, to a guest with a real e-mail: the rooms, the total,
 * **how to pay** — every method offered, with the exact amount, the reference
 * to quote and the deadline — and the links to the booking page and the
 * invoice. Pro attaches the invoice PDF (T7). Logged `invoices.send`.
 */
class GuestBookingReceived extends BaseEmail {

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
	 * The booking the last e-mail was about (for the log after sending).
	 *
	 * @var Booking|null
	 */
	private ?Booking $booking = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->title       = __( 'Booking received', 'radius-hotel-booking' );
		$this->description = __( 'To the guest when a booking is made: how to pay, the deadline and the invoice.', 'radius-hotel-booking' );
		parent::__construct();
	}

	/**
	 * Email id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'guest_booking_received';
	}

	/**
	 * Trigger action.
	 *
	 * @return string
	 */
	public function get_trigger_action() {
		return 'rtbp_booking_created';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function get_default_settings() {
		return array(
			'enabled' => true,
			'subject' => __( 'Your booking {booking_reference}', 'radius-hotel-booking' ),
			'heading' => __( 'Thank you for your booking', 'radius-hotel-booking' ),
		);
	}

	/**
	 * The e-mail for a new booking.
	 *
	 * @param array $args `[ Booking, source ]`.
	 * @return array
	 */
	protected function prepare_email_data( $args ) {
		$booking = $args[0] ?? null;
		// A booking brought over from the old system (M18): the guest booked long ago.
		if ( ! $booking instanceof Booking || 'import' === ( $args[1] ?? '' ) ) {
			return array();
		}
		// The booking as committed (its invoice, deadline and any *Paid now* payment).
		$booking = GuestBookingView::booking( (int) $booking->id );
		$guest   = $booking && $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		if ( ! $guest || $guest->email_is_placeholder || ! is_email( (string) $guest->email ) ) {
			return array();
		}
		$this->booking = $booking;
		$view          = GuestBookingView::data( $booking );

		return array(
			'to'         => (string) $guest->email,
			'data'       => array(
				'intro'        => 'pending' === $view['status']
					? __( 'Hello {guest_name}, we have received your booking {booking_reference}. The hotel will confirm it shortly.', 'radius-hotel-booking' )
					: __( 'Hello {guest_name}, thank you for your booking {booking_reference}.', 'radius-hotel-booking' ),
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
				'due'          => $view['balance_due'] > 0 ? Money::format( $view['balance_due'] ) : '',
				'deadline'     => $view['payment_due_at'] ? Dates::format( Dates::from_iso( $view['payment_due_at'] ), 'datetime' ) : '',
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

	/**
	 * Send, then log the invoice as sent.
	 *
	 * @param array $email_data Email data.
	 * @return bool
	 */
	public function send( $email_data ) {
		$sent = parent::send( $email_data );
		if ( $sent && $this->booking ) {
			$invoice = ( new InvoiceRepository() )->forBooking( (int) $this->booking->id );
			if ( $invoice ) {
				( new InvoiceRepository() )->update( (int) $invoice->id, array( 'sent_at' => Dates::to_db( Dates::now() ) ) );
				rtbp_activity(
					'invoices.send',
					array(
						'type'  => 'booking',
						'id'    => (int) $this->booking->id,
						'label' => (string) $this->booking->reference,
					),
					array(
						'after'       => array(
							'number' => (string) $invoice->number,
							'to'     => (string) ( is_array( $email_data['to'] ) ? implode( ', ', $email_data['to'] ) : $email_data['to'] ),
						),
						'description' => sprintf(
							/* translators: 1: invoice number, 2: booking reference. */
							__( 'Sent invoice %1$s for %2$s to the guest', 'radius-hotel-booking' ),
							$invoice->number,
							$this->booking->reference
						),
					)
				);
			}
		}
		return $sent;
	}
}
