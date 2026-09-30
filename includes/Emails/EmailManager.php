<?php
/**
 * Email manager.
 *
 * @package RadiusTheme\RadiusHotelBooking\Emails
 */

namespace RadiusTheme\RadiusHotelBooking\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail;

/**
 * Class EmailManager
 *
 * Single point of control for the email system: it registers every email class,
 * holds the instances and exposes them to the settings UI.
 *
 * Each email subclasses Abstracts\BaseEmail, declares the action that triggers
 * it, and is constructed here — the base class hooks itself onto that trigger.
 */
class EmailManager {

	/**
	 * Registered email instances, keyed by email id.
	 *
	 * @var BaseEmail[]
	 */
	private array $emails = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		MergeTags::init();
		$this->register_emails();
	}

	/**
	 * Instantiate every registered email class.
	 *
	 * @return void
	 */
	private function register_emails(): void {
		/**
		 * Filter the list of email classes.
		 *
		 * BOILERPLATE: add your email classes to this array, or append to it
		 * from an add-on plugin.
		 *
		 * @param string[] $classes Fully qualified BaseEmail subclasses.
		 */
		$classes = (array) apply_filters(
			'rtbp_email_classes',
			array(
				// The guest's booking status e-mails (M03).
				Booking\GuestBookingApproved::class,
				Booking\GuestBookingDeclined::class,
				Booking\GuestBookingCancelled::class,
				Booking\GuestBookingReceived::class,
				Booking\GuestPaymentReceived::class,
				Booking\GuestPaymentReminder::class,
				Booking\GuestBookingReleased::class,
			)
		);

		foreach ( $classes as $class_name ) {
			if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, BaseEmail::class ) ) {
				continue;
			}

			$email = new $class_name();

			$this->emails[ $email->get_id() ] = $email;
		}
	}

	/**
	 * All registered emails.
	 *
	 * @return BaseEmail[]
	 */
	public function get_emails(): array {
		return $this->emails;
	}

	/**
	 * One email by id.
	 *
	 * @param string $id Email id.
	 *
	 * @return BaseEmail|null
	 */
	public function get_email( string $id ): ?BaseEmail {
		return $this->emails[ $id ] ?? null;
	}
}
