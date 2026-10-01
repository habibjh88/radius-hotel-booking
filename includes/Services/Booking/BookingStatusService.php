<?php
/**
 * Booking status transitions (M03).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\PaymentDeadline;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Every status change of a booking (3.5–3.10), each a guarded move of the
 * `StatusMachine`:
 *
 * - approve / decline / cancel act on the whole booking (every line the move
 *   applies to) or on one line; decline and cancel need a reason; declined
 *   and cancelled lines leave the occupying set in the same transaction
 *   (booking-engine §8);
 * - check-in / check-out / no-show are per line. Check-in can move the guest
 *   to another room of the same type: both rooms are locked (ascending id)
 *   and the new one is checked against the line's own window with the
 *   engine's predicate. An early check-out frees the room from now on
 *   (`occupied_until_gmt`). A no-show is possible once the window started.
 *
 * Each change locks the booking row first, re-reads the lines under that
 * lock, stores the booking's summary status, is logged inside the
 * transaction and announced after the commit (`rtbp_booking_status_changed`).
 * A move the machine does not allow is refused: 409 `illegal_transition`.
 */
class BookingStatusService {

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
	 * Engine queries.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $availability;

	/**
	 * Constructor.
	 *
	 * @param BookingRepository|null      $bookings     Bookings.
	 * @param BookingRoomRepository|null  $lines        Booking lines.
	 * @param AvailabilityRepository|null $availability Engine queries.
	 */
	public function __construct( ?BookingRepository $bookings = null, ?BookingRoomRepository $lines = null, ?AvailabilityRepository $availability = null ) {
		$this->bookings     = $bookings ?? new BookingRepository();
		$this->lines        = $lines ?? new BookingRoomRepository();
		$this->availability = $availability ?? new AvailabilityRepository();
	}

	/**
	 * Approve a pending booking, or one of its lines (3.6).
	 *
	 * @param int $booking_id Booking id.
	 * @param int $line_id    One line (0 = every pending line).
	 * @return int Booking id.
	 */
	public function approve( int $booking_id, int $line_id = 0 ): int {
		return $this->bookingMove( 'approve', $booking_id, $line_id, '' );
	}

	/**
	 * Decline a pending booking, or one of its lines, with a reason (3.7).
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $reason     Why (sent to the guest).
	 * @param int    $line_id    One line (0 = every pending line).
	 * @return int Booking id.
	 */
	public function decline( int $booking_id, string $reason, int $line_id = 0 ): int {
		return $this->bookingMove( 'decline', $booking_id, $line_id, self::reason( $reason ) );
	}

	/**
	 * Cancel a booking, or one of its lines, with a reason (3.8). The rooms
	 * are free again at once.
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $reason     Why.
	 * @param int    $line_id    One line (0 = every pending or confirmed line).
	 * @return int Booking id.
	 */
	public function cancel( int $booking_id, string $reason, int $line_id = 0 ): int {
		return $this->bookingMove( 'cancel', $booking_id, $line_id, self::reason( $reason ) );
	}

	/**
	 * Release an unpaid booking past its deadline (5.14): its rooms are
	 * cancelled and free at once, the guest gets the *released* e-mail (not
	 * the cancelled one), the invoice is marked cancelled, and it is logged
	 * `bookings.release_overdue` — by the staff member, or as `system` from
	 * Pro's automatic release (T8). Refused unless the booking is still
	 * overdue **under the lock** (a payment may have arrived meanwhile).
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $reason     Why ('' = not paid by the deadline).
	 * @param array  $only_if    More conditions, checked under the lock (an automatic
	 *                           release): `as_of` (DateTimeImmutable — overdue already
	 *                           at that time, i.e. now minus a grace period),
	 *                           `unless_held` (bool — refuse a booking put on hold).
	 * @return int Booking id.
	 * @throws DomainException 404, 409 `not_overdue` / `on_hold` / `illegal_transition`, 422 reason.
	 */
	public function release( int $booking_id, string $reason = '', array $only_if = array() ): int {
		$reason = '' === trim( $reason ) ? __( 'Not paid by the deadline.', 'radius-hotel-booking' ) : self::reason( $reason );
		$as_of  = ( $only_if['as_of'] ?? null ) instanceof DateTimeImmutable ? $only_if['as_of'] : null;
		$held   = ! empty( $only_if['unless_held'] );
		return $this->bookingMove(
			'cancel',
			$booking_id,
			0,
			$reason,
			'release_overdue',
			'release',
			static function ( Booking $booking ) use ( $as_of, $held ) {
				if ( ! PaymentDeadline::overdue( $booking, $as_of ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'not_overdue', __( 'This booking is not overdue any more: it cannot be released.', 'radius-hotel-booking' ), array( 'payment_status' => (string) $booking->payment_status ) );
				}
				if ( $held && $booking->on_hold ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'on_hold', __( 'This booking is on hold: it is not released automatically.', 'radius-hotel-booking' ) );
				}
			}
		);
	}

	/**
	 * Check a line in (3.9), optionally into another room of the same type.
	 *
	 * @param int                    $line_id Line id.
	 * @param int                    $room_id Another room (0 = keep the room).
	 * @param DateTimeImmutable|null $now     Now.
	 * @return int Booking id.
	 * @throws DomainException 404, 409 `illegal_transition` / `stay_over` / `room_unavailable`.
	 */
	public function checkIn( int $line_id, int $room_id = 0, ?DateTimeImmutable $now = null ): int {
		$now = $now ?? Dates::now();
		return $this->lineMove(
			'check_in',
			$line_id,
			function ( BookingRoom $line, Booking $booking ) use ( $room_id, $now ) {
				if ( strtotime( (string) $line->end_at_gmt . ' UTC' ) <= $now->getTimestamp() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'stay_over', __( 'This stay has already ended. It cannot be checked in.', 'radius-hotel-booking' ), array( 'line_id' => (int) $line->id ) );
				}
				// An early arrival (critical review): on the arrival day only, and the room
				// becomes busy from now — checked free for [now, start) under its lock.
				$start_ts = strtotime( (string) $line->start_at_gmt . ' UTC' );
				$early    = $start_ts > $now->getTimestamp();
				if ( $early ) {
					$arrival = Dates::from_gmt( (string) $line->start_at_gmt )->setTimezone( Dates::timezone() );
					if ( $arrival->format( 'Y-m-d' ) !== $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' ) ) {
						// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict(
							'too_early',
							sprintf(
								/* translators: %s: arrival date. */
								__( 'This stay starts on %s. Check the guest in on the arrival day, or change the dates.', 'radius-hotel-booking' ),
								wp_date( (string) get_option( 'date_format' ), $start_ts )
							),
							array( 'line_id' => (int) $line->id )
						);
						// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
					}
				}
				$from   = $early ? Dates::to_gmt_db( $now ) : (string) $line->start_at_gmt;
				$fields = array(
					'checked_in_at' => Dates::to_db( $now ),
					'checked_in_by' => get_current_user_id() ? get_current_user_id() : null,
				);
				$extra  = array();
				if ( $early ) {
					// The stay starts now; the price stays as sold (3.16).
					$fields['start_at_gmt'] = $from;
					$fields['start_at']     = Dates::to_db( $now );
					$extra                  = array(
						'before' => array( 'start' => (string) $line->start_at ),
						'after'  => array( 'start' => Dates::to_db( $now ) ),
					);
				}
				if ( $room_id && $room_id !== (int) $line->room_id ) {
					$room   = $this->moveTo( $line, $room_id, $now, $from );
					$fields = array_merge(
						$fields,
						array(
							'room_id'     => (int) $room['id'],
							'room_number' => (string) $room['number'],
							'floor_name'  => (string) ( $room['floor_name'] ?? '' ),
						)
					);
					$extra  = array_merge_recursive(
						$extra,
						array(
							'before' => array( 'room' => (string) $line->room_number ),
							'after'  => array( 'room' => (string) $room['number'] ),
						)
					);
				} elseif ( $early ) {
					$this->moveTo( $line, (int) $line->room_id, $now, $from );
				}
				return array( $fields, $extra );
			}
		);
	}

	/**
	 * The rooms a line could move to at check-in: the other available rooms
	 * of its type free for its own window (the same predicate as the move,
	 * which checks again under the lock). Two queries.
	 *
	 * @param int $line_id Line id.
	 * @return array[] `{ id, number, floor }`, in floor and number order.
	 * @throws DomainException 404.
	 */
	public function freeRooms( int $line_id ): array {
		$line = $line_id > 0 ? $this->lines->find( $line_id ) : null;
		if ( ! $line instanceof BookingRoom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
		}
		$candidates = array_values(
			array_filter(
				$this->availability->rooms( array( (int) $line->room_type_id ) ),
				static fn( $room ) => Room::AVAILABLE === $room['state'] && (int) $room['id'] !== (int) $line->room_id
			)
		);
		if ( ! $candidates ) {
			return array();
		}
		$buffer = (int) ( $this->availability->buffers( array( (int) $line->room_type_id ) )[ (int) $line->room_type_id ] ?? 0 );
		// An early arrival needs the room from now (as `checkIn()` checks it).
		$now       = Dates::to_gmt_db( Dates::now() );
		$from      = max( $now, (string) $line->start_at_gmt );
		$conflicts = $this->availability->conflicts(
			array_map(
				static fn( $room ) => array(
					'room_id'        => (int) $room['id'],
					'start_gmt'      => $from,
					'end_gmt'        => (string) $line->occupied_until_gmt,
					'buffer_minutes' => $buffer,
				),
				$candidates
			),
			'',
			(int) $line->id
		);
		$out = array();
		foreach ( $candidates as $index => $room ) {
			if ( ! isset( $conflicts[ $index ] ) ) {
				$out[] = array(
					'id'     => (int) $room['id'],
					'number' => (string) $room['number'],
					'floor'  => (string) ( $room['floor_name'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Check a line out (3.10). Before the end of its window, the room is free
	 * from now on.
	 *
	 * @param int                    $line_id Line id.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return int Booking id.
	 */
	public function checkOut( int $line_id, ?DateTimeImmutable $now = null ): int {
		$now = $now ?? Dates::now();
		return $this->lineMove(
			'check_out',
			$line_id,
			function ( BookingRoom $line ) use ( $now ) {
				$fields = array(
					'checked_out_at' => Dates::to_db( $now ),
					'checked_out_by' => get_current_user_id() ? get_current_user_id() : null,
				);
				$extra  = array();
				if ( $now->getTimestamp() < strtotime( (string) $line->end_at_gmt . ' UTC' ) ) {
					// Early check-out: the room is free from now (plus the cleaning buffer).
					$fields['occupied_until_gmt'] = Dates::to_gmt_db( $now );
					$extra                        = array(
						'before' => array( 'occupied_until' => (string) $line->occupied_until_gmt ),
						'after'  => array( 'occupied_until' => $fields['occupied_until_gmt'] ),
					);
				}
				return array( $fields, $extra );
			}
		);
	}

	/**
	 * Mark a line as a no-show, once its window has started. The room leaves
	 * the occupying set.
	 *
	 * @param int                    $line_id Line id.
	 * @param DateTimeImmutable|null $now     Now.
	 * @return int Booking id.
	 */
	public function noShow( int $line_id, ?DateTimeImmutable $now = null ): int {
		$now = $now ?? Dates::now();
		return $this->lineMove(
			'no_show',
			$line_id,
			function ( BookingRoom $line ) use ( $now ) {
				if ( strtotime( (string) $line->start_at_gmt . ' UTC' ) > $now->getTimestamp() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'too_early', __( 'A no-show can only be recorded once the stay has started.', 'radius-hotel-booking' ), array( 'line_id' => (int) $line->id ) );
				}
				return array( array(), array() );
			}
		);
	}

	/**
	 * Approve / decline / cancel on a booking or one of its lines.
	 *
	 * @param string $action     approve|decline|cancel.
	 * @param int    $booking_id Booking id.
	 * @param int    $line_id    One line, or 0.
	 * @param string $reason     Reason (decline, cancel).
	 * @param string $log_as     Log the move under another action (`release_overdue`).
	 * @param string $announce_as Announce it under another action (`release`: its own guest e-mail).
	 * @param callable|null $guard Checked on the booking under the lock; throws to refuse.
	 * @return int Booking id.
	 * @throws DomainException 404, 409 `illegal_transition`.
	 */
	private function bookingMove( string $action, int $booking_id, int $line_id, string $reason, string $log_as = '', string $announce_as = '', ?callable $guard = null ): int {
		$this->booking( $booking_id );
		$changes = Transaction::run(
			function () use ( $action, $booking_id, $line_id, $reason, $log_as, $guard ) {
				$this->bookings->lock( $booking_id );
				$booking = $this->booking( $booking_id );
				if ( $guard ) {
					// A condition checked on the booking as it is under the lock (the release: still overdue).
					$guard( $booking );
				}
				$lines   = $this->lines->forBooking( $booking_id );
				$targets = array();
				foreach ( $lines as $line ) {
					if ( $line_id && (int) $line->id !== $line_id ) {
						continue;
					}
					if ( null !== StatusMachine::to( $action, (string) $line->status ) ) {
						$targets[] = $line;
					} elseif ( $line_id ) {
						self::illegal( $action, (string) $line->status, $line_id );
					}
				}
				if ( $line_id && ! $targets && ! array_filter( $lines, static fn( $l ) => (int) $l->id === $line_id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This room is not on the booking.', 'radius-hotel-booking' ) );
				}
				if ( ! $targets ) {
					self::illegal( $action, (string) $booking->status, 0 );
				}

				$changes = array();
				foreach ( $targets as $line ) {
					$to = StatusMachine::to( $action, (string) $line->status );
					$this->lines->update( (int) $line->id, array( 'status' => $to ) );
					$changes[ (int) $line->id ] = array(
						'room' => (string) $line->room_number,
						'from' => (string) $line->status,
						'to'   => $to,
					);
				}
				// A declined or cancelled room stops counting towards the total.
				$money  = BookingTotals::changes( $booking, $this->lines->forBooking( $booking_id ) );
				$fields = array_merge( array( 'status' => $this->summary( $booking_id ) ), $money );
				if ( 'approve' === $action ) {
					$fields['approved_by'] = get_current_user_id() ? get_current_user_id() : null;
					$fields['approved_at'] = Dates::to_db( Dates::now() );
				}
				// The booking's own reason only when the booking itself ended (critical review):
				// from the header, or a room at a time down to the last live one.
				if ( '' !== $reason && in_array( $fields['status'], array( StatusMachine::CANCELLED, StatusMachine::DECLINED ), true ) ) {
					$fields['cancelled_reason'] = mb_substr( $reason, 0, 191 );
				}
				$this->bookings->update( $booking_id, $fields );
				$this->log(
					'' !== $log_as ? $log_as : $action,
					$booking,
					$changes,
					$reason,
					isset( $money['total'] ) ? array(
						'before' => array( 'total' => (float) $booking->total ),
						'after'  => array( 'total' => $money['total'] ),
					) : array()
				);
				return $changes;
			}
		);
		$this->announce( $booking_id, '' !== $announce_as ? $announce_as : $action, $changes, $reason, $line_id );
		return $booking_id;
	}

	/**
	 * A per-line move.
	 *
	 * @param string   $action  check_in|check_out|no_show.
	 * @param int      $line_id Line id.
	 * @param callable $extra   `( BookingRoom $line, Booking $booking ) → [ fields, log extra ]`; may throw.
	 * @return int Booking id.
	 * @throws DomainException 404, 409.
	 */
	private function lineMove( string $action, int $line_id, callable $extra ): int {
		$first = $line_id > 0 ? $this->lines->find( $line_id ) : null;
		if ( ! $first instanceof BookingRoom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
		}
		$booking_id = (int) $first->booking_id;

		$changes = Transaction::run(
			function () use ( $action, $line_id, $booking_id, $extra ) {
				// Locking reads only before the room lock of a room change (booking-engine §7.1).
				$booking = $this->bookings->lockedFind( $booking_id );
				if ( ! $booking instanceof Booking ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
				}
				// Re-read under the lock: another desk may have moved it meanwhile.
				$line = $this->lines->lockedFind( $line_id );
				if ( ! $line instanceof BookingRoom ) {
					// Removed from the booking meanwhile (3.12).
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
				}
				$to   = StatusMachine::to( $action, (string) $line->status );
				if ( null === $to ) {
					self::illegal( $action, (string) $line->status, $line_id );
				}
				list( $fields, $log ) = $extra( $line, $booking );
				$this->lines->update( $line_id, array_merge( $fields, array( 'status' => $to ) ) );
				$this->bookings->update( $booking_id, array( 'status' => $this->summary( $booking_id ) ) );
				$changes = array(
					$line_id => array(
						'room' => (string) ( $fields['room_number'] ?? $line->room_number ),
						'from' => (string) $line->status,
						'to'   => $to,
					),
				);
				$this->log( $action, $booking, $changes, '', $log );
				return $changes;
			}
		);
		$this->announce( $booking_id, $action, $changes, '', $line_id );
		return $booking_id;
	}

	/**
	 * Move a line to another room of its type, for its own window (check-in).
	 * Both rooms are locked; the new one must be available and free.
	 *
	 * Also used for the line's own room on an early check-in: it must then be
	 * free from `$from` (now) — the part of the stay not yet checked.
	 *
	 * @param BookingRoom       $line    Line.
	 * @param int               $room_id New room (or the line's own).
	 * @param DateTimeImmutable $now     Now.
	 * @param string            $from    GMT start the room must be free from (the line's start, or now when early).
	 * @return array The room (`{ id, room_type_id, number, state, floor_name }`).
	 * @throws DomainException 409 `room_unavailable`.
	 */
	private function moveTo( BookingRoom $line, int $room_id, DateTimeImmutable $now, string $from ): array {
		$rooms = $this->availability->lockRooms( array_values( array_unique( array( (int) $line->room_id, $room_id ) ) ) );
		$room  = $rooms[ $room_id ] ?? null;
		$fail  = static function ( string $reason, string $number, ?string $ref = null ) use ( $room_id ) {
			$context = array(
				'room_id' => $room_id,
				'room'    => $number,
				'reason'  => $reason,
			);
			if ( $ref ) {
				$context['booking_ref'] = $ref;
			}
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict(
				'room_unavailable',
				'' === $number
					? __( 'This room is no longer available. Choose another room.', 'radius-hotel-booking' )
					/* translators: %s: room number. */
					: sprintf( __( 'Room %s is not free for this stay. Choose another room.', 'radius-hotel-booking' ), $number ),
				$context
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};
		if ( ! $room ) {
			$fail( 'removed', '' );
		}
		if ( (int) $room['room_type_id'] !== (int) $line->room_type_id ) {
			// The price was made for this room type: a move to another type is a line edit.
			$fail( 'moved', (string) $room['number'] );
		}
		if ( Room::AVAILABLE !== $room['state'] ) {
			$fail( (string) $room['state'], (string) $room['number'] );
		}
		$buffers   = $this->availability->buffers( array( (int) $line->room_type_id ) );
		$conflicts = $this->availability->conflicts(
			array(
				array(
					'room_id'        => $room_id,
					'start_gmt'      => $from,
					'end_gmt'        => (string) $line->occupied_until_gmt,
					'buffer_minutes' => (int) ( $buffers[ (int) $line->room_type_id ] ?? 0 ),
				),
			),
			'',
			(int) $line->id,
			Dates::to_gmt_db( $now )
		);
		if ( $conflicts ) {
			$conflict = reset( $conflicts );
			$interval = $conflict['interval'] ?? null;
			$fail( (string) $conflict['reason'], (string) $room['number'], $interval && 'line' === ( $interval['kind'] ?? '' ) ? (string) ( $interval['label'] ?? '' ) : null );
		}
		// The lock does not read the floor; the line keeps its name as a snapshot.
		$floor              = ! empty( $room['floor_id'] ) ? ( new FloorRepository() )->find( (int) $room['floor_id'] ) : null;
		$room['floor_name'] = $floor ? (string) $floor->name : '';
		return $room;
	}

	/**
	 * The booking's summary status from its stored lines.
	 *
	 * @param int $booking_id Booking id.
	 * @return string
	 */
	private function summary( int $booking_id ): string {
		return StatusMachine::summary( array_map( static fn( $line ) => (string) $line->status, $this->lines->forBooking( $booking_id ) ) );
	}

	/**
	 * Log a change, inside the transaction.
	 *
	 * @param string  $action  Action.
	 * @param Booking $booking Booking.
	 * @param array   $changes Line id => `{ room, from, to }`.
	 * @param string  $reason  Reason.
	 * @param array   $extra   `{ before, after }` to merge (room, occupied until).
	 * @return void
	 */
	private function log( string $action, Booking $booking, array $changes, string $reason, array $extra ): void {
		$before = array();
		$after  = array();
		foreach ( $changes as $change ) {
			/* translators: %s: room number. */
			$key            = sprintf( __( 'room %s', 'radius-hotel-booking' ), $change['room'] );
			$before[ $key ] = $change['from'];
			$after[ $key ]  = $change['to'];
		}
		if ( '' !== $reason ) {
			$after['reason'] = $reason;
		}
		$rooms = implode( ', ', array_column( $changes, 'room' ) );
		rtbp_activity(
			'bookings.' . $action,
			array(
				'type'  => 'booking',
				'id'    => (int) $booking->id,
				'label' => (string) $booking->reference,
			),
			array(
				'before'      => array_merge( $before, $extra['before'] ?? array() ),
				'after'       => array_merge( $after, $extra['after'] ?? array() ),
				'description' => sprintf(
					/* translators: 1: action in words, 2: booking reference, 3: room numbers. */
					__( '%1$s %2$s (room %3$s)', 'radius-hotel-booking' ),
					self::verb( $action ),
					$booking->reference,
					$rooms
				),
			)
		);
	}

	/**
	 * Announce a change after the commit.
	 *
	 * @param int    $booking_id Booking id.
	 * @param string $action     Action.
	 * @param array  $changes    Line id => `{ room, from, to }`.
	 * @param string $reason     Reason.
	 * @param int    $line_id    The one line acted on (0 = the booking).
	 * @return void
	 */
	private function announce( int $booking_id, string $action, array $changes, string $reason, int $line_id ): void {
		Transaction::afterCommit(
			function () use ( $booking_id, $action, $changes, $reason, $line_id ) {
				/**
				 * A booking's status changed (e-mails, notifications, M05 invoices).
				 *
				 * @param Booking $booking The booking, as stored now.
				 * @param string  $action  approve|decline|cancel|check_in|check_out|no_show.
				 * @param array   $changes Line id => `{ room, from, to }`.
				 * @param string  $reason  Reason (decline, cancel), or ''.
				 * @param int     $line_id The one line acted on, or 0 for the whole booking.
				 */
				do_action( 'rtbp_booking_status_changed', $this->booking( $booking_id ), $action, $changes, $reason, $line_id );
			}
		);
	}

	/**
	 * A booking, or 404.
	 *
	 * @param int $id Id.
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
	 * A required reason (3 to 191 characters).
	 *
	 * @param string $reason Reason.
	 * @return string
	 * @throws DomainException 422.
	 */
	private static function reason( string $reason ): string {
		$reason = sanitize_text_field( $reason );
		if ( mb_strlen( $reason ) < 3 || mb_strlen( $reason ) > 191 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'reason' => __( 'Give a reason (3 to 191 characters).', 'radius-hotel-booking' ) ) );
		}
		return $reason;
	}

	/**
	 * Refuse a move the machine does not allow.
	 *
	 * @param string $action  Action.
	 * @param string $from    Current status.
	 * @param int    $line_id Line, or 0 for the booking.
	 * @return never
	 * @throws DomainException 409 `illegal_transition`.
	 */
	private static function illegal( string $action, string $from, int $line_id ) {
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw DomainException::conflict(
			'illegal_transition',
			/* translators: %s: current status, e.g. pending. */
			sprintf( __( 'This cannot be done while the status is “%s”.', 'radius-hotel-booking' ), str_replace( '_', ' ', $from ) ),
			array(
				'action'  => $action,
				'from'    => $from,
				'line_id' => $line_id,
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * An action in words (log and messages).
	 *
	 * @param string $action Action.
	 * @return string
	 */
	private static function verb( string $action ): string {
		$verbs = array(
			'approve'   => __( 'Approved', 'radius-hotel-booking' ),
			'decline'   => __( 'Declined', 'radius-hotel-booking' ),
			'cancel'    => __( 'Cancelled', 'radius-hotel-booking' ),
			'check_in'  => __( 'Checked in', 'radius-hotel-booking' ),
			'check_out' => __( 'Checked out', 'radius-hotel-booking' ),
			'no_show'   => __( 'No-show for', 'radius-hotel-booking' ),
			'release_overdue' => __( 'Released (unpaid)', 'radius-hotel-booking' ),
		);
		return $verbs[ $action ] ?? $action;
	}
}
