<?php
/**
 * The availability calendar (features 8.1–8.4, 8.6).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\AvailabilityRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BlockRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RateCalendarRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\PriceResolver;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * One room type, one month: every rate plan it sells × every date, with the
 * price a one-unit stay starting that day would cost (the full pricing
 * pipeline), the override, the open/closed state and how many rooms are
 * free for that rate's window. Plus the whole-type row and, per day, rooms
 * with nothing on them at all.
 *
 * Reads the same four sources as the search (catalogue, rooms, busy
 * intervals over the month, the calendar) and prices in memory.
 *
 * Writes (`save()`) change cells: a price override (8.2), a rate closed on a
 * date (8.3), the whole room type closed on a date (8.4). Past dates cannot
 * be changed. Each save is one transaction under the room type's row lock,
 * logged per kind of change with the dates it touched.
 */
class CalendarService {

	/**
	 * Most cells one save may change (a month of 11 rates plus the type row).
	 */
	public const MAX_CELLS = 400;

	/**
	 * How far ahead a date may be changed, in days.
	 */
	public const MAX_DAYS_AHEAD = 1100;

	/**
	 * Most days one bulk update may span.
	 */
	public const MAX_BULK_DAYS = 366;

	/**
	 * What a bulk update can do (8.5).
	 */
	public const BULK_ACTIONS = array( 'set_price', 'clear_price', 'open', 'close' );

	/**
	 * Engine queries.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $availability;

	/**
	 * Calendar rows.
	 *
	 * @var RateCalendarRepository
	 */
	private RateCalendarRepository $calendar;

	/**
	 * Room types.
	 *
	 * @var RoomTypeRepository
	 */
	private RoomTypeRepository $types;

	/**
	 * Prices.
	 *
	 * @var PriceResolver
	 */
	private PriceResolver $prices;

	/**
	 * Blocks (the overlay's labels).
	 *
	 * @var BlockRepository
	 */
	private BlockRepository $blocks;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityRepository|null $availability Engine queries.
	 * @param RateCalendarRepository|null $calendar     Calendar rows.
	 * @param RoomTypeRepository|null     $types        Room types.
	 * @param PriceResolver|null          $prices       Prices.
	 * @param BlockRepository|null        $blocks       Blocks.
	 */
	public function __construct( ?AvailabilityRepository $availability = null, ?RateCalendarRepository $calendar = null, ?RoomTypeRepository $types = null, ?PriceResolver $prices = null, ?BlockRepository $blocks = null ) {
		$this->availability = $availability ?? new AvailabilityRepository();
		$this->calendar     = $calendar ?? new RateCalendarRepository();
		$this->types        = $types ?? new RoomTypeRepository();
		$this->prices       = $prices ?? new PriceResolver();
		$this->blocks       = $blocks ?? new BlockRepository();
	}

	/**
	 * The month grid of a room type.
	 *
	 * @param int                    $room_type_id Room type id.
	 * @param string                 $month        `Y-m`; empty = this month.
	 * @param DateTimeImmutable|null $now          Now (checks).
	 * @return array
	 * @throws DomainException 404 unknown room type, 422 bad month.
	 */
	public function grid( int $room_type_id, string $month = '', ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$today = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		if ( '' === $month ) {
			$month = substr( $today, 0, 7 );
		}
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'month' => __( 'Choose a month.', 'radius-hotel-booking' ) ) );
		}

		$types = AvailabilityService::catalogueTypes( $this->availability->catalogue( $room_type_id, true ) );
		$type  = $types[ $room_type_id ] ?? null;
		if ( ! $room_type_id || ! $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'Room type not found.', 'radius-hotel-booking' ) );
		}

		$first = Dates::local( $month . '-01' );
		$dates = array();
		for ( $day = $first; $day->format( 'Y-m' ) === $month; $day = Dates::add_days( $day, 1 ) ) {
			$dates[] = $day->format( 'Y-m-d' );
		}
		$last = (string) end( $dates );

		$rooms    = $this->availability->rooms( array( $room_type_id ) );
		$sellable = array_values( array_filter( $rooms, static fn( $room ) => Room::AVAILABLE === $room['state'] ) );
		$buffer   = null === $type['buffer_minutes'] ? max( 0, (int) rtbp_setting( 'booking', 'bufferMinutes', 0 ) ) : max( 0, (int) $type['buffer_minutes'] );

		// Busy over the month, stretched to the end of the longest window that
		// starts on the last day (an overnight stay, a flexible plan of up to
		// 7 days), plus the buffer.
		$end = Dates::add_days( Dates::end_of_day( $last ), 2 )->getTimestamp();
		foreach ( $type['rates'] as $rate ) {
			try {
				$end = max( $end, StayWindow::for( $rate, $last, 1 )->endGmt()->getTimestamp() );
			} catch ( DomainException $e ) {
				continue; // No such window that day (a clock change).
			}
		}
		$from = Dates::start_of_day( $dates[0] )->getTimestamp() - $buffer * MINUTE_IN_SECONDS;
		$to   = $end + $buffer * MINUTE_IN_SECONDS;
		$busy = $this->availability->busy( array_map( static fn( $room ) => (int) $room['id'], $sellable ), gmdate( 'Y-m-d H:i:s', $from ), gmdate( 'Y-m-d H:i:s', $to ), '', 0, Dates::to_gmt_db( $now ) );

		RateCalendar::flush();
		RateCalendar::prime( array( $room_type_id ), $dates[0], $last );
		$occupancy = OccupancyCalculator::primeFrom( array( $room_type_id ), array( $room_type_id => $rooms ), $busy, $dates );

		$steps = $this->prices->steps();
		$rates = array();
		foreach ( $type['rates'] as $plan_id => $rate ) {
			$cells = array();
			foreach ( $dates as $date ) {
				$cell = $this->cell( $room_type_id, $plan_id, $rate, $date, $today, $sellable, $busy, $buffer, $steps );
				$cells[] = $cell;
			}
			$rates[] = array(
				'rate_plan_id' => $plan_id,
				'name'         => $rate['name'],
				'type'         => $rate['type'],
				'multi_unit'   => $rate['multi_unit'],
				'price'        => $rate['price'],
				'sale_price'   => $rate['sale_price'],
				'cells'        => $cells,
			);
		}

		// Blocks on this type's sellable rooms (8.12 overlay), from the busy
		// intervals already loaded: block id => rooms it closes here.
		$block_rooms = array();
		$block_spans = array();
		foreach ( $busy as $room_id => $intervals ) {
			foreach ( $intervals as $interval ) {
				if ( 'block' === $interval['kind'] ) {
					$block_rooms[ $interval['ref_id'] ][ $room_id ] = true;
					$block_spans[ $room_id ][] = array( strtotime( $interval['s'] . ' UTC' ), strtotime( $interval['e'] . ' UTC' ) );
				}
			}
		}

		$days = array();
		foreach ( $dates as $date ) {
			list( $occupied, $count ) = $occupancy[ $room_type_id ][ $date ];
			$day_start = Dates::start_of_day( $date )->getTimestamp();
			$day_end   = Dates::add_days( Dates::start_of_day( $date ), 1 )->getTimestamp();
			$blocked   = 0;
			foreach ( $block_spans as $spans ) {
				foreach ( $spans as $span ) {
					if ( $span[0] < $day_end && $span[1] > $day_start ) {
						++$blocked;
						break;
					}
				}
			}
			$days[] = array(
				'date'          => $date,
				'closed'        => null !== RateCalendar::closure( $room_type_id, 0, array( $date ) ),
				'past'          => $date < $today,
				'sellable'      => $count,
				// Rooms with nothing on them at all that day.
				'free_rooms'    => $count - $occupied,
				// Rooms a block touches that day.
				'blocked_rooms' => $blocked,
			);
		}

		// Only when a block touches the month: one more query for the labels.
		$blocks = array();
		foreach ( $block_rooms ? $this->blocks->labelled( array_keys( $block_rooms ) ) : array() as $id => $row ) {
			$blocks[] = array_merge( BlockService::shape( $row ), array( 'rooms' => count( $block_rooms[ $id ] ) ) );
		}

		return array(
			'room_type' => array(
				'id'             => $room_type_id,
				'name'           => $type['name'],
				'rooms_total'    => count( $rooms ),
				'rooms_sellable' => count( $sellable ),
			),
			'month'     => $month,
			'today'     => $today,
			'dates'     => $dates,
			'days'      => $days,
			'rates'     => $rates,
			'blocks'    => $blocks,
		);
	}

	/**
	 * Change cells of a room type.
	 *
	 * @param int                    $room_type_id Room type id.
	 * @param array[]                $cells        Each `{ rate_plan_id (0 = the room type), date,
	 *                                             price_override? (number, or null to clear), is_closed? }`.
	 * @param DateTimeImmutable|null $now          Now (checks).
	 * @return array `{ changed: int }`.
	 * @throws DomainException 404, 422 (errors keyed `cells.<i>.<field>`).
	 */
	public function save( int $room_type_id, array $cells, ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$today = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		$limit = Dates::add_days( Dates::local( $today ), self::MAX_DAYS_AHEAD )->format( 'Y-m-d' );

		$type = $this->types->find( $room_type_id );
		if ( ! $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'Room type not found.', 'radius-hotel-booking' ) );
		}
		if ( ! $cells || count( $cells ) > self::MAX_CELLS ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				/* translators: %d: most cells per save. */
				array( 'cells' => sprintf( __( 'Change between 1 and %d dates at a time.', 'radius-hotel-booking' ), self::MAX_CELLS ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$plan_names = $this->planNames( $room_type_id, (string) $type->name );

		$errors  = array();
		$changes = array();
		foreach ( array_values( $cells ) as $i => $cell ) {
			$cell    = (array) $cell;
			$plan_id = (int) ( $cell['rate_plan_id'] ?? -1 );
			$date    = (string) ( $cell['date'] ?? '' );
			if ( ! array_key_exists( $plan_id, $plan_names ) ) {
				$errors[ "cells.$i.rate_plan_id" ] = __( 'This room type does not sell this rate plan.', 'radius-hotel-booking' );
				continue;
			}
			if ( ! Dates::is_date( $date ) ) {
				$errors[ "cells.$i.date" ] = __( 'Choose a valid date.', 'radius-hotel-booking' );
				continue;
			}
			if ( $date < $today ) {
				$errors[ "cells.$i.date" ] = __( 'Past dates cannot be changed.', 'radius-hotel-booking' );
				continue;
			}
			if ( $date > $limit ) {
				$errors[ "cells.$i.date" ] = __( 'This date is too far ahead.', 'radius-hotel-booking' );
				continue;
			}
			$change = array();
			if ( array_key_exists( 'price_override', $cell ) ) {
				$price = $cell['price_override'];
				if ( 0 === $plan_id && null !== $price && '' !== $price ) {
					$errors[ "cells.$i.price_override" ] = __( 'Set prices on a rate, not on the whole room type.', 'radius-hotel-booking' );
					continue;
				}
				if ( null === $price || '' === $price ) {
					$change['price_override'] = null;
				} elseif ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 || (float) $price > 999999999 ) {
					$errors[ "cells.$i.price_override" ] = __( 'Enter a price of 0 or more.', 'radius-hotel-booking' );
					continue;
				} else {
					$change['price_override'] = Money::round( (float) $price );
				}
			}
			if ( array_key_exists( 'is_closed', $cell ) ) {
				$change['is_closed'] = rest_sanitize_boolean( $cell['is_closed'] );
			}
			if ( ! $change ) {
				$errors[ "cells.$i" ] = __( 'Nothing to change.', 'radius-hotel-booking' );
				continue;
			}
			// A later cell for the same rate and date wins.
			$changes[ $plan_id . '|' . $date ] = array_merge( $changes[ $plan_id . '|' . $date ] ?? array(), $change );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$result = $this->commit(
			$room_type_id,
			$changes,
			function ( array $diff ) use ( $room_type_id, $plan_names, $type ) {
				$log = array();
				foreach ( $diff as $row ) {
					if ( $row['price_changed'] ) {
						$log[ 'availability.override|' . $row['plan_id'] ][ $row['date'] ] = array( $row['before']['price_override'], $row['after']['price_override'] );
					}
					if ( $row['closed_changed'] ) {
						$log[ ( $row['after']['is_closed'] ? 'availability.close|' : 'availability.open|' ) . $row['plan_id'] ][ $row['date'] ] = null;
					}
				}
				foreach ( $log as $key => $entries ) {
					list( $action, $plan_id ) = explode( '|', $key );
					$this->logChange( $action, (int) $plan_id, $entries, $room_type_id, (string) $type->name, $plan_names[ (int) $plan_id ] ?? '' );
				}
			}
		);

		RateCalendar::flush();
		return $result;
	}

	/**
	 * Bulk update (8.5): every date of a range that falls on the chosen
	 * weekdays × the chosen rate plans gets the same change. With `$apply`
	 * false nothing is written and the counts are a preview.
	 *
	 * @param int                    $room_type_id Room type id.
	 * @param array                  $input        `{ from, to, weekdays: int[] (0 = Sunday … 6),
	 *                                             rate_plan_ids: int[] (0 = the room type),
	 *                                             action: set_price|clear_price|open|close, price? }`.
	 * @param bool                   $apply        Write (true) or only count (false).
	 * @param DateTimeImmutable|null $now          Now (checks).
	 * @return array `{ dates, cells, changes }`: matching dates, cells they cover, cells that
	 *               change (or changed).
	 * @throws DomainException 404, 422.
	 */
	public function bulk( int $room_type_id, array $input, bool $apply, ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$today = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		$limit = Dates::add_days( Dates::local( $today ), self::MAX_DAYS_AHEAD )->format( 'Y-m-d' );

		$type = $this->types->find( $room_type_id );
		if ( ! $type ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'Room type not found.', 'radius-hotel-booking' ) );
		}
		$plan_names = $this->planNames( $room_type_id, (string) $type->name );

		$errors = array();
		$action = (string) ( $input['action'] ?? '' );
		if ( ! in_array( $action, self::BULK_ACTIONS, true ) ) {
			$errors['action'] = __( 'Choose what to change.', 'radius-hotel-booking' );
		}

		$from = (string) ( $input['from'] ?? '' );
		$to   = (string) ( $input['to'] ?? '' );
		if ( ! Dates::is_date( $from ) ) {
			$errors['from'] = __( 'Choose a valid date.', 'radius-hotel-booking' );
		} elseif ( $from < $today ) {
			$errors['from'] = __( 'Past dates cannot be changed.', 'radius-hotel-booking' );
		}
		if ( ! Dates::is_date( $to ) ) {
			$errors['to'] = __( 'Choose a valid date.', 'radius-hotel-booking' );
		} elseif ( $to > $limit ) {
			$errors['to'] = __( 'This date is too far ahead.', 'radius-hotel-booking' );
		} elseif ( Dates::is_date( $from ) && $to < $from ) {
			$errors['to'] = __( 'The last date must be on or after the first.', 'radius-hotel-booking' );
		} elseif ( Dates::is_date( $from ) && Dates::local( $from )->diff( Dates::local( $to ) )->days >= self::MAX_BULK_DAYS ) {
			/* translators: %d: most days in one bulk update. */
			$errors['to'] = sprintf( __( 'Choose at most %d days at a time.', 'radius-hotel-booking' ), self::MAX_BULK_DAYS );
		}

		$weekdays = array_values( array_unique( array_map( 'intval', (array) ( $input['weekdays'] ?? array() ) ) ) );
		if ( ! $weekdays || array_diff( $weekdays, range( 0, 6 ) ) ) {
			$errors['weekdays'] = __( 'Choose at least one day of the week.', 'radius-hotel-booking' );
		}

		$plan_ids = array_values( array_unique( array_map( 'intval', (array) ( $input['rate_plan_ids'] ?? array() ) ) ) );
		if ( ! $plan_ids ) {
			$errors['rate_plan_ids'] = __( 'Choose at least one rate.', 'radius-hotel-booking' );
		} elseif ( array_diff( $plan_ids, array_keys( $plan_names ) ) ) {
			$errors['rate_plan_ids'] = __( 'This room type does not sell this rate plan.', 'radius-hotel-booking' );
		} elseif ( in_array( $action, array( 'set_price', 'clear_price' ), true ) && in_array( 0, $plan_ids, true ) ) {
			$errors['rate_plan_ids'] = __( 'Set prices on a rate, not on the whole room type.', 'radius-hotel-booking' );
		}

		$price = $input['price'] ?? null;
		if ( 'set_price' === $action ) {
			if ( null === $price || '' === $price || ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 || (float) $price > 999999999 ) {
				$errors['price'] = __( 'Enter a price of 0 or more.', 'radius-hotel-booking' );
			} else {
				$price = Money::round( (float) $price );
			}
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$change = array(
			'set_price'   => array( 'price_override' => $price ),
			'clear_price' => array( 'price_override' => null ),
			'open'        => array( 'is_closed' => false ),
			'close'       => array( 'is_closed' => true ),
		)[ $action ];

		$dates = array();
		for ( $day = Dates::local( $from ); $day->format( 'Y-m-d' ) <= $to; $day = Dates::add_days( $day, 1 ) ) {
			if ( in_array( (int) $day->format( 'w' ), $weekdays, true ) ) {
				$dates[] = $day->format( 'Y-m-d' );
			}
		}
		$changes = array();
		foreach ( $plan_ids as $plan_id ) {
			foreach ( $dates as $date ) {
				$changes[ $plan_id . '|' . $date ] = $change;
			}
		}
		$counts = array(
			'dates'   => count( $dates ),
			'cells'   => count( $changes ),
			'changes' => 0,
		);

		if ( ! $apply ) {
			$counts['changes'] = $changes ? count( $this->diff( $room_type_id, $changes ) ) : 0;
			return $counts;
		}
		if ( ! $changes ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'weekdays' => __( 'No date in the range falls on the chosen days.', 'radius-hotel-booking' ) ) );
		}

		$result = $this->commit(
			$room_type_id,
			$changes,
			function ( array $diff ) use ( $room_type_id, $type, $plan_names, $plan_ids, $action, $price, $from, $to, $weekdays, $counts ) {
				if ( ! $diff ) {
					return;
				}
				$rates = implode( ', ', array_map( static fn( $id ) => 0 === $id ? __( 'whole room type', 'radius-hotel-booking' ) : $plan_names[ $id ], $plan_ids ) );
				$range = sprintf( '%s – %s', Dates::format( Dates::local( $from ) ), Dates::format( Dates::local( $to ) ) );
				$n = count( $diff );
				switch ( $action ) {
					case 'set_price':
						/* translators: 1: room type, 2: rate plans, 3: date range, 4: price, 5: calendar cells changed. */
						$description = sprintf( _n( 'Set the price of %1$s (%2$s) to %4$s for %3$s: %5$d change', 'Set the price of %1$s (%2$s) to %4$s for %3$s: %5$d changes', $n, 'radius-hotel-booking' ), $type->name, $rates, $range, Money::format( (float) $price ), $n );
						break;
					case 'clear_price':
						/* translators: 1: room type, 2: rate plans, 3: date range, 4: calendar cells changed. */
						$description = sprintf( _n( 'Went back to the usual price of %1$s (%2$s) for %3$s: %4$d change', 'Went back to the usual price of %1$s (%2$s) for %3$s: %4$d changes', $n, 'radius-hotel-booking' ), $type->name, $rates, $range, $n );
						break;
					case 'open':
						/* translators: 1: room type, 2: rate plans, 3: date range, 4: calendar cells changed. */
						$description = sprintf( _n( 'Opened %1$s (%2$s) for %3$s: %4$d change', 'Opened %1$s (%2$s) for %3$s: %4$d changes', $n, 'radius-hotel-booking' ), $type->name, $rates, $range, $n );
						break;
					default:
						/* translators: 1: room type, 2: rate plans, 3: date range, 4: calendar cells changed. */
						$description = sprintf( _n( 'Closed %1$s (%2$s) for %3$s: %4$d change', 'Closed %1$s (%2$s) for %3$s: %4$d changes', $n, 'radius-hotel-booking' ), $type->name, $rates, $range, $n );
				}
				rtbp_activity(
					'availability.bulk',
					array(
						'type'  => 'room_type',
						'id'    => $room_type_id,
						'label' => (string) $type->name,
					),
					array(
						'after'       => array(
							'action'        => $action,
							'price'         => $price,
							'from'          => $from,
							'to'            => $to,
							'weekdays'      => $weekdays,
							'rate_plan_ids' => $plan_ids,
							'dates'         => $counts['dates'],
							'cells'         => $counts['cells'],
							'changed'       => $n,
						),
						'description' => $description,
					)
				);
			}
		);

		RateCalendar::flush();
		return array_merge( $counts, array( 'changes' => $result['changed'] ) );
	}

	/**
	 * Write changes under the room type's row lock, in one transaction.
	 *
	 * @param int      $room_type_id Room type id.
	 * @param array    $changes      `"<plan>|<date>" => { price_override?, is_closed? }`.
	 * @param callable $log          Receives the diff (inside the transaction) to log it.
	 * @return array `{ changed: int }`.
	 */
	private function commit( int $room_type_id, array $changes, callable $log ): array {
		return Transaction::run(
			function () use ( $room_type_id, $changes, $log ) {
				$this->types->lock( $room_type_id );

				$diff = $this->diff( $room_type_id, $changes );
				foreach ( $diff as $row ) {
					if ( null === $row['after']['price_override'] && ! $row['after']['is_closed'] ) {
						$this->calendar->delete( $room_type_id, $row['plan_id'], $row['date'] );
					} else {
						$this->calendar->upsert( $room_type_id, $row['plan_id'], $row['date'], $row['after']['price_override'], $row['after']['is_closed'] );
					}
				}
				$log( $diff );

				$changed = count( $diff );
				Transaction::afterCommit(
					static function () use ( $room_type_id, $changed ) {
						/**
						 * Calendar cells of a room type changed.
						 *
						 * @param int $room_type_id Room type id.
						 * @param int $changed      Cells changed.
						 */
						do_action( 'rtbp_rate_calendar_saved', $room_type_id, $changed );
					}
				);

				return array( 'changed' => $changed );
			}
		);
	}

	/**
	 * The cells that a set of changes would really change.
	 *
	 * @param int   $room_type_id Room type id.
	 * @param array $changes      `"<plan>|<date>" => { price_override?, is_closed? }`.
	 * @return array[] `{ plan_id, date, before, after, price_changed, closed_changed }`.
	 */
	private function diff( int $room_type_id, array $changes ): array {
		$dates   = array_map( static fn( $key ) => substr( $key, strpos( $key, '|' ) + 1 ), array_keys( $changes ) );
		$current = array();
		foreach ( $this->calendar->between( array( $room_type_id ), min( $dates ), max( $dates ) ) as $row ) {
			$current[ $row['rate_plan_id'] . '|' . $row['date'] ] = $row;
		}

		$diff = array();
		foreach ( $changes as $key => $change ) {
			list( $plan_id, $date ) = explode( '|', $key );
			$before = $current[ $key ] ?? array(
				'price_override' => null,
				'is_closed'      => false,
			);
			$after  = array(
				'price_override' => array_key_exists( 'price_override', $change ) ? $change['price_override'] : $before['price_override'],
				'is_closed'      => array_key_exists( 'is_closed', $change ) ? $change['is_closed'] : $before['is_closed'],
			);

			$price_changed  = ! self::samePrice( $before['price_override'], $after['price_override'] );
			$closed_changed = $before['is_closed'] !== $after['is_closed'];
			if ( $price_changed || $closed_changed ) {
				$diff[] = array(
					'plan_id'        => (int) $plan_id,
					'date'           => $date,
					'before'         => $before,
					'after'          => $after,
					'price_changed'  => $price_changed,
					'closed_changed' => $closed_changed,
				);
			}
		}
		return $diff;
	}

	/**
	 * Plans this room type has a rate for (on or off: an override can be
	 * prepared before the rate is switched on), plus 0 = the room type.
	 *
	 * @param int    $room_type_id Room type id.
	 * @param string $type_name    Room type name.
	 * @return array<int, string> Plan id => name.
	 */
	private function planNames( int $room_type_id, string $type_name ): array {
		$plan_names = array( 0 => $type_name );
		foreach ( $this->availability->catalogue( $room_type_id, true ) as $row ) {
			if ( null !== $row['rate_id'] && null !== $row['plan_name'] ) {
				$plan_names[ (int) $row['rate_plan_id'] ] = (string) $row['plan_name'];
			}
		}
		return $plan_names;
	}

	/**
	 * One cell of the grid.
	 *
	 * @param int    $type_id  Room type id.
	 * @param int    $plan_id  Rate plan id.
	 * @param array  $rate     Rate (with plan fields).
	 * @param string $date     Local date.
	 * @param string $today    Today.
	 * @param array  $sellable Sellable rooms.
	 * @param array  $busy     Busy intervals per room.
	 * @param int    $buffer   Buffer minutes.
	 * @param array  $steps    Pricing steps.
	 * @return array `{ date, price, override, closed, free }`.
	 */
	private function cell( int $type_id, int $plan_id, array $rate, string $date, string $today, array $sellable, array $busy, int $buffer, array $steps ): array {
		$closure = RateCalendar::closure( $type_id, $plan_id, array( $date ) );
		$cell    = array(
			'date'     => $date,
			'price'    => null,
			'override' => RateCalendar::override( $type_id, $plan_id, $date ),
			// Closed for this rate on its own (8.3); the whole type is in `days`.
			'closed'   => null !== $closure && 'rate_closed' === $closure['code'],
			'free'     => null,
		);
		try {
			$window = StayWindow::for( $rate, $date, 1 );
		} catch ( DomainException $e ) {
			return $cell; // A window that does not exist that day (a clock change).
		}
		$cell['price'] = $this->prices->price( (object) $rate, $window, $type_id, $plan_id, $today, $steps )['total'];

		$start = $window->startGmt()->getTimestamp();
		$end   = $window->endGmt()->getTimestamp();
		$free  = 0;
		foreach ( $sellable as $room ) {
			if ( ! Overlap::conflict( $busy[ (int) $room['id'] ] ?? array(), $start, $end, $buffer ) ) {
				++$free;
			}
		}
		$cell['free'] = $free;
		return $cell;
	}

	/**
	 * Log one kind of change for one rate (or the room type) over its dates.
	 *
	 * @param string $action     `availability.override|close|open`.
	 * @param int    $plan_id    Rate plan id; 0 = the room type.
	 * @param array  $entries    Date => [ before, after ] (override) or null.
	 * @param int    $type_id    Room type id.
	 * @param string $type_name  Room type name.
	 * @param string $plan_name  Rate plan name.
	 * @return void
	 */
	private function logChange( string $action, int $plan_id, array $entries, int $type_id, string $type_name, string $plan_name ): void {
		ksort( $entries );
		$dates   = array_keys( $entries );
		$subject = 0 === $plan_id ? $type_name : sprintf( '%s · %s', $type_name, $plan_name );
		$range   = count( $dates ) > 1
			/* translators: 1: first date, 2: last date, 3: number of dates. */
			? sprintf( _n( '%1$s to %2$s (%3$d date)', '%1$s to %2$s (%3$d dates)', count( $dates ), 'radius-hotel-booking' ), Dates::format( Dates::local( $dates[0] ) ), Dates::format( Dates::local( end( $dates ) ) ), count( $dates ) )
			: Dates::format( Dates::local( $dates[0] ) );

		switch ( $action ) {
			case 'availability.close':
				/* translators: 1: room type (and rate plan), 2: dates. */
				$description = sprintf( __( 'Closed %1$s on %2$s', 'radius-hotel-booking' ), $subject, $range );
				$context     = array( 'after' => array( 'closed' => $dates ) );
				break;
			case 'availability.open':
				/* translators: 1: room type (and rate plan), 2: dates. */
				$description = sprintf( __( 'Opened %1$s on %2$s', 'radius-hotel-booking' ), $subject, $range );
				$context     = array( 'after' => array( 'opened' => $dates ) );
				break;
			default:
				/* translators: 1: room type and rate plan, 2: dates. */
				$description = sprintf( __( 'Changed the price of %1$s on %2$s', 'radius-hotel-booking' ), $subject, $range );
				$context     = array(
					'before' => array_map( static fn( $pair ) => $pair[0], $entries ),
					'after'  => array_map( static fn( $pair ) => $pair[1], $entries ),
				);
		}

		rtbp_activity(
			$action,
			array(
				'type'  => 'room_type',
				'id'    => $type_id,
				'label' => $type_name,
			),
			array_merge(
				$context,
				array(
					'rate_plan_id' => $plan_id,
					'rate_plan'    => $plan_name,
					'description'  => $description,
				)
			)
		);
	}

	/**
	 * Two override prices are the same (both none, or equal once rounded).
	 *
	 * @param float|null $a Price.
	 * @param float|null $b Price.
	 * @return bool
	 */
	private static function samePrice( ?float $a, ?float $b ): bool {
		if ( null === $a || null === $b ) {
			return $a === $b;
		}
		return Money::equals( $a, $b );
	}
}
