<?php
/**
 * Creating bookings (M02).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\Guest;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\HoldRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;
use RadiusTheme\RadiusHotelBooking\Support\Sequence;

defined( 'ABSPATH' ) || exit;

/**
 * `create()` writes a booking and its lines on the locked path
 * (booking-engine §7.1): inside one transaction it locks the rooms and
 * re-checks every line (`BookingWriter::lockAndCheck()`), refuses a price
 * that moved unless accepted, then resolves the guest, writes the booking
 * (reference `RT-{year}-{000001}`), the lines (snapshots and the frozen
 * price), consumes the holds and logs `bookings.create`. Any failure rolls
 * everything back: a multi-room booking never half-succeeds. The
 * `rtbp_booking_created` action fires after the commit.
 *
 * Nothing is read inside the transaction before the lock: on hosts where
 * READ COMMITTED is unavailable the guarantee rests on that (§7.1).
 */
class BookingService {

	/**
	 * Most lines one booking can take.
	 */
	public const MAX_LINES = 10;

	/**
	 * The locked write path.
	 *
	 * @var BookingWriter
	 */
	private BookingWriter $writer;

	/**
	 * Guests.
	 *
	 * @var GuestService
	 */
	private GuestService $guests;

	/**
	 * Holds.
	 *
	 * @var HoldService
	 */
	private HoldService $holds;

	/**
	 * Bookings.
	 *
	 * @var BookingRepository
	 */
	private BookingRepository $bookings;

	/**
	 * Booking lines.
	 *
	 * @var BookingRoomRepository
	 */
	private BookingRoomRepository $lines;

	/**
	 * Constructor.
	 *
	 * @param BookingWriter|null         $writer   The locked write path.
	 * @param GuestService|null          $guests   Guests.
	 * @param HoldService|null           $holds    Holds.
	 * @param BookingRepository|null     $bookings Bookings.
	 * @param BookingRoomRepository|null $lines    Booking lines.
	 */
	public function __construct( ?BookingWriter $writer = null, ?GuestService $guests = null, ?HoldService $holds = null, ?BookingRepository $bookings = null, ?BookingRoomRepository $lines = null ) {
		$this->writer   = $writer ?? new BookingWriter();
		$this->guests   = $guests ?? new GuestService();
		$this->holds    = $holds ?? new HoldService();
		$this->bookings = $bookings ?? new BookingRepository();
		$this->lines    = $lines ?? new BookingRoomRepository();
	}

	/**
	 * Create a booking.
	 *
	 * @param array                  $input  `{ hold_token?, lines: [ { room_id, rate_plan_id, arrival, units?,
	 *                                       checkin_time?, adults, children?, room_type_id?, expected_total? } ],
	 *                                       guest_id | guest: { first_name, last_name, phone, email?, id_type?,
	 *                                       id_number? }, payment_state (unpaid|paid), note?, accept_new_price? }`.
	 * @param array                  $actor  `{ audience (staff|public), user_id?, session_key? }`.
	 * @param string                 $source `desk` (default), `web`, `import`.
	 * @param DateTimeImmutable|null $now    Now (checks).
	 * @return Booking
	 * @throws DomainException 409 `room_unavailable` / `price_changed` / `guest_banned` / engine rules, 422 input.
	 */
	public function create( array $input, array $actor, string $source = 'desk', ?DateTimeImmutable $now = null ): Booking {
		$now      = $now ?? Dates::now();
		$source   = in_array( $source, Booking::SOURCES, true ) ? $source : 'desk';
		$audience = AvailabilityService::PUBLIC === ( $actor['audience'] ?? '' ) ? AvailabilityService::PUBLIC : AvailabilityService::STAFF;
		$data     = $this->validate( $input );
		$token    = $data['hold_token'];

		// Outside the transaction: fast answers before any lock is taken.
		$this->holds->assertUsable( $token, $actor );
		// "Paid now" marks money as taken: M13's `payments.record` (legacy mark-as-paid).
		if ( 'paid' === $data['payment_state'] ) {
			self::requireKey( 'payments.record' );
		}
		if ( ! $data['guest_id'] ) {
			self::requireKey( 'guests.create' );
		}
		if ( $data['guest_id'] ) {
			$this->refuseBanned( $this->guests->get( $data['guest_id'] ) );
		}

		$id = Transaction::run(
			function () use ( $data, $token, $audience, $source, $now ) {
				// 1–3. Lock the rooms, re-check, re-quote. Nothing is read before this.
				$checked = $this->writer->lockAndCheck(
					$data['requests'],
					$token,
					array(
						'audience'         => $audience,
						'accept_new_price' => $data['accept_new_price'],
						'now'              => $now,
					)
				);

				// The guest after the lock (a new one is created in this transaction).
				$guest = $data['guest_id'] ? $this->guests->get( $data['guest_id'] ) : $this->newGuest( $data['guest'] );
				$this->refuseBanned( $guest );

				// 4. Write.
				$floors = array();
				foreach ( ( new FloorRepository() )->findMany( array_values( array_unique( array_map( static fn( $item ) => (int) $item['room']['floor_id'], $checked ) ) ) ) as $floor ) {
					$floors[ (int) $floor->id ] = (string) $floor->name;
				}
				$status   = BookingWriter::initialStatus( $source );
				$total    = Money::sum( array_map( static fn( $item ) => (float) $item['quote']['total'], $checked ) );
				$paid     = 'paid' === $data['payment_state'];
				$year     = $now->setTimezone( Dates::timezone() )->format( 'Y' );
				$user_id  = get_current_user_id();
				$booking  = $this->bookings->create(
					array(
						'reference'        => Sequence::format( 'RT-' . $year . '-', Sequence::next( 'booking_' . $year ) ),
						'guest_id'         => (int) $guest->id,
						'source'           => $source,
						'status'           => $status,
						'payment_status'   => $paid ? 'paid' : 'unpaid',
						'subtotal'         => $total,
						'total'            => $total,
						'paid_total'       => $paid ? $total : 0,
						'balance_due'      => $paid ? 0 : $total,
						'currency'         => (string) rtbp_setting( 'general', 'currencyCode', 'XOF' ),
						'adults'           => array_sum( array_column( $data['requests'], 'adults' ) ),
						'children'         => array_sum( array_column( $data['requests'], 'children' ) ),
						'special_requests' => $data['note'],
						'public_token'     => bin2hex( random_bytes( 16 ) ),
						'created_by'       => 'web' === $source || ! $user_id ? null : $user_id,
						'created_at_gmt'   => Dates::to_gmt_db( $now ),
					)
				);
				if ( ! $booking->id ) {
					throw new \RuntimeException( 'The booking could not be saved.' );
				}

				$logged_lines = array();
				foreach ( $checked as $index => $item ) {
					$request = $data['requests'][ $index ];
					$quote   = $item['quote'];
					$row     = $item['window']->toRow();
					$line = $this->lines->create(
						array_merge(
							$row,
							array(
								'booking_id'         => (int) $booking->id,
								'room_id'            => (int) $item['room']['id'],
								'room_type_id'       => (int) $item['room_type_id'],
								'rate_plan_id'       => (int) $item['rate_plan_id'],
								'rate_plan_name'     => (string) $item['plan']->name,
								'room_number'        => (string) $item['room']['number'],
								'floor_name'         => $floors[ (int) $item['room']['floor_id'] ] ?? '',
								'occupied_until_gmt' => $row['end_at_gmt'],
								'units'              => $item['window']->units(),
								'adults'             => $request['adults'],
								'children'           => $request['children'],
								'status'             => $status,
								'unit_price'         => Money::round( (float) $quote['total'] / max( 1, $item['window']->units() ) ),
								'total'              => (float) $quote['total'],
								'price_breakdown'    => wp_json_encode(
									array(
										'unit_prices' => $quote['unit_prices'],
										'steps'       => $quote['steps'],
									)
								),
							)
						)
					);
					if ( ! $line->id ) {
						// A failed insert must not commit a booking without its room (the room would stay sellable).
						throw new \RuntimeException( 'A booking line could not be saved.' );
					}
					$logged_lines[] = sprintf( '%s · %s · %s', $item['room']['number'], $item['plan']->name, $row['start_at'] );
				}

				// The holds were for this booking.
				if ( '' !== $token ) {
					( new HoldRepository() )->delete( $token );
				}

				// 5. Log, inside the transaction.
				rtbp_activity(
					'bookings.create',
					array(
						'type'  => 'booking',
						'id'    => (int) $booking->id,
						'label' => (string) $booking->reference,
					),
					array(
						'after'       => array(
							'reference'      => (string) $booking->reference,
							'guest'          => $guest->fullName(),
							'lines'          => implode( '; ', $logged_lines ),
							'total'          => $total,
							'payment_status' => $paid ? 'paid' : 'unpaid',
							'status'         => $status,
							'source'         => $source,
						),
						'description' => sprintf(
							/* translators: 1: booking reference, 2: guest name, 3: number of rooms. */
							_n( 'Created booking %1$s for %2$s (%3$d room)', 'Created booking %1$s for %2$s (%3$d rooms)', count( $checked ), 'radius-hotel-booking' ),
							$booking->reference,
							$guest->fullName(),
							count( $checked )
						),
					)
				);
				return (int) $booking->id;
			}
		);

		$booking = $this->get( $id );

		// After the outermost commit: an import (M18) may wrap many bookings in one transaction.
		Transaction::afterCommit(
			static function () use ( $booking, $source ) {
				/**
				 * A booking was created (after the commit): e-mails, notifications.
				 *
				 * @param Booking $booking The booking.
				 * @param string  $source  desk|web|import.
				 */
				do_action( 'rtbp_booking_created', $booking, $source );
			}
		);

		return $booking;
	}

	/**
	 * One booking, or 404.
	 *
	 * @param int $id Id.
	 * @return Booking
	 * @throws DomainException 404.
	 */
	public function get( int $id ): Booking {
		$booking = $id > 0 ? $this->bookings->find( $id ) : null;
		if ( ! $booking instanceof Booking ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
		}
		return $booking;
	}

	/**
	 * A booking's lines.
	 *
	 * @param int $booking_id Booking id.
	 * @return \RadiusTheme\RadiusHotelBooking\Models\BookingRoom[]
	 */
	public function lines( int $booking_id ): array {
		return $this->lines->forBooking( $booking_id );
	}

	/**
	 * The guest of a "new guest" booking. A phone or e-mail already on file
	 * under **another name** is refused (409 `guest_exists` with the match),
	 * so the desk picks that guest or corrects the details — never a silent
	 * booking under someone else. Under the same name, that guest is used and
	 * their empty details are filled in only with `guests.edit`.
	 *
	 * @param array $input New guest fields.
	 * @return Guest
	 * @throws DomainException 409 `guest_exists`, 422 fields.
	 */
	private function newGuest( array $input ): Guest {
		try {
			return $this->guests->create( $input );
		} catch ( DomainException $e ) {
			$match = $e->getContext()['guests'][0] ?? null;
			if ( 'guest_exists' !== $e->getErrorCode() || ! $match ) {
				throw $e;
			}
			$existing = $this->guests->get( (int) $match['id'] );
			$typed    = trim( (string) ( $input['first_name'] ?? '' ) . ' ' . (string) ( $input['last_name'] ?? '' ) );
			if ( self::nameKey( $typed ) !== self::nameKey( $existing->fullName() ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict(
					'guest_exists',
					sprintf(
						/* translators: 1: 'phone' or 'e-mail', 2: guest name, 3: guest reference. */
						__( 'This %1$s is already on file for %2$s (%3$s). Use that guest, or correct the details.', 'radius-hotel-booking' ),
						'email' === ( $match['match'] ?? '' ) ? __( 'e-mail', 'radius-hotel-booking' ) : __( 'phone', 'radius-hotel-booking' ),
						$existing->fullName(),
						$existing->reference
					),
					$e->getContext()
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return Access::can( 'guests.edit' ) ? $this->guests->findOrCreate( $input ) : $existing;
		}
	}

	/**
	 * A name for comparing: lower case, no accents, single spaces.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function nameKey( string $name ): string {
		return (string) preg_replace( '/\s+/', ' ', trim( mb_strtolower( remove_accents( $name ) ) ) );
	}

	/**
	 * Refuse unless the current user holds a key (services re-check what the
	 * controller asked for; a PIN-level key passes with the request's grant).
	 *
	 * @param string $key Access key.
	 * @return void
	 * @throws DomainException 403 `access_locked`.
	 */
	private static function requireKey( string $key ): void {
		if ( class_exists( Access::class ) && ! Access::can( $key ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'access_locked', __( 'You do not have permission to do this.', 'radius-hotel-booking' ), 403, array(), array( 'key' => $key ) );
		}
	}

	/**
	 * Refuse a banned guest (9.11).
	 *
	 * @param Guest $guest Guest.
	 * @return void
	 * @throws DomainException 409 `guest_banned`.
	 */
	private function refuseBanned( Guest $guest ): void {
		if ( Guest::BANNED === (string) $guest->standing ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'guest_banned', __( 'This guest is banned. A booking cannot be made for them.', 'radius-hotel-booking' ), array( 'guest_id' => (int) $guest->id ) );
		}
	}

	/**
	 * Validate the input (the engine checks the rooms, windows and prices).
	 *
	 * @param array $input Input.
	 * @return array `{ requests, guest_id, guest, payment_state, note, hold_token, accept_new_price }`.
	 * @throws DomainException 422.
	 */
	private function validate( array $input ): array {
		$errors = array();
		$lines  = isset( $input['lines'] ) && is_array( $input['lines'] ) ? array_values( $input['lines'] ) : array();
		if ( ! $lines ) {
			$errors['lines'] = __( 'Add at least one room.', 'radius-hotel-booking' );
		} elseif ( count( $lines ) > self::MAX_LINES ) {
			/* translators: %d: most rooms per booking. */
			$errors['lines'] = sprintf( __( 'A booking can take at most %d rooms.', 'radius-hotel-booking' ), self::MAX_LINES );
		}

		$requests = array();
		foreach ( $lines as $index => $line ) {
			$line    = is_array( $line ) ? $line : array();
			$arrival = sanitize_text_field( (string) ( $line['arrival'] ?? '' ) );
			$adults  = (int) ( $line['adults'] ?? 1 );
			$kids    = (int) ( $line['children'] ?? 0 );
			if ( ! Dates::is_date( $arrival ) ) {
				$errors[ "lines.$index.arrival" ] = __( 'Choose the arrival date.', 'radius-hotel-booking' );
			}
			if ( (int) ( $line['room_id'] ?? 0 ) <= 0 ) {
				$errors[ "lines.$index.room_id" ] = __( 'Choose a room.', 'radius-hotel-booking' );
			}
			if ( (int) ( $line['rate_plan_id'] ?? 0 ) <= 0 ) {
				$errors[ "lines.$index.rate_plan_id" ] = __( 'Choose a rate.', 'radius-hotel-booking' );
			}
			if ( $adults < 1 || $adults > 50 || $kids < 0 || $kids > 50 ) {
				$errors[ "lines.$index.adults" ] = __( 'Enter the number of guests.', 'radius-hotel-booking' );
			}
			$checkin   = sanitize_text_field( (string) ( $line['checkin_time'] ?? '' ) );
			$request   = array(
				'room_id'      => (int) ( $line['room_id'] ?? 0 ),
				'room_type_id' => (int) ( $line['room_type_id'] ?? 0 ),
				'rate_plan_id' => (int) ( $line['rate_plan_id'] ?? 0 ),
				'arrival'      => $arrival,
				'units'        => max( 1, (int) ( $line['units'] ?? 1 ) ),
				'checkin_time' => $checkin,
				'adults'       => $adults,
				'children'     => $kids,
			);
			$has_price = isset( $line['expected_total'] ) && is_numeric( $line['expected_total'] );
			if ( $has_price ) {
				$request['expected_total'] = (float) $line['expected_total'];
			}
			$requests[] = $request;
		}

		$guest_id = (int) ( $input['guest_id'] ?? 0 );
		$guest    = isset( $input['guest'] ) && is_array( $input['guest'] ) ? $input['guest'] : array();
		if ( ! $guest_id && ! $guest ) {
			$errors['guest'] = __( 'Choose the guest or add a new one.', 'radius-hotel-booking' );
		}

		$payment = sanitize_key( (string) ( $input['payment_state'] ?? 'unpaid' ) );
		if ( ! in_array( $payment, Booking::PAYMENT_STATES, true ) ) {
			$errors['payment_state'] = __( 'Choose from the list.', 'radius-hotel-booking' );
		}
		$note = sanitize_textarea_field( (string) ( $input['note'] ?? '' ) );
		if ( mb_strlen( $note ) > 2000 ) {
			$errors['note'] = __( 'This is too long.', 'radius-hotel-booking' );
		}
		$token = sanitize_text_field( (string) ( $input['hold_token'] ?? '' ) );

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return array(
			'requests'         => $requests,
			'guest_id'         => $guest_id,
			'guest'            => $guest,
			'payment_state'    => $payment,
			'note'             => $note,
			'hold_token'       => $token,
			'accept_new_price' => ! empty( $input['accept_new_price'] ) && 'false' !== $input['accept_new_price'],
		);
	}
}
