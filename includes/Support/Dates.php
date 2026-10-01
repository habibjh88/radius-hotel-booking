<?php
/**
 * Date and time helper.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The only place the plugin turns strings into moments in time (ADR-007).
 *
 * Rules:
 * - Always `DateTimeImmutable` with an explicit zone — never `date()`,
 *   `strtotime()` or the server's default zone.
 * - "Local" means the site time zone (Settings → General → Timezone in
 *   WordPress; Africa/Abidjan for the client). Stored `*_at` columns are
 *   local, `*_at_gmt` twins are UTC, and comparisons use the GMT twins.
 * - Adding days is calendar arithmetic in the local zone (`+1 day` keeps the
 *   wall-clock time across a DST change); never add 86 400 seconds.
 *
 * JS twin for display: src/lib/format.js.
 */
class Dates {

	/**
	 * Database format.
	 */
	const DB = 'Y-m-d H:i:s';

	/**
	 * The site time zone.
	 *
	 * @return DateTimeZone
	 */
	public static function timezone(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * UTC.
	 *
	 * @return DateTimeZone
	 */
	public static function utc(): DateTimeZone {
		return new DateTimeZone( 'UTC' );
	}

	/**
	 * Now, in the site time zone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', self::timezone() );
	}

	/**
	 * Today's local date, `Y-m-d`.
	 *
	 * @return string
	 */
	public static function today(): string {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Parse a local date-time or date (`Y-m-d H:i:s`, `Y-m-d H:i`, `Y-m-d`)
	 * in the site time zone. Strict: anything else throws.
	 *
	 * @param string $value Local value.
	 * @return DateTimeImmutable
	 * @throws InvalidArgumentException When the value is not a valid date.
	 */
	public static function local( string $value ): DateTimeImmutable {
		return self::parse( $value, self::timezone() );
	}

	/**
	 * Parse a UTC value from a `_gmt` column, returned in the site time zone.
	 *
	 * @param string $value UTC value.
	 * @return DateTimeImmutable
	 * @throws InvalidArgumentException When the value is not a valid date.
	 */
	public static function from_gmt( string $value ): DateTimeImmutable {
		return self::parse( $value, self::utc() )->setTimezone( self::timezone() );
	}

	/**
	 * Parse an ISO 8601 value with an offset (as sent by the API clients),
	 * returned in the site time zone.
	 *
	 * @param string $value ISO 8601 value, e.g. 2026-10-02T20:00:00+00:00.
	 * @return DateTimeImmutable
	 * @throws InvalidArgumentException When the value is not ISO 8601.
	 */
	public static function from_iso( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( DateTimeInterface::ATOM, $value );
		if ( ! $date ) {
			throw new InvalidArgumentException( sprintf( 'Invalid ISO 8601 date: %s', esc_html( $value ) ) );
		}
		return $date->setTimezone( self::timezone() );
	}

	/**
	 * A local date at a local wall-clock time: `at( '2026-10-02', '20:00' )`.
	 *
	 * @param string $date `Y-m-d`.
	 * @param string $time `H:i` or `H:i:s`.
	 * @return DateTimeImmutable
	 * @throws InvalidArgumentException When either part is invalid.
	 */
	public static function at( string $date, string $time ): DateTimeImmutable {
		$time = 5 === strlen( $time ) ? $time . ':00' : $time;
		return self::parse( $date . ' ' . $time, self::timezone() );
	}

	/**
	 * Local midnight at the start of a day.
	 *
	 * @param DateTimeImmutable|string $day A moment, or a `Y-m-d` date.
	 * @return DateTimeImmutable
	 */
	public static function start_of_day( $day ): DateTimeImmutable {
		$moment = is_string( $day ) ? self::local( $day ) : $day->setTimezone( self::timezone() );
		return $moment->setTime( 0, 0, 0 );
	}

	/**
	 * Local midnight at the start of the next day — the exclusive end of a
	 * day, for half-open `[start, end)` ranges. Includes 00:00 bookings in
	 * "today", which the legacy code skipped.
	 *
	 * @param DateTimeImmutable|string $day A moment, or a `Y-m-d` date.
	 * @return DateTimeImmutable
	 */
	public static function end_of_day( $day ): DateTimeImmutable {
		// The next calendar date's start, not "start + 1 day": where a
		// daylight-saving change happens at midnight, a day can start at 01:00,
		// and adding a day to that would overlap the next day by an hour.
		$date = self::start_of_day( $day )->format( 'Y-m-d' );
		$next = ( new DateTimeImmutable( $date, self::utc() ) )->modify( '+1 day' )->format( 'Y-m-d' );
		return self::start_of_day( $next );
	}

	/**
	 * Add (or subtract) calendar days in the site time zone, keeping the
	 * wall-clock time.
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @param int               $days   Days, may be negative.
	 * @return DateTimeImmutable
	 */
	public static function add_days( DateTimeImmutable $moment, int $days ): DateTimeImmutable {
		$local = $moment->setTimezone( self::timezone() );
		return 0 === $days ? $local : $local->modify( sprintf( '%+d days', $days ) );
	}

	/**
	 * Local database value (`Y-m-d H:i:s`).
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @return string
	 */
	public static function to_db( DateTimeImmutable $moment ): string {
		return $moment->setTimezone( self::timezone() )->format( self::DB );
	}

	/**
	 * UTC database value for a `_gmt` column.
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @return string
	 */
	public static function to_gmt_db( DateTimeImmutable $moment ): string {
		return $moment->setTimezone( self::utc() )->format( self::DB );
	}

	/**
	 * Both columns for storing a moment: `[ 'local' => …, 'gmt' => … ]`.
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @return array{local:string,gmt:string}
	 */
	public static function pair( DateTimeImmutable $moment ): array {
		return array(
			'local' => self::to_db( $moment ),
			'gmt'   => self::to_gmt_db( $moment ),
		);
	}

	/**
	 * ISO 8601 with offset, for API responses.
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @return string
	 */
	public static function to_iso( DateTimeImmutable $moment ): string {
		return $moment->setTimezone( self::timezone() )->format( DateTimeInterface::ATOM );
	}

	/**
	 * Whether a string is a real calendar date `Y-m-d` (rejects 2026-02-30).
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	public static function is_date( string $value ): bool {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, self::timezone() );
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Format a moment for display with the plugin's date/time settings,
	 * translated through wp_date().
	 *
	 * @param DateTimeImmutable $moment Moment.
	 * @param string            $type   'date', 'time' or 'datetime'.
	 * @return string
	 */
	public static function format( DateTimeImmutable $moment, string $type = 'date' ): string {
		$formats = self::display_formats();
		$format  = 'time' === $type ? $formats['time'] : ( 'datetime' === $type ? $formats['date'] . ' ' . $formats['time'] : $formats['date'] );

		return (string) wp_date( $format, $moment->getTimestamp(), self::timezone() );
	}

	/**
	 * The PHP date/time formats from Settings → General (also localised to JS).
	 *
	 * @return array{date:string,time:string}
	 */
	public static function display_formats(): array {
		$general = SettingsHelper::get_setting( 'general' );
		$general = is_array( $general ) ? $general : array();
		$date    = (string) ( $general['dateFormat'] ?? '' );

		return array(
			'date' => '' !== $date ? $date : (string) get_option( 'date_format', 'Y-m-d' ),
			'time' => '24h' === ( $general['timeSystem'] ?? '' ) ? 'H:i' : 'g:i A',
		);
	}

	/**
	 * Strict parse in a zone.
	 *
	 * @param string       $value Value.
	 * @param DateTimeZone $zone  Zone.
	 * @return DateTimeImmutable
	 * @throws InvalidArgumentException When the value is not a valid date.
	 */
	private static function parse( string $value, DateTimeZone $zone ): DateTimeImmutable {
		$value = trim( $value );

		foreach ( array( '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d' ) as $format ) {
			$date   = DateTimeImmutable::createFromFormat( $format, $value, $zone );
			$errors = DateTimeImmutable::getLastErrors();
			if ( $date && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ) {
				return $date;
			}
		}

		throw new InvalidArgumentException( sprintf( 'Invalid date: %s', esc_html( $value ) ) );
	}
}
