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
 * in the period (a booking line's `start_at_gmt`); `departure` — stays
 * *ending* in it (`end_at_gmt`, R1-T12); `created` — bookings *taken* in the
 * period (`bookings.created_at_gmt`). The same switch as the dashboard and
 * booking lists.
 *
 * `from` / `to` may carry a time (`Y-m-d H:i`, R1-T12): the bounds are then
 * that minute (start) and the end of that minute (end); the days stay whole
 * calendar days for the chart buckets.
 */
final class ReportRange {

	/**
	 * Date modes.
	 */
	public const MODES = array( 'arrival', 'departure', 'created' );

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
	 * Start time (`H:i`), '' for the whole first day.
	 *
	 * @var string
	 */
	public string $from_time = '';

	/**
	 * End time (`H:i`), '' for the whole last day.
	 *
	 * @var string
	 */
	public string $to_time = '';

	/**
	 * Days in the period.
	 *
	 * @var int
	 */
	public int $days;

	/**
	 * Constructor.
	 *
	 * @param string $from      First day.
	 * @param string $to        Last day.
	 * @param string $mode      Date mode.
	 * @param string $from_time Start time (`H:i`) or ''.
	 * @param string $to_time   End time (`H:i`) or ''.
	 */
	private function __construct( string $from, string $to, string $mode, string $from_time = '', string $to_time = '' ) {
		$this->from      = $from;
		$this->to        = $to;
		$this->mode      = $mode;
		$this->from_time = $from_time;
		$this->to_time   = $to_time;
		$start           = Dates::start_of_day( $from );
		$end             = Dates::end_of_day( $to );
		if ( '' !== $from_time ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $from_time ) );
			$start         = $start->setTime( $h, $m );
		}
		if ( '' !== $to_time ) {
			list( $h, $m ) = array_map( 'intval', explode( ':', $to_time ) );
			$end           = Dates::start_of_day( $to )->setTime( $h, $m )->modify( '+1 minute' );
		}
		$this->from_gmt = Dates::to_gmt_db( $start );
		$this->to_gmt   = Dates::to_gmt_db( $end );
		$this->days     = (int) Dates::start_of_day( $from )->diff( Dates::start_of_day( $to ) )->days + 1;
	}

	/**
	 * The column the period filters on, for the mode: a booking line's start
	 * (`arrival`) or end (`departure`), or the booking's creation.
	 *
	 * @param string $line    Booking line alias.
	 * @param string $booking Booking alias.
	 * @return string SQL column (aliases are the caller's, never user input).
	 */
	public function column( string $line = 'br', string $booking = 'b' ): string {
		if ( 'created' === $this->mode ) {
			return $booking . '.created_at_gmt';
		}
		return $line . ( 'departure' === $this->mode ? '.end_at_gmt' : '.start_at_gmt' );
	}

	/**
	 * Split `Y-m-d` or `Y-m-d H:i` into its day and time ('' when no time).
	 *
	 * @param string $value Input.
	 * @return string[] `[ day, time ]`.
	 */
	private static function splitTime( string $value ): array {
		$value = trim( $value );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})$/', $value, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( $value, '' );
	}

	/**
	 * Every date (an export of all bookings): from the first to the last day
	 * any booking has in the mode's column (removed bookings included, which
	 * the export skips; today when there is none). Not
	 * held to MAX_DAYS — an export streams, a report never asks for this.
	 *
	 * @param string $mode Date mode (`arrival` when unknown).
	 * @return self
	 */
	public static function allDates( string $mode ): self {
		global $wpdb;
		$mode  = in_array( $mode, self::MODES, true ) ? $mode : 'arrival';
		$probe = new self( Dates::today(), Dates::today(), $mode );
		$table = 'created' === $mode ? rtbp_table( 'bookings' ) : rtbp_table( 'booking_rooms' );
		$col   = substr( $probe->column( 't', 't' ), 2 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- two aggregates, read fresh.
		$span = $wpdb->get_row( $wpdb->prepare( 'SELECT MIN(%i) AS first, MAX(%i) AS last FROM %i', $col, $col, $table ) );
		if ( ! $span || empty( $span->first ) ) {
			return $probe;
		}
		$day = static fn( string $gmt ): string => Dates::from_gmt( $gmt )->format( 'Y-m-d' );
		return new self( $day( (string) $span->first ), $day( (string) $span->last ), $mode );
	}

	/**
	 * The range from request input.
	 *
	 * @param array $input `from`, `to` (`Y-m-d` or `Y-m-d H:i`), `mode`.
	 * @return self
	 * @throws DomainException 422 on a bad day, a reversed or too long range, an unknown mode.
	 */
	public static function fromInput( array $input ): self {
		list( $from_in, $from_time ) = self::splitTime( (string) ( $input['from'] ?? '' ) );
		list( $to_in, $to_time )     = self::splitTime( (string) ( $input['to'] ?? '' ) );
		$input['from']               = $from_in;
		$input['to']                 = $to_in;
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
		if ( ! $errors && ( $to < $from || ( $to === $from && '' !== $from_time && '' !== $to_time && $to_time < $from_time ) ) ) {
			$errors['to'] = __( 'The end date must be on or after the start date.', 'radius-hotel-booking' );
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$errors['mode'] = __( 'Choose check-in, check-out or booking date.', 'radius-hotel-booking' );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}

		$range = new self( $from, $to, $mode, $from_time, $to_time );
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
	 * @return array `{ from, to, mode, days, from_time, to_time }` (the times also key the cache).
	 */
	public function toArray(): array {
		return array(
			'from'      => $this->from,
			'to'        => $this->to,
			'mode'      => $this->mode,
			'days'      => $this->days,
			'from_time' => $this->from_time,
			'to_time'   => $this->to_time,
		);
	}
}
