<?php
/**
 * A concrete stay window derived from a rate plan.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use DateTimeImmutable;
use DateTimeZone;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * The half-open interval `[start, end)` a booking line occupies, derived
 * from a rate plan, a local start date and a number of units
 * (booking-engine §2). Pure: it reads nothing but its arguments (the time
 * zone defaults to the site's).
 *
 * - fixed: start = D @ start_time; the end is D' @ end_time, where D' is the
 *   next day when the window crosses midnight or lasts 24 h
 *   (end_time <= start_time), plus (n − 1) days.
 * - flexible: start = D @ chosen check-in (within the plan's range, on a
 *   30-minute step); end = start + duration, plus (n − 1) days.
 *
 * All arithmetic is **wall-clock** in the local zone: days are added as
 * calendar days and durations as wall-clock minutes, then the result is
 * converted to GMT. An Overnight across a DST change still ends at 08:00.
 * Never 86 400-second days.
 */
final class StayWindow {

	/**
	 * The most units (days or nights) one window may cover. Bounds the work a
	 * quote or a search does per window, whatever a rate's maximum says.
	 */
	const MAX_UNITS = 365;

	/**
	 * Start, local.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $start;

	/**
	 * End (exclusive), local.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $end;

	/**
	 * Units (days or nights) this window covers.
	 *
	 * @var int
	 */
	private int $units;

	/**
	 * Constructor.
	 *
	 * @param DateTimeImmutable $start Start, local.
	 * @param DateTimeImmutable $end   End, local.
	 * @param int               $units Units.
	 */
	private function __construct( DateTimeImmutable $start, DateTimeImmutable $end, int $units ) {
		$this->start = $start;
		$this->end   = $end;
		$this->units = $units;
	}

	/**
	 * Derive the window.
	 *
	 * @param RatePlan|array    $plan    The plan (model or its fields).
	 * @param string            $date    Local start date `Y-m-d`.
	 * @param int               $units   Units (days/nights); 1 unless the plan is multi-unit.
	 * @param string|null       $checkin Chosen check-in `HH:MM` (flexible plans; default: the earliest allowed).
	 * @param DateTimeZone|null $tz      Time zone (default: the site's).
	 * @return self
	 * @throws DomainException 422 on invalid input (`invalid_checkin_time`, bad date or units).
	 */
	public static function for( $plan, string $date, int $units = 1, ?string $checkin = null, ?DateTimeZone $tz = null ): self {
		$plan = $plan instanceof RatePlan ? $plan->toArray() : (array) $plan;
		$tz   = $tz ?? Dates::timezone();

		$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );
		if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'date' => __( 'Choose a valid date.', 'radius-hotel-booking' ) ) );
		}
		if ( $units > self::MAX_UNITS ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid(
				/* translators: %d: the most nights or days in one stay. */
				array( 'units' => sprintf( __( 'A stay can cover at most %d nights or days.', 'radius-hotel-booking' ), self::MAX_UNITS ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( $units < 1 || ( $units > 1 && empty( $plan['multi_unit'] ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'units' => __( 'This rate plan is sold as a single stay.', 'radius-hotel-booking' ) ) );
		}

		if ( RatePlan::FLEXIBLE === ( $plan['type'] ?? '' ) ) {
			$time = null === $checkin || '' === $checkin ? (string) ( $plan['checkin_from'] ?? '00:00' ) : $checkin;
			if ( ! self::checkinAllowed( $plan, $time ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
				throw new DomainException(
					'invalid_checkin_time',
					sprintf(
						/* translators: 1: earliest check-in time, 2: latest check-in time. */
						__( 'Choose a check-in time between %1$s and %2$s, on the hour or half hour.', 'radius-hotel-booking' ),
						(string) ( $plan['checkin_from'] ?? '00:00' ),
						(string) ( $plan['checkin_until'] ?? '23:30' )
					),
					422,
					array( 'checkin_time' => __( 'This check-in time is not allowed for this rate plan.', 'radius-hotel-booking' ) )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$duration = (int) ( $plan['duration_minutes'] ?? 0 );
		} else {
			$time     = (string) ( $plan['start_time'] ?? '' );
			$duration = self::fixedMinutes( $time, (string) ( $plan['end_time'] ?? '' ) );
		}
		if ( null === self::minutesOf( $time ) || $duration < 1 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'rate_plan_id' => __( 'This rate plan has no valid times.', 'radius-hotel-booking' ) ) );
		}

		// Wall clock: the local date and time as plain numbers, moved in UTC
		// (which has no DST), then read back as local wall time.
		$wall_start = new DateTimeImmutable( $date . ' ' . $time, new DateTimeZone( 'UTC' ) );
		$wall_end   = $wall_start->modify( '+' . $duration . ' minutes' )->modify( '+' . ( $units - 1 ) . ' days' );

		$start = new DateTimeImmutable( $wall_start->format( 'Y-m-d H:i:s' ), $tz );
		$end   = new DateTimeImmutable( $wall_end->format( 'Y-m-d H:i:s' ), $tz );

		// A local time skipped by a clock change (e.g. 02:30 when clocks jump
		// from 02:00 to 03:00) does not exist: PHP would move the start but not
		// the end, giving a window that ends before it starts.
		if ( $start->format( 'Y-m-d H:i' ) !== $wall_start->format( 'Y-m-d H:i' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'invalid_checkin_time', __( 'This check-in time does not exist on that day because of a clock change.', 'radius-hotel-booking' ), 422, array( 'checkin_time' => __( 'This time does not exist on that day.', 'radius-hotel-booking' ) ) );
		}
		// Every overlap check relies on end > start.
		if ( $end <= $start ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'invalid_window', __( 'This stay window has no length on that day because of a clock change.', 'radius-hotel-booking' ), 422 );
		}

		return new self( $start, $end, $units );
	}

	/**
	 * A fixed window's length in minutes: (end − start) mod 24 h, where 0
	 * means a full 24 h (start == end).
	 *
	 * @param string $start `HH:MM`.
	 * @param string $end   `HH:MM`.
	 * @return int Minutes (0 when a time is invalid).
	 */
	public static function fixedMinutes( string $start, string $end ): int {
		$from = self::minutesOf( $start );
		$to   = self::minutesOf( $end );
		if ( null === $from || null === $to ) {
			return 0;
		}
		$minutes = ( $to - $from + 1440 ) % 1440;
		return 0 === $minutes ? 1440 : $minutes;
	}

	/**
	 * Minutes since midnight of an `HH:MM` time, or null when invalid.
	 *
	 * @param string|null $time Time.
	 * @return int|null
	 */
	public static function minutesOf( ?string $time ): ?int {
		if ( null === $time || ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m ) ) {
			return null;
		}
		return (int) $m[1] * 60 + (int) $m[2];
	}

	/**
	 * Whether a flexible plan accepts this check-in time: inside its range
	 * and on a 30-minute step.
	 *
	 * @param array  $plan Plan fields.
	 * @param string $time `HH:MM`.
	 * @return bool
	 */
	public static function checkinAllowed( array $plan, string $time ): bool {
		$at    = self::minutesOf( $time );
		$from  = self::minutesOf( $plan['checkin_from'] ?? '00:00' ) ?? 0;
		$until = self::minutesOf( $plan['checkin_until'] ?? '23:30' ) ?? 1410;
		return null !== $at && 0 === $at % 30 && $at >= $from && $at <= $until;
	}

	/**
	 * Start, local.
	 *
	 * @return DateTimeImmutable
	 */
	public function start(): DateTimeImmutable {
		return $this->start;
	}

	/**
	 * End (exclusive), local.
	 *
	 * @return DateTimeImmutable
	 */
	public function end(): DateTimeImmutable {
		return $this->end;
	}

	/**
	 * Start in GMT.
	 *
	 * @return DateTimeImmutable
	 */
	public function startGmt(): DateTimeImmutable {
		return $this->start->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * End in GMT.
	 *
	 * @return DateTimeImmutable
	 */
	public function endGmt(): DateTimeImmutable {
		return $this->end->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Units covered.
	 *
	 * @return int
	 */
	public function units(): int {
		return $this->units;
	}

	/**
	 * Real elapsed minutes (differs from wall-clock minutes across a DST change).
	 *
	 * @return int
	 */
	public function minutes(): int {
		return intdiv( $this->end->getTimestamp() - $this->start->getTimestamp(), 60 );
	}

	/**
	 * Whether the window ends on a later local date than it starts.
	 *
	 * @return bool
	 */
	public function crossesMidnight(): bool {
		return $this->end->format( 'Y-m-d' ) > $this->start->format( 'Y-m-d' );
	}

	/**
	 * The local start date of each unit (the dates pricing looks up).
	 *
	 * @return string[] `Y-m-d`.
	 */
	public function unitDates(): array {
		$dates = array();
		for ( $i = 0; $i < $this->units; $i++ ) {
			$dates[] = $this->start->modify( '+' . $i . ' days' )->format( 'Y-m-d' );
		}
		return $dates;
	}

	/**
	 * The four columns a booking line stores (booking-engine §3). The only
	 * place these values are produced.
	 *
	 * @param string $prefix Column prefix (default none: start_at, …).
	 * @return array<string, string>
	 */
	public function toRow( string $prefix = '' ): array {
		return array(
			$prefix . 'start_at'     => $this->start->format( 'Y-m-d H:i:s' ),
			$prefix . 'start_at_gmt' => $this->startGmt()->format( 'Y-m-d H:i:s' ),
			$prefix . 'end_at'       => $this->end->format( 'Y-m-d H:i:s' ),
			$prefix . 'end_at_gmt'   => $this->endGmt()->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * The window for the API (ISO 8601 with offset).
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'start'            => $this->start->format( DATE_ATOM ),
			'end'              => $this->end->format( DATE_ATOM ),
			'start_gmt'        => $this->startGmt()->format( DATE_ATOM ),
			'end_gmt'          => $this->endGmt()->format( DATE_ATOM ),
			'units'            => $this->units,
			'minutes'          => $this->minutes(),
			'crosses_midnight' => $this->crossesMidnight(),
		);
	}
}
