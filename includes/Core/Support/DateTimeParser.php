<?php
/**
 * DateTimeParser
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Support;

use DateInterval;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * DateTimeParser
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Support
 * @author
 * @version 1.0.0
 */
class DateTimeParser {

	/**
	 * The DateTime object associated with the current instance.
	 *
	 * @var \DateTime
	 */
	private $dateTime;

	/**
	 * The timezone associated with the current instance.
	 *
	 * @var \DateTimeZone
	 */
	private $timezone;

	/**
	 * Constructs a new instance of the class.
	 *
	 * @param string|null $dateTimeString Optional DateTime string to initialize the DateTime object. Defaults to null.
	 * @param \DateTimeZone|string|null $timezone Optional timezone as a DateTimeZone object or a timezone string. Defaults to the system's default timezone.
	 *
	 * @return void
	 * @throws \DateInvalidTimeZoneException If the provided timezone is invalid.
	 * @throws \InvalidArgumentException If the provided DateTime string is invalid.
	 */
	public function __construct( $dateTimeString = null, $timezone = null ) {
		// Accept either DateTimeZone or timezone string
		$this->timezone = ( $timezone instanceof \DateTimeZone ) ? $timezone : new \DateTimeZone( $timezone ?: date_default_timezone_get() ); //phpcs:ignore

		if ( $dateTimeString ) {
			$this->parseDateTime( $dateTimeString );
		} else {
			$this->dateTime = new \DateTime( 'now', $this->timezone );
		}
	}

	/**
	 * Parses the given DateTime string and timezone to create a new instance of the class.
	 *
	 * @param string $dateTimeString The DateTime string to be parsed.
	 * @param \DateTimeZone|string|null $timezone Optional timezone as a DateTimeZone object or a timezone string. Defaults to the system's default timezone if not provided.
	 *
	 * @return self A new instance of the class initialized with the provided DateTime string and timezone.
	 */
	public static function parse( $dateTimeString, $timezone = null ) {
		return new self( $dateTimeString, $timezone );
	}

	/**
	 * Parses a given date-time string into a DateTime object using various formats.
	 *
	 * @param string $dateTimeString The date-time string to parse. It tries multiple formats including 'Y-m-d H:i:s',
	 *                                'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', and 'd/m/Y'.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the date-time string cannot be parsed into a valid DateTime object.
	 */
	private function parseDateTime( $dateTimeString ) {
		$dateTimeString = trim( $dateTimeString );

		// 1) Try strict 'Y-m-d H:i:s' using createFromFormat (best)
		$dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $dateTimeString, $this->timezone );
		if ( $dt !== false ) {
			$this->dateTime = $dt;
			return;
		}

		// 2) Try generic constructor (handles many formats)
		try {
			$this->dateTime = new \DateTime( $dateTimeString, $this->timezone );
			return;
		} catch ( \Exception $e ) {
			// fallthrough to custom patterns
		}

		// 3) Try some common custom patterns
		$patterns = array(
			'/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})$/' => 'Y-m-d H:i',
			'/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/' => 'd/m/Y H:i:s',
			'/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/' => 'd/m/Y H:i',
			'/^(\d{2})\/(\d{2})\/(\d{4})$/' => 'd/m/Y',
		);

		foreach ( $patterns as $pattern => $format ) {
			if ( preg_match( $pattern, $dateTimeString ) ) {
				$dt = \DateTime::createFromFormat( $format, $dateTimeString, $this->timezone );
				if ( $dt !== false ) {
					$this->dateTime = $dt;
					return;
				}
			}
		}

		// Nothing matched
		throw new \InvalidArgumentException(
			sprintf( 'Unable to parse date/time string: %s', esc_html( $dateTimeString ) )
		);  }

	/**
	 * Formats the DateTime object according to the specified format.
	 *
	 * @param string $format Optional format string for the date and time. Defaults to 'Y-m-d H:i:s'.
	 *
	 * @return string The formatted date and time string.
	 */
	public function format( $format = 'Y-m-d H:i:s' ) {
		return $this->dateTime->format( $format );
	}

	/**
	 * Retrieves the timestamp from the associated DateTime object.
	 *
	 * @return int The Unix timestamp represented by the DateTime object.
	 */
	public function getTimestamp() {
		return $this->dateTime->getTimestamp();
	}

	/**
	 * Converts the object to its string representation.
	 *
	 * @return string The string representation of the object.
	 */
	public function __toString() {
		return $this->format();
	}

	/**
	 * Retrieves the DateTime object.
	 *
	 * @return \DateTime The DateTime instance associated with this object.
	 */
	public function getDateTime() {
		return $this->dateTime;
	}

	/**
	 * Adds a time interval to the current date and time.
	 *
	 * @param string|\DateInterval $interval The interval to add, either as a string compatible with \DateInterval or an instance of \DateInterval.
	 *
	 * @return $this The current object for method chaining.
	 */
	public function add( $interval ) {
		if ( is_string( $interval ) ) {
			$this->dateTime->add( new \DateInterval( $interval ) );
		} elseif ( $interval instanceof \DateInterval ) {
			$this->dateTime->add( $interval );
		}
		return $this;
	}

	/**
	 *
	 * Subtracts a time interval from the current DateTime object.
	 *
	 * @param string|\DateInterval $interval The interval to subtract, either as a string recognized by \DateInterval or an instance of \DateInterval.
	 *
	 * @return $this Returns the current instance for method chaining.
	 */
	public function sub( $interval ) {
		if ( is_string( $interval ) ) {
			$this->dateTime->sub( new \DateInterval( $interval ) );
		} elseif ( $interval instanceof \DateInterval ) {
			$this->dateTime->sub( $interval );
		}
		return $this;
	}

	/**
	 * Sets the timezone for the current object.
	 *
	 * @param \DateTimeZone|string $timezone The timezone to set. Can be a \DateTimeZone object or a valid timezone string.
	 *
	 * @return $this Returns the current instance for method chaining.
	 */
	public function setTimezone( $timezone ) {
		$tz             = ( $timezone instanceof \DateTimeZone ) ? $timezone : new \DateTimeZone( $timezone );
		$this->timezone = $tz;
		$this->dateTime->setTimezone( $tz );
		return $this;
	}

	/**
	 * Retrieves the numeric representation of the day of the week (1 for Monday through 7 for Sunday).
	 *
	 * @return int The numeric value representing the day of the week.
	 */
	public function dayOfWeek() {
		return (int) $this->dateTime->format( 'N' );
	}

	/**
	 * Calculates the day of the year for the given date.
	 *
	 * @return int The numeric value representing the day of the year, starting from 1.
	 */
	public function dayOfYear() {
		return (int) $this->dateTime->format( 'z' ) + 1;
	}

	/**
	 * Determines if the current day is a weekend.
	 *
	 * @return bool True if the day is Saturday (6) or Sunday (7), otherwise false.
	 */
	public function isWeekend() {
		return in_array( $this->dayOfWeek(), array( 6, 7 ) );
	}

	/**
	 * Determines if the current day is a weekday.
	 *
	 * @return bool True if the current day is a weekday, false otherwise.
	 */
	public function isWeekday() {
		return ! $this->isWeekend();
	}

	/**
	 * Calculates the difference between the current date/time object and the given date/time.
	 *
	 * @param string|\DateTime|self $dateTime The date/time to compare against. It can be a string, an instance of DateTime, or an instance of the current class.
	 * @param bool $absolute Whether to calculate the absolute difference (ignoring sign). Defaults to false.
	 *
	 * @return \DateInterval The difference between the two date/time objects as a DateInterval object.
	 * @throws \InvalidArgumentException If the provided date/time parameter is invalid.
	 */
	public function diff( $dateTime, $absolute = false ) {
		if ( is_string( $dateTime ) ) {
			$dateTime = new self( $dateTime, $this->timezone );
		}

		if ( $dateTime instanceof self ) {
			return $this->dateTime->diff( $dateTime->getDateTime(), $absolute );
		}

		if ( $dateTime instanceof \DateTime ) {
			return $this->dateTime->diff( $dateTime, $absolute );
		}

		throw new \InvalidArgumentException( 'Invalid date/time parameter' );
	}

	/**
	 * Determines if the given date parameter represents the same calendar day
	 * as the current object's date.
	 *
	 * @param mixed $other The date to compare, which can be an instance of the same class,
	 *                     a \DateTime object, or a string representing a date.
	 *
	 * @return bool True if the provided date matches the same calendar day, false otherwise.
	 *
	 * @throws \InvalidArgumentException If the parameter provided is not a valid type.
	 */
	public function isSameDay( $other ) {
		if ( $other instanceof self ) {
			$otherDt = $other->getDateTime();
		} elseif ( $other instanceof \DateTime ) {
			$otherDt = clone $other;
			$otherDt->setTimezone( $this->timezone );
		} elseif ( is_string( $other ) ) {
			$otherDt = new \DateTime( $other, $this->timezone );
		} else {
			throw new \InvalidArgumentException( 'Invalid parameter for isSameDay()' );
		}

		$otherDt->setTimezone( $this->timezone );
		return $this->dateTime->format( 'Y-m-d' ) === $otherDt->format( 'Y-m-d' );
	}

	/**
	 * Determines if the current date matches today's date in the specified timezone.
	 *
	 * @return bool True if the current date is today, otherwise false.
	 * @throws \Exception Exception thrown if the current date cannot be parsed.
	 */
	public function isToday() {
		$now = new \DateTime( 'now', $this->timezone );
		return $this->dateTime->format( 'Y-m-d' ) === $now->format( 'Y-m-d' );
	}

	/**
	 * Determines whether the stored date and time corresponds to tomorrow's date.
	 *
	 * @return bool True if the date is tomorrow, otherwise false.
	 * @throws \Exception Exception thrown if the current date cannot be parsed.
	 */
	public function isTomorrow() {
		$now      = new \DateTime( 'now', $this->timezone );
		$tomorrow = ( clone $now )->add( new \DateInterval( 'P1D' ) );
		return $this->dateTime->format( 'Y-m-d' ) === $tomorrow->format( 'Y-m-d' );
	}

	/**
	 * Determines if the current date represents yesterday's date.
	 *
	 * @return bool True if the date is yesterday, false otherwise.
	 */
	public function isYesterday() {
		$now       = new \DateTime( 'now', $this->timezone );
		$yesterday = ( clone $now )->sub( new \DateInterval( 'P1D' ) );
		return $this->dateTime->format( 'Y-m-d' ) === $yesterday->format( 'Y-m-d' );
	}

	/**
	 * Determines whether the timestamp of the object represents a future date and time.
	 *
	 * @return bool True if the object's timestamp is in the future, otherwise false.
	 */
	public function isFuture() {
		$nowTs = ( new \DateTime( 'now', $this->timezone ) )->getTimestamp();
		return $this->getTimestamp() > $nowTs;
	}

	/**
	 * Determines if the current object's timestamp is in the past compared to the current time.
	 *
	 * @return bool True if the timestamp is in the past, false otherwise.
	 */
	public function isPast() {
		$nowTs = ( new \DateTime( 'now', $this->timezone ) )->getTimestamp();
		return $this->getTimestamp() < $nowTs;
	}
}
