<?php
/**
 * Room holds (feature 2.14, booking-engine §7).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Repositories\HoldRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingWriter;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Selecting a room (desk or web) holds it for `booking.holdMinutes` while the
 * form is completed, so two people cannot take the same room at the same
 * second. A hold is made through the locked write path, like a booking.
 *
 * - One **token** groups a booking's holds; every hold of a token expires at
 *   the same moment, and each new hold or extension moves it.
 * - **Owner:** staff holds carry the user id, web holds the visitor's session
 *   key. A token can only be used by the kind of owner that made it.
 * - Expired holds stop counting at once (every query compares the expiry) and
 *   are deleted by the sweep every 5 minutes.
 * - Activity: staff holds are logged (`holds.create` / `holds.release`); web
 *   holds are only counted per day (option `rtbp_web_hold_counts`), so the
 *   log is not flooded by visitors.
 */
class HoldService {

	/**
	 * Most live holds one token may have (one booking's rooms).
	 */
	public const MAX_PER_TOKEN = 10;

	/**
	 * Days of web hold counts kept.
	 */
	private const COUNT_DAYS = 31;

	/**
	 * Holds.
	 *
	 * @var HoldRepository
	 */
	private HoldRepository $holds;

	/**
	 * The locked write path.
	 *
	 * @var BookingWriter
	 */
	private BookingWriter $writer;

	/**
	 * Constructor.
	 *
	 * @param HoldRepository|null $holds  Holds.
	 * @param BookingWriter|null  $writer Write path.
	 */
	public function __construct( ?HoldRepository $holds = null, ?BookingWriter $writer = null ) {
		$this->holds  = $holds ?? new HoldRepository();
		$this->writer = $writer ?? new BookingWriter();
	}

	/**
	 * Register the sweep (every 5 minutes, through the Scheduler).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter(
			'rtbp_scheduled_events',
			static function ( $events ) {
				$events                     = (array) $events;
				$events['rtbp_sweep_holds'] = array(
					'recurrence' => 'rtbp_five_minutes',
					'callback'   => static fn() => ( new self() )->sweep(),
				);
				return $events;
			}
		);
	}

	/**
	 * Hold a room.
	 *
	 * @param array                  $input `room_id`, `rate_plan_id`, `arrival`, `units`, `checkin_time`,
	 *                                      `room_type_id` (as shown), `token` (to add to a booking's holds).
	 * @param array                  $actor `audience` (staff|public), `user_id`, `session_key`.
	 * @param DateTimeImmutable|null $now   Now (checks).
	 * @return array `{ token, expires_at, hold: { id, room_id, room, rate_plan_id, window, total } }`.
	 * @throws DomainException 409 when the room cannot be held, 403 on a foreign token, 422 on input.
	 */
	public function create( array $input, array $actor, ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$actor = $this->actor( $actor );
		$token = isset( $input['token'] ) ? (string) $input['token'] : '';
		if ( '' !== $token ) {
			$this->assertOwner( $token, $actor, true );
		} else {
			$token = self::newToken();
		}

		$expires = Dates::to_gmt_db( $now->modify( '+' . $this->minutes() . ' minutes' ) );
		$now_gmt = Dates::to_gmt_db( $now );

		$result = Transaction::run(
			function () use ( $input, $actor, $token, $expires, $now, $now_gmt ) {
				$checked = $this->writer->lockAndCheck(
					array(
						array(
							'room_id'      => (int) ( $input['room_id'] ?? 0 ),
							'room_type_id' => (int) ( $input['room_type_id'] ?? 0 ),
							'rate_plan_id' => (int) ( $input['rate_plan_id'] ?? 0 ),
							'arrival'      => (string) ( $input['arrival'] ?? '' ),
							'units'        => (int) ( $input['units'] ?? 1 ),
							'checkin_time' => (string) ( $input['checkin_time'] ?? '' ),
						),
					),
					$token,
					array(
						'audience' => $actor['audience'],
						'now'      => $now,
					)
				);
				$item  = $checked[0];
				$start = Dates::to_gmt_db( $item['window']->startGmt() );
				$end   = Dates::to_gmt_db( $item['window']->endGmt() );

				// The same room and window again under this token: just refresh it.
				$live    = array_filter( $this->holds->forToken( $token ), static fn( $row ) => $row['expires_at_gmt'] > $now_gmt );
				$hold_id = 0;
				foreach ( $live as $row ) {
					if ( (int) $row['room_id'] === (int) $item['room']['id'] && $row['start_at_gmt'] === $start && $row['end_at_gmt'] === $end ) {
						$hold_id = (int) $row['id'];
					}
				}
				if ( ! $hold_id ) {
					if ( count( $live ) >= self::MAX_PER_TOKEN ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
						throw DomainException::conflict( 'too_many_holds', __( 'Too many rooms are held for this booking.', 'radius-hotel-booking' ) );
					}
					$hold_id = $this->holds->insert(
						array(
							'room_id'        => (int) $item['room']['id'],
							'room_type_id'   => $item['room_type_id'],
							'rate_plan_id'   => $item['rate_plan_id'],
							'start_at_gmt'   => $start,
							'end_at_gmt'     => $end,
							'token'          => $token,
							'user_id'        => $actor['user_id'] ? $actor['user_id'] : null,
							'session_key'    => $actor['session_key'],
							'expires_at_gmt' => $expires,
						)
					);
				}
				// Every hold of the booking expires together.
				$this->holds->extend( $token, $expires, $now_gmt );

				$hold = array(
					'id'           => $hold_id,
					'room_id'      => (int) $item['room']['id'],
					'room'         => (string) $item['room']['number'],
					'room_type_id' => $item['room_type_id'],
					'rate_plan_id' => $item['rate_plan_id'],
					'window'       => $item['window']->toArray(),
					'total'        => $item['quote']['total'],
				);

				if ( AvailabilityService::STAFF === $actor['audience'] ) {
					rtbp_activity(
						'holds.create',
						null,
						array(
							'after'       => array(
								'room'         => $hold['room'],
								'rate_plan_id' => $hold['rate_plan_id'],
								'start'        => $hold['window']['start'],
								'end'          => $hold['window']['end'],
							),
							'description' => sprintf(
								/* translators: 1: room number, 2: rate plan name, 3: start date and time. */
								__( 'Held room %1$s for %2$s from %3$s', 'radius-hotel-booking' ),
								$hold['room'],
								(string) $item['plan']->name,
								Dates::format( $item['window']->start(), 'datetime' )
							),
						)
					);
				}
				return $hold;
			}
		);

		if ( AvailabilityService::PUBLIC === $actor['audience'] ) {
			$this->countWebHold( $now );
		}

		/**
		 * A room was held.
		 *
		 * @param array  $hold     The hold.
		 * @param string $token    Its token.
		 * @param string $audience staff|public.
		 */
		do_action( 'rtbp_hold_created', $result, $token, $actor['audience'] );

		return array(
			'token'      => $token,
			'expires_at' => Dates::to_iso( Dates::from_gmt( $expires ) ),
			'hold'       => $result,
		);
	}

	/**
	 * Give every live hold of a token a fresh expiry (the form is still being
	 * filled in). An expired hold is not revived: the room may be gone.
	 *
	 * @param string                 $token Token.
	 * @param array                  $actor Actor.
	 * @param DateTimeImmutable|null $now   Now.
	 * @return array `{ token, expires_at, holds }`.
	 * @throws DomainException 410 `hold_expired`, 403 on a foreign token.
	 */
	public function extend( string $token, array $actor, ?DateTimeImmutable $now = null ): array {
		$now   = $now ?? Dates::now();
		$actor = $this->actor( $actor );
		$this->assertOwner( $token, $actor, false );

		$expires = Dates::to_gmt_db( $now->modify( '+' . $this->minutes() . ' minutes' ) );
		$count   = $this->holds->extend( $token, $expires, Dates::to_gmt_db( $now ) );
		if ( ! $count ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'hold_expired', __( 'The room is no longer held. Check that it is still free and choose it again.', 'radius-hotel-booking' ), 410 );
		}
		return array(
			'token'      => $token,
			'expires_at' => Dates::to_iso( Dates::from_gmt( $expires ) ),
			'holds'      => $count,
		);
	}

	/**
	 * Release a token's holds (all, or one room of the booking).
	 *
	 * @param string $token   Token.
	 * @param array  $actor   Actor.
	 * @param int    $hold_id One hold; 0 = all.
	 * @return int Holds released.
	 * @throws DomainException 403 on a foreign token.
	 */
	public function release( string $token, array $actor, int $hold_id = 0 ): int {
		$actor = $this->actor( $actor );
		$rows  = $this->assertOwner( $token, $actor, false );
		$count = $this->holds->delete( $token, $hold_id );

		if ( $count && AvailabilityService::STAFF === $actor['audience'] ) {
			$rooms = array();
			foreach ( $rows as $row ) {
				if ( ! $hold_id || (int) $row['id'] === $hold_id ) {
					$rooms[] = (int) $row['room_id'];
				}
			}
			rtbp_activity(
				'holds.release',
				null,
				array(
					'before'      => array( 'room_ids' => $rooms ),
					'description' => sprintf(
						/* translators: %d: number of rooms. */
						_n( 'Released %d held room', 'Released %d held rooms', $count, 'radius-hotel-booking' ),
						$count
					),
				)
			);
		}
		return $count;
	}

	/**
	 * Delete expired holds (the cron sweep). Expired holds are already
	 * ignored by every query; this only keeps the table small.
	 *
	 * @param DateTimeImmutable|null $now Now.
	 * @return int Holds deleted.
	 */
	public function sweep( ?DateTimeImmutable $now = null ): int {
		$before = Dates::to_gmt_db( $now ?? Dates::now() );
		$total  = 0;
		do {
			$deleted = $this->holds->deleteExpired( $before, 1000 );
			$total  += $deleted;
		} while ( 1000 === $deleted );
		return $total;
	}

	/**
	 * A new random token (32 letters and digits).
	 *
	 * @return string
	 */
	public static function newToken(): string {
		return wp_generate_password( 32, false, false );
	}

	/**
	 * Whether a string looks like a token.
	 *
	 * @param string $token Candidate.
	 * @return bool
	 */
	public static function isToken( string $token ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9]{32}$/', $token );
	}

	/**
	 * Hold length from the settings (5–120 minutes).
	 *
	 * @return int
	 */
	private function minutes(): int {
		return max( 5, min( 120, (int) rtbp_setting( 'booking', 'holdMinutes', 15 ) ) );
	}

	/**
	 * Normalise the actor.
	 *
	 * @param array $actor Raw.
	 * @return array `{ audience, user_id, session_key }`.
	 * @throws DomainException 403 when a web visitor has no session key.
	 */
	private function actor( array $actor ): array {
		$audience = AvailabilityService::PUBLIC === ( $actor['audience'] ?? '' ) ? AvailabilityService::PUBLIC : AvailabilityService::STAFF;
		$session  = preg_replace( '/[^A-Za-z0-9_-]/', '', substr( (string) ( $actor['session_key'] ?? '' ), 0, 64 ) );
		if ( AvailabilityService::PUBLIC === $audience && '' === $session ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'hold_forbidden', __( 'Your session has expired. Reload the page and try again.', 'radius-hotel-booking' ), 403 );
		}
		return array(
			'audience'    => $audience,
			'user_id'     => AvailabilityService::STAFF === $audience ? (int) ( $actor['user_id'] ?? get_current_user_id() ) : 0,
			'session_key' => AvailabilityService::PUBLIC === $audience ? $session : '',
		);
	}

	/**
	 * Check the actor may use a token: staff tokens belong to staff, a web
	 * token to the session that made it.
	 *
	 * @param string $token     Token.
	 * @param array  $actor     Actor.
	 * @param bool   $allow_new An unknown token is fine (adding a first hold).
	 * @return array[] The token's rows.
	 * @throws DomainException 403 / 404.
	 */
	private function assertOwner( string $token, array $actor, bool $allow_new ): array {
		if ( ! self::isToken( $token ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'token' => __( 'This hold is not valid.', 'radius-hotel-booking' ) ) );
		}
		$rows = $this->holds->forToken( $token );
		if ( ! $rows ) {
			if ( $allow_new ) {
				return array();
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'The room is no longer held.', 'radius-hotel-booking' ) );
		}
		foreach ( $rows as $row ) {
			$own = AvailabilityService::STAFF === $actor['audience']
				? null !== $row['user_id'] && (int) $row['user_id'] > 0
				: '' !== $actor['session_key'] && hash_equals( (string) $row['session_key'], $actor['session_key'] );
			if ( ! $own ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw new DomainException( 'hold_forbidden', __( 'This hold belongs to someone else.', 'radius-hotel-booking' ), 403 );
			}
		}
		return $rows;
	}

	/**
	 * Count a web hold for today (kept 31 days).
	 *
	 * @param DateTimeImmutable $now Now.
	 * @return void
	 */
	private function countWebHold( DateTimeImmutable $now ): void {
		$day    = $now->setTimezone( Dates::timezone() )->format( 'Y-m-d' );
		$counts = get_option( 'rtbp_web_hold_counts', array() );
		$counts = is_array( $counts ) ? $counts : array();
		$counts[ $day ] = (int) ( $counts[ $day ] ?? 0 ) + 1;
		krsort( $counts );
		update_option( 'rtbp_web_hold_counts', array_slice( $counts, 0, self::COUNT_DAYS, true ), false );
	}
}
