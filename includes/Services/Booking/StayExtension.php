<?php
/**
 * Extend a checked-in guest's stay in the same room (booking-engine §10 #48).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\BookingRoom;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\StayWindow;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * A guest in the room stays on for one more slot of any rate plan the room
 * type sells: the next window of that plan starting at (or shortly after)
 * the end of the stay — Overnight after a Half Day, another 24 hours after
 * 24 hours. The stay is extended **in place**: one line, in the same room,
 * whose end moves to the slot's end; the gap between the two (17:00 → 20:00)
 * is held for the guest and not charged. The slot is priced by the normal
 * pipeline and added to the line's frozen price, so the booking total and
 * the balance due grow; the invoice is re-issued after the commit.
 *
 * The room is locked and checked free from the current end to the new end
 * (`BookingWriter::lockAndCheck()` for the slot, the gap on top), the
 * guest's own line excluded. A room booked by someone else in between is
 * refused (409 `room_unavailable`, with their booking reference): the desk
 * can then move the guest to another room first.
 */
class StayExtension {

	/**
	 * Longest gap between the end of the stay and the slot's start that is
	 * held for the guest (minutes; filter `rtbp_extend_max_gap_minutes`).
	 */
	public const MAX_GAP_MINUTES = 240;

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
	 */
	public function __construct() {
		$this->bookings     = new BookingRepository();
		$this->lines        = new BookingRoomRepository();
		$this->availability = new AvailabilityRepository();
	}

	/**
	 * The slots the stay can be extended with: for each rate plan the room
	 * type sells, its next window starting at the end of the stay or within
	 * the longest gap, priced, and whether the room is free until its end.
	 * Not stored; the extension checks everything again under the lock.
	 *
	 * @param int                    $line_id Line id (checked in).
	 * @param DateTimeImmutable|null $now     Now.
	 * @return array[] `{ rate_plan_id, name, arrival, checkin_time, start, end, gap_minutes, total, free, blocked_by }`,
	 *                 by start then end.
	 * @throws DomainException 404, 409 `illegal_transition`.
	 */
	public function options( int $line_id, ?DateTimeImmutable $now = null ): array {
		$now  = $now ?? Dates::now();
		$line = $this->checkedIn( $line_id > 0 ? $this->lines->find( $line_id ) : null );

		$end       = Dates::from_gmt( (string) $line->occupied_until_gmt );
		$max_gap   = self::maxGap();
		$booked_at = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		$prices    = new PriceResolver();
		$plans     = new RatePlanRepository();

		$out = array();
		foreach ( $this->soldPlans( (int) $line->room_type_id ) as $plan_id ) {
			$plan = $plans->find( $plan_id );
			if ( ! $plan instanceof RatePlan ) {
				continue;
			}
			$slot = self::nextSlot( $plan, $end );
			if ( ! $slot ) {
				continue;
			}
			list( $arrival, $checkin, $window ) = $slot;
			$gap = (int) round( ( $window->startGmt()->getTimestamp() - $end->getTimestamp() ) / MINUTE_IN_SECONDS );
			if ( $gap < 0 || $gap > $max_gap || AvailabilityService::tooLate( $window, AvailabilityService::STAFF, $now ) ) {
				continue;
			}
			try {
				$quote = $prices->quote( (int) $line->room_type_id, $plan_id, $arrival, 1, $checkin, $booked_at );
			} catch ( DomainException $e ) {
				// Not sold on that date (closed, outside its units…): not offered.
				continue;
			}
			$out[] = array(
				'rate_plan_id' => $plan_id,
				'name'         => (string) $plan->name,
				'arrival'      => $arrival,
				'checkin_time' => $checkin,
				'start'        => Dates::to_iso( $window->startGmt() ),
				'end'          => Dates::to_iso( $window->endGmt() ),
				'end_gmt'      => Dates::to_gmt_db( $window->endGmt() ),
				'gap_minutes'  => $gap,
				'total'        => (float) $quote['total'],
				'free'         => true,
				'blocked_by'   => '',
			);
		}
		if ( ! $out ) {
			return array();
		}

		// The room from the current end to each slot's end, in one query.
		$buffer    = (int) ( $this->availability->buffers( array( (int) $line->room_type_id ) )[ (int) $line->room_type_id ] ?? 0 );
		$conflicts = $this->availability->conflicts(
			array_map(
				static fn( $option ) => array(
					'room_id'        => (int) $line->room_id,
					'start_gmt'      => (string) $line->occupied_until_gmt,
					'end_gmt'        => $option['end_gmt'],
					'buffer_minutes' => $buffer,
				),
				$out
			),
			'',
			(int) $line->id,
			Dates::to_gmt_db( $now )
		);
		foreach ( $out as $index => $option ) {
			unset( $out[ $index ]['end_gmt'] );
			if ( isset( $conflicts[ $index ] ) ) {
				$interval                    = $conflicts[ $index ]['interval'] ?? null;
				$out[ $index ]['free']       = false;
				$out[ $index ]['blocked_by'] = $interval && 'line' === ( $interval['kind'] ?? '' ) ? (string) ( $interval['label'] ?? '' ) : (string) $conflicts[ $index ]['reason'];
			}
		}
		usort( $out, static fn( $a, $b ) => array( $a['start'], $a['end'] ) <=> array( $b['start'], $b['end'] ) );
		return $out;
	}

	/**
	 * Extend the stay with one slot of a rate plan (see the class doc).
	 *
	 * @param int                    $line_id        Line id (checked in).
	 * @param int                    $rate_plan_id   The slot's rate plan.
	 * @param float|null             $expected_total The slot price shown (refused with `price_changed` when it moved).
	 * @param DateTimeImmutable|null $now            Now.
	 * @return int Booking id.
	 * @throws DomainException 404, 409 `illegal_transition` / `room_unavailable` / `price_changed` / `no_slot`, 422.
	 */
	public function extend( int $line_id, int $rate_plan_id, ?float $expected_total = null, ?DateTimeImmutable $now = null ): int {
		$now   = $now ?? Dates::now();
		$first = $this->checkedIn( $line_id > 0 ? $this->lines->find( $line_id ) : null );
		$plan  = ( new RatePlanRepository() )->find( $rate_plan_id );
		if ( ! $plan instanceof RatePlan ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'rate_plan_id' => __( 'Choose how long the guest stays on.', 'radius-hotel-booking' ) ) );
		}
		$booking_id = (int) $first->booking_id;

		$changed = Transaction::run(
			function () use ( $line_id, $booking_id, $plan, $rate_plan_id, $expected_total, $now ) {
				// Locking reads before the room lock (booking-engine §7.1).
				$booking = $this->bookings->lockedFind( $booking_id );
				if ( ! $booking instanceof Booking ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This booking does not exist.', 'radius-hotel-booking' ) );
				}
				$line = $this->checkedIn( $this->lines->lockedFind( $line_id ) );

				// The slot, from the stay's end as stored now (another desk may have extended it).
				$end  = Dates::from_gmt( (string) $line->occupied_until_gmt );
				$slot = self::nextSlot( $plan, $end );
				$gap  = $slot ? (int) round( ( $slot[2]->startGmt()->getTimestamp() - $end->getTimestamp() ) / MINUTE_IN_SECONDS ) : -1;
				if ( ! $slot || $gap < 0 || $gap > self::maxGap() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'no_slot', __( 'This rate plan has no slot right after this stay. Choose another one.', 'radius-hotel-booking' ), array( 'line_id' => $line_id ) );
				}
				list( $arrival, $checkin ) = $slot;

				// Locks the room; the rate, the date rules, the price and the slot's window
				// (the guest's own line excluded, so its cleaning buffer does not count).
				$checked = ( new BookingWriter() )->lockAndCheck(
					array(
						array(
							'room_id'        => (int) $line->room_id,
							'room_type_id'   => (int) $line->room_type_id,
							'rate_plan_id'   => $rate_plan_id,
							'arrival'        => $arrival,
							'checkin_time'   => $checkin,
							'units'          => 1,
							'expected_total' => $expected_total,
						),
					),
					'',
					array(
						'audience'     => AvailabilityService::STAFF,
						'exclude_line' => $line_id,
						'now'          => $now,
					)
				);
				$item   = reset( $checked );
				$window = $item['window'];

				// The gap held for the guest must be free too.
				$new_end_gmt = Dates::to_gmt_db( $window->endGmt() );
				if ( $gap > 0 ) {
					$conflicts = $this->availability->conflicts(
						array(
							array(
								'room_id'        => (int) $line->room_id,
								'start_gmt'      => (string) $line->occupied_until_gmt,
								'end_gmt'        => Dates::to_gmt_db( $window->startGmt() ),
								'buffer_minutes' => (int) $item['buffer_minutes'],
							),
						),
						'',
						$line_id,
						Dates::to_gmt_db( $now )
					);
					if ( $conflicts ) {
						$conflict = reset( $conflicts );
						$interval = $conflict['interval'] ?? null;
						$ref      = $interval && 'line' === ( $interval['kind'] ?? '' ) ? (string) ( $interval['label'] ?? '' ) : '';
						// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict(
							'room_unavailable',
							sprintf(
								/* translators: %s: room number. */
								__( 'Room %s is not free until the end of this slot. Move the guest to another room first, or choose a shorter slot.', 'radius-hotel-booking' ),
								(string) $line->room_number
							),
							array_filter(
								array(
									'room_id'     => (int) $line->room_id,
									'reason'      => (string) $conflict['reason'],
									'booking_ref' => $ref,
								)
							)
						);
						// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
					}
				}

				// One line, longer: the slot's price added to the frozen one.
				$added = (float) $item['quote']['total'];
				$total = (float) Money::round( (float) $line->total + $added );
				$units = (int) $line->units + $window->units();
				$this->lines->update(
					$line_id,
					array(
						'end_at'             => Dates::to_db( $window->endGmt() ),
						'end_at_gmt'         => $new_end_gmt,
						'occupied_until_gmt' => $new_end_gmt,
						'units'              => $units,
						'unit_price'         => Money::round( $total / max( 1, $units ) ),
						'total'              => $total,
						'rate_plan_name'     => self::planNames( (string) $line->rate_plan_name, (string) $plan->name ),
						'price_breakdown'    => self::breakdown( $line, $item['quote'], (string) $plan->name, $window, $now ),
					)
				);

				// The booking's totals and payment status follow (as after a line edit).
				$money = BookingTotals::changes( $booking, $this->lines->forBooking( $booking_id ) );
				if ( $money ) {
					$this->bookings->update( $booking_id, $money );
				}

				$before = array(
					'end'   => (string) $line->end_at,
					'total' => (float) $line->total,
				);
				$after  = array(
					'end'   => Dates::to_db( $window->endGmt() ),
					'total' => $total,
					'slot'  => (string) $plan->name,
				);
				rtbp_activity(
					'bookings.extend_stay',
					array(
						'type'  => 'booking',
						'id'    => (int) $booking->id,
						'label' => (string) $booking->reference,
					),
					array(
						'before'      => $before,
						'after'       => $after,
						'description' => sprintf(
							/* translators: 1: booking reference, 2: room number, 3: rate plan name. */
							__( 'Extended the stay of %1$s (room %2$s) with %3$s', 'radius-hotel-booking' ),
							$booking->reference,
							$line->room_number,
							$plan->name
						),
					)
				);
				return array( $before, $after );
			}
		);

		Transaction::afterCommit(
			function () use ( $booking_id, $line_id, $changed ) {
				// The invoice is re-issued with the longer stay; dashboard, reports and guest caches refresh.
				do_action( 'rtbp_booking_changed', $this->bookings->find( $booking_id ), 'extend_stay', $line_id, $changed[0], $changed[1] );
			}
		);
		return $booking_id;
	}

	/**
	 * The plan's next window starting at or after `$end`: a fixed plan on the
	 * end's date or the day after; a flexible one at the end, rounded up to
	 * the next 30 minutes (when its check-in range allows that time).
	 *
	 * @param RatePlan          $plan Plan.
	 * @param DateTimeImmutable $end  End of the stay.
	 * @return array|null `[ arrival Y-m-d, checkin H:i|null, StayWindow ]`, or null.
	 */
	private static function nextSlot( RatePlan $plan, DateTimeImmutable $end ): ?array {
		$tz    = Dates::timezone();
		$local = $end->setTimezone( $tz );
		if ( 'flexible' === (string) $plan->type ) {
			$minutes = (int) $local->format( 'G' ) * 60 + (int) $local->format( 'i' ) + ( (int) $local->format( 's' ) > 0 ? 1 : 0 );
			$minutes = (int) ( ceil( $minutes / 30 ) * 30 );
			$start   = $local->setTime( 0, 0 )->modify( '+' . $minutes . ' minutes' );
			try {
				return array( $start->format( 'Y-m-d' ), $start->format( 'H:i' ), StayWindow::for( $plan, $start->format( 'Y-m-d' ), 1, $start->format( 'H:i' ) ) );
			} catch ( DomainException $e ) {
				return null;
			}
		}
		foreach ( array( 0, 1 ) as $days ) {
			$date = $local->setTime( 0, 0 )->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
			try {
				$window = StayWindow::for( $plan, $date, 1 );
			} catch ( DomainException $e ) {
				return null;
			}
			if ( $window->startGmt()->getTimestamp() >= $end->getTimestamp() ) {
				return array( $date, null, $window );
			}
		}
		return null;
	}

	/**
	 * The rate plans the room type sells (enabled rates of live plans).
	 *
	 * @param int $room_type_id Room type id.
	 * @return int[]
	 */
	private function soldPlans( int $room_type_id ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the desk's option list, live.
		$ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					'SELECT r.rate_plan_id FROM %i r JOIN %i p ON p.id = r.rate_plan_id AND p.deleted_at IS NULL AND p.is_active = 1
					WHERE r.room_type_id = %d AND r.enabled = 1 ORDER BY r.sort_order, p.sort_order, p.id',
					rtbp_table( 'room_type_rates' ),
					rtbp_table( 'rate_plans' ),
					$room_type_id
				)
			)
		);
		// phpcs:enable
		return $ids;
	}

	/**
	 * The line, when it is checked in.
	 *
	 * @param BookingRoom|null $line Line.
	 * @return BookingRoom
	 * @throws DomainException 404, 409 `illegal_transition`.
	 */
	private function checkedIn( $line ): BookingRoom {
		if ( ! $line instanceof BookingRoom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room is not on a booking.', 'radius-hotel-booking' ) );
		}
		if ( StatusMachine::CHECKED_IN !== (string) $line->status ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::conflict( 'illegal_transition', __( 'Only a guest who is checked in can extend the stay.', 'radius-hotel-booking' ), array( 'line_id' => (int) $line->id ) );
		}
		return $line;
	}

	/**
	 * The longest gap held for the guest, in minutes.
	 *
	 * @return int
	 */
	private static function maxGap(): int {
		/**
		 * The longest gap between the end of a stay and an extension slot's
		 * start that is held for the guest (minutes; 240 = 4 hours).
		 *
		 * @param int $minutes Minutes.
		 */
		return max( 0, (int) apply_filters( 'rtbp_extend_max_gap_minutes', self::MAX_GAP_MINUTES ) );
	}

	/**
	 * The line's rate plan names after an extension ("Half Day + Overnight").
	 *
	 * @param string $current Current name(s).
	 * @param string $added   The slot's plan.
	 * @return string At most 191 characters.
	 */
	private static function planNames( string $current, string $added ): string {
		$names = '' === $current ? $added : $current . ' + ' . $added;
		return function_exists( 'mb_substr' ) ? mb_substr( $names, 0, 191 ) : substr( $names, 0, 191 );
	}

	/**
	 * The line's price breakdown with the slot added: the price before, then
	 * the slot's own steps (dated), the earlier breakdowns kept.
	 *
	 * @param BookingRoom $line   Line before the extension.
	 * @param array       $quote  The slot's quote.
	 * @param string      $name   The slot's plan.
	 * @param StayWindow        $window The slot.
	 * @param DateTimeImmutable $now    When it was extended.
	 * @return string JSON.
	 */
	private static function breakdown( BookingRoom $line, array $quote, string $name, StayWindow $window, DateTimeImmutable $now ): string {
		$old   = json_decode( (string) $line->price_breakdown, true );
		$old   = is_array( $old ) ? $old : array();
		$steps = array(
			array(
				'date'  => $window->start()->format( 'Y-m-d' ),
				'steps' => array(
					array(
						'step'    => 'stay',
						'rule_id' => null,
						'label'   => __( 'Price before the extension', 'radius-hotel-booking' ),
						'before'  => null,
						'after'   => (float) $line->total,
					),
					array(
						'step'    => 'extension',
						'rule_id' => null,
						/* translators: %s: rate plan name. */
						'label'   => sprintf( __( 'Extended with %s', 'radius-hotel-booking' ), $name ),
						'before'  => (float) $line->total,
						'after'   => (float) Money::round( (float) $line->total + (float) $quote['total'] ),
					),
				),
			),
		);
		$history   = (array) ( $old['extensions'] ?? array() );
		$history[] = array(
			'plan'  => $name,
			'start' => Dates::to_gmt_db( $window->startGmt() ),
			'end'   => Dates::to_gmt_db( $window->endGmt() ),
			'total' => (float) $quote['total'],
			'steps' => $quote['steps'] ?? array(),
			// When and by whom, for the booking screen (a name snapshot, like the line's).
			'at'    => Dates::to_gmt_db( $now ),
			'by'    => get_current_user_id() ? (string) wp_get_current_user()->display_name : '',
		);
		return (string) wp_json_encode(
			array(
				'unit_prices'      => array_merge( (array) ( $old['unit_prices'] ?? array() ), (array) ( $quote['unit_prices'] ?? array() ) ),
				'steps'            => $steps,
				'extensions'       => $history,
				'before_extension' => $old['before_extension'] ?? $old,
			)
		);
	}
}
