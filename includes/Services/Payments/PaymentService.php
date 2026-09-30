<?php
/**
 * Payments: the ledger and the booking's payment status (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Payments
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Payments;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\Payment;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\PaymentRepository;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * The ledger is the truth (ADR-010): staff **record** payments and refunds,
 * and correct a mistake by **voiding** a row (a new, negating row with a
 * reason) — nothing is edited or deleted. The booking's `paid_total`,
 * `balance_due` and `payment_status` are derived from it by
 * `recalculate()`, never set by a button (5.10, 5.11).
 *
 * Every write locks the booking row first, so two desks recording or
 * voiding on one booking run one after the other; it is logged inside the
 * transaction, and `rtbp_payment_recorded` fires after the commit
 * (receipts, e-mails).
 */
class PaymentService {

	/**
	 * Longest reference and note.
	 */
	public const MAX_REFERENCE = 100;
	public const MAX_NOTE      = 1000;

	/**
	 * Bookings.
	 *
	 * @var BookingRepository
	 */
	private BookingRepository $bookings;

	/**
	 * Ledger rows.
	 *
	 * @var PaymentRepository
	 */
	private PaymentRepository $payments;

	/**
	 * Constructor.
	 *
	 * @param BookingRepository|null $bookings Bookings.
	 * @param PaymentRepository|null $payments Ledger rows.
	 */
	public function __construct( ?BookingRepository $bookings = null, ?PaymentRepository $payments = null ) {
		$this->bookings = $bookings ?? new BookingRepository();
		$this->payments = $payments ?? new PaymentRepository();
	}

	/**
	 * The payment status for what was paid against what is owed (3.4). One
	 * rule for the ledger and for a total that moved (a room added,
	 * declined, removed): nothing paid is *unpaid* — or *refunded* once
	 * money came back —, at least the total is *paid* (more than the total
	 * means a refund is owed), anything between is *partially paid*.
	 *
	 * @param float $paid     Net paid (payments − refunds).
	 * @param float $total    Booking total.
	 * @param bool  $refunded Money was given back.
	 * @return string
	 */
	public static function statusFor( float $paid, float $total, bool $refunded ): string {
		if ( Money::round( $paid ) <= 0 ) {
			return $refunded ? 'refunded' : 'unpaid';
		}
		if ( Money::round( $paid ) >= Money::round( $total ) ) {
			return 'paid';
		}
		return 'partially_paid';
	}

	/**
	 * A booking's ledger.
	 *
	 * @param int $booking_id Booking id.
	 * @return Payment[]
	 * @throws DomainException 404.
	 */
	public function forBooking( int $booking_id ): array {
		$this->booking( $booking_id );
		return $this->payments->forBooking( $booking_id );
	}

	/**
	 * Record a payment or a refund (5.9, 5.11).
	 *
	 * @param int                    $booking_id Booking id.
	 * @param array                  $input      `{ type (payment|refund), amount, method, reference?, note?,
	 *                                           received_at? (site time Y-m-d H:i; default now) }`.
	 * @param DateTimeImmutable|null $now        Now.
	 * @return Payment The new row.
	 * @throws DomainException 404, 409 `nothing_due` / `nothing_to_refund`, 422 fields.
	 */
	public function record( int $booking_id, array $input, ?DateTimeImmutable $now = null ): Payment {
		self::requireKey( 'payments.record' );
		$now  = $now ?? Dates::now();
		$data = $this->validate( $input, $now );
		$this->booking( $booking_id );

		$payment = Transaction::run(
			function () use ( $booking_id, $data ) {
				$booking = $this->lockedBooking( $booking_id );
				$this->checkAmount( $booking, $data['type'], $data['amount'] );
				$payment = $this->insert( $booking, $data );
				return $payment;
			}
		);

		$this->announce( $booking_id, $payment );
		return $payment;
	}

	/**
	 * Write a checked row, clear *on hold* on the first money in, re-derive
	 * the booking and log it. Inside a transaction, the booking locked (also
	 * *Paid now* at booking creation, M02).
	 *
	 * @param Booking $booking Booking, read under its lock.
	 * @param array   $data    Validated row `{ type, amount (positive), method, reference, note, received_at }`.
	 * @return Payment
	 * @throws \RuntimeException When the row cannot be written.
	 */
	public function insert( Booking $booking, array $data ): Payment {
		$signed  = 'refund' === $data['type'] ? -$data['amount'] : $data['amount'];
		$receipt = 'payment' === $data['type'] ? $this->nextReceipt( $booking ) : null;
		$payment = $this->payments->create(
			array(
				'booking_id'      => (int) $booking->id,
				'type'            => $data['type'],
				'amount'          => Money::round( $signed ),
				'method'          => $data['method'],
				'reference'       => $data['reference'],
				'note'            => '' === $data['note'] ? null : $data['note'],
				'received_at'     => Dates::to_db( $data['received_at'] ),
				'received_at_gmt' => Dates::to_gmt_db( $data['received_at'] ),
				'recorded_by'     => get_current_user_id() ? get_current_user_id() : null,
				'receipt_no'      => $receipt,
			)
		);
		if ( ! $payment->id ) {
			throw new \RuntimeException( 'The payment could not be saved.' );
		}
		if ( 'payment' === $data['type'] && (int) $booking->on_hold ) {
			// Money in ends the hold (legacy parity).
			$this->bookings->update( (int) $booking->id, array( 'on_hold' => 0 ) );
		}
		$after = $this->recalculate( $booking );

		rtbp_activity(
			'payments.record',
			self::subject( $booking ),
			array(
				'before'      => array(
					'paid_total'     => Money::round( (float) $booking->paid_total ),
					'payment_status' => (string) $booking->payment_status,
				),
				'after'       => array(
					'type'           => $data['type'],
					'amount'         => Money::round( $data['amount'] ),
					'method'         => PaymentSettings::method_label( $data['method'] ),
					'reference'      => $data['reference'],
					'paid_total'     => $after['paid_total'],
					'payment_status' => $after['payment_status'],
				),
				'description' => sprintf(
					/* translators: 1: amount, 2: method, 3: booking reference. */
					'refund' === $data['type'] ? __( 'Refunded %1$s (%2$s) on %3$s', 'radius-hotel-booking' ) : __( 'Recorded %1$s (%2$s) on %3$s', 'radius-hotel-booking' ),
					Money::format( $data['amount'] ),
					PaymentSettings::method_label( $data['method'] ),
					$booking->reference
				),
			)
		);
		return $payment;
	}

	/**
	 * The next receipt number of a booking (5.12): `R-{invoice number}-{n}`,
	 * n counting the booking's receipts from 1. Under the booking lock, so two
	 * payments on one booking never share a number; the invoice is issued
	 * first when the booking predates invoices.
	 *
	 * @param Booking $booking Booking, read under its lock.
	 * @return string
	 */
	private function nextReceipt( Booking $booking ): string {
		$invoice = ( new InvoiceService() )->forBookingOrIssue( $booking );
		$count   = 0;
		foreach ( $this->payments->forBooking( (int) $booking->id ) as $row ) {
			if ( '' !== (string) $row->receipt_no ) {
				++$count;
			}
		}
		return 'R-' . $invoice->number . '-' . ( $count + 1 );
	}

	/**
	 * Void a payment or refund recorded by mistake (a negating row with the
	 * reason; the original stays, shown struck through).
	 *
	 * @param int                    $payment_id Payment id.
	 * @param string                 $reason     Why (3–191 characters).
	 * @param DateTimeImmutable|null $now        Now.
	 * @return Payment The void row.
	 * @throws DomainException 404, 409 `already_voided` / `not_voidable`, 422 reason.
	 */
	public function void( int $payment_id, string $reason, ?DateTimeImmutable $now = null ): Payment {
		self::requireKey( 'payments.void' );
		$now    = $now ?? Dates::now();
		$reason = trim( sanitize_text_field( $reason ) );
		if ( mb_strlen( $reason ) < 3 || mb_strlen( $reason ) > 191 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'reason' => __( 'Say why, in 3 to 191 characters.', 'radius-hotel-booking' ) ) );
		}
		$first = $payment_id > 0 ? $this->payments->find( $payment_id ) : null;
		if ( ! $first instanceof Payment ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This payment does not exist.', 'radius-hotel-booking' ) );
		}
		$booking_id = (int) $first->booking_id;

		$void = Transaction::run(
			function () use ( $booking_id, $payment_id, $reason, $now ) {
				// The booking lock serialises voids of one booking: the "already voided" check below holds.
				$booking  = $this->lockedBooking( $booking_id );
				$original = $this->payments->lockedFind( $payment_id );
				if ( ! in_array( (string) $original->type, array( 'payment', 'refund', 'adjustment' ), true ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'not_voidable', __( 'A void cannot be voided.', 'radius-hotel-booking' ), array( 'payment_id' => $payment_id ) );
				}
				foreach ( $this->payments->forBooking( $booking_id ) as $row ) {
					if ( (int) $row->voids_payment_id === $payment_id ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict( 'already_voided', __( 'This payment was already voided.', 'radius-hotel-booking' ), array( 'payment_id' => $payment_id ) );
					}
				}
				$void = $this->payments->create(
					array(
						'booking_id'       => $booking_id,
						'type'             => 'void',
						'amount'           => Money::round( - (float) $original->amount ),
						'method'           => (string) $original->method,
						'reference'        => (string) $original->reference,
						'note'             => $reason,
						'voids_payment_id' => $payment_id,
						'received_at'      => Dates::to_db( $now ),
						'received_at_gmt'  => Dates::to_gmt_db( $now ),
						'recorded_by'      => get_current_user_id() ? get_current_user_id() : null,
					)
				);
				if ( ! $void->id ) {
					throw new \RuntimeException( 'The void could not be saved.' );
				}
				$after = $this->recalculate( $booking );
				rtbp_activity(
					'payments.void',
					self::subject( $booking ),
					array(
						'before'      => array(
							'amount'         => Money::round( (float) $original->amount ),
							'method'         => PaymentSettings::method_label( (string) $original->method ),
							'reference'      => (string) $original->reference,
							'paid_total'     => Money::round( (float) $booking->paid_total ),
							'payment_status' => (string) $booking->payment_status,
						),
						'after'       => array(
							'reason'         => $reason,
							'paid_total'     => $after['paid_total'],
							'payment_status' => $after['payment_status'],
						),
						'description' => sprintf(
							/* translators: 1: amount, 2: booking reference. */
							__( 'Voided %1$s on %2$s', 'radius-hotel-booking' ),
							Money::format( abs( (float) $original->amount ) ),
							$booking->reference
						),
					)
				);
				return $void;
			}
		);

		$this->announce( $booking_id, $void );
		return $void;
	}

	/**
	 * Put a booking's payment on hold or take it off (legacy *On hold*: the
	 * desk is waiting for money it was promised). A payment clears it.
	 *
	 * @param int  $booking_id Booking id.
	 * @param bool $on_hold    On hold.
	 * @return Booking
	 * @throws DomainException 404, 409 `already_paid`.
	 */
	public function setOnHold( int $booking_id, bool $on_hold ): Booking {
		self::requireKey( 'payments.change_status' );
		$this->booking( $booking_id );
		Transaction::run(
			function () use ( $booking_id, $on_hold ) {
				$booking = $this->lockedBooking( $booking_id );
				if ( (bool) (int) $booking->on_hold === $on_hold ) {
					return;
				}
				if ( $on_hold && 'paid' === (string) $booking->payment_status ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'already_paid', __( 'This booking is paid: there is nothing to hold.', 'radius-hotel-booking' ) );
				}
				$this->bookings->update( $booking_id, array( 'on_hold' => $on_hold ? 1 : 0 ) );
				rtbp_activity(
					'payments.on_hold',
					self::subject( $booking ),
					array(
						'before'      => array( 'on_hold' => (bool) (int) $booking->on_hold ),
						'after'       => array( 'on_hold' => $on_hold ),
						'description' => sprintf(
							/* translators: %s: booking reference. */
							$on_hold ? __( 'Put the payment of %s on hold', 'radius-hotel-booking' ) : __( 'Took the payment of %s off hold', 'radius-hotel-booking' ),
							$booking->reference
						),
					)
				);
			}
		);
		return $this->booking( $booking_id );
	}

	/**
	 * Remind the guest to pay (5.14): the *Payment reminder* e-mail, now.
	 * Refused when nothing is due on a booking still going ahead, when the
	 * guest has no e-mail address, or when the e-mail is switched off in
	 * Settings → E-mail. Logged `payments.remind`.
	 *
	 * @param int $booking_id Booking id.
	 * @return void
	 * @throws DomainException 404, 409 `nothing_due` / `no_email` / `reminder_off` / `not_sent`.
	 */
	public function remind( int $booking_id ): void {
		self::requireKey( 'invoices.send' );
		$booking = $this->booking( $booking_id );
		if ( (float) $booking->balance_due <= 0 || ! in_array( (string) $booking->status, PaymentDeadline::OPEN, true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'nothing_due', __( 'Nothing is due on this booking.', 'radius-hotel-booking' ) );
		}
		$guest = $booking->guest_id ? ( new \RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository() )->find( (int) $booking->guest_id ) : null;
		if ( ! $guest || $guest->email_is_placeholder || ! is_email( (string) $guest->email ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'no_email', __( 'This guest has no e-mail address. Call them instead.', 'radius-hotel-booking' ) );
		}
		$email = function_exists( 'radius_hotel_booking' ) && radius_hotel_booking()->emails ? radius_hotel_booking()->emails->get_email( 'guest_payment_reminder' ) : null;
		if ( ! $email || ! $email->is_enabled() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'reminder_off', __( 'The payment reminder e-mail is switched off in Settings → E-mail.', 'radius-hotel-booking' ) );
		}
		if ( ! $email->trigger( $booking ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'not_sent', __( 'The reminder could not be sent. Check the site\'s e-mail settings.', 'radius-hotel-booking' ) );
		}
		rtbp_activity(
			'payments.remind',
			self::subject( $booking ),
			array(
				'after'       => array(
					'to'          => (string) $guest->email,
					'balance_due' => Money::round( (float) $booking->balance_due ),
				),
				'description' => sprintf(
					/* translators: %s: booking reference. */
					__( 'Sent a payment reminder for %s', 'radius-hotel-booking' ),
					$booking->reference
				),
			)
		);
	}

	/**
	 * Re-derive the booking's money from its ledger and store what changed.
	 * Inside the caller's transaction, the booking locked.
	 *
	 * @param Booking $booking Booking (its total is current).
	 * @return array `{ paid_total, balance_due, payment_status }` after.
	 */
	public function recalculate( Booking $booking ): array {
		$paid     = 0.0;
		$refunded = false;
		$voided   = array();
		$rows     = $this->payments->forBooking( (int) $booking->id );
		foreach ( $rows as $row ) {
			if ( 'void' === $row->type ) {
				$voided[ (int) $row->voids_payment_id ] = true;
			}
		}
		foreach ( $rows as $row ) {
			$paid += (float) $row->amount;
			if ( 'refund' === $row->type && ! isset( $voided[ (int) $row->id ] ) ) {
				$refunded = true;
			}
		}
		$total  = (float) $booking->total;
		$fields = array(
			'paid_total'     => Money::round( $paid ),
			'balance_due'    => Money::round( $total - $paid ),
			'payment_status' => self::statusFor( $paid, $total, $refunded ),
		);
		$changed = array();
		foreach ( $fields as $key => $value ) {
			if ( (string) $value !== (string) $booking->{$key} && ! ( is_float( $value ) && Money::equals( $value, (float) $booking->{$key} ) ) ) {
				$changed[ $key ] = $value;
			}
		}
		if ( $changed ) {
			$this->bookings->update( (int) $booking->id, $changed );
		}
		return $fields;
	}

	/**
	 * Validate a payment or refund.
	 *
	 * @param array             $input Input.
	 * @param DateTimeImmutable $now   Now.
	 * @return array `{ type, amount, method, reference, note, received_at: DateTimeImmutable }`.
	 * @throws DomainException 422.
	 */
	public function validate( array $input, DateTimeImmutable $now ): array {
		$errors = array();
		$type   = sanitize_key( (string) ( $input['type'] ?? 'payment' ) );
		if ( ! in_array( $type, array( 'payment', 'refund' ), true ) ) {
			$errors['type'] = __( 'Choose from the list.', 'radius-hotel-booking' );
		}
		$raw    = $input['amount'] ?? '';
		$amount = is_numeric( $raw ) ? Money::round( (float) $raw ) : 0.0;
		if ( $amount <= 0 ) {
			$errors['amount'] = __( 'Enter an amount above zero.', 'radius-hotel-booking' );
		}
		$method = sanitize_key( (string) ( $input['method'] ?? '' ) );
		if ( ! in_array( $method, array_column( (array) rtbp_setting( 'payments', 'methods', array() ), 'key' ), true ) ) {
			$errors['method'] = __( 'Choose how the money was paid.', 'radius-hotel-booking' );
		}
		$reference = sanitize_text_field( (string) ( $input['reference'] ?? '' ) );
		if ( mb_strlen( $reference ) > self::MAX_REFERENCE ) {
			$errors['reference'] = __( 'This is too long.', 'radius-hotel-booking' );
		}
		$note = sanitize_textarea_field( (string) ( $input['note'] ?? '' ) );
		if ( mb_strlen( $note ) > self::MAX_NOTE ) {
			$errors['note'] = __( 'This is too long.', 'radius-hotel-booking' );
		}
		$received = $now;
		$when     = trim( (string) ( $input['received_at'] ?? '' ) );
		if ( '' !== $when ) {
			$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', str_replace( 'T', ' ', substr( $when, 0, 16 ) ), Dates::timezone() );
			if ( ! $parsed ) {
				$errors['received_at'] = __( 'Enter the date and time the money was received.', 'radius-hotel-booking' );
			} elseif ( $parsed->getTimestamp() > $now->getTimestamp() + 5 * MINUTE_IN_SECONDS ) {
				$errors['received_at'] = __( 'This is in the future.', 'radius-hotel-booking' );
			} else {
				$received = $parsed;
			}
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return array(
			'type'        => $type,
			'amount'      => $amount,
			'method'      => $method,
			'reference'   => $reference,
			'note'        => $note,
			'received_at' => $received,
		);
	}

	/**
	 * A payment may not exceed what is due; a refund may not exceed what was
	 * paid. Under the booking lock.
	 *
	 * @param Booking $booking Booking.
	 * @param string  $type    payment|refund.
	 * @param float   $amount  Positive amount.
	 * @return void
	 * @throws DomainException 409 `nothing_due` / `nothing_to_refund`, 422 amount.
	 */
	private function checkAmount( Booking $booking, string $type, float $amount ): void {
		$due  = Money::round( (float) $booking->total - (float) $booking->paid_total );
		$paid = Money::round( (float) $booking->paid_total );
		if ( 'payment' === $type ) {
			if ( $due <= 0 ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict( 'nothing_due', __( 'Nothing is due on this booking.', 'radius-hotel-booking' ), array( 'balance_due' => $due ) );
			}
			if ( Money::round( $amount ) > $due ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'amount' => sprintf( /* translators: %s: amount due. */ __( 'This is more than the %s due.', 'radius-hotel-booking' ), Money::format( $due ) ) ) );
			}
			return;
		}
		if ( $paid <= 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'nothing_to_refund', __( 'Nothing was paid on this booking.', 'radius-hotel-booking' ) );
		}
		if ( Money::round( $amount ) > $paid ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'amount' => sprintf( /* translators: %s: amount paid. */ __( 'This is more than the %s paid.', 'radius-hotel-booking' ), Money::format( $paid ) ) ) );
		}
	}

	/**
	 * Tell listeners after the commit (receipts and e-mails, M05 T4a / T5).
	 * Public for *Paid now*, written by the booking's own transaction.
	 *
	 * @param int     $booking_id Booking id.
	 * @param Payment $payment    The new row.
	 * @return void
	 */
	public function announce( int $booking_id, Payment $payment ): void {
		Transaction::afterCommit(
			function () use ( $booking_id, $payment ) {
				$booking = $this->bookings->find( $booking_id );
				if ( ! $booking instanceof Booking ) {
					return;
				}
				/**
				 * A ledger row was written (after the commit): a payment, a refund or a void.
				 *
				 * @param Payment $payment The row.
				 * @param Booking $booking The booking as it is now.
				 */
				do_action( 'rtbp_payment_recorded', $payment, $booking );
			}
		);
	}

	/**
	 * The activity subject of a booking.
	 *
	 * @param Booking $booking Booking.
	 * @return array
	 */
	private static function subject( Booking $booking ): array {
		return array(
			'type'  => 'booking',
			'id'    => (int) $booking->id,
			'label' => (string) $booking->reference,
		);
	}

	/**
	 * One booking, or 404.
	 *
	 * @param int $id Booking id.
	 * @return Booking
	 * @throws DomainException 404.
	 */
	private function booking( int $id ): Booking {
		$booking = $id > 0 ? $this->bookings->find( $id ) : null;
		if ( ! $booking instanceof Booking ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
		}
		return $booking;
	}

	/**
	 * Lock the booking and read it.
	 *
	 * @param int $id Booking id.
	 * @return Booking
	 * @throws DomainException 404.
	 */
	private function lockedBooking( int $id ): Booking {
		$booking = $this->bookings->lockedFind( $id );
		if ( ! $booking instanceof Booking ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
		}
		return $booking;
	}

	/**
	 * Whether the current user holds an access key (services re-check).
	 *
	 * @param string $key Key.
	 * @return void
	 * @throws DomainException 403 `access_locked`.
	 */
	public static function requireKey( string $key ): void {
		if ( class_exists( Access::class ) && ! Access::can( $key ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'access_locked', __( 'You do not have permission to do this.', 'radius-hotel-booking' ), 403, array(), array( 'key' => $key ) );
		}
	}
}
