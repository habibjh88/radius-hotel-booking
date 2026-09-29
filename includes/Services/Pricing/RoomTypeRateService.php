<?php
/**
 * The Price & Rates grid of a room type.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Pricing
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Pricing;

use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Support\Db;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Which rate plans a room type sells, and at what price (features 7.6–7.9):
 * one row per rate plan with on/off, price, sale price, min/max units and
 * the display order. The whole grid is read and saved in one call.
 */
class RoomTypeRateService {

	/**
	 * The largest price accepted.
	 */
	const MAX_PRICE = 99999999;

	/**
	 * The most units (days or nights) a rate may require or allow.
	 */
	const MAX_UNITS = 365;

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
	 * The grid: every rate plan of the library (inactive ones too), with this
	 * room type's rate for it — set rows first in their order, then the plans
	 * it does not sell yet in library order.
	 *
	 * @param int $room_type_id Room type id.
	 * @return array[]
	 */
	public function grid( int $room_type_id ): array {
		$this->roomType( $room_type_id );
		$rows = $this->rates->forRoomType( $room_type_id );

		$grid = array();
		foreach ( $this->plans->ordered() as $plan ) {
			$row    = $rows[ (int) $plan->id ] ?? null;
			$grid[] = array(
				'rate_plan'  => array(
					'id'               => (int) $plan->id,
					'name'             => (string) $plan->name,
					'type'             => (string) $plan->type,
					'start_time'       => $plan->start_time,
					'end_time'         => $plan->end_time,
					'duration_minutes' => (int) $plan->duration_minutes,
					'checkin_from'     => $plan->checkin_from,
					'checkin_until'    => $plan->checkin_until,
					'multi_unit'       => (bool) $plan->multi_unit,
					'is_active'        => (bool) $plan->is_active,
				),
				'enabled'    => $row ? (bool) $row->enabled : false,
				'price'      => $row ? (float) $row->price : null,
				'sale_price' => $row && null !== $row->sale_price ? (float) $row->sale_price : null,
				'min_units'  => $row ? (int) $row->min_units : 1,
				'max_units'  => $row && null !== $row->max_units ? (int) $row->max_units : null,
				'sort_order' => $row ? (int) $row->sort_order : null,
				'_order'     => array( $row ? 0 : 1, $row ? (int) $row->sort_order : (int) $plan->sort_order, (int) $plan->id ),
			);
		}

		usort( $grid, static fn( $a, $b ) => $a['_order'] <=> $b['_order'] );
		return array_map(
			static function ( $item ) {
				unset( $item['_order'] );
				return $item;
			},
			$grid
		);
	}

	/**
	 * Save the grid. `$rows` is in display order (its order becomes
	 * `sort_order`); a row is `{ rate_plan_id, enabled, price, sale_price,
	 * min_units, max_units }`. Everything is validated first (errors keyed
	 * `rates.<index>.<field>`), then written under the room type's lock.
	 *
	 * @param int   $room_type_id Room type id.
	 * @param array $rows         Rows.
	 * @return array[] The saved grid.
	 * @throws DomainException With field errors.
	 */
	public function save( int $room_type_id, array $rows ): array {
		$type  = $this->roomType( $room_type_id );
		$plans = array();
		foreach ( $this->plans->ordered() as $plan ) {
			$plans[ (int) $plan->id ] = $plan;
		}

		list( $clean, $errors ) = $this->validate( array_values( $rows ), $plans );
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors, __( 'Please correct the highlighted prices.', 'radius-hotel-booking' ) );
		}

		list( $changes, $old_order, $new_order ) = Transaction::run(
			function () use ( $room_type_id, $clean ) {
				// Serialises saves of one grid, and keeps the room type from being deleted meanwhile.
				if ( ! $this->types->lock( $room_type_id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This room type does not exist.', 'radius-hotel-booking' ) );
				}
				// Lock every plan being switched on (ascending ids: one lock order),
				// so a plan cannot be deleted while a room type starts selling it
				// (RatePlanService::delete() locks the plan row too).
				$on = array_keys( array_filter( $clean, static fn( $values ) => (bool) $values['enabled'] ) );
				sort( $on );
				foreach ( $on as $plan_id ) {
					if ( ! $this->plans->lock( (int) $plan_id ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict( 'rate_plan_gone', __( 'A rate plan was just deleted. Reload the page and try again.', 'radius-hotel-booking' ) );
					}
				}

				$existing = $this->rates->forRoomType( $room_type_id );
				$changes  = array();
				foreach ( $clean as $plan_id => $after ) {
					$row    = $existing[ $plan_id ] ?? null;
					$before = $row ? self::values( $row ) : null;
					if ( $before === $after || ( ! $row && self::untouched( $after ) ) ) {
						continue; // Unchanged, or a plan never priced and still off: no row.
					}
					if ( $row ) {
						$saved = Db::quietly( fn() => $this->rates->update( (int) $row->id, $after ) );
					} else {
						$model = Db::quietly(
							fn() => $this->rates->create(
								$after + array(
									'room_type_id' => $room_type_id,
									'rate_plan_id' => $plan_id,
								)
							)
						);
						$saved = $model && $model->id;
					}
					if ( ! $saved ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw new DomainException( 'rates_not_saved', __( 'The prices could not be saved. Nothing was changed.', 'radius-hotel-booking' ), 500 );
					}
					$changes[ $plan_id ] = array( $before, $after );
				}

				// The display order of the stored rows, before and after.
				$old = array();
				foreach ( $existing as $plan_id => $row ) {
					$old[ $plan_id ] = (int) $row->sort_order;
				}
				$new = array();
				foreach ( $clean as $plan_id => $after ) {
					if ( isset( $existing[ $plan_id ] ) || isset( $changes[ $plan_id ] ) ) {
						$new[ $plan_id ] = (int) $after['sort_order'];
					}
				}
				asort( $old );
				asort( $new );
				return array( $changes, array_keys( $old ), array_keys( $new ) );
			}
		);

		$this->log( $type, $plans, $changes, $old_order, $new_order );

		/**
		 * Fires after a room type's rates were saved.
		 *
		 * @param int   $room_type_id Room type id.
		 * @param array $changes      Plan id => [ before (null = new row), after ].
		 */
		do_action( 'rtbp_room_type_rates_saved', $room_type_id, $changes );

		return $this->grid( $room_type_id );
	}

	/**
	 * Validate the rows.
	 *
	 * @param array      $rows  Rows, in display order.
	 * @param RatePlan[] $plans Live plans by id.
	 * @return array{0: array<int, array>, 1: array<string, string>} Clean values by plan id, and errors.
	 */
	private function validate( array $rows, array $plans ): array {
		$clean  = array();
		$errors = array();

		foreach ( $rows as $index => $row ) {
			$row     = (array) $row;
			$key     = 'rates.' . $index . '.';
			$plan_id = absint( $row['rate_plan_id'] ?? 0 );
			if ( ! isset( $plans[ $plan_id ] ) ) {
				$errors[ $key . 'rate_plan_id' ] = __( 'This rate plan does not exist any more. Reload the page.', 'radius-hotel-booking' );
				continue;
			}
			if ( isset( $clean[ $plan_id ] ) ) {
				$errors[ $key . 'rate_plan_id' ] = __( 'This rate plan is listed twice.', 'radius-hotel-booking' );
				continue;
			}

			$enabled = rest_sanitize_boolean( $row['enabled'] ?? false );
			$price   = self::amount( $row['price'] ?? null );
			$sale    = self::amount( $row['sale_price'] ?? null );

			if ( false === $price ) {
				$errors[ $key . 'price' ] = __( 'Enter a price of 0 or more.', 'radius-hotel-booking' );
			} elseif ( null === $price && $enabled ) {
				$errors[ $key . 'price' ] = __( 'Enter the price to sell this rate plan.', 'radius-hotel-booking' );
			}
			if ( false === $sale ) {
				$errors[ $key . 'sale_price' ] = __( 'Enter a sale price of 0 or more, or leave it empty.', 'radius-hotel-booking' );
			} elseif ( null !== $sale && is_float( $price ) && $sale >= $price ) {
				$errors[ $key . 'sale_price' ] = __( 'The sale price must be lower than the price.', 'radius-hotel-booking' );
			}

			$multi = (bool) $plans[ $plan_id ]->multi_unit;
			$min   = 1;
			$max   = 1;
			if ( $multi ) {
				$min = filter_var( $row['min_units'] ?? 1, FILTER_VALIDATE_INT );
				$max = null === ( $row['max_units'] ?? null ) || '' === $row['max_units'] ? null : filter_var( $row['max_units'], FILTER_VALIDATE_INT );
				if ( false === $min || $min < 1 || $min > self::MAX_UNITS ) {
					/* translators: %d: the largest number of units. */
					$errors[ $key . 'min_units' ] = sprintf( __( 'Between 1 and %d.', 'radius-hotel-booking' ), self::MAX_UNITS );
				}
				if ( false === $max || ( null !== $max && ( $max > self::MAX_UNITS || ( is_int( $min ) && $max < $min ) ) ) ) {
					$errors[ $key . 'max_units' ] = __( 'The maximum must be at least the minimum, or empty for no maximum.', 'radius-hotel-booking' );
				}
			}

			$clean[ $plan_id ] = array(
				'price'      => is_float( $price ) ? $price : 0.0,
				'sale_price' => is_float( $sale ) ? $sale : null,
				'min_units'  => is_int( $min ) ? $min : 1,
				'max_units'  => is_int( $max ) ? $max : null,
				'enabled'    => $enabled ? 1 : 0,
				'sort_order' => (int) $index,
			);
		}

		return array( $clean, $errors );
	}

	/**
	 * A money amount: null when empty, false when invalid, else rounded to
	 * the currency's decimals (whole francs for XOF).
	 *
	 * @param mixed $value Input.
	 * @return float|null|false
	 */
	private static function amount( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$number = filter_var( $value, FILTER_VALIDATE_FLOAT );
		if ( false === $number || $number < 0 || $number > self::MAX_PRICE ) {
			return false;
		}
		return (float) Money::round( $number );
	}

	/**
	 * Whether clean values describe a rate nobody set: off, no price, no sale,
	 * default units. Such a plan stays without a row (the grid shows it empty).
	 *
	 * @param array $values Clean values.
	 * @return bool
	 */
	private static function untouched( array $values ): bool {
		// Single-unit plans are always saved as 1–1, so max 1 is a default too.
		return ! $values['enabled'] && 0.0 === $values['price'] && null === $values['sale_price'] && 1 === $values['min_units'] && in_array( $values['max_units'], array( null, 1 ), true );
	}

	/**
	 * A stored row as the values compared with the clean input.
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function values( object $row ): array {
		return array(
			'price'      => (float) $row->price,
			'sale_price' => null === $row->sale_price ? null : (float) $row->sale_price,
			'min_units'  => (int) $row->min_units,
			'max_units'  => null === $row->max_units ? null : (int) $row->max_units,
			'enabled'    => (int) $row->enabled,
			'sort_order' => (int) $row->sort_order,
		);
	}

	/**
	 * Log each changed rate (before/after of its changed fields), and one
	 * entry for a pure reorder.
	 *
	 * @param RoomType   $type      Room type.
	 * @param RatePlan[] $plans     Plans by id.
	 * @param array      $changes   Plan id => [ before, after ].
	 * @param int[]      $old_order Plan ids of the stored rows, in order, before.
	 * @param int[]      $new_order The same, after.
	 * @return void
	 */
	private function log( RoomType $type, array $plans, array $changes, array $old_order, array $new_order ): void {
		foreach ( $changes as $plan_id => list( $before, $after ) ) {
			$old = $before ?? array();
			$new = $after;
			unset( $old['sort_order'], $new['sort_order'] );
			$old['enabled'] = (bool) ( $old['enabled'] ?? false );
			$new['enabled'] = (bool) $new['enabled'];

			$diff = array(
				'before' => array(),
				'after'  => array(),
			);
			foreach ( $new as $field => $value ) {
				if ( ! array_key_exists( $field, $old ) || $old[ $field ] !== $value ) {
					$diff['before'][ $field ] = $old[ $field ] ?? null;
					$diff['after'][ $field ]  = $value;
				}
			}
			if ( ! $diff['after'] ) {
				continue; // Only its position changed: the order entry below covers it.
			}
			$label = sprintf( '%s · %s', $type->name, $plans[ $plan_id ]->name ?? '' );
			rtbp_activity(
				'rates.update',
				array(
					'type'  => 'room_type_rate',
					'id'    => $type->id . ':' . $plan_id,
					'label' => $label,
				),
				$diff + array(
					/* translators: %s: room type and rate plan, e.g. "Standard Room · Overnight". */
					'description' => sprintf( __( 'Changed the rate %s', 'radius-hotel-booking' ), $label ),
				)
			);
		}

		// A new row only appended at the end is not a reorder.
		$kept = array_values( array_intersect( $new_order, $old_order ) );
		if ( $kept !== array_values( $old_order ) ) {
			$names = static fn( array $ids ) => array_values( array_map( static fn( $id ) => (string) ( $plans[ $id ]->name ?? '' ), $ids ) );
			rtbp_activity(
				'rates.update',
				$type,
				array(
					'before'      => array( 'order' => $names( $old_order ) ),
					'after'       => array( 'order' => $names( $new_order ) ),
					/* translators: %s: room type name. */
					'description' => sprintf( __( 'Reordered the rates of %s', 'radius-hotel-booking' ), $type->name ),
				)
			);
		}
	}

	/**
	 * A live room type, or 404.
	 *
	 * @param int $id Id.
	 * @return RoomType
	 * @throws DomainException When missing.
	 */
	private function roomType( int $id ): RoomType {
		$type = $this->types->find( $id );
		if ( ! $type instanceof RoomType ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This room type does not exist.', 'radius-hotel-booking' ) );
		}
		return $type;
	}
}
