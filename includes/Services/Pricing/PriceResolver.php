<?php
/**
 * The price of a stay, step by step.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Pricing
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Pricing;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\StayWindow;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Prices a stay in the one documented order (booking-engine §6, feature
 * 7.16). Each unit (the local date its night or day starts) runs through
 * the steps by position:
 *
 * | pos | step            | where                                            |
 * |-----|-----------------|--------------------------------------------------|
 * | 10  | base            | the room type rate's price                        |
 * | 20  | sale            | the sale price, when set and lower                |
 * | 30  | date_override   | `rtbp_price_date_override` (the rate calendar, M08) |
 * | 40  | seasonal        | Pro, through `rtbp_price_steps`                   |
 * | 50  | occupancy       | Pro, through `rtbp_price_steps`                   |
 * | 60  | booking_window  | Pro, through `rtbp_price_steps`                   |
 * | 90  | floor_round     | max(0, x), rounded to the currency                |
 *
 * A step is `[ 'position' => int, 'apply' => callable( float $before, array
 * $context ): ?array ]`. `apply` returns null when it does not apply, or
 * `[ 'after' => float, 'label' => string, 'rule_id' => ?int ]`. Base comes
 * first and floor_round last whatever the filter does. Every applied step
 * is recorded as `{ step, rule_id, label, before, after }` — what the
 * PriceBreakdown shows and what a booking line freezes.
 *
 * The same pipeline runs for the desk and the web: no admin exemption.
 */
class PriceResolver {

	/**
	 * Rates.
	 *
	 * @var RoomTypeRateRepository
	 */
	private RoomTypeRateRepository $rates;

	/**
	 * Rate plans.
	 *
	 * @var RatePlanRepository
	 */
	private RatePlanRepository $plans;

	/**
	 * Room types.
	 *
	 * @var RoomTypeRepository
	 */
	private RoomTypeRepository $types;

	/**
	 * Constructor.
	 *
	 * @param RoomTypeRateRepository|null $rates Rates.
	 * @param RatePlanRepository|null     $plans Rate plans.
	 * @param RoomTypeRepository|null     $types Room types.
	 */
	public function __construct( ?RoomTypeRateRepository $rates = null, ?RatePlanRepository $plans = null, ?RoomTypeRepository $types = null ) {
		$this->rates = $rates ?? new RoomTypeRateRepository();
		$this->plans = $plans ?? new RatePlanRepository();
		$this->types = $types ?? new RoomTypeRepository();
	}

	/**
	 * Quote a stay.
	 *
	 * @param int         $room_type_id Room type id.
	 * @param int         $rate_plan_id Rate plan id.
	 * @param string      $arrival      Local start date `Y-m-d`.
	 * @param int         $units        Days or nights (1 unless the plan is multi-unit).
	 * @param string|null $checkin      Check-in `HH:MM` for flexible plans.
	 * @param string|null $booked_at    Local date the booking is made (default today).
	 * @return array{ window: array, units: int, unit_prices: float[], total: float, steps: array[] }
	 * @throws DomainException When the room type does not sell the plan, or the stay is invalid.
	 */
	public function quote( int $room_type_id, int $rate_plan_id, string $arrival, int $units = 1, ?string $checkin = null, ?string $booked_at = null ): array {
		$type = $this->types->find( $room_type_id );
		$plan = $this->plans->find( $rate_plan_id );
		if ( ! $type instanceof RoomType ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'room_type_id' => __( 'Choose a room type.', 'radius-hotel-booking' ) ) );
		}
		if ( ! $plan instanceof RatePlan ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'rate_plan_id' => __( 'Choose a rate plan.', 'radius-hotel-booking' ) ) );
		}

		$rate = $this->rates->pair( $room_type_id, $rate_plan_id );
		// A hidden room type, a rate switched off or an inactive plan is not sold.
		if ( ! $rate || ! (int) $rate->enabled || ! $plan->is_active || ! $type->is_active ) {
			$message = sprintf(
				/* translators: 1: room type name, 2: rate plan name. */
				__( '%1$s does not sell %2$s.', 'radius-hotel-booking' ),
				$type->name,
				$plan->name
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'rate_not_sold', $message, 409, array( 'rate_plan_id' => $message ) );
		}
		if ( $plan->multi_unit ) {
			$this->assertUnits( $units, (int) $rate->min_units, null === $rate->max_units ? null : (int) $rate->max_units );
		}

		$window    = StayWindow::for( $plan, $arrival, $units, $checkin );
		$booked_at = null !== $booked_at && Dates::is_date( $booked_at ) ? $booked_at : Dates::today();

		return $this->price( $rate, $window, $room_type_id, $rate_plan_id, $booked_at );
	}

	/**
	 * Price a derived window with a rate row already loaded — the part of
	 * `quote()` the availability search (M08) runs for every sellable rate
	 * without querying again. The caller has checked that the rate is sold
	 * and the units are within its bounds.
	 *
	 * @param object     $rate         Rate row (`price`, `sale_price`, …).
	 * @param StayWindow $window       The stay.
	 * @param int        $room_type_id Room type id.
	 * @param int        $rate_plan_id Rate plan id.
	 * @param string     $booked_at    Local date the booking is made.
	 * @param array|null $steps        `steps()`, when the caller prices many windows.
	 * @return array{ window: array, units: int, unit_prices: float[], total: float, steps: array[] }
	 */
	public function price( object $rate, StayWindow $window, int $room_type_id, int $rate_plan_id, string $booked_at, ?array $steps = null ): array {
		$steps   = $steps ?? $this->steps();
		$units   = $window->units();
		$arrival = $window->start()->format( 'Y-m-d' );

		$unit_prices = array();
		$all_steps   = array();
		foreach ( $window->unitDates() as $index => $date ) {
			$context = array(
				'room_type_id' => $room_type_id,
				'rate_plan_id' => $rate_plan_id,
				'date'         => $date,
				'unit'         => $index,
				'units'        => $units,
				'arrival'      => $arrival,
				'booked_at'    => $booked_at,
				'rate'         => $rate,
				'window'       => $window,
			);

			$amount   = 0.0;
			$recorded = array();
			foreach ( $steps as $name => $step ) {
				$result = call_user_func( $step['apply'], $amount, $context );
				// A non-number, NAN or INF is ignored (it would round to a free stay).
				if ( ! is_array( $result ) || ! isset( $result['after'] ) || ! is_numeric( $result['after'] ) || ! is_finite( (float) $result['after'] ) ) {
					continue;
				}
				$after = (float) $result['after'];
				// Compare raw amounts: Money::equals() rounds both, so it would always match here.
				if ( 'floor_round' === $name && abs( $after - $amount ) < 0.000001 ) {
					$amount = $after;
					continue; // Nothing to floor or round: not worth a line.
				}
				$recorded[] = array(
					'step'    => (string) $name,
					'rule_id' => isset( $result['rule_id'] ) ? (int) $result['rule_id'] : null,
					'label'   => (string) ( $result['label'] ?? $name ),
					'before'  => 'base' === $name ? null : $amount,
					'after'   => $after,
				);
				$amount     = $after;
			}

			$unit_prices[] = $amount;
			$all_steps[]   = array(
				'date'  => $date,
				'steps' => $recorded,
			);
		}

		return array(
			'window'      => $window->toArray(),
			'units'       => $units,
			'unit_prices' => $unit_prices,
			'total'       => Money::sum( $unit_prices ),
			'steps'       => $all_steps,
		);
	}

	/**
	 * The steps in order: the core ones, the filter's additions between base
	 * and floor_round.
	 *
	 * @return array<string, array{position: int, apply: callable}>
	 */
	public function steps(): array {
		$core = array(
			'base'          => array(
				'position' => 10,
				'apply'    => static fn( float $before, array $context ) => array(
					'after' => (float) $context['rate']->price,
					'label' => __( 'Base price', 'radius-hotel-booking' ),
				),
			),
			'sale'          => array(
				'position' => 20,
				'apply'    => static function ( float $before, array $context ) {
					$sale = $context['rate']->sale_price;
					// NULL = no sale; 0 is a real (free) price when set (booking-engine §10 #25).
					if ( null === $sale || (float) $sale >= $before ) {
						return null;
					}
					return array(
						'after' => (float) $sale,
						'label' => __( 'Sale price', 'radius-hotel-booking' ),
					);
				},
			),
			'date_override' => array(
				'position' => 30,
				'apply'    => static function ( float $before, array $context ) {
					/**
					 * A price for this room type, rate plan and local date that
					 * replaces base and sale (the rate calendar, M08). Null = none.
					 *
					 * @param float|null $price        Override (null).
					 * @param int        $room_type_id Room type id.
					 * @param int        $rate_plan_id Rate plan id.
					 * @param string     $date         Local date `Y-m-d`.
					 */
					$price = apply_filters( 'rtbp_price_date_override', null, $context['room_type_id'], $context['rate_plan_id'], $context['date'] );
					if ( null === $price || ! is_numeric( $price ) || ! is_finite( (float) $price ) ) {
						return null;
					}
					return array(
						'after' => (float) $price,
						'label' => __( 'Price for this date', 'radius-hotel-booking' ),
					);
				},
			),
		);
		$floor = array(
			'position' => 90,
			'apply'    => static fn( float $before ) => array(
				'after' => Money::round( max( 0.0, $before ) ),
				'label' => __( 'Rounded', 'radius-hotel-booking' ),
			),
		);

		/**
		 * Filters the pricing steps. Add a step as `name => [ 'position' =>
		 * int (between 10 and 90), 'apply' => callable( float $before, array
		 * $context ) ]`; `apply` returns null (does not apply) or `[ 'after'
		 * => float, 'label' => string, 'rule_id' => ?int ]`. The context holds
		 * `room_type_id`, `rate_plan_id`, `date` (the unit's local date),
		 * `unit`, `units`, `arrival`, `booked_at`, `rate` (the rate row) and
		 * `window` (StayWindow). Pro adds `seasonal` (40), `occupancy` (50)
		 * and `booking_window` (60).
		 *
		 * @param array $steps Name => step.
		 */
		$filtered = (array) apply_filters( 'rtbp_price_steps', $core );

		$steps = array( 'base' => $core['base'] );
		$extra = array();
		foreach ( $filtered as $name => $step ) {
			if ( in_array( $name, array( 'base', 'floor_round' ), true ) || ! is_array( $step ) || ! isset( $step['apply'] ) || ! is_callable( $step['apply'] ) ) {
				continue;
			}
			$position = (int) ( $step['position'] ?? 50 );
			$extra[]  = array( max( 11, min( 89, $position ) ), count( $extra ), (string) $name, $step );
		}
		usort( $extra, static fn( $a, $b ) => array( $a[0], $a[1] ) <=> array( $b[0], $b[1] ) );
		foreach ( $extra as $item ) {
			$steps[ $item[2] ] = $item[3];
		}
		$steps['floor_round'] = $floor;
		return $steps;
	}

	/**
	 * Check the units against the rate's minimum and maximum (7.8).
	 *
	 * @param int      $units Units asked.
	 * @param int      $min   Minimum.
	 * @param int|null $max   Maximum (null = none).
	 * @return void
	 * @throws DomainException 422 when outside.
	 */
	private function assertUnits( int $units, int $min, ?int $max ): void {
		if ( $units < max( 1, $min ) ) {
			/* translators: %d: minimum number of nights or days. */
			$message = sprintf( _n( 'This rate needs at least %d night or day.', 'This rate needs at least %d nights or days.', $min, 'radius-hotel-booking' ), $min );
		} elseif ( null !== $max && $units > $max ) {
			/* translators: %d: maximum number of nights or days. */
			$message = sprintf( _n( 'This rate allows at most %d night or day.', 'This rate allows at most %d nights or days.', $max, 'radius-hotel-booking' ), $max );
		}
		if ( isset( $message ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'units' => $message ) );
		}
	}
}
