<?php
/**
 * The locked write path (booking-engine §7.1).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

use DateTimeImmutable;
use LogicException;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\OccupancyCalculator;
use RadiusTheme\RadiusHotelBooking\Services\Availability\Overlap;
use RadiusTheme\RadiusHotelBooking\Services\Availability\RateCalendar;
use RadiusTheme\RadiusHotelBooking\Services\Availability\StayWindow;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Every path that makes a room busy — a hold (M08), a booking, an added or
 * edited line, a room change at check-in (M02/M03), an import (M18) — calls
 * `lockAndCheck()` inside its transaction, then writes. MySQL cannot forbid
 * overlapping ranges, so **the row lock is the guarantee**:
 *
 * 1. lock the rooms, ascending id (no deadlock between two writers);
 * 2. re-check each (room, window) inside the lock: room live and available,
 *    the rate still sold and open that date, the window not past, and no
 *    occupying line, block or foreign live hold (`AvailabilityRepository::
 *    conflicts()`, the same predicate as the search) — nor another request of
 *    the same call on the same room;
 * 3. re-quote (rules may have changed since the page loaded) and, when the
 *    caller shows a price, refuse `price_changed` unless it accepts the new one.
 *
 * All-or-nothing: the first failure throws and the caller's transaction rolls
 * back, so a multi-room booking never half-succeeds.
 */
class BookingWriter {

	/**
	 * Line statuses a new booking can start in (feature 8.11).
	 */
	public const PENDING   = 'pending';
	public const CONFIRMED = 'confirmed';

	/**
	 * Engine queries.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $availability;

	/**
	 * Rate plans.
	 *
	 * @var RatePlanRepository
	 */
	private RatePlanRepository $plans;

	/**
	 * Prices.
	 *
	 * @var PriceResolver
	 */
	private PriceResolver $prices;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityRepository|null $availability Engine queries.
	 * @param RatePlanRepository|null     $plans        Rate plans.
	 * @param PriceResolver|null          $prices       Prices.
	 */
	public function __construct( ?AvailabilityRepository $availability = null, ?RatePlanRepository $plans = null, ?PriceResolver $prices = null ) {
		$this->availability = $availability ?? new AvailabilityRepository();
		$this->plans        = $plans ?? new RatePlanRepository();
		$this->prices       = $prices ?? new PriceResolver();
	}

	/**
	 * Lock the rooms and prove every request can still be written.
	 *
	 * @param array[] $requests   Each `{ room_id, rate_plan_id, arrival (Y-m-d), units?, checkin_time?,
	 *                            room_type_id? (must match the room), expected_total? (the price shown),
	 *                            adults?, children?, child_ages? (checked against the type's limits),
	 *                            price_override? (importing only: the legacy price, frozen as is) }`.
	 * @param string  $hold_token The caller's hold token: its own holds are not conflicts.
	 * @param array   $options    `audience` (staff|public), `exclude_line` (a line being edited),
	 *                            `accept_new_price` (bool), `now` (DateTimeImmutable, checks),
	 *                            `importing` (bool, M18): a booking that already exists in the old
	 *                            system — it may have started already, sit on a date or room that is
	 *                            closed now, break today's date and guest rules, and keep its own
	 *                            price (`price_override`); the lock, removed / moved rooms, the
	 *                            overlap (with the buffer) and duplicates are checked as always.
	 * @return array[] Per request `{ room, room_type_id, rate_plan_id, plan, window: StayWindow, quote, buffer_minutes }`.
	 * @throws LogicException  Outside a transaction (a programming error).
	 * @throws DomainException 409 `room_unavailable` / `rate_closed` / `rate_not_sold` / `price_changed` /
	 *                         `booking_window` / `same_day_cutoff` (public) / `occupancy`, 422 input.
	 */
	public function lockAndCheck( array $requests, string $hold_token = '', array $options = array() ): array {
		if ( ! Transaction::active() ) {
			throw new LogicException( 'BookingWriter::lockAndCheck() must run inside Transaction::run().' );
		}
		if ( ! $requests ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'rooms' => __( 'Choose a room.', 'radius-hotel-booking' ) ) );
		}

		$audience  = AvailabilityService::PUBLIC === ( $options['audience'] ?? '' ) ? AvailabilityService::PUBLIC : AvailabilityService::STAFF;
		$importing = ! empty( $options['importing'] ) && AvailabilityService::STAFF === $audience;
		$now       = $options['now'] ?? Dates::now();
		$booked_at = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );

		// 1. Lock, ascending id.
		$rooms = $this->availability->lockRooms( array_column( $requests, 'room_id' ) );

		// 2. Per request: the room, the rate, the window, the rules, the price.
		$checked = array();
		$types   = array();
		foreach ( array_values( $requests ) as $index => $request ) {
			$room_id = (int) ( $request['room_id'] ?? 0 );
			$room    = $rooms[ $room_id ] ?? null;
			if ( ! $room ) {
				$this->unavailable( $index, $room_id, '', 'removed', null, $audience );
			}
			if ( ! empty( $request['room_type_id'] ) && (int) $request['room_type_id'] !== (int) $room['room_type_id'] ) {
				// The room was moved to another type since the page loaded.
				$this->unavailable( $index, $room_id, (string) $room['number'], 'moved', null, $audience );
			}
			if ( Room::AVAILABLE !== $room['state'] && ! $importing ) {
				$this->unavailable( $index, $room_id, (string) $room['number'], (string) $room['state'], null, $audience );
			}

			$type_id = (int) $room['room_type_id'];
			$plan_id = (int) ( $request['rate_plan_id'] ?? 0 );
			$units   = max( 1, (int) ( $request['units'] ?? 1 ) );
			$checkin = isset( $request['checkin_time'] ) && '' !== $request['checkin_time'] ? (string) $request['checkin_time'] : null;
			$arrival = (string) ( $request['arrival'] ?? '' );

			// Refuses a rate no longer sold, a bad date, units outside the bounds.
			// Occupancy as the search counted it: without the requester's own
			// holds or the line being edited, else the price shown never matches.
			try {
				$quote = OccupancyCalculator::excluding(
					$hold_token,
					(int) ( $options['exclude_line'] ?? 0 ),
					fn() => $this->prices->quote( $type_id, $plan_id, $arrival, $units, $checkin, $booked_at )
				);
			} catch ( DomainException $e ) {
				// An imported booking may use a rate the room type no longer sells, or a stay
				// length outside today's limits: its price is the legacy one.
				$tolerated = 'rate_not_sold' === $e->getErrorCode() || isset( $e->getFieldErrors()['units'] );
				if ( ! $importing || ! isset( $request['price_override'] ) || ! $tolerated ) {
					throw $e;
				}
				$quote = null;
			}
			$plan  = $this->plans->find( $plan_id );
			if ( ! $plan instanceof RatePlan ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::invalid( array( 'rate_plan_id' => __( 'Choose a rate plan.', 'radius-hotel-booking' ) ) );
			}
			$window = StayWindow::for( $plan, $arrival, $units, $checkin );
			if ( $importing && isset( $request['price_override'] ) ) {
				$quote = self::importedQuote( $quote, (float) $request['price_override'], $window->units() );
			}

			$grace = AvailabilityService::STAFF === $audience ? AvailabilityService::STAFF_GRACE_MINUTES * MINUTE_IN_SECONDS : 0;
			if ( ! $importing && $window->startGmt()->getTimestamp() < $now->getTimestamp() - $grace ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict( 'past', __( 'This time has already passed.', 'radius-hotel-booking' ), array( 'index' => $index ) );
			}

			// The calendar may have closed the date since the search.
			RateCalendar::flush();
			$closed = $importing ? null : RateCalendar::closure( $type_id, $plan_id, $window->unitDates() );
			if ( $closed ) {
				$reason = AvailabilityService::reason( $closed['code'], array( 'date' => $closed['date'] ) );
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict(
					$closed['code'],
					$reason['message'],
					array(
						'index' => $index,
						'date'  => $closed['date'],
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			// The search's public date rules and the guest limits: a request
			// posted directly must not skip them (critical review, M08).
			$rule = $importing ? null : AvailabilityService::dateRuleReason( $arrival, $audience, $now );
			if ( $rule ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict( $rule['code'], $rule['message'], array( 'index' => $index ) );
			}
			if ( ! $importing && ( isset( $request['adults'] ) || isset( $request['children'] ) || ! empty( $request['child_ages'] ) ) ) {
				list( $adults, $children ) = AvailabilityService::guestCounts( $request );
				$type                      = $types[ $type_id ] ?? ( new RoomTypeRepository() )->find( $type_id );
				$types[ $type_id ]         = $type;
				if ( ! $type || $adults > (int) $type->max_adults || $children > (int) $type->max_children ) {
					$reason = AvailabilityService::reason( 'occupancy' );
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'occupancy', $reason['message'], array( 'index' => $index ) );
				}
			}

			// 3. The price moved since it was shown.
			if ( ! $importing && isset( $request['expected_total'] ) && is_numeric( $request['expected_total'] ) && empty( $options['accept_new_price'] )
				&& ! Money::equals( (float) $request['expected_total'], (float) $quote['total'] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw DomainException::conflict(
					'price_changed',
					__( 'The price has changed since it was shown. Check the new price and confirm again.', 'radius-hotel-booking' ),
					array(
						'index' => $index,
						// The web sees the new total only, not the pricing rules behind it.
						'quote' => AvailabilityService::PUBLIC === $audience ? array( 'total' => $quote['total'] ) : $quote,
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$checked[ $index ] = array(
				'room'         => $room,
				'room_type_id' => $type_id,
				'rate_plan_id' => $plan_id,
				'plan'         => $plan,
				'window'       => $window,
				'quote'        => $quote,
			);
		}

		// Two requests of this call on the same room must not collide either.
		$buffers = $this->availability->buffers( array_unique( array_column( $checked, 'room_type_id' ) ) );
		foreach ( $checked as $index => $item ) {
			$checked[ $index ]['buffer_minutes'] = $buffers[ $item['room_type_id'] ] ?? 0;
			foreach ( $checked as $other => $second ) {
				if ( $other >= $index || (int) $second['room']['id'] !== (int) $item['room']['id'] ) {
					continue;
				}
				if ( Overlap::overlaps(
					$item['window']->startGmt()->getTimestamp(),
					$item['window']->endGmt()->getTimestamp(),
					$second['window']->startGmt()->getTimestamp(),
					$second['window']->endGmt()->getTimestamp(),
					$checked[ $index ]['buffer_minutes'] * MINUTE_IN_SECONDS
				) ) {
					$this->unavailable( $index, (int) $item['room']['id'], (string) $item['room']['number'], 'duplicate', null, $audience );
				}
			}
		}

		// The §4 predicate, inside the lock.
		$conflicts = $this->availability->conflicts(
			array_map(
				static fn( $item ) => array(
					'room_id'        => (int) $item['room']['id'],
					'start_gmt'      => Dates::to_gmt_db( $item['window']->startGmt() ),
					'end_gmt'        => Dates::to_gmt_db( $item['window']->endGmt() ),
					'buffer_minutes' => $item['buffer_minutes'],
				),
				$checked
			),
			$hold_token,
			(int) ( $options['exclude_line'] ?? 0 ),
			Dates::to_gmt_db( $now )
		);
		foreach ( $conflicts as $index => $conflict ) {
			$room = $checked[ $index ]['room'];
			$this->unavailable( $index, (int) $room['id'], (string) $room['number'], (string) $conflict['reason'], $conflict['interval'], $audience );
		}

		// The floor name, for the line's snapshot (after the lock: it reads nothing the check depends on).
		$floors = array();
		foreach ( ( new FloorRepository() )->findMany( array_values( array_unique( array_map( static fn( $item ) => (int) $item['room']['floor_id'], $checked ) ) ) ) as $floor ) {
			$floors[ (int) $floor->id ] = (string) $floor->name;
		}
		foreach ( $checked as $index => $item ) {
			$checked[ $index ]['floor_name'] = $floors[ (int) $item['room']['floor_id'] ] ?? '';
		}

		return $checked;
	}

	/**
	 * A line's stored fields from a checked request: the window, the room
	 * and rate snapshots and the **frozen price** (3.16). The caller adds
	 * `booking_id` and `status`.
	 *
	 * @param array $item    One entry of `lockAndCheck()`'s result.
	 * @param array $request The request it came from (`adults`, `children`).
	 * @return array
	 */
	public static function lineFields( array $item, array $request ): array {
		$row = $item['window']->toRow();
		return array_merge(
			$row,
			array(
				'room_id'            => (int) $item['room']['id'],
				'room_type_id'       => (int) $item['room_type_id'],
				'rate_plan_id'       => (int) $item['rate_plan_id'],
				'rate_plan_name'     => (string) $item['plan']->name,
				'room_number'        => (string) $item['room']['number'],
				'floor_name'         => (string) ( $item['floor_name'] ?? '' ),
				'occupied_until_gmt' => $row['end_at_gmt'],
				'units'              => $item['window']->units(),
				'adults'             => (int) ( $request['adults'] ?? 1 ),
				'children'           => (int) ( $request['children'] ?? 0 ),
			),
			self::priceFields( $item )
		);
	}

	/**
	 * The frozen price of a checked request.
	 *
	 * @param array $item One entry of `lockAndCheck()`'s result.
	 * @return array `{ unit_price, total, price_breakdown }`.
	 */
	public static function priceFields( array $item ): array {
		$quote = $item['quote'];
		return array(
			'unit_price'      => Money::round( (float) $quote['total'] / max( 1, $item['window']->units() ) ),
			'total'           => (float) $quote['total'],
			'price_breakdown' => wp_json_encode(
				array(
					'unit_prices' => $quote['unit_prices'],
					'steps'       => $quote['steps'],
				)
			),
		);
	}

	/**
	 * An imported line's quote: the legacy price, frozen as is, spread evenly
	 * over the units; today's price (when the rate is still sold) is kept in
	 * the breakdown for reference.
	 *
	 * @param array|null $quote Today's quote, or null (the rate is not sold any more).
	 * @param float      $price Legacy price.
	 * @param int        $units Units.
	 * @return array
	 */
	private static function importedQuote( ?array $quote, float $price, int $units ): array {
		$price = max( 0.0, (float) Money::round( $price ) );
		$each  = Money::round( $price / max( 1, $units ) );
		$steps = $quote ? (array) $quote['steps'] : array();
		$steps[] = array(
			'code'     => 'import',
			'label'    => __( 'Price from the old system', 'radius-hotel-booking' ),
			'total'    => $price,
			'computed' => $quote ? (float) $quote['total'] : null,
		);
		return array(
			'total'       => $price,
			'unit_prices' => array_fill( 0, max( 1, $units ), $each ),
			'steps'       => $steps,
		);
	}

	/**
	 * The status a new booking's lines start in (feature 8.11): with manual
	 * approval on, a guest's booking waits as `pending` until staff accept it;
	 * a booking staff make at the desk is accepted by making it. `pending`
	 * lines still occupy their rooms (D5).
	 *
	 * @param string $source `desk`, `web`, `import`, …
	 * @return string `pending` or `confirmed`.
	 */
	public static function initialStatus( string $source ): string {
		$status = 'desk' !== $source && rtbp_setting( 'booking', 'manualApproval', false ) ? self::PENDING : self::CONFIRMED;

		/**
		 * The status a new booking starts in.
		 *
		 * @param string $status `pending` or `confirmed`.
		 * @param string $source Booking source.
		 */
		$status = (string) apply_filters( 'rtbp_booking_initial_status', $status, $source );
		return in_array( $status, array( self::PENDING, self::CONFIRMED ), true ) ? $status : self::PENDING;
	}

	/**
	 * Refuse with 409 `room_unavailable`.
	 *
	 * @param int        $index    Request index.
	 * @param int        $room_id  Room id.
	 * @param string     $number   Room number.
	 * @param string     $reason   `booked`, `held`, `blocked`, `buffer`, a room state, `removed`, `moved`, `duplicate`.
	 * @param array|null $interval The conflicting interval.
	 * @param string     $audience Staff also get the booking reference.
	 * @return never
	 * @throws DomainException Always.
	 */
	private function unavailable( int $index, int $room_id, string $number, string $reason, ?array $interval, string $audience ) {
		$context = array(
			'index'   => $index,
			'room_id' => $room_id,
			'room'    => $number,
			'reason'  => $reason,
		);
		if ( AvailabilityService::STAFF === $audience && $interval && Overlap::LINE === $interval['kind'] ) {
			$context['booking_ref'] = $interval['label'];
		}
		$message = '' === $number
			? __( 'This room is no longer available. Choose another room.', 'radius-hotel-booking' )
			/* translators: %s: room number. */
			: sprintf( __( 'Room %s is no longer available for this time. Choose another room.', 'radius-hotel-booking' ), $number );
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
		throw DomainException::conflict( 'room_unavailable', $message, $context );
	}
}
