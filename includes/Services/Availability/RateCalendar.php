<?php
/**
 * Per-date overrides and closures, read side (features 8.2–8.4).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use RadiusTheme\RadiusHotelBooking\Repositories\RateCalendarRepository;

defined( 'ABSPATH' ) || exit;

/**
 * A request-wide cache of `rate_calendar`. The availability search loads the
 * dates it covers in one query (`prime()`); the price resolver's date
 * override (`rtbp_price_date_override`) and the closure checks then read the
 * cache. A lookup outside what was primed loads that room type and date on
 * its own (a single quote outside a search).
 *
 * Calendar writes (T4a) call `flush()`.
 */
final class RateCalendar {

	/**
	 * Cells: type => plan (0 = whole type) => date => `{ price_override, is_closed }`.
	 *
	 * @var array<int, array<int, array<string, array>>>
	 */
	private static array $cells = array();

	/**
	 * Loaded ranges per room type: list of `[ from, to ]`.
	 *
	 * @var array<int, array<int, string[]>>
	 */
	private static array $loaded = array();

	/**
	 * Register the date-override filter.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'rtbp_price_date_override', array( self::class, 'filter_override' ), 10, 4 );
	}

	/**
	 * `rtbp_price_date_override`: the calendar's price, unless an earlier
	 * callback already answered.
	 *
	 * @param float|null $price        Earlier answer.
	 * @param int        $room_type_id Room type id.
	 * @param int        $rate_plan_id Rate plan id.
	 * @param string     $date         Local date.
	 * @return float|null
	 */
	public static function filter_override( $price, $room_type_id, $rate_plan_id, $date ) {
		if ( null !== $price ) {
			return $price;
		}
		return self::override( (int) $room_type_id, (int) $rate_plan_id, (string) $date );
	}

	/**
	 * Load the rows of these room types between two local dates (one query).
	 *
	 * @param int[]                       $room_type_ids Room type ids.
	 * @param string                      $from          First local date.
	 * @param string                      $to            Last local date (inclusive).
	 * @param RateCalendarRepository|null $repository    Repository.
	 * @return void
	 */
	public static function prime( array $room_type_ids, string $from, string $to, ?RateCalendarRepository $repository = null ): void {
		$room_type_ids = array_values( array_unique( array_filter( array_map( 'intval', $room_type_ids ) ) ) );
		if ( ! $room_type_ids || $from > $to ) {
			return;
		}
		$rows = ( $repository ?? new RateCalendarRepository() )->between( $room_type_ids, $from, $to );
		foreach ( $room_type_ids as $type ) {
			// Drop what was cached for this range, so a re-prime sees deletions.
			foreach ( self::$cells[ $type ] ?? array() as $plan => $dates ) {
				foreach ( array_keys( $dates ) as $date ) {
					if ( $date >= $from && $date <= $to ) {
						unset( self::$cells[ $type ][ $plan ][ $date ] );
					}
				}
			}
			self::$loaded[ $type ][] = array( $from, $to );
		}
		foreach ( $rows as $row ) {
			self::$cells[ $row['room_type_id'] ][ $row['rate_plan_id'] ][ $row['date'] ] = array(
				'price_override' => $row['price_override'],
				'is_closed'      => $row['is_closed'],
			);
		}
	}

	/**
	 * The override price of a rate on a date, or null.
	 *
	 * @param int    $room_type_id Room type id.
	 * @param int    $rate_plan_id Rate plan id.
	 * @param string $date         Local date.
	 * @return float|null
	 */
	public static function override( int $room_type_id, int $rate_plan_id, string $date ): ?float {
		self::ensure( $room_type_id, $date );
		$cell = self::$cells[ $room_type_id ][ $rate_plan_id ][ $date ] ?? null;
		return $cell ? $cell['price_override'] : null;
	}

	/**
	 * The first closure among some dates: the whole room type first (8.4),
	 * then the rate (8.3).
	 *
	 * @param int      $room_type_id Room type id.
	 * @param int      $rate_plan_id Rate plan id.
	 * @param string[] $dates        Local dates (a stay's unit dates).
	 * @return array|null `{ code: room_type_closed|rate_closed, date }`, or null when open.
	 */
	public static function closure( int $room_type_id, int $rate_plan_id, array $dates ): ?array {
		foreach ( $dates as $date ) {
			self::ensure( $room_type_id, $date );
		}
		foreach ( array(
			0 => 'room_type_closed',
			$rate_plan_id => 'rate_closed',
		) as $plan => $code ) {
			foreach ( $dates as $date ) {
				if ( ! empty( self::$cells[ $room_type_id ][ $plan ][ $date ]['is_closed'] ) ) {
					return array(
						'code' => $code,
						'date' => $date,
					);
				}
			}
		}
		return null;
	}

	/**
	 * Forget everything (after a calendar write, in checks).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cells  = array();
		self::$loaded = array();
	}

	/**
	 * Load one date of a room type when no primed range covers it.
	 *
	 * @param int    $room_type_id Room type id.
	 * @param string $date         Local date.
	 * @return void
	 */
	private static function ensure( int $room_type_id, string $date ): void {
		foreach ( self::$loaded[ $room_type_id ] ?? array() as $range ) {
			if ( $date >= $range[0] && $date <= $range[1] ) {
				return;
			}
		}
		self::prime( array( $room_type_id ), $date, $date );
	}
}
