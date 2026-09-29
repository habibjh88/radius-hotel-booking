<?php
/**
 * Interval overlap (booking-engine §4).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Availability
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Availability;

use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * Pure checks: no database, no clock. Times are GMT `Y-m-d H:i:s` strings or
 * Unix timestamps.
 *
 * - Intervals are half-open `[start, end)`: a window ending at 17:00 and one
 *   starting at 17:00 do not collide.
 * - The cleaning buffer is added to the end of both the existing stay and the
 *   candidate: `a.start < b.end + buffer AND b.start < a.end + buffer`.
 * - A block is a closure, not a stay: no cleaning buffer applies around it.
 * - A conflict without the buffer (`booked`, `held`, `blocked`) outranks one
 *   caused by the buffer alone (`buffer`), so the reason shown is the real one.
 */
class Overlap {

	/**
	 * Busy-interval kinds (the `kind` of `AvailabilityRepository::busy()`).
	 */
	public const LINE  = 'line';
	public const HOLD  = 'hold';
	public const BLOCK = 'block';

	/**
	 * Room-level reasons, in the order they are reported.
	 */
	public const REASON_BOOKED  = 'booked';
	public const REASON_HELD    = 'held';
	public const REASON_BLOCKED = 'blocked';
	public const REASON_BUFFER  = 'buffer';

	/**
	 * Whether two intervals collide.
	 *
	 * @param int $a_start        Start of A (timestamp).
	 * @param int $a_end          End of A (timestamp, exclusive).
	 * @param int $b_start        Start of B (timestamp).
	 * @param int $b_end          End of B (timestamp, exclusive).
	 * @param int $buffer_seconds Cleaning time added to both ends; 0 = none.
	 * @return bool
	 */
	public static function overlaps( int $a_start, int $a_end, int $b_start, int $b_end, int $buffer_seconds = 0 ): bool {
		$buffer_seconds = max( 0, $buffer_seconds );
		return $a_start < $b_end + $buffer_seconds && $b_start < $a_end + $buffer_seconds;
	}

	/**
	 * A GMT database time as a timestamp.
	 *
	 * @param string $gmt `Y-m-d H:i:s` in UTC.
	 * @return int
	 */
	public static function ts( string $gmt ): int {
		return Dates::from_gmt( $gmt )->getTimestamp();
	}

	/**
	 * The conflict a candidate window has with a room's busy intervals.
	 *
	 * @param array<int, array> $busy           The room's intervals: `{ s, e, kind, ref_id, label }`
	 *                                          (GMT strings), as `AvailabilityRepository::busy()` returns them.
	 * @param int               $start          Candidate start (timestamp).
	 * @param int               $end            Candidate end (timestamp, exclusive).
	 * @param int               $buffer_minutes Cleaning buffer of the room's type.
	 * @return array|null `{ reason, interval }`, or null when the room is free.
	 */
	public static function conflict( array $busy, int $start, int $end, int $buffer_minutes ): ?array {
		$buffer   = max( 0, $buffer_minutes ) * MINUTE_IN_SECONDS;
		$fallback = null;

		foreach ( $busy as $interval ) {
			$s    = self::ts( (string) $interval['s'] );
			$e    = self::ts( (string) $interval['e'] );
			$kind = (string) $interval['kind'];

			if ( self::overlaps( $start, $end, $s, $e ) ) {
				return array(
					'reason'   => self::reasonFor( $kind ),
					'interval' => $interval,
				);
			}
			if ( null === $fallback && self::BLOCK !== $kind && $buffer && self::overlaps( $start, $end, $s, $e, $buffer ) ) {
				$fallback = array(
					'reason'   => self::REASON_BUFFER,
					'interval' => $interval,
				);
			}
		}

		return $fallback;
	}

	/**
	 * Why a room cannot take a window, or null when it can: its state first
	 * (maintenance / out of service make it unsellable for any window), then
	 * its busy intervals.
	 *
	 * @param string            $state          Room state.
	 * @param array<int, array> $busy           The room's busy intervals.
	 * @param int               $start          Candidate start (timestamp).
	 * @param int               $end            Candidate end (timestamp, exclusive).
	 * @param int               $buffer_minutes Cleaning buffer.
	 * @return array|null `{ reason, interval|null }`.
	 */
	public static function roomReason( string $state, array $busy, int $start, int $end, int $buffer_minutes ): ?array {
		if ( Room::AVAILABLE !== $state ) {
			return array(
				'reason'   => $state,
				'interval' => null,
			);
		}
		return self::conflict( $busy, $start, $end, $buffer_minutes );
	}

	/**
	 * The room-level reason for a busy kind.
	 *
	 * @param string $kind Busy kind.
	 * @return string
	 */
	public static function reasonFor( string $kind ): string {
		switch ( $kind ) {
			case self::HOLD:
				return self::REASON_HELD;
			case self::BLOCK:
				return self::REASON_BLOCKED;
			default:
				return self::REASON_BOOKED;
		}
	}
}
