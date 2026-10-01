<?php
/**
 * The period and date mode a report covers (M10, 10.7).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Reports
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Reports;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * `from` / `to` are calendar days in the hotel's time zone, both included
 * (`to` defaults to today, `from` to the first of `to`'s month). The bounds
 * used in SQL are `[from 00:00, to + 1 day 00:00)` converted to GMT, so a
 * daylight-saving change or a midnight booking is never lost.
 *
 * `mode` answers the question every report asks: `arrival` — stays *starting*
 * in the period (a booking line's `start_at_gmt`); `created` — bookings
 * *taken* in the period (`bookings.created_at_gmt`). The same switch as the
 * dashboard and booking lists.
 */
final class ReportRange {

	/**
	 * Date modes.
	 */
	public const MODES = array( 'arrival', 'created' );

	/**
	 * Longest period, in days (three years).
	 */
	public const MAX_DAYS = 1100;

	/**
	 * First day (`Y-m-d`, site time).
	 *
	 * @var string
	 */
	public string $from;

	/**
	 * Last day, included (`Y-m-d`, site time).
	 *
	 * @var string
	 */
	public string $to;

	/**
	 * `arrival` or `created`.
	 *
	 * @var string
	 */
	public string $mode;

	/**
	 * Start bound (GMT, DB format), included.
	 *
	 * @var string
	 */
	public string $from_gmt;

	/**
	 * End bound (GMT, DB format), excluded: the day after `to`, 00:00.
	 *
	 * @var string
	 */
	public string $to_gmt;

	/**
	 * Days in the period.
	 *
	 * @var int
	 */
	public int $days;

	/**
	 * Constructor.
	 *
	 * @param string $from First day.
	 * @param string $to   Last day.
	 * @param string $mode Date mode.
	 */
	private function __construct( string $from, string $to, string $mode ) {
		$this->from     = $from;
		$this->to       = $to;
		$this->mode     = $mode;
		$this->from_gmt = Dates::to_gmt_db( Dates::start_of_day( $from ) );
		$this->to_gmt   = Dates::to_gmt_db( Dates::end_of_day( $to ) );
		$this->days     = (int) Dates::start_of_day( $from )->diff( Dates::start_of_day( $to ) )->days + 1;
	}

	/**
	 * The range from request input.
	 *
	 * @param array $input `from`, `to` (`Y-m-d`), `mode`.
	 * @return self
	 * @throws DomainException 422 on a bad day, a reversed or too long range, an unknown mode.
	 */
	public static function fromInput( array $input ): self {
		$to   = (string) ( $input['to'] ?? '' );
		$to   = '' !== $to ? $to : Dates::today();
		$from = (string) ( $input['from'] ?? '' );
		$from = '' !== $from ? $from : ( Dates::is_date( $to ) ? substr( $to, 0, 8 ) . '01' : '' );
		$mode = (string) ( $input['mode'] ?? 'arrival' );

		$errors = array();
		if ( ! Dates::is_date( $from ) ) {
			$errors['from'] = __( 'Choose a valid start date.', 'radius-hotel-booking' );
		}
		if ( ! Dates::is_date( $to ) ) {
			$errors['to'] = __( 'Choose a valid end date.', 'radius-hotel-booking' );
		}
		if ( ! $errors && $to < $from ) {
			$errors['to'] = __( 'The end date must be on or after the start date.', 'radius-hotel-booking' );
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$errors['mode'] = __( 'Choose arrival date or booking date.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$range = new self( $from, $to, $mode );
		if ( $range->days > self::MAX_DAYS ) {
			/* translators: %d: number of days. */
			$errors = array( 'from' => sprintf( __( 'Choose a period of at most %d days.', 'radius-hotel-booking' ), self::MAX_DAYS ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return $range;
	}

	/**
	 * How the sales chart groups (legacy rule, 10.5): one day or less → by
	 * payment method; up to 62 days → per day; longer → per month.
	 *
	 * @return string `method` | `day` | `month`
	 */
	public function chartMode(): string {
		if ( $this->days <= 1 ) {
			return 'method';
		}
		return $this->days <= 62 ? 'day' : 'month';
	}

	/**
	 * Every bucket of the period, in order (`Y-m-d` days or `Y-m` months),
	 * so empty days and months show as zero.
	 *
	 * @param string $unit `day` or `month`.
	 * @return string[]
	 */
	public function buckets( string $unit ): array {
		$keys   = array();
		$cursor = Dates::start_of_day( 'month' === $unit ? substr( $this->from, 0, 8 ) . '01' : $this->from );
		$last   = Dates::start_of_day( $this->to );
		while ( $cursor <= $last ) {
			$keys[] = $cursor->format( 'month' === $unit ? 'Y-m' : 'Y-m-d' );
			$cursor = $cursor->modify( 'month' === $unit ? '+1 month' : '+1 day' );
		}
		return $keys;
	}

	/**
	 * The range as sent back to the screen.
	 *
	 * @return array `{ from, to, mode, days }`.
	 */
	public function toArray(): array {
		return array(
			'from' => $this->from,
			'to'   => $this->to,
			'mode' => $this->mode,
			'days' => $this->days,
		);
	}
}
