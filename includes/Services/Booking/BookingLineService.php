<?php
/**
 * Adding, editing and removing a booking's rooms (M03, 3.11, 3.12, 3.16).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\HoldRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Every change runs the §7.1 write path: transaction → lock the booking and
 * the line (locking reads) → `BookingWriter::lockAndCheck()` locks the rooms,
 * re-checks and re-quotes → write → stored totals and summary status follow
 * the lines → `bookings.line_*` logged inside the transaction →
 * `rtbp_booking_changed` after the commit (M05 re-issues the invoice).
 *
 * **Price freeze (3.16).** A line keeps the price it was sold at. An edit
 * re-prices **only that line**, and only when what is priced changes: the
 * rate plan, the room type (a room of another type) or the window (date,
 * length, check-in time). A room of the same type or other guest counts keep
 * the frozen price. The old and new price are logged.
 */
class BookingLineService {

	/**
	 * Line statuses a room can still be edited or removed in: once the guest
	 * is in, the stay is history (a room move is check-in's job).
	 */
	public const EDITABLE = array( 'pending', 'confirmed' );

	/**
	 * Booking statuses a room can be added to.
	 */
	public const OPEN = array( 'pending', 'confirmed', 'checked_in' );

	/**
	 * Line statuses that ended without a stay: they no longer count as rooms of the booking.
	 */
	public const ENDED = array( 'declined', 'cancelled', 'no_show' );

	/**
	 * How many of the lines are still rooms of the booking (for the last-room
	 * rule and the room limit).
	 *
	 * @param BookingRoom[] $lines Lines.
	 * @return int
	 */
	public static function liveCount( array $lines ): int {
		return count( array_filter( $lines, static fn( $line ) => ! in_array( (string) $line->status, self::ENDED, true ) ) );
	}

	/**
	 * The locked write path.
	 *
	 * @var BookingWriter
	 */
	private BookingWriter $writer;

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
	 * @param HoldService|null           $holds    Holds.
	 * @param BookingRepository|null     $bookings Bookings.
	 * @param BookingRoomRepository|null $lines    Booking lines.
	 */
	public function __construct( ?BookingWriter $writer = null, ?HoldService $holds = null, ?BookingRepository $bookings = null, ?BookingRoomRepository $lines = null ) {
		$this->writer   = $writer ?? new BookingWriter();
		$this->holds    = $holds ?? new HoldService();
		$this->bookings = $bookings ?? new BookingRepository();
		$this->lines    = $lines ?? new BookingRoomRepository();
	}

	/**
	 * Add a room to a booking (3.11): the same availability, lock and price
	 * path as creating one.
	 *
	 * @param int                    $booking_id Booking id.
	 * @param array                  $input      `{ room_id, rate_plan_id, arrival, units?, checkin_time?, adults,
	 *                                           children?, room_type_id?, expected_total?, hold_token?, accept_new_price? }`.
	 * @param array                  $actor      `{ audience, user_id? }` (the hold's owner).
	 * @param DateTimeImmutable|null $now        Now (checks).
	 * @return int The new line's id.
	 * @throws DomainException 404, 409 `booking_closed` / engine codes, 422.
	 */
	public function add( int $booking_id, array $input, array $actor, ?DateTimeImmutable $now = null ): int {
		$now    = $now ?? Dates::now();
		$errors = array();
		$req    = BookingService::lineRequest( $input, '', $errors );
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		$token = sanitize_text_field( (string) ( $input['hold_token'] ?? '' ) );
		$this->holds->assertUsable( $token, $actor );

		$result = Transaction::run(
			function () use ( $booking_id, $req, $token, $input, $now ) {
				$booking = $this->lockedBooking( $booking_id );
				if ( ! in_array( (string) $booking->status, self::OPEN, true ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'booking_closed', __( 'This booking is closed: a room cannot be added to it.', 'radius-hotel-booking' ), array( 'status' => (string) $booking->status ) );
				}
				$checked = $this->writer->lockAndCheck(
					array( $req ),
					$token,
					array(
						'audience'         => AvailabilityService::STAFF,
						'accept_new_price' => self::flag( $input['accept_new_price'] ?? false ),
						'now'              => $now,
					)
				);
				$item = $checked[0];

				// Read after the room lock (booking-engine §7.1: no plain read before it).
				if ( self::liveCount( $this->lines->forBooking( $booking_id ) ) >= BookingService::MAX_LINES ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::invalid( array( 'room_id' => sprintf( /* translators: %d: most rooms per booking. */ __( 'A booking can take at most %d rooms.', 'radius-hotel-booking' ), BookingService::MAX_LINES ) ) );
				}

				// A room added to a booking still waiting for approval waits with it.
				$status = 'pending' === (string) $booking->status ? 'pending' : 'confirmed';
				$line   = $this->lines->create(
					array_merge(
						BookingWriter::lineFields( $item, $req ),
						array(
							'booking_id' => $booking_id,
							'status'     => $status,
						)
					)
				);
				if ( ! $line->id ) {
					throw new \RuntimeException( 'The booking line could not be saved.' );
				}
				if ( '' !== $token ) {
					( new HoldRepository() )->delete( $token );
				}

				$after = self::describe( $line );
				$money = $this->settle( $booking );
				$this->log( 'line_add', $booking, (string) $line->room_number, array(), $after, $money );
				return array( (int) $line->id, array(), $after );
			}
		);

		$this->announce( $booking_id, 'line_add', $result[0], $result[1], $result[2] );
		return $result[0];
	}

	/**
	 * Edit a room (3.12): room, rate plan, dates, length, check-in time,
	 * guests. Missing fields keep their current value. The new window is
	 * checked under the lock, the line's own occupancy left out.
	 *
	 * @param int                    $line_id Line id.
	 * @param array                  $input   As `add()`; every field optional.
	 * @param array                  $actor   `{ audience, user_id? }`.
	 * @param DateTimeImmutable|null $now     Now (checks).
	 * @return int The booking id.
	 * @throws DomainException 404, 409 `illegal_transition` / `price_changed` / engine codes, 422.
	 */
	public function edit( int $line_id, array $input, array $actor, ?DateTimeImmutable $now = null ): int {
		$now        = $now ?? Dates::now();
		$booking_id = $this->bookingOf( $line_id );
		$token      = sanitize_text_field( (string) ( $input['hold_token'] ?? '' ) );
		$this->holds->assertUsable( $token, $actor );

		$result = Transaction::run(
			function () use ( $booking_id, $line_id, $input, $token, $now ) {
				$booking = $this->lockedBooking( $booking_id );
				$line    = $this->lockedLine( $line_id, 'line_edit' );

				// The request: what was sent, over what the line has now.
				$current = array(
					'room_id'      => (int) $line->room_id,
					'rate_plan_id' => (int) $line->rate_plan_id,
					'arrival'      => substr( (string) $line->start_at, 0, 10 ),
					'units'        => (int) $line->units,
					'checkin_time' => substr( (string) $line->start_at, 11, 5 ),
					'adults'       => (int) $line->adults,
					'children'     => (int) $line->children,
				);
				$sent    = array_intersect_key( $input, array_flip( array( 'room_id', 'room_type_id', 'rate_plan_id', 'arrival', 'units', 'checkin_time', 'adults', 'children' ) ) );
				$errors  = array();
				$req     = BookingService::lineRequest( array_merge( $current, $sent ), '', $errors );
				if ( $errors ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::invalid( $errors );
				}

				// The price shown is compared below, only when the line is re-priced.
				$checked = $this->writer->lockAndCheck(
					array( $req ),
					$token,
					array(
						'audience'     => AvailabilityService::STAFF,
						'exclude_line' => $line_id,
						'now'          => $now,
					)
				);
				$item   = $checked[0];
				$fields = BookingWriter::lineFields( $item, $req );

				$reprice = (int) $item['rate_plan_id'] !== (int) $line->rate_plan_id
					|| (int) $item['room_type_id'] !== (int) $line->room_type_id
					|| $fields['start_at_gmt'] !== (string) $line->start_at_gmt
					|| $fields['end_at_gmt'] !== (string) $line->end_at_gmt;
				if ( $reprice ) {
					$shown = $input['expected_total'] ?? null;
					if ( is_numeric( $shown ) && ! self::flag( $input['accept_new_price'] ?? false ) && ! Money::equals( (float) $shown, (float) $fields['total'] ) ) {
						// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict(
							'price_changed',
							__( 'The price has changed since it was shown. Check the new price and confirm again.', 'radius-hotel-booking' ),
							array(
								'index' => 0,
								'quote' => $item['quote'],
							)
						);
						// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
					}
				} else {
					// Nothing priced changed: the frozen price stays (3.16).
					unset( $fields['unit_price'], $fields['total'], $fields['price_breakdown'] );
				}

				// Snapshots stay as sold unless the room or the plan itself changed.
				if ( (int) $item['room']['id'] === (int) $line->room_id ) {
					unset( $fields['room_number'], $fields['floor_name'] );
				}
				if ( (int) $item['rate_plan_id'] === (int) $line->rate_plan_id ) {
					unset( $fields['rate_plan_name'] );
				}

				$before  = self::describe( $line );
				$changed = array();
				foreach ( $fields as $key => $value ) {
					if ( (string) $value !== (string) $line->{$key} ) {
						$changed[ $key ] = $value;
					}
				}
				if ( ! $changed ) {
					return array( $booking_id, array(), array() );
				}
				// A pending or confirmed line is occupied to its end: `occupied_until` follows it.
				$this->lines->update( $line_id, $changed );
				$after = self::describe( $this->lines->find( $line_id ) );
				$diff  = array_diff_assoc( $after, $before );
				$old   = array_intersect_key( $before, $diff );

				$money = $this->settle( $booking );
				$this->log( 'line_edit', $booking, (string) $line->room_number, $old, $diff, $money );
				if ( '' !== $token ) {
					( new HoldRepository() )->delete( $token );
				}
				return array( $booking_id, $old, $diff );
			}
		);

		if ( $result[2] ) {
			$this->announce( $booking_id, 'line_edit', $line_id, $result[1], $result[2] );
		}
		return $booking_id;
	}

	/**
	 * Remove a room from a booking (3.12): a room booked by mistake. The
	 * line row goes (the activity log keeps what it was); the room is free
	 * at once. The booking's only room is not removed — cancel the booking.
	 *
	 * @param int $line_id Line id.
	 * @return int The booking id.
	 * @throws DomainException 404, 409 `illegal_transition` / `last_line`.
	 */
	public function remove( int $line_id ): int {
		$booking_id = $this->bookingOf( $line_id );

		$before = Transaction::run(
			function () use ( $booking_id, $line_id ) {
				$booking = $this->lockedBooking( $booking_id );
				$line    = $this->lockedLine( $line_id, 'line_remove' );
				// Cancelled, declined and no-show rooms do not count: removing the last live room would end the booking unlogged.
				if ( self::liveCount( $this->lines->forBooking( $booking_id ) ) <= 1 ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'last_line', __( 'This is the only room on the booking. Cancel the booking instead.', 'radius-hotel-booking' ), array( 'line_id' => $line_id ) );
				}
				$before = self::describe( $line );
				if ( ! $this->lines->delete( $line_id ) ) {
					throw new \RuntimeException( 'The booking line could not be removed.' );
				}
				$money = $this->settle( $booking );
				$this->log( 'line_remove', $booking, (string) $line->room_number, $before, array(), $money );
				return $before;
			}
		);

		$this->announce( $booking_id, 'line_remove', $line_id, $before, array() );
		return $booking_id;
	}

	/**
	 * Store the booking's summary status and totals from its lines as they are now.
	 *
	 * @param Booking $booking Booking, read under its lock (the old values).
	 * @return array The changed money fields.
	 */
	private function settle( Booking $booking ): array {
		$lines  = $this->lines->forBooking( (int) $booking->id );
		$money  = BookingTotals::changes( $booking, $lines );
		$status = StatusMachine::summary( array_map( static fn( $line ) => (string) $line->status, $lines ) );
		$fields = array_merge( $money, $status !== (string) $booking->status ? array( 'status' => $status ) : array() );
		if ( $fields ) {
			$this->bookings->update( (int) $booking->id, $fields );
		}
		return $money;
	}

	/**
	 * What a line is, in words, for the log and the hook.
	 *
	 * @param BookingRoom $line Line.
	 * @return array `{ room, rate_plan, stay, guests, price }`.
	 */
	private static function describe( BookingRoom $line ): array {
		return array(
			'room'      => (string) $line->room_number,
			'rate_plan' => (string) $line->rate_plan_name,
			// An en dash: the log already puts an arrow between the old and the new value.
			'stay'      => substr( (string) $line->start_at, 0, 16 ) . ' – ' . substr( (string) $line->end_at, 0, 16 ),
			'guests'    => (int) $line->adults . ' + ' . (int) $line->children,
			'price'     => Money::round( (float) $line->total ),
		);
	}

	/**
	 * Log a change, inside the transaction.
	 *
	 * @param string  $action  line_add|line_edit|line_remove.
	 * @param Booking $booking Booking (old values).
	 * @param string  $room    Room number (before an edit).
	 * @param array   $before  Old values.
	 * @param array   $after   New values.
	 * @param array   $money   Changed booking money.
	 * @return void
	 */
	private function log( string $action, Booking $booking, string $room, array $before, array $after, array $money ): void {
		if ( isset( $money['total'] ) ) {
			$before['total'] = Money::round( (float) $booking->total );
			$after['total']  = Money::round( (float) $money['total'] );
		}
		$descriptions = array(
			/* translators: 1: room number, 2: booking reference. */
			'line_add'    => __( 'Added room %1$s to %2$s', 'radius-hotel-booking' ),
			/* translators: 1: room number, 2: booking reference. */
			'line_edit'   => __( 'Changed room %1$s on %2$s', 'radius-hotel-booking' ),
			/* translators: 1: room number, 2: booking reference. */
			'line_remove' => __( 'Removed room %1$s from %2$s', 'radius-hotel-booking' ),
		);
		rtbp_activity(
			'bookings.' . $action,
			array(
				'type'  => 'booking',
				'id'    => (int) $booking->id,
				'label' => (string) $booking->reference,
			),
			array(
				'before'      => $before,
				'after'       => $after,
				'description' => sprintf( $descriptions[ $action ], $room, $booking->reference ),
			)
		);
	}

	/**
	 * Tell listeners after the commit (M05 re-issues the invoice).
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $action     line_add|line_edit|line_remove.
	 * @param int    $line_id    Line id (a removed line's former id).
	 * @param array  $before     Old values.
	 * @param array  $after      New values.
	 * @return void
	 */
	private function announce( int $booking_id, string $action, int $line_id, array $before, array $after ): void {
		Transaction::afterCommit(
			function () use ( $booking_id, $action, $line_id, $before, $after ) {
				$booking = $this->bookings->find( $booking_id );
				if ( ! $booking instanceof Booking ) {
					return;
				}
				/**
				 * A booking's rooms changed (after the commit).
				 *
				 * @param Booking $booking The booking as it is now.
				 * @param string  $action  line_add|line_edit|line_remove.
				 * @param int     $line_id The line.
				 * @param array   $before  Old values (`room`, `rate_plan`, `stay`, `guests`, `price`).
				 * @param array   $after   New values.
				 */
				do_action( 'rtbp_booking_changed', $booking, $action, $line_id, $before, $after );
			}
		);
	}

	/**
	 * The booking a line belongs to (read before the transaction, to lock it first).
	 *
	 * @param int $line_id Line id.
	 * @return int
	 * @throws DomainException 404.
	 */
	private function bookingOf( int $line_id ): int {
		$line = $line_id > 0 ? $this->lines->find( $line_id ) : null;
		if ( ! $line instanceof BookingRoom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
		}
		return (int) $line->booking_id;
	}

	/**
	 * Lock and read the booking.
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
	 * Lock and read a line that may still be changed.
	 *
	 * @param int    $id     Line id.
	 * @param string $action line_edit|line_remove (for the error).
	 * @return BookingRoom
	 * @throws DomainException 404, 409 `illegal_transition`.
	 */
	private function lockedLine( int $id, string $action ): BookingRoom {
		$line = $this->lines->lockedFind( $id );
		if ( ! $line instanceof BookingRoom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
		}
		if ( ! in_array( (string) $line->status, self::EDITABLE, true ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'illegal_transition',
				__( 'This room can no longer be changed: only a pending or confirmed room can.', 'radius-hotel-booking' ),
				array(
					'action'  => $action,
					'from'    => (string) $line->status,
					'line_id' => $id,
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $line;
	}

	/**
	 * A JSON or form boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function flag( $value ): bool {
		return ! empty( $value ) && 'false' !== $value;
	}
}
