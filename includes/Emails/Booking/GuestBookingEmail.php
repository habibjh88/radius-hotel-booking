<?php
/**
 * Guest e-mails about a booking's status (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Emails\Booking;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingTotals;
use RadiusTheme\RadiusHotelBooking\Services\Booking\StatusMachine;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Sent to the guest when a status change settles the **booking**
 * (`rtbp_booking_status_changed`, after the commit), judged by the status
 * the booking now has rather than by which button was used (critical
 * review): *approved* once nothing waits any more, *declined* / *cancelled*
 * once every room is — from the header, or a room at a time down to the
 * last. A change that leaves the booking in between sends nothing. The
 * e-mail lists only the rooms it is about. Never sent to a guest without a
 * real e-mail (a placeholder address). Written in the site language
 * (ADR-019). Each e-mail has its own switch in Settings → E-mail.
 */
abstract class GuestBookingEmail extends BaseEmail {

	/**
	 * Template, under `templates/emails/`.
	 *
	 * @var string
	 */
	public $template_html = 'guest-booking-status.php';

	/**
	 * Recipient type.
	 *
	 * @var string
	 */
	public $recipient_type = 'guest';

	/**
	 * The status action this e-mail answers: approve|decline|cancel.
	 *
	 * @return string
	 */
	abstract protected function action(): string;

	/**
	 * The sentence under the heading.
	 *
	 * @return string
	 */
	abstract protected function intro(): string;

	/**
	 * Trigger action.
	 *
	 * @return string
	 */
	public function get_trigger_action() {
		return 'rtbp_booking_status_changed';
	}

	/**
	 * The e-mail for a status change, or nothing when it is not ours.
	 *
	 * @param array $args `[ Booking, action, changes, reason, line_id ]`.
	 * @return array
	 */
	protected function prepare_email_data( $args ) {
		list( $booking, $action, , $reason ) = array_pad( (array) $args, 5, null );
		if ( ! $booking instanceof Booking || $this->action() !== $action || ! $this->settles( (string) $booking->status ) ) {
			return array();
		}
		$guest = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		if ( ! $guest || $guest->email_is_placeholder || ! is_email( (string) $guest->email ) ) {
			return array();
		}

		$rooms = array();
		foreach ( ( new BookingRoomRepository() )->forBooking( (int) $booking->id ) as $line ) {
			if ( ! $this->lists( (string) $line->status ) ) {
				continue;
			}
			$rooms[] = array(
				'room'  => (string) $line->room_number,
				'rate'  => (string) $line->rate_plan_name,
				'start' => Dates::format( Dates::from_gmt( (string) $line->start_at_gmt ), 'datetime' ),
				'end'   => Dates::format( Dates::from_gmt( (string) $line->end_at_gmt ), 'datetime' ),
				'total' => Money::format( (float) $line->total ),
			);
		}

		if ( ! $rooms ) {
			return array();
		}

		return array(
			'to'         => (string) $guest->email,
			'data'       => array(
				'intro'  => $this->intro(),
				'rooms'  => $rooms,
				'total'  => Money::format( (float) $booking->total ),
				'reason' => (string) $reason,
			),
			'merge_tags' => array(
				'guest_name'        => $guest->fullName(),
				'booking_reference' => (string) $booking->reference,
				'reason'            => (string) $reason,
			),
		);
	}

	/**
	 * Whether the booking's new status is the one this e-mail announces.
	 *
	 * @param string $status The booking's stored status after the change.
	 * @return bool
	 */
	private function settles( string $status ): bool {
		switch ( $this->action() ) {
			case 'approve':
				// Nothing waits any more (the last pending room was approved).
				return StatusMachine::PENDING !== $status && ! in_array( $status, array( StatusMachine::DECLINED, StatusMachine::CANCELLED ), true );
			case 'decline':
				return StatusMachine::DECLINED === $status;
			case 'cancel':
				return StatusMachine::CANCELLED === $status;
		}
		return false;
	}

	/**
	 * Whether a room belongs in this e-mail: the rooms still booked for an
	 * approval, the declined or cancelled ones otherwise.
	 *
	 * @param string $status The line's status.
	 * @return bool
	 */
	private function lists( string $status ): bool {
		if ( 'approve' === $this->action() ) {
			return ! in_array( $status, BookingTotals::NOT_CHARGED, true );
		}
		return in_array( $status, BookingTotals::NOT_CHARGED, true );
	}
}
