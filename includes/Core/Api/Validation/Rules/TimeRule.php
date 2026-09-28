<?php
/**
 * TimeRule
 * Validates whether a given value matches a specific time format.
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Class TimeRule
 *
 * Validates whether a given value matches a specific time format.
 *
 * Example usage:
 * ```php
 * $rule = new TimeRule('H:i');
 * $rule->validate('14:30'); // true
 * $rule->validate('25:00'); // false
 * ```
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Validation\Rules
 */
class TimeRule {
	/**
	 * The expected time format.
	 *
	 * @var string
	 */
	private string $format;

	/**
	 * The validation error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * TimeRule constructor.
	 *
	 * @param string $format  The expected time format (default: 'H:i:s').
	 * @param string $message The error message returned when validation fails.
	 */
	public function __construct( string $format = 'H:i:s', string $message = 'Field must be a valid time' ) {
		$this->format  = $format;
		$this->message = $message;
	}

	/**
	 * Validate whether the given value matches the expected time format.
	 *
	 * @param mixed $value The value to validate.
	 * @return bool True if valid time, false otherwise.
	 */
	public function validate( $value ): bool {
		$time = \DateTime::createFromFormat( $this->format, $value );
		return $time && $time->format( $this->format ) === $value;
	}

	/**
	 * Get the validation error message.
	 *
	 * @return string The error message.
	 */
	public function getMessage(): string {
		return $this->message;
	}
}
