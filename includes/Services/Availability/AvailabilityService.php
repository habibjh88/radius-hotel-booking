<?php
/**
 * The availability search (booking-engine §5).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * "Which rooms can I sell, for which window, at what price — and if not,
 * why not?" for every room type × rate plan in scope.
 *
 * Four queries whatever the size of the property (§5.3): the catalogue, the
 * rooms, the busy intervals over the envelope of every candidate window, and
 * the rate calendar for the dates covered. Everything else runs in memory:
 * the §5.2 rule checks in order (the first failure is the rate's reason), the
 * room-by-room overlap (`Overlap`), and the price (`PriceResolver::price()`,
 * with the calendar and the occupancy primed, so pricing adds no free query).
 *
 * The result is the full, internal shape: it carries booking references,
 * block reasons and state notes. `AvailabilityResource` (T2b) strips them
 * for the public.
 */
class AvailabilityService {

	public const STAFF  = 'staff';
	public const PUBLIC = 'public';

	/**
	 * Minutes staff may still sell a window that has already started (a
	 * walk-in in progress, §5.2 `past`, §10 #26).
	 */
	public const STAFF_GRACE_MINUTES = 30;

	/**
	 * Busy intervals, catalogue and rooms.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $repository;

	/**
	 * Prices.
	 *
	 * @var PriceResolver
	 */
	private PriceResolver $prices;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityRepository|null $repository Repository.
	 * @param PriceResolver|null          $prices     Price resolver.
	 */
	public function __construct( ?AvailabilityRepository $repository = null, ?PriceResolver $prices = null ) {
		$this->repository = $repository ?? new AvailabilityRepository();
		$this->prices     = $prices ?? new PriceResolver();
	}

	/**
	 * Search.
	 *
	 * @param array                  $input    `arrival`, `departure`, `adults`, `children`, `child_ages[]`,
	 *                                         `room_type_id`, `rate_plan_id`, `checkin_time`, `hold_token`,
	 *                                         `exclude_booking_line` (staff).
	 * @param string                 $audience `staff` or `public`.
	 * @param DateTimeImmutable|null $now      "Now" (checks); default the current time.
	 * @return array `{ span, guests, room_types[], other_options[] }` (booking-engine §5.1).
	 * @throws DomainException 422 on invalid input.
	 */
	public function search( array $input, string $audience = self::STAFF, ?DateTimeImmutable $now = null ): array {
		$audience = self::PUBLIC === $audience ? self::PUBLIC : self::STAFF;
		$now      = $now ?? Dates::now();
		$request  = $this->normalise( $input, $audience );

		// Query 1: the catalogue.
		$types = self::catalogueTypes( $this->repository->catalogue( $request['room_type_id'] ), $request['rate_plan_id'] );
		if ( ! $types ) {
			return $this->result( $request, array(), array() );
		}

		// Query 2: the rooms of those types only (package scoping, §5.3).
		$rooms_by_type = array();
		foreach ( $this->repository->rooms( array_keys( $types ) ) as $room ) {
			$rooms_by_type[ (int) $room['room_type_id'] ][] = $room;
		}

		// Derive every candidate window; the span decides units (§2).
		$default_buffer = max( 0, (int) rtbp_setting( 'booking', 'bufferMinutes', 0 ) );
		$candidates     = array();
		$from           = null;
		$to             = null;
		$dates          = array();
		foreach ( $types as $type_id => $type ) {
			$types[ $type_id ]['buffer'] = null === $type['buffer_minutes'] ? $default_buffer : max( 0, (int) $type['buffer_minutes'] );
			foreach ( $type['rates'] as $plan_id => $rate ) {
				$candidate                           = $this->candidate( $rate, $request );
				$candidates[ $type_id ][ $plan_id ] = $candidate;
				if ( ! $candidate['window'] ) {
					continue;
				}
				$window = $candidate['window'];
				$buffer = $types[ $type_id ]['buffer'] * MINUTE_IN_SECONDS;
				// Whole local days, so the busy list also answers occupancy per day.
				$day_start = Dates::start_of_day( $window->start() )->getTimestamp();
				$day_end   = Dates::end_of_day( $window->end() )->getTimestamp();
				$from      = min( $from ?? PHP_INT_MAX, $day_start, $window->startGmt()->getTimestamp() - $buffer );
				$to        = max( $to ?? 0, $day_end, $window->endGmt()->getTimestamp() + $buffer );
				foreach ( $window->unitDates() as $date ) {
					$dates[ $date ] = true;
				}
			}
		}

		// Query 3: everything that occupies those rooms in the envelope.
		$room_ids = array();
		foreach ( $rooms_by_type as $list ) {
			foreach ( $list as $room ) {
				$room_ids[] = (int) $room['id'];
			}
		}
		$busy = null === $from ? array() : $this->repository->busy(
			$room_ids,
			gmdate( 'Y-m-d H:i:s', $from ),
			gmdate( 'Y-m-d H:i:s', $to ),
			$request['hold_token'],
			$request['exclude_booking_line'],
			Dates::to_gmt_db( $now )
		);

		// Query 4: the rate calendar for every date a unit starts on.
		if ( $dates ) {
			ksort( $dates );
			RateCalendar::prime( array_keys( $types ), (string) array_key_first( $dates ), (string) array_key_last( $dates ) );
		}
		OccupancyCalculator::primeFrom( array_keys( $types ), $rooms_by_type, $busy, array_keys( $dates ) );

		$steps     = $this->prices->steps();
		$booked_at = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );

		$room_types    = array();
		$other_options = array();
		foreach ( $types as $type_id => $type ) {
			$rooms       = $rooms_by_type[ $type_id ] ?? array();
			$sellable    = array_values( array_filter( $rooms, static fn( $room ) => Room::AVAILABLE === $room['state'] ) );
			$rates_out   = array();
			$room_status = array();

			foreach ( $type['rates'] as $plan_id => $rate ) {
				$candidate = $candidates[ $type_id ][ $plan_id ];
				$window    = $candidate['window'];
				$reason    = $candidate['reason'] ?? $this->ruleReason( $type_id, $plan_id, $type, $rate, $candidate, $request, $audience, $now, count( $sellable ) );

				$out = array(
					'rate_plan_id' => $plan_id,
					'name'         => $rate['name'],
					'type'         => $rate['type'],
					'multi_unit'   => $rate['multi_unit'],
					'window'       => $window ? $window->toArray() : null,
					'units'        => $candidate['units'],
					'available'    => false,
					'free_count'   => 0,
					'price'        => null,
					'reasons'      => array(),
				);

				if ( $reason && 'incompatible_span' === $reason['code'] ) {
					$out['reasons']  = array( $reason );
					$other_options[] = array_merge(
						array(
							'room_type_id'   => $type_id,
							'room_type_name' => $type['name'],
						),
						$out
					);
					continue;
				}

				// Price whatever the rooms say, once the rules pass.
				if ( ! $reason && $window ) {
					$out['price'] = $this->prices->price( (object) $rate, $window, $type_id, $plan_id, $booked_at, $steps );
				}

				// Room by room (only the type's own rooms).
				if ( ! $reason && $window ) {
					$start = $window->startGmt()->getTimestamp();
					$end   = $window->endGmt()->getTimestamp();
					foreach ( $rooms as $room ) {
						$room_id  = (int) $room['id'];
						$conflict = Overlap::roomReason( (string) $room['state'], $busy[ $room_id ] ?? array(), $start, $end, $type['buffer'] );
						if ( $conflict ) {
							$room_status[ $room_id ]['reasons_by_rate'][ $plan_id ] = $this->roomReason( $conflict );
						} else {
							$room_status[ $room_id ]['available_for'][] = $plan_id;
							++$out['free_count'];
						}
					}
					if ( ! $out['free_count'] ) {
						$reason = self::reason( 'fully_booked' );
					}
				}

				$out['available'] = ! $reason;
				$out['reasons']   = $reason ? array( $reason ) : array();
				$rates_out[]      = $out;
			}

			$room_types[] = array(
				'id'             => $type_id,
				'name'           => $type['name'],
				'max_adults'     => $type['max_adults'],
				'max_children'   => $type['max_children'],
				'rooms_total'    => count( $rooms ),
				'rooms_sellable' => count( $sellable ),
				'rates'          => $rates_out,
				'floors'         => $this->floors( $rooms, $room_status ),
			);
		}

		return $this->result( $request, $room_types, $other_options );
	}

	/**
	 * A reason object with its translated message.
	 *
	 * @param string $code  Reason code.
	 * @param array  $extra Extra fields (`date`, `booking_ref`, …).
	 * @return array `{ code, message, … }`.
	 */
	public static function reason( string $code, array $extra = array() ): array {
		$messages = array(
			'past'              => __( 'This time has already passed.', 'radius-hotel-booking' ),
			'booking_window'    => __( 'This date is too far ahead to book online.', 'radius-hotel-booking' ),
			'same_day_cutoff'   => __( 'Same-day bookings are closed for today.', 'radius-hotel-booking' ),
			'rate_disabled'     => __( 'This rate is not sold for this room type.', 'radius-hotel-booking' ),
			'room_type_closed'  => __( 'This room type is closed on this date.', 'radius-hotel-booking' ),
			'rate_closed'       => __( 'This rate is closed on this date.', 'radius-hotel-booking' ),
			'min_units'         => __( 'The stay is shorter than this rate allows.', 'radius-hotel-booking' ),
			'max_units'         => __( 'The stay is longer than this rate allows.', 'radius-hotel-booking' ),
			'occupancy'         => __( 'Too many guests for this room type.', 'radius-hotel-booking' ),
			'incompatible_span' => __( 'This rate does not match the dates chosen.', 'radius-hotel-booking' ),
			'invalid_checkin_time' => __( 'This check-in time is not allowed for this rate.', 'radius-hotel-booking' ),
			'invalid_window'    => __( 'This rate cannot be sold on this date.', 'radius-hotel-booking' ),
			'no_rooms'          => __( 'This room type has no rooms that can be sold.', 'radius-hotel-booking' ),
			'fully_booked'      => __( 'Fully booked.', 'radius-hotel-booking' ),
			'booked'            => __( 'Booked', 'radius-hotel-booking' ),
			'held'              => __( 'Being booked', 'radius-hotel-booking' ),
			'blocked'           => __( 'Blocked', 'radius-hotel-booking' ),
			'buffer'            => __( 'Being cleaned', 'radius-hotel-booking' ),
			'maintenance'       => __( 'Maintenance', 'radius-hotel-booking' ),
			'out_of_service'    => __( 'Out of service', 'radius-hotel-booking' ),
		);
		return array_merge(
			array(
				'code'    => $code,
				'message' => $messages[ $code ] ?? $code,
			),
			$extra
		);
	}

	/**
	 * Validate and normalise the request.
	 *
	 * @param array  $input    Raw input.
	 * @param string $audience Audience.
	 * @return array
	 * @throws DomainException 422.
	 */
	private function normalise( array $input, string $audience ): array {
		$errors    = array();
		$arrival   = (string) ( $input['arrival'] ?? '' );
		$departure = (string) ( $input['departure'] ?? '' );
		if ( '' === $departure ) {
			$departure = $arrival;
		}
		if ( ! Dates::is_date( $arrival ) ) {
			$errors['arrival'] = __( 'Choose an arrival date.', 'radius-hotel-booking' );
		}
		// A departure left out follows the arrival: only a given one is reported.
		if ( ! Dates::is_date( $departure ) && $departure !== $arrival ) {
			$errors['departure'] = __( 'Choose a departure date.', 'radius-hotel-booking' );
		}
		$nights = 0;
		if ( ! $errors && Dates::is_date( $departure ) ) {
			$nights = (int) Dates::local( $arrival )->diff( Dates::local( $departure ) )->format( '%r%a' );
			if ( $nights < 0 ) {
				$errors['departure'] = __( 'The departure cannot be before the arrival.', 'radius-hotel-booking' );
			} elseif ( $nights > StayWindow::MAX_UNITS ) {
				/* translators: %d: the most nights or days in one stay. */
				$errors['departure'] = sprintf( __( 'A stay can cover at most %d nights or days.', 'radius-hotel-booking' ), StayWindow::MAX_UNITS );
			}
		}

		$adults   = isset( $input['adults'] ) ? (int) $input['adults'] : 1;
		$children = isset( $input['children'] ) ? (int) $input['children'] : 0;
		if ( $adults < 1 || $adults > 99 ) {
			$errors['adults'] = __( 'Enter between 1 and 99 adults.', 'radius-hotel-booking' );
		}
		if ( $children < 0 || $children > 99 ) {
			$errors['children'] = __( 'Enter between 0 and 99 children.', 'radius-hotel-booking' );
		}
		// 8.9: with ages given, a child older than the maximum child age counts as an adult.
		if ( isset( $input['child_ages'] ) && is_array( $input['child_ages'] ) && $input['child_ages'] ) {
			$max_age  = (int) rtbp_setting( 'booking', 'maxChildAge', 16 );
			$ages     = array_map( 'intval', array_slice( $input['child_ages'], 0, 99 ) );
			$older    = count( array_filter( $ages, static fn( $age ) => $age > $max_age ) );
			$children = count( $ages ) - $older;
			$adults  += $older;
		}

		$checkin = isset( $input['checkin_time'] ) ? (string) $input['checkin_time'] : '';
		if ( '' !== $checkin && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $checkin ) ) {
			$errors['checkin_time'] = __( 'Enter the check-in time as HH:MM.', 'radius-hotel-booking' );
		}

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		return array(
			'arrival'              => $arrival,
			'departure'            => $departure,
			'nights'               => $nights,
			'adults'               => $adults,
			'children'             => $children,
			'room_type_id'         => max( 0, (int) ( $input['room_type_id'] ?? 0 ) ),
			'rate_plan_id'         => max( 0, (int) ( $input['rate_plan_id'] ?? 0 ) ),
			'checkin_time'         => '' === $checkin ? null : $checkin,
			'hold_token'           => preg_replace( '/[^A-Za-z0-9_-]/', '', substr( (string) ( $input['hold_token'] ?? '' ), 0, 64 ) ),
			// Editing a line is a staff action only.
			'exclude_booking_line' => self::STAFF === $audience ? max( 0, (int) ( $input['exclude_booking_line'] ?? 0 ) ) : 0,
		);
	}

	/**
	 * Group the catalogue rows by room type (the search, the calendar).
	 * Switched-off rates and inactive plans are left out unless that plan was
	 * asked for, in which case they are kept to report `rate_disabled`.
	 *
	 * @param array[] $rows         Catalogue rows.
	 * @param int     $rate_plan_id Requested plan; 0 = all.
	 * @return array<int, array>
	 */
	public static function catalogueTypes( array $rows, int $rate_plan_id = 0 ): array {
		$types = array();
		foreach ( $rows as $row ) {
			$type_id = (int) $row['type_id'];
			if ( ! isset( $types[ $type_id ] ) ) {
				$types[ $type_id ] = array(
					'name'           => (string) $row['type_name'],
					'max_adults'     => (int) $row['max_adults'],
					'max_children'   => (int) $row['max_children'],
					'buffer_minutes' => null === $row['buffer_minutes'] ? null : (int) $row['buffer_minutes'],
					'rates'          => array(),
				);
			}
			// No rate, or a removed plan.
			if ( null === $row['rate_id'] || null === $row['plan_name'] ) {
				continue;
			}
			$plan_id = (int) $row['rate_plan_id'];
			if ( $rate_plan_id && $plan_id !== $rate_plan_id ) {
				continue;
			}
			$sold = (int) $row['enabled'] && (int) $row['plan_active'];
			if ( ! $sold && ! $rate_plan_id ) {
				continue;
			}
			$types[ $type_id ]['rates'][ $plan_id ] = array(
				'sold'             => (bool) $sold,
				'name'             => (string) $row['plan_name'],
				'type'             => (string) $row['plan_type'],
				'start_time'       => $row['start_time'],
				'end_time'         => $row['end_time'],
				'duration_minutes' => (int) $row['duration_minutes'],
				'checkin_from'     => $row['checkin_from'],
				'checkin_until'    => $row['checkin_until'],
				'multi_unit'       => (bool) (int) $row['multi_unit'],
				'price'            => (float) $row['price'],
				'sale_price'       => null === $row['sale_price'] ? null : (float) $row['sale_price'],
				'min_units'        => max( 1, (int) $row['min_units'] ),
				'max_units'        => null === $row['max_units'] ? null : (int) $row['max_units'],
			);
		}
		if ( $rate_plan_id ) {
			// Asking for one plan: types that do not sell it at all drop out.
			$types = array_filter( $types, static fn( $type ) => (bool) $type['rates'] );
		}
		return $types;
	}

	/**
	 * Units and window of one rate for the searched span (§2). A plan that
	 * does not fit the span gets its single-unit window on the arrival date
	 * and the reason `incompatible_span`.
	 *
	 * @param array $rate    Rate (with plan fields).
	 * @param array $request Request.
	 * @return array `{ units, window: ?StayWindow, reason: ?array }`.
	 */
	private function candidate( array $rate, array $request ): array {
		$nights = $request['nights'];
		$units  = 1;
		$fits   = true;
		if ( $rate['multi_unit'] ) {
			$units = max( 1, $nights );
		} elseif ( $nights > 1 ) {
			$fits = false;
		}

		try {
			$window = StayWindow::for( $rate, $request['arrival'], $units, RatePlan::FLEXIBLE === $rate['type'] ? $request['checkin_time'] : null );
		} catch ( DomainException $e ) {
			$code = 'invalid_checkin_time' === $e->getErrorCode() ? 'invalid_checkin_time' : 'invalid_window';
			return array(
				'units'  => $units,
				'window' => null,
				'reason' => self::reason( $code ),
			);
		}

		// A single-unit window fits one night only when it runs overnight or 24 h.
		if ( ! $rate['multi_unit'] && 1 === $nights && ! $window->crossesMidnight() && $window->minutes() < DAY_IN_SECONDS / MINUTE_IN_SECONDS ) {
			$fits = false;
		}

		return array(
			'units'  => $units,
			'window' => $window,
			'reason' => $fits ? null : self::reason( 'incompatible_span' ),
		);
	}

	/**
	 * The §5.2 rule checks up to `no_rooms`, in order; the first failure.
	 * (`incompatible_span` is decided with the window; `fully_booked` after
	 * the rooms.)
	 *
	 * @param int               $type_id   Room type id.
	 * @param int               $plan_id   Rate plan id.
	 * @param array             $type      Room type.
	 * @param array             $rate      Rate.
	 * @param array             $candidate Units and window.
	 * @param array             $request   Request.
	 * @param string            $audience  Audience.
	 * @param DateTimeImmutable $now       Now.
	 * @param int               $sellable  Sellable rooms of the type.
	 * @return array|null
	 */
	private function ruleReason( int $type_id, int $plan_id, array $type, array $rate, array $candidate, array $request, string $audience, DateTimeImmutable $now, int $sellable ): ?array {
		$window = $candidate['window'];
		$start  = $window->startGmt()->getTimestamp();
		$now_ts = $now->getTimestamp();
		$grace  = self::STAFF === $audience ? self::STAFF_GRACE_MINUTES * MINUTE_IN_SECONDS : 0;
		if ( $start < $now_ts - $grace ) {
			return self::reason( 'past' );
		}

		if ( self::PUBLIC === $audience ) {
			$today = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
			$ahead = max( 0, (int) rtbp_setting( 'booking', 'bookingWindowDays', 0 ) );
			if ( $ahead && $request['arrival'] > Dates::add_days( Dates::local( $today ), $ahead )->format( 'Y-m-d' ) ) {
				return self::reason( 'booking_window' );
			}
			if ( $request['arrival'] === $today ) {
				$cutoff = (string) rtbp_setting( 'booking', 'sameDayCutoff', '14:00' );
				if ( ! rtbp_setting( 'booking', 'sameDayEnabled', false ) || $now->setTimezone( Dates::timezone() )->format( 'H:i' ) >= $cutoff ) {
					return self::reason( 'same_day_cutoff' );
				}
			}
		}

		if ( ! $rate['sold'] ) {
			return self::reason( 'rate_disabled' );
		}

		$closed = RateCalendar::closure( $type_id, $plan_id, $window->unitDates() );
		if ( $closed ) {
			return self::reason( $closed['code'], array( 'date' => $closed['date'] ) );
		}

		if ( $rate['multi_unit'] ) {
			if ( $candidate['units'] < $rate['min_units'] ) {
				return self::reason( 'min_units', array( 'min' => $rate['min_units'] ) );
			}
			if ( null !== $rate['max_units'] && $candidate['units'] > $rate['max_units'] ) {
				return self::reason( 'max_units', array( 'max' => $rate['max_units'] ) );
			}
		}

		if ( $request['adults'] > $type['max_adults'] || $request['children'] > $type['max_children'] ) {
			return self::reason( 'occupancy' );
		}

		if ( ! $sellable ) {
			return self::reason( 'no_rooms' );
		}
		return null;
	}

	/**
	 * A room-level reason from an overlap result.
	 *
	 * @param array $conflict `{ reason, interval }`.
	 * @return array
	 */
	private function roomReason( array $conflict ): array {
		$interval = $conflict['interval'];
		$extra    = array();
		if ( $interval && Overlap::LINE === $interval['kind'] ) {
			$extra['booking_id']  = $interval['ref_id'];
			$extra['booking_ref'] = $interval['label'];
		} elseif ( $interval && Overlap::BLOCK === $interval['kind'] ) {
			$extra['block_id']     = $interval['ref_id'];
			$extra['block_reason'] = $interval['label'];
		}
		if ( $interval ) {
			$extra['until'] = Dates::to_iso( Dates::from_gmt( $interval['e'] ) );
		}
		return self::reason( $conflict['reason'], $extra );
	}

	/**
	 * Rooms grouped by floor, each with the rates it can take and why not.
	 *
	 * @param array[] $rooms       The type's rooms (floor order).
	 * @param array   $room_status Room id => `{ available_for, reasons_by_rate }`.
	 * @return array[]
	 */
	private function floors( array $rooms, array $room_status ): array {
		$floors = array();
		foreach ( $rooms as $room ) {
			$room_id  = (int) $room['id'];
			$floor_id = (int) $room['floor_id'];
			if ( ! isset( $floors[ $floor_id ] ) ) {
				$floors[ $floor_id ] = array(
					'id'    => $floor_id,
					'name'  => (string) $room['floor_name'],
					'rooms' => array(),
				);
			}
			$floors[ $floor_id ]['rooms'][] = array(
				'id'              => $room_id,
				'number'          => (string) $room['number'],
				'state'           => (string) $room['state'],
				'state_note'      => (string) $room['state_note'],
				'available_for'   => $room_status[ $room_id ]['available_for'] ?? array(),
				'reasons_by_rate' => (object) ( $room_status[ $room_id ]['reasons_by_rate'] ?? array() ),
			);
		}
		return array_values( $floors );
	}

	/**
	 * The response envelope.
	 *
	 * @param array $request       Request.
	 * @param array $room_types    Room types.
	 * @param array $other_options Rates that do not fit the span.
	 * @return array
	 */
	private function result( array $request, array $room_types, array $other_options ): array {
		return array(
			'span'          => array(
				'arrival'   => $request['arrival'],
				'departure' => $request['departure'],
				'nights'    => $request['nights'],
			),
			'guests'        => array(
				'adults'   => $request['adults'],
				'children' => $request['children'],
			),
			'room_types'    => $room_types,
			'other_options' => $other_options,
		);
	}
}
