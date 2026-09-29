<?php
/**
 * Rate plans: create, change, delete.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Pricing
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Pricing;

use RadiusTheme\RadiusHotelBooking\ActivityLog\ChangeDiff;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Repositories\RatePlanRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\StayWindow;
use RadiusTheme\RadiusHotelBooking\Support\Db;

defined( 'ABSPATH' ) || exit;

/**
 * The rate plan library (features 7.1–7.5, 7.10). A plan's behaviour lives
 * in explicit fields: `fixed` (start and end time on 30-minute steps; the
 * duration is derived, equal times = 24 h) or `flexible` (a duration and the
 * allowed check-in range). A plan in use cannot be deleted; deactivate it.
 */
class RatePlanService {

	/**
	 * Allowed HTML in the policy text: basic formatting only (7.5).
	 */
	const POLICY_TAGS = array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);

	/**
	 * Rate plans.
	 *
	 * @var RatePlanRepository
	 */
	private RatePlanRepository $plans;

	/**
	 * Room type rates (who sells a plan).
	 *
	 * @var RoomTypeRateRepository
	 */
	private RoomTypeRateRepository $rates;

	/**
	 * Constructor.
	 *
	 * @param RatePlanRepository|null     $plans Rate plans.
	 * @param RoomTypeRateRepository|null $rates Room type rates.
	 */
	public function __construct( ?RatePlanRepository $plans = null, ?RoomTypeRateRepository $rates = null ) {
		$this->plans = $plans ?? new RatePlanRepository();
		$this->rates = $rates ?? new RoomTypeRateRepository();
	}

	/**
	 * Every plan in display order.
	 *
	 * @return RatePlan[]
	 */
	public function all(): array {
		return $this->plans->ordered();
	}

	/**
	 * One plan, or 404.
	 *
	 * @param int $id Id.
	 * @return RatePlan
	 * @throws DomainException When missing.
	 */
	public function get( int $id ): RatePlan {
		$plan = $this->plans->find( $id );
		if ( ! $plan instanceof RatePlan ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This rate plan does not exist.', 'radius-hotel-booking' ) );
		}
		return $plan;
	}

	/**
	 * Where a plan is used (7.10): the live room types selling it (an enabled
	 * rate) and its upcoming bookings, which the bookings module (M02) adds
	 * through `rtbp_rate_plan_usage`.
	 *
	 * @param int $id Plan id.
	 * @return array{room_types: int, bookings: int}
	 */
	public function usage( int $id ): array {
		/**
		 * Filters where a rate plan is used.
		 *
		 * @param array $usage   `{ room_types: int, bookings: int }`.
		 * @param int   $plan_id Rate plan id.
		 */
		$usage = (array) apply_filters(
			'rtbp_rate_plan_usage',
			array(
				'room_types' => $this->rates->roomTypesSelling( $id ),
				'bookings'   => 0,
			),
			$id
		);
		return array(
			'room_types' => max( 0, (int) ( $usage['room_types'] ?? 0 ) ),
			'bookings'   => max( 0, (int) ( $usage['bookings'] ?? 0 ) ),
		);
	}

	/**
	 * Create a plan (added last).
	 *
	 * @param array $input Fields.
	 * @return RatePlan
	 * @throws DomainException When the insert did not happen.
	 */
	public function create( array $input ): RatePlan {
		$data               = $this->validate( $input, null );
		$data['code']       = $this->uniqueCode( '' !== (string) ( $input['code'] ?? '' ) ? (string) $input['code'] : $data['name'], 0 );
		$data['sort_order'] = $this->plans->nextSortOrder();

		$plan = Db::quietly( fn() => $this->plans->create( $data ) );
		if ( ! $plan instanceof RatePlan || ! $plan->id ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'rate_plan_not_saved', __( 'The rate plan could not be saved. Please try again.', 'radius-hotel-booking' ), 409 );
		}

		rtbp_activity(
			'rate_plans.create',
			$plan,
			array(
				'after'       => self::audited( $data ),
				/* translators: %s: rate plan name. */
				'description' => sprintf( __( 'Added the rate plan %s', 'radius-hotel-booking' ), $plan->name ),
			)
		);

		/**
		 * Fires after a rate plan is created.
		 *
		 * @param RatePlan $plan Plan.
		 */
		do_action( 'rtbp_rate_plan_created', $plan );

		return $plan;
	}

	/**
	 * Change a plan (only the fields given; switching type needs that type's fields).
	 *
	 * @param int   $id    Id.
	 * @param array $input Fields.
	 * @return RatePlan
	 */
	public function update( int $id, array $input ): RatePlan {
		$plan   = $this->get( $id );
		$before = self::audited( $plan->toArray() );
		$data   = $this->validate( $input, $plan );
		if ( isset( $input['code'] ) && '' !== (string) $input['code'] && (string) $input['code'] !== $plan->code ) {
			$data['code'] = $this->uniqueCode( (string) $input['code'], $id );
		}

		if ( $data ) {
			$this->plans->update( $id, $data );
		}
		$plan = $this->get( $id );
		if ( empty( $before['multi_unit'] ) && $plan->multi_unit ) {
			// Its rates were held at 1–1 while it was single-unit.
			$this->rates->liftSingleUnitCap( $id );
		}

		$diff = ChangeDiff::between( $before, self::audited( $plan->toArray() ) );
		if ( $diff['after'] ) {
			rtbp_activity(
				'rate_plans.update',
				$plan,
				$diff + array(
					/* translators: %s: rate plan name. */
					'description' => sprintf( __( 'Changed the rate plan %s', 'radius-hotel-booking' ), $plan->name ),
				)
			);

			/**
			 * Fires after a rate plan changed. Existing bookings keep the window
			 * they were sold with; only new bookings use the new one.
			 *
			 * @param RatePlan $plan Plan.
			 * @param array    $diff Before/after of the changed fields.
			 */
			do_action( 'rtbp_rate_plan_updated', $plan, $diff );
		}

		return $plan;
	}

	/**
	 * Delete a plan (soft). Refused while a room type sells it or a booking
	 * uses it (7.10): deactivate it instead.
	 *
	 * @param int $id Id.
	 * @return void
	 * @throws DomainException When in use.
	 */
	public function delete( int $id ): void {
		$plan = $this->get( $id );

		Transaction::run(
			function () use ( $plan, $id ) {
				if ( ! $this->plans->lock( $id ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::notFound( __( 'This rate plan does not exist.', 'radius-hotel-booking' ) );
				}
				$usage = $this->usage( $id );
				if ( $usage['room_types'] || $usage['bookings'] ) {
					$parts = array();
					if ( $usage['room_types'] ) {
						/* translators: %d: number of room types. */
						$parts[] = sprintf( _n( '%d room type sells it', '%d room types sell it', $usage['room_types'], 'radius-hotel-booking' ), $usage['room_types'] );
					}
					if ( $usage['bookings'] ) {
						/* translators: %d: number of bookings. */
						$parts[] = sprintf( _n( '%d upcoming booking uses it', '%d upcoming bookings use it', $usage['bookings'], 'radius-hotel-booking' ), $usage['bookings'] );
					}
					$message = sprintf(
						/* translators: 1: rate plan name, 2: where it is used, e.g. "2 room types sell it". */
						__( '%1$s cannot be deleted: %2$s. Deactivate it instead.', 'radius-hotel-booking' ),
						$plan->name,
						implode( ', ', $parts )
					);
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw DomainException::conflict( 'rate_plan_in_use', $message, $usage );
				}
				if ( ! $plan->delete() ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
					throw new DomainException( 'rate_plan_not_saved', __( 'The rate plan could not be deleted. Please try again.', 'radius-hotel-booking' ), 500 );
				}
			}
		);

		rtbp_activity(
			'rate_plans.delete',
			$plan,
			array(
				'before'      => self::audited( $plan->toArray() ),
				/* translators: %s: rate plan name. */
				'description' => sprintf( __( 'Deleted the rate plan %s', 'radius-hotel-booking' ), $plan->name ),
			)
		);

		/**
		 * Fires after a rate plan is deleted.
		 *
		 * @param RatePlan $plan Plan.
		 */
		do_action( 'rtbp_rate_plan_deleted', $plan );
	}

	/**
	 * Validate and clean the fields given. On create every field is required
	 * or defaulted; on update only the fields sent change, but the result must
	 * still be a complete plan of its (possibly new) type.
	 *
	 * @param array         $input Input.
	 * @param RatePlan|null $plan  The plan being changed, or null on create.
	 * @return array Clean columns.
	 * @throws DomainException With field errors.
	 */
	private function validate( array $input, ?RatePlan $plan ): array {
		$current = $plan ? $plan->toArray() : array();
		$has     = static fn( $key ) => null === $plan || array_key_exists( $key, $input );
		$data    = array();
		$errors  = array();

		if ( $has( 'name' ) ) {
			$name = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
			if ( '' === $name || mb_strlen( $name ) > 120 ) {
				$errors['name'] = __( 'Give the rate plan a name of up to 120 characters.', 'radius-hotel-booking' );
			}
			$data['name'] = $name;
		}

		$type = $has( 'type' ) ? sanitize_key( (string) ( $input['type'] ?? RatePlan::FIXED ) ) : (string) $current['type'];
		if ( ! in_array( $type, RatePlan::types(), true ) ) {
			$errors['type'] = __( 'Choose a fixed or a flexible window.', 'radius-hotel-booking' );
		}
		$data['type'] = $type;

		// The window fields of the resulting type: sent, else current — but
		// never the old type's values when the type changes (a fixed plan's
		// derived duration must not become a flexible plan's duration).
		$switching = null !== $plan && $type !== (string) $current['type'];
		$field     = static fn( $key ) => array_key_exists( $key, $input ) ? $input[ $key ] : ( $switching ? null : ( $current[ $key ] ?? null ) );
		$step  = static function ( $value ) {
			$minutes = StayWindow::minutesOf( is_string( $value ) ? $value : null );
			return null !== $minutes && 0 === $minutes % 30;
		};

		if ( RatePlan::FIXED === $type ) {
			$start = (string) $field( 'start_time' );
			$end   = (string) $field( 'end_time' );
			if ( ! $step( $start ) ) {
				$errors['start_time'] = __( 'Choose a check-in time on the hour or half hour.', 'radius-hotel-booking' );
			}
			if ( ! $step( $end ) ) {
				$errors['end_time'] = __( 'Choose a check-out time on the hour or half hour.', 'radius-hotel-booking' );
			}
			$data += array(
				'start_time'       => $start,
				'end_time'         => $end,
				'duration_minutes' => StayWindow::fixedMinutes( $start, $end ),
				'checkin_from'     => null,
				'checkin_until'    => null,
			);
		} elseif ( RatePlan::FLEXIBLE === $type ) {
			$duration = filter_var( $field( 'duration_minutes' ), FILTER_VALIDATE_INT );
			$from     = (string) ( $field( 'checkin_from' ) ?? '00:00' );
			$until    = (string) ( $field( 'checkin_until' ) ?? '23:30' );
			if ( false === $duration || $duration < 30 || $duration > 10080 || 0 !== $duration % 30 ) {
				$errors['duration_minutes'] = __( 'A stay length from 30 minutes to 7 days, in half hours.', 'radius-hotel-booking' );
			}
			if ( ! $step( $from ) ) {
				$errors['checkin_from'] = __( 'Choose a time on the hour or half hour.', 'radius-hotel-booking' );
			}
			if ( ! $step( $until ) ) {
				$errors['checkin_until'] = __( 'Choose a time on the hour or half hour.', 'radius-hotel-booking' );
			} elseif ( $step( $from ) && StayWindow::minutesOf( $until ) < StayWindow::minutesOf( $from ) ) {
				$errors['checkin_until'] = __( 'The latest check-in must not be earlier than the earliest.', 'radius-hotel-booking' );
			}
			$data += array(
				'start_time'       => null,
				'end_time'         => null,
				'duration_minutes' => false === $duration ? 0 : $duration,
				'checkin_from'     => $from,
				'checkin_until'    => $until,
			);
		}

		if ( $has( 'multi_unit' ) ) {
			$data['multi_unit'] = rest_sanitize_boolean( $input['multi_unit'] ?? false ) ? 1 : 0;
		}
		if ( $has( 'features' ) ) {
			$clean = array();
			foreach ( is_array( $input['features'] ?? null ) ? $input['features'] : array() as $item ) {
				$item = mb_substr( sanitize_text_field( (string) $item ), 0, 60 );
				if ( '' !== $item && ! in_array( $item, $clean, true ) ) {
					$clean[] = $item;
				}
			}
			if ( count( $clean ) > 10 ) {
				$errors['features'] = __( 'Use 10 features or fewer.', 'radius-hotel-booking' );
			}
			$data['features'] = $clean;
		}
		if ( $has( 'policy' ) ) {
			$data['policy'] = wp_kses( (string) ( $input['policy'] ?? '' ), self::POLICY_TAGS );
		}
		if ( array_key_exists( 'is_active', $input ) ) {
			$data['is_active'] = rest_sanitize_boolean( $input['is_active'] ) ? 1 : 0;
		}

		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return $data;
	}

	/**
	 * A code unique among plans (removed ones included).
	 *
	 * @param string $source    Name or requested code.
	 * @param int    $except_id Ignore this plan.
	 * @return string
	 */
	private function uniqueCode( string $source, int $except_id ): string {
		$base = substr( sanitize_title( $source ), 0, 50 );
		$base = '' !== $base ? $base : 'rate-plan';
		$code = $base;
		for ( $i = 2; $this->plans->codeTaken( $code, $except_id ); $i++ ) {
			$code = $base . '-' . $i;
		}
		return $code;
	}

	/**
	 * The fields the activity log compares.
	 *
	 * @param array $row Row or input.
	 * @return array
	 */
	private static function audited( array $row ): array {
		$keys = array( 'name', 'code', 'type', 'start_time', 'end_time', 'duration_minutes', 'checkin_from', 'checkin_until', 'multi_unit', 'features', 'policy', 'is_active' );
		$out  = array_intersect_key( $row, array_flip( $keys ) );
		if ( isset( $out['policy'] ) ) {
			// Long text: record that it changed, not the whole text.
			$out['policy'] = '' === (string) $out['policy'] ? '' : md5( (string) $out['policy'] );
		}
		foreach ( array( 'multi_unit', 'is_active' ) as $flag ) {
			if ( isset( $out[ $flag ] ) ) {
				$out[ $flag ] = (bool) $out[ $flag ];
			}
		}
		return $out;
	}
}
